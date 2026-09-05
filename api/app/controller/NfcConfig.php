<?php
declare (strict_types = 1);

namespace app\controller;

use app\model\NfcDevice;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * NFC 设备配置控制器(Agent E)
 * 商家后台专用: 3 tab 配置 — 任务配置 / Wi-Fi&二维码 / 私域配置
 *
 * 路由(鉴权):
 *   GET  /api/admin/nfc/device/:id/config       getConfig
 *   PUT  /api/admin/nfc/device/:id/config       saveConfig
 *   GET  /api/admin/nfc/device/:id/aggregation  getAggregationSnapshot
 */
class NfcConfig extends BaseController
{
    /**
     * GET /api/admin/nfc/device/:id/config
     * 返回设备详情 + 3 tab 配置 + 今日触发数
     */
    public function getConfig($id = 0)
    {
        try {
            $deviceId = (int)$id;
            if ($deviceId <= 0) {
                $deviceId = (int)$this->request->param('id', 0);
            }
            if ($deviceId <= 0) {
                return $this->validationError(['id' => '设备ID不能为空']);
            }

            $device = NfcDevice::find($deviceId);
            if (!$device) {
                return $this->error('设备不存在', 404, 'device_not_found');
            }
            if (!$this->checkDeviceAccess($device)) {
                return $this->error('无权访问该设备', 403, 'device_access_denied');
            }

            // 今日触发数(从 device_triggers 取)
            $today = date('Y-m-d');
            $todayTriggerCount = 0;
            try {
                $todayTriggerCount = (int)\think\facade\Db::name('device_triggers')
                    ->where('device_id', $deviceId)
                    ->where('create_time', '>=', $today . ' 00:00:00')
                    ->where('create_time', '<=', $today . ' 23:59:59')
                    ->count();
            } catch (\Throwable $e) {
                $todayTriggerCount = 0;
            }

            // 拉取一次聚合页(给「任务配置」tab 显示已启用 block)
            $aggregation = [];
            try {
                $svc = new \app\service\AggregationPageService();
                $aggregation = $svc->getByDeviceCode((string)$device->device_code);
            } catch (\Throwable $e) {
                Log::warning('拉取聚合页快照失败', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
            }

            return $this->success([
                'device' => $this->formatDeviceForAdmin($device),
                'tabs'   => [
                    'task' => $this->extractTaskBlocks($aggregation),
                    'wifi' => $this->extractWifiBlock($device, $aggregation),
                    'private_domain' => $this->extractPrivateDomainBlock($device),
                ],
                'today_trigger_count' => $todayTriggerCount,
            ], '获取设备配置成功');
        } catch (\Throwable $e) {
            Log::error('获取设备配置失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_nfc_config_failed');
        }
    }

    /**
     * PUT /api/admin/nfc/device/:id/config
     * body:
     *   - task:           { ai_copy_enabled }
     *   - wifi:           { ssid, password, shop_owner_qr }
     *   - private_domain: { wechat, qq, wework } 三组 url + qr_url
     */
    public function saveConfig($id = 0)
    {
        try {
            $deviceId = (int)$id;
            if ($deviceId <= 0) {
                $deviceId = (int)$this->request->param('id', 0);
            }
            if ($deviceId <= 0) {
                return $this->validationError(['id' => '设备ID不能为空']);
            }

            $device = NfcDevice::find($deviceId);
            if (!$device) {
                return $this->error('设备不存在', 404, 'device_not_found');
            }
            if (!$this->checkDeviceAccess($device)) {
                return $this->error('无权访问该设备', 403, 'device_access_denied');
            }

            $payload = $this->request->post();

            // tab1: 任务配置(开关类)
            if (isset($payload['task']) && is_array($payload['task'])) {
                $task = $payload['task'];
                if (isset($task['ai_copy_enabled'])) {
                    $device->ai_copy_enabled = (int)$task['ai_copy_enabled'] ? 1 : 0;
                }
            }

            // tab2: Wi-Fi + 店长二维码
            if (isset($payload['wifi']) && is_array($payload['wifi'])) {
                $wifi = $payload['wifi'];
                if (isset($wifi['ssid'])) {
                    $device->wifi_ssid = trim((string)$wifi['ssid']);
                }
                // 密码走加密 setter(不写明文到 DB)
                if (isset($wifi['password']) && (string)$wifi['password'] !== '') {
                    $device->wifi_password = (string)$wifi['password'];
                }
                if (isset($wifi['shop_owner_qr'])) {
                    $device->shop_owner_qr = trim((string)$wifi['shop_owner_qr']) ?: null;
                }
            }

            // tab3: 私域配置(微信/QQ/企微)
            if (isset($payload['private_domain']) && is_array($payload['private_domain'])) {
                $pd = $payload['private_domain'];
                // QQ 直接复用 agent C 的字段
                if (isset($pd['qq']) && is_array($pd['qq'])) {
                    $qq = (array)($device->qq_contact_config ?? []);
                    $qq['qq_number']    = trim((string)($pd['qq']['qq_number'] ?? ($qq['qq_number'] ?? '')));
                    $qq['qq_qrcode']    = trim((string)($pd['qq']['qq_qrcode_url'] ?? ($pd['qq']['qq_qrcode'] ?? ($qq['qq_qrcode'] ?? ''))));
                    $qq['qq_group_url'] = trim((string)($pd['qq']['qq_group_url'] ?? ($qq['qq_group_url'] ?? '')));
                    $qq['kefu_qrcode']  = trim((string)($pd['qq']['kefu_qrcode_url'] ?? ($pd['qq']['kefu_qrcode'] ?? ($qq['kefu_qrcode'] ?? ''))));
                    $device->qq_contact_config = $qq;
                }
                // 微信 / 企微
                if (isset($pd['wechat']) || isset($pd['wework'])) {
                    $wc = (array)($device->wechat_contact_config ?? []);
                    if (isset($pd['wechat'])) {
                        $wechat = $pd['wechat'];
                        $wc['wechat_url']      = trim((string)($wechat['url'] ?? ''));
                        $wc['wechat_qr_url']   = trim((string)($wechat['qr_url'] ?? ''));
                        $wc['wechat_id']       = trim((string)($wechat['id'] ?? ''));
                    }
                    if (isset($pd['wework'])) {
                        $wework = $pd['wework'];
                        $wc['wework_url']      = trim((string)($wework['url'] ?? ''));
                        $wc['wework_qr_url']   = trim((string)($wework['qr_url'] ?? ''));
                        $wc['kefu_wechat']     = trim((string)($wework['kefu_wechat'] ?? ''));
                    }
                    $device->wechat_contact_config = $wc;
                }
            }

            $device->save();

            // 清掉缓存,保证下次聚合页/触发重新读
            try {
                (new \app\service\NfcService())->clearConfigCache((string)$device->device_code);
            } catch (\Throwable $e) {
                Log::warning('清缓存失败(可忽略)', ['error' => $e->getMessage()]);
            }

            return $this->success([
                'device_id' => $deviceId,
                'updated'   => true,
            ], '保存设备配置成功');
        } catch (ValidateException $e) {
            return $this->validationError(['nfc_config' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('保存设备配置失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'save_nfc_config_failed');
        }
    }

    /**
     * GET /api/admin/nfc/device/:id/aggregation
     * 拉聚合页快照(给「任务配置」tab 显示已启用 block)
     */
    public function getAggregationSnapshot($id = 0)
    {
        try {
            $deviceId = (int)$id;
            if ($deviceId <= 0) {
                $deviceId = (int)$this->request->param('id', 0);
            }
            $device = NfcDevice::find($deviceId);
            if (!$device) {
                return $this->error('设备不存在', 404, 'device_not_found');
            }
            if (!$this->checkDeviceAccess($device)) {
                return $this->error('无权访问该设备', 403, 'device_access_denied');
            }
            $svc = new \app\service\AggregationPageService();
            $payload = $svc->getByDeviceCode((string)$device->device_code);
            return $this->success([
                'device_id' => $deviceId,
                'blocks'    => $payload['blocks'] ?? [],
                'meta'      => $payload['meta'] ?? [],
            ], '获取聚合页快照成功');
        } catch (\Throwable $e) {
            Log::error('获取聚合页快照失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_aggregation_snapshot_failed');
        }
    }

    // =================================================================
    // helper
    // =================================================================

    protected function formatDeviceForAdmin(NfcDevice $device): array
    {
        return [
            'id'               => (int)$device->id,
            'device_code'      => (string)$device->device_code,
            'device_name'      => (string)$device->device_name,
            'merchant_id'      => (int)$device->merchant_id,
            'type'             => (string)$device->type,
            'type_text'        => $device->type_text,
            'device_type'      => (string)$device->device_type,
            'trigger_mode'     => (string)$device->trigger_mode,
            'trigger_mode_text'=> $device->trigger_mode_text,
            'status'           => (int)$device->status,
            'status_text'      => $device->status_text,
            'is_online'        => (bool)$device->is_online,
            'battery_level'    => (int)$device->battery_level,
            'last_heartbeat'   => $device->last_heartbeat,
            'location'         => (string)($device->location ?? ''),
        ];
    }

    protected function extractTaskBlocks(array $aggregation): array
    {
        $blocks = $aggregation['blocks'] ?? [];
        $out = [];
        $map = [
            'wifi'     => 'Wi-Fi 一键连',
            'publish'  => '发布任务(文案/视频)',
            'groupbuy' => '团购券',
            'review'   => '多平台点评',
            'contact'  => '私域加粉',
            'lottery'  => '大转盘抽奖',
        ];
        foreach ($map as $k => $label) {
            $enabled = !empty($blocks[$k]['enabled']);
            $out[] = [
                'block'   => $k,
                'label'   => $label,
                'enabled' => $enabled,
            ];
        }
        return $out;
    }

    protected function extractWifiBlock(NfcDevice $device, array $aggregation): array
    {
        // 注意: NfcDevice::getWifiPasswordAttr 永远返回空串(安全设计),
        // 判断"是否已设置"必须读原始字段,否则 password_set 恒为 false
        $rawPwd = '';
        try {
            $rawPwd = (string)$device->getData('wifi_password');
        } catch (\Throwable $e) {
            $rawPwd = '';
        }
        return [
            'ssid'           => (string)($device->wifi_ssid ?? ''),
            // 密码不回传明文,只回「是否已设置」
            'password_set'   => $rawPwd !== '',
            'shop_owner_qr'  => (string)($device->shop_owner_qr ?? ''),
            'has_wifi_block' => !empty($aggregation['blocks']['wifi']['enabled']),
        ];
    }

    protected function extractPrivateDomainBlock(NfcDevice $device): array
    {
        $qq = (array)($device->qq_contact_config ?? []);
        $wc = (array)($device->wechat_contact_config ?? []);

        return [
            'wechat' => [
                'url'     => (string)($wc['wechat_url'] ?? ''),
                'qr_url'  => (string)($wc['wechat_qr_url'] ?? ''),
                'id'      => (string)($wc['wechat_id'] ?? ''),
            ],
            'wework' => [
                'url'         => (string)($wc['wework_url'] ?? ''),
                'qr_url'      => (string)($wc['wework_qr_url'] ?? ''),
                'kefu_wechat' => (string)($wc['kefu_wechat'] ?? ''),
            ],
            'qq' => [
                'qq_number'    => (string)($qq['qq_number'] ?? ''),
                'qq_qrcode'    => (string)($qq['qq_qrcode'] ?? ''),
                'qq_group_url' => (string)($qq['qq_group_url'] ?? ''),
                'kefu_qrcode'  => (string)($qq['kefu_qrcode'] ?? ''),
            ],
        ];
    }

    protected function checkDeviceAccess(NfcDevice $device): bool
    {
        try {
            $userRole   = $this->request->user_role ?? '';
            $merchantId = (int)($this->request->merchant_id ?? 0);
            if ($userRole === 'admin' || $merchantId === 0) {
                return true;
            }
            return (int)$device->merchant_id === $merchantId;
        } catch (\Throwable $e) {
            return false;
        }
    }
}