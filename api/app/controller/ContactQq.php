<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\ContactService;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * QQ 联系方式控制器(Agent C)
 *
 * 顾客端:
 *   GET  /api/contact/qq-config?device_id=     -> getQqConfig
 * 商家/平台后台(鉴权):
 *   PUT  /api/contact/qq-config                -> setQqConfig
 *   POST /api/contact/qq-action               -> recordQqAction
 *
 * 与 AggregationPageService::readQqConfig() 字段结构保持一致,
 * 以保证 uni-app 调用 /api/nfc/aggregation-page 仍能拿到 QQ 入口。
 */
class ContactQq extends BaseController
{
    protected ContactService $contactService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->contactService = new ContactService();
    }

    /**
     * GET /api/contact/qq-config?device_id=
     */
    public function getQqConfig()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            $config = $this->contactService->getQqConfig($deviceId);
            return $this->success([
                'device_id' => $deviceId,
                'config'    => $config,
                'enabled'   => !empty($config) && !empty($config['qq_number']),
            ], '获取QQ配置成功');
        } catch (\Exception $e) {
            Log::error('获取QQ配置失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_qq_config_failed');
        }
    }

    /**
     * PUT /api/contact/qq-config
     * { device_id, qq_number, qq_qrcode, qq_group_url, kefu_qrcode, enabled }
     */
    public function setQqConfig()
    {
        try {
            $payload  = $this->request->post();
            $deviceId = (int)($payload['device_id'] ?? 0);
            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            unset($payload['device_id']);
            $ok = $this->contactService->setQqConfig($deviceId, $payload);
            if (!$ok) {
                return $this->error('保存QQ配置失败', 500, 'set_qq_config_failed');
            }
            return $this->success([
                'device_id' => $deviceId,
                'config'    => $this->contactService->getQqConfig($deviceId),
            ], '保存QQ配置成功');
        } catch (ValidateException $e) {
            return $this->validationError(['qq' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('保存QQ配置失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'set_qq_config_failed');
        }
    }

    /**
     * POST /api/contact/qq-action
     * { device_id, action: view|click|copy_qq|join_group|contact_kefu, user_hash? }
     */
    public function recordQqAction()
    {
        try {
            $payload  = $this->request->post();
            $deviceId = (int)($payload['device_id'] ?? 0);
            $action   = (string)($payload['action'] ?? 'view');
            $userHash = (string)($payload['user_hash'] ?? '');
            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            if (!in_array($action, ['view', 'click', 'copy_qq', 'join_group', 'contact_kefu'], true)) {
                $action = 'view';
            }
            if ($userHash === '') {
                try {
                    $ip = request()->ip() ?: '0.0.0.0';
                    $ua = request()->header('User-Agent') ?: '';
                    $userHash = md5($ip . '|' . $ua);
                } catch (\Throwable $e) {
                    $userHash = md5((string)time());
                }
            }
            $ok = $this->contactService->recordQqAction($deviceId, $userHash, $action);
            return $this->success(['recorded' => $ok], '记录QQ动作成功');
        } catch (\Exception $e) {
            Log::warning('记录QQ动作失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'record_qq_action_failed');
        }
    }
}
