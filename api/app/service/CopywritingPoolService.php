<?php
declare (strict_types = 1);

namespace app\service;

use app\model\CopywritingPool;
use app\model\NfcDevice;
use think\facade\Log;
use think\facade\Db;
use think\exception\ValidateException;

/**
 * 文案池服务(模块3)
 * 提供设备文案池的"换一批"轮播逻辑:
 *   - 首次访问: 返回单条 + rotate_token
 *   - 携带 rotate_token 调用 rotateCopywriting:
 *       * 基于 token + 时间窗口索引,避免短时间内重复
 *       * 同设备轮播时按权重 + 末位排除策略返回
 *
 * 设计参考: CopywritingPool 模型字段 weight/sort/used_count 用作权重轮播依据
 */
class CopywritingPoolService
{
    /**
     * 默认前端预生成候选数
     */
    const DEFAULT_POOL_LIMIT = 20;

    /**
     * 一次取多条(用于前端"预读")
     */
    public function prebuildPool(int $deviceId, string $scene = CopywritingPool::SCENE_PUBLISH, int $limit = 5): array
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }

        $list = CopywritingPool::where('device_id', $deviceId)
            ->where('scene', $scene)
            ->where('status', CopywritingPool::STATUS_ENABLED)
            ->order('sort', 'desc')
            ->order('weight', 'desc')
            ->order('id', 'asc')
            ->limit($limit)
            ->select();

        return $this->formatPool($list->isEmpty() ? [] : $list);
    }

    /**
     * 旋转获取下一条文案(首调不带 token 返单条,之后带 token 用于错峰)
     *
     * @param int    $deviceId
     * @param string $rotateToken 前一次返回的 token;空表示首次
     * @return array {content, content_id, rotate_token, has_more}
     */
    public function rotateCopywriting(int $deviceId, string $rotateToken = ''): array
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }

        $scene = CopywritingPool::SCENE_PUBLISH;

        // 1) 设备有预置文案直接兜底
        if (empty($rotateToken)) {
            $exists = CopywritingPool::where('device_id', $deviceId)
                ->where('scene', $scene)
                ->where('status', CopywritingPool::STATUS_ENABLED)
                ->count();
            if ($exists == 0) {
                $fallback = (string)($device->promo_copywriting ?: '推荐一家超赞的店!');
                return [
                    'content'      => $fallback,
                    'content_id'   => 0,
                    'rotate_token' => $this->makeRotateToken($deviceId, 0),
                    'has_more'     => false,
                    'source'       => 'fallback',
                ];
            }
        }

        // 2) 有 token: 解出序号,排除刚返过的
        $exclude = $this->decodeRotateToken($deviceId, $rotateToken);

        $row = CopywritingPool::where('device_id', $deviceId)
            ->where('scene', $scene)
            ->where('status', CopywritingPool::STATUS_ENABLED)
            ->where('id', '<>', $exclude)
            ->order('sort', 'desc')
            ->order('weight', 'desc')
            ->orderRaw('RAND()')
            ->find();

        if (!$row) {
            // 没有可用候选,降级到设备预置
            $fallback = (string)($device->promo_copywriting ?: '推荐一家超赞的店!');
            return [
                'content'      => $fallback,
                'content_id'   => 0,
                'rotate_token' => $this->makeRotateToken($deviceId, 0),
                'has_more'     => false,
                'source'       => 'fallback',
            ];
        }

        // 3) 异步累加 used_count(失败不影响主流程)
        $this->bumpUsedCount((int)$row->id);

        return [
            'content'      => (string)$row->content,
            'content_id'   => (int)$row->id,
            'rotate_token' => $this->makeRotateToken($deviceId, (int)$row->id),
            'has_more'     => true,
            'source'       => 'pool',
        ];
    }

    /**
     * 直接返回固定集合(给前端"预读后续")
     */
    public function batchFetch(int $deviceId, int $count = 5, string $scene = CopywritingPool::SCENE_PUBLISH): array
    {
        $rows = CopywritingPool::where('device_id', $deviceId)
            ->where('scene', $scene)
            ->where('status', CopywritingPool::STATUS_ENABLED)
            ->order('sort', 'desc')
            ->order('weight', 'desc')
            ->orderRaw('RAND()')
            ->limit(max(1, $count))
            ->select();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'content_id' => (int)$row->id,
                'content'    => (string)$row->content,
            ];
        }
        return $items;
    }

    protected function formatPool(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'content_id' => (int)$row->id,
                'content'    => (string)$row->content,
                'weight'     => (int)$row->weight,
            ];
        }
        return $out;
    }

    /**
     * 构造 rotate_token:
     *   - 写入设备ID + 最后命中文案ID + 时间戳哈希
     *   - 服务端可解码查 last_used
     *   - 不携带敏感数据
     */
    protected function makeRotateToken(int $deviceId, int $lastId): string
    {
        $secret = config('app.app_key') ?: 'xmt_rotate_default_secret';
        $payload = json_encode([
            'd' => $deviceId,
            'i' => $lastId,
            't' => time(),
        ]);
        $sig = hash_hmac('sha256', $payload, $secret);
        return base64_encode($payload) . '.' . substr($sig, 0, 16);
    }

    /**
     * 解码并返回应排除的 content_id
     * 异常或伪造 token 返回 0(不做严格校验,只用作错峰)
     */
    protected function decodeRotateToken(int $deviceId, string $token): int
    {
        if (empty($token)) {
            return 0;
        }
        try {
            [$payloadEnc] = explode('.', $token, 2);
            $payload = base64_decode($payloadEnc);
            $data = json_decode($payload, true);
            if (!is_array($data)) return 0;
            // 32 位有符号整数上限保护
            $id = isset($data['i']) ? (int)$data['i'] : 0;
            $maxTime = 86400 * 7; // 7 天
            if (isset($data['t']) && (time() - (int)$data['t']) > $maxTime) {
                return 0; // 过期作废
            }
            return $id;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 自增 used_count(不抛错)
     */
    protected function bumpUsedCount(int $copyId): void
    {
        try {
            \think\facade\Db::name('copywriting_pool')
                ->where('id', $copyId)
                ->inc('used_count')
                ->update(['last_used_time' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            Log::warning('文案 used_count 自增失败', [
                'copy_id' => $copyId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ========================================================================
    // Agent C 业务闭环: 管理端 CRUD(已就绪的轮播逻辑保持不变)
    // ========================================================================

    /**
     * 列出某设备某场景的文案(含已停用),用于商家后台
     */
    public function getByDevice(int $deviceId, string $scene = CopywritingPool::SCENE_PUBLISH): array
    {
        if ($deviceId <= 0) {
            throw new ValidateException('设备ID不合法');
        }
        $list = CopywritingPool::where('device_id', $deviceId)
            ->where('scene', $scene)
            ->order('sort', 'desc')
            ->order('weight', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        return $list;
    }

    /**
     * 新增文案(商家后台)
     */
    public function add(array $data): array
    {
        $this->validatePayload($data, false);
        $deviceId = (int)$data['device_id'];
        $scene    = $data['scene'] ?? CopywritingPool::SCENE_PUBLISH;

        $merchantId = 0;
        try {
            $device = NfcDevice::find($deviceId);
            if (!$device) {
                throw new ValidateException('设备不存在');
            }
            $merchantId = (int)$device->merchant_id;
        } catch (ValidateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $merchantId = 0;
        }

        $payload = [
            'device_id'   => $deviceId,
            'merchant_id' => $merchantId,
            'scene'       => $scene,
            'content'     => (string)$data['content'],
            'weight'      => isset($data['weight']) ? max(1, (int)$data['weight']) : 10,
            'status'      => isset($data['status']) ? (int)$data['status'] : CopywritingPool::STATUS_ENABLED,
            'sort'        => isset($data['sort']) ? (int)$data['sort'] : 0,
        ];
        $row = CopywritingPool::create($payload);
        return $row->toArray();
    }

    /**
     * 更新文案
     */
    public function update(int $id, array $data): array
    {
        $row = CopywritingPool::find($id);
        if (!$row) {
            throw new ValidateException('文案不存在');
        }
        $payload = [];
        if (array_key_exists('content', $data)) {
            $payload['content'] = trim((string)$data['content']);
            if ($payload['content'] === '') {
                throw new ValidateException('内容不能为空');
            }
            if (mb_strlen($payload['content']) > 1000) {
                throw new ValidateException('内容长度不能超过1000');
            }
        }
        if (array_key_exists('weight', $data)) {
            $payload['weight'] = max(1, (int)$data['weight']);
        }
        if (array_key_exists('status', $data)) {
            $payload['status'] = (int)$data['status'] === CopywritingPool::STATUS_ENABLED
                ? CopywritingPool::STATUS_ENABLED
                : CopywritingPool::STATUS_DISABLED;
        }
        if (array_key_exists('sort', $data)) {
            $payload['sort'] = (int)$data['sort'];
        }
        if (array_key_exists('scene', $data) && in_array($data['scene'], [
            CopywritingPool::SCENE_PUBLISH,
            CopywritingPool::SCENE_REVIEW,
            CopywritingPool::SCENE_GROUPBUY,
        ], true)) {
            $payload['scene'] = $data['scene'];
        }
        if (!empty($payload)) {
            $row->save($payload);
        }
        return $row->toArray();
    }

    /**
     * 软删除文案
     */
    public function delete(int $id): bool
    {
        $row = CopywritingPool::find($id);
        if (!$row) {
            throw new ValidateException('文案不存在');
        }
        return (bool)$row->delete();
    }

    /**
     * 批量导入(每行一条)
     *
     * @param int    $deviceId
     * @param array  $lines     ["文案1","文案2",...]
     * @param string $scene
     * @param int    $weight    默认权重
     * @return array  {imported:int, skipped:int, ids:array}
     */
    public function batchImport(int $deviceId, array $lines, string $scene = CopywritingPool::SCENE_PUBLISH, int $weight = 10): array
    {
        if ($deviceId <= 0) {
            throw new ValidateException('设备ID不合法');
        }
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }
        $merchantId = (int)$device->merchant_id;

        $imported = 0;
        $skipped  = 0;
        $ids      = [];
        foreach ($lines as $line) {
            $content = trim((string)$line);
            if ($content === '' || mb_strlen($content) > 1000) {
                $skipped++;
                continue;
            }
            try {
                $row = CopywritingPool::create([
                    'device_id'   => $deviceId,
                    'merchant_id' => $merchantId,
                    'scene'       => $scene,
                    'content'     => $content,
                    'weight'      => max(1, $weight),
                    'status'      => CopywritingPool::STATUS_ENABLED,
                    'sort'        => 0,
                ]);
                $imported++;
                $ids[] = (int)$row->id;
            } catch (\Throwable $e) {
                Log::warning('文案批量导入失败', [
                    'device_id' => $deviceId,
                    'error'     => $e->getMessage(),
                ]);
                $skipped++;
            }
        }
        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'ids'      => $ids,
        ];
    }

    /**
     * 校验入库字段
     */
    protected function validatePayload(array $data, bool $partial): void
    {
        if (!$partial || array_key_exists('device_id', $data)) {
            if (empty($data['device_id']) || (int)$data['device_id'] <= 0) {
                throw new ValidateException('设备ID不合法');
            }
        }
        if (!$partial || array_key_exists('content', $data)) {
            $content = trim((string)($data['content'] ?? ''));
            if ($content === '') {
                throw new ValidateException('内容不能为空');
            }
            if (mb_strlen($content) > 1000) {
                throw new ValidateException('内容长度不能超过1000');
            }
        }
        if (isset($data['scene']) && !in_array($data['scene'], [
            CopywritingPool::SCENE_PUBLISH,
            CopywritingPool::SCENE_REVIEW,
            CopywritingPool::SCENE_GROUPBUY,
        ], true)) {
            throw new ValidateException('场景不合法');
        }
        if (isset($data['weight']) && (int)$data['weight'] < 1) {
            throw new ValidateException('权重必须 ≥ 1');
        }
    }
}
