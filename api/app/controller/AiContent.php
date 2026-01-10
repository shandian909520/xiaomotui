<?php
declare(strict_types=1);

namespace app\controller;

use app\service\WenxinService;
use think\facade\Log;
use think\Response;

/**
 * AI内容生成控制器
 * 提供AI文案生成相关的API接口
 */
class AiContent extends BaseController
{
    /**
     * 生成营销文案
     *
     * @return Response
     */
    public function generateText(): Response
    {
        try {
            // 获取请求参数
            $params = $this->request->only([
                'scene',
                'style',
                'platform',
                'category',
                'requirements',
            ], 'post');

            // 参数验证
            $this->validateParams($params);

            // 初始化文心一言服务
            $wenxinService = new WenxinService();

            // 生成文案
            $result = $wenxinService->generateText($params);

            return $this->success([
                'text' => $result['text'],
                'tokens' => $result['tokens'],
                'time' => $result['time'],
                'model' => $result['model'],
                'params' => $params,
            ], '文案生成成功');

        } catch (\Exception $e) {
            Log::error('AI文案生成失败', [
                'params' => $params ?? [],
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量生成文案
     *
     * @return Response
     */
    public function batchGenerateText(): Response
    {
        try {
            // 获取批量参数
            $batchParams = $this->request->post('batch_params', []);

            if (empty($batchParams) || !is_array($batchParams)) {
                return $this->error('批量参数不能为空');
            }

            // 限制批量数量
            if (count($batchParams) > 10) {
                return $this->error('单次批量生成最多支持10条');
            }

            // 验证每个参数
            foreach ($batchParams as $params) {
                $this->validateParams($params);
            }

            // 初始化文心一言服务
            $wenxinService = new WenxinService();

            // 批量生成
            $results = $wenxinService->batchGenerateText($batchParams);

            // 统计结果
            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failCount = count($results) - $successCount;

            return $this->success([
                'results' => $results,
                'total' => count($results),
                'success_count' => $successCount,
                'fail_count' => $failCount,
            ], "批量生成完成，成功{$successCount}条，失败{$failCount}条");

        } catch (\Exception $e) {
            Log::error('批量AI文案生成失败', [
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * 测试AI服务连接
     *
     * @return Response
     */
    public function testConnection(): Response
    {
        try {
            $wenxinService = new WenxinService();
            $result = $wenxinService->testConnection();

            if ($result['success']) {
                return $this->success($result, '连接测试成功');
            } else {
                return $this->error($result['message'], $result);
            }

        } catch (\Exception $e) {
            return $this->error('连接测试失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取AI服务状态
     *
     * @return Response
     */
    public function getStatus(): Response
    {
        try {
            $wenxinService = new WenxinService();
            $status = $wenxinService->getStatus();

            return $this->success($status, '获取状态成功');

        } catch (\Exception $e) {
            return $this->error('获取状态失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取配置信息
     *
     * @return Response
     */
    public function getConfig(): Response
    {
        try {
            $wenxinService = new WenxinService();
            $config = $wenxinService->getConfig();

            return $this->success($config, '获取配置成功');

        } catch (\Exception $e) {
            return $this->error('获取配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 清除Token缓存
     *
     * @return Response
     */
    public function clearCache(): Response
    {
        try {
            $wenxinService = new WenxinService();
            $result = $wenxinService->clearTokenCache();

            if ($result) {
                return $this->success([], 'Token缓存清除成功');
            } else {
                return $this->error('Token缓存清除失败');
            }

        } catch (\Exception $e) {
            return $this->error('清除缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取支持的风格列表
     *
     * @return Response
     */
    public function getStyles(): Response
    {
        $styles = [
            ['value' => '温馨', 'label' => '温馨', 'description' => '温暖、亲切、有人情味，营造舒适放松的氛围'],
            ['value' => '时尚', 'label' => '时尚', 'description' => '前卫、新潮、个性化，突出流行趋势'],
            ['value' => '文艺', 'label' => '文艺', 'description' => '有情怀、有格调、富有诗意和文化气息'],
            ['value' => '潮流', 'label' => '潮流', 'description' => '年轻活力、时尚动感、紧跟潮流'],
            ['value' => '高端', 'label' => '高端', 'description' => '精致优雅、品质感强、体现尊贵体验'],
            ['value' => '亲民', 'label' => '亲民', 'description' => '接地气、实惠、贴近生活'],
        ];

        return $this->success($styles, '获取风格列表成功');
    }

    /**
     * 获取支持的平台列表
     *
     * @return Response
     */
    public function getPlatforms(): Response
    {
        $platforms = [
            [
                'value' => 'DOUYIN',
                'label' => '抖音',
                'description' => '简短有力，20-50字，节奏感强，适合短视频',
            ],
            [
                'value' => 'XIAOHONGSHU',
                'label' => '小红书',
                'description' => '真实生活化，100-200字，分享式语气',
            ],
            [
                'value' => 'WECHAT',
                'label' => '微信',
                'description' => '亲切自然，内容详实，适合朋友圈分享',
            ],
        ];

        return $this->success($platforms, '获取平台列表成功');
    }

    /**
     * 参数验证
     *
     * @param array $params 参数
     * @throws \Exception
     */
    private function validateParams(array $params): void
    {
        // 必填字段验证
        if (empty($params['scene'])) {
            throw new \Exception('场景描述不能为空');
        }

        if (empty($params['style'])) {
            throw new \Exception('风格不能为空');
        }

        if (empty($params['platform'])) {
            throw new \Exception('平台不能为空');
        }

        // 风格验证
        $validStyles = ['温馨', '时尚', '文艺', '潮流', '高端', '亲民'];
        if (!in_array($params['style'], $validStyles)) {
            throw new \Exception('不支持的风格: ' . $params['style']);
        }

        // 平台验证
        $validPlatforms = ['DOUYIN', 'XIAOHONGSHU', 'WECHAT'];
        if (!in_array(strtoupper($params['platform']), $validPlatforms)) {
            throw new \Exception('不支持的平台: ' . $params['platform']);
        }

        // 长度验证
        if (mb_strlen($params['scene']) > 50) {
            throw new \Exception('场景描述不能超过50个字符');
        }

        if (!empty($params['requirements']) && mb_strlen($params['requirements']) > 200) {
            throw new \Exception('特殊要求不能超过200个字符');
        }
    }
}