<?php
declare (strict_types = 1);

namespace app\service;

use app\model\DeviceAlert;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;

/**
 * 告警规则配置服务
 */
class AlertRuleService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'alert_rule_';

    /**
     * 规则缓存时间(秒)
     */
    const CACHE_TTL = 1800; // 30分钟

    /**
     * 默认告警规则
     */
    protected array $defaultRules = [
        DeviceAlert::TYPE_OFFLINE => [
            'enabled' => true,
            'threshold' => 600, // 10分钟
            'level_thresholds' => [
                DeviceAlert::LEVEL_LOW => 600,      // 10分钟
                DeviceAlert::LEVEL_MEDIUM => 1800,  // 30分钟
                DeviceAlert::LEVEL_HIGH => 3600,    // 1小时
                DeviceAlert::LEVEL_CRITICAL => 7200 // 2小时
            ],
            'notification_channels' => ['system', 'wechat'],
            'suppress_time' => 1800, // 30分钟内不重复告警
            'description' => '设备离线时间超过阈值时触发告警'
        ],
        DeviceAlert::TYPE_LOW_BATTERY => [
            'enabled' => true,
            'threshold' => 20, // 20%
            'level_thresholds' => [
                DeviceAlert::LEVEL_CRITICAL => 5,  // 5%
                DeviceAlert::LEVEL_HIGH => 10,     // 10%
                DeviceAlert::LEVEL_MEDIUM => 15,   // 15%
                DeviceAlert::LEVEL_LOW => 20       // 20%
            ],
            'notification_channels' => ['system', 'wechat'],
            'suppress_time' => 3600, // 1小时内不重复告警
            'description' => '设备电池电量低于阈值时触发告警'
        ],
        DeviceAlert::TYPE_RESPONSE_TIMEOUT => [
            'enabled' => true,
            'threshold' => 5000, // 5秒
            'level_thresholds' => [
                DeviceAlert::LEVEL_LOW => 5000,      // 5秒
                DeviceAlert::LEVEL_MEDIUM => 10000,  // 10秒
                DeviceAlert::LEVEL_HIGH => 20000,    // 20秒
                DeviceAlert::LEVEL_CRITICAL => 30000 // 30秒
            ],
            'notification_channels' => ['system'],
            'suppress_time' => 600, // 10分钟内不重复告警
            'description' => '设备响应时间超过阈值时触发告警'
        ],
        DeviceAlert::TYPE_DEVICE_ERROR => [
            'enabled' => true,
            'threshold' => 1, // 任何错误都告警
            'error_levels' => [
                'SYSTEM_ERROR' => DeviceAlert::LEVEL_CRITICAL,
                'HARDWARE_ERROR' => DeviceAlert::LEVEL_HIGH,
                'NETWORK_ERROR' => DeviceAlert::LEVEL_MEDIUM,
                'CONFIG_ERROR' => DeviceAlert::LEVEL_MEDIUM,
                'USER_ERROR' => DeviceAlert::LEVEL_LOW
            ],
            'notification_channels' => ['system', 'wechat', 'sms'],
            'suppress_time' => 300, // 5分钟内不重复告警
            'description' => '设备发生故障错误时触发告警'
        ],
        DeviceAlert::TYPE_SIGNAL_WEAK => [
            'enabled' => true,
            'threshold' => 30, // 30%
            'level_thresholds' => [
                DeviceAlert::LEVEL_CRITICAL => 10,  // 10%
                DeviceAlert::LEVEL_HIGH => 15,      // 15%
                DeviceAlert::LEVEL_MEDIUM => 20,    // 20%
                DeviceAlert::LEVEL_LOW => 30        // 30%
            ],
            'notification_channels' => ['system'],
            'suppress_time' => 1800, // 30分钟内不重复告警
            'description' => '设备信号强度低于阈值时触发告警'
        ],
        DeviceAlert::TYPE_TEMPERATURE => [
            'enabled' => true,
            'min_threshold' => -10, // -10°C
            'max_threshold' => 70,  // 70°C
            'level_thresholds' => [
                DeviceAlert::LEVEL_LOW => 5,       // 温度偏差5度
                DeviceAlert::LEVEL_MEDIUM => 10,   // 温度偏差10度
                DeviceAlert::LEVEL_HIGH => 15,     // 温度偏差15度
                DeviceAlert::LEVEL_CRITICAL => 20  // 温度偏差20度
            ],
            'notification_channels' => ['system'],
            'suppress_time' => 1800, // 30分钟内不重复告警
            'description' => '设备温度超出正常范围时触发告警'
        ],
        DeviceAlert::TYPE_HEARTBEAT => [
            'enabled' => true,
            'threshold' => 300, // 5分钟
            'level_thresholds' => [
                DeviceAlert::LEVEL_LOW => 300,      // 5分钟
                DeviceAlert::LEVEL_MEDIUM => 900,   // 15分钟
                DeviceAlert::LEVEL_HIGH => 1800,    // 30分钟
                DeviceAlert::LEVEL_CRITICAL => 3600 // 1小时
            ],
            'notification_channels' => ['system', 'wechat'],
            'suppress_time' => 900, // 15分钟内不重复告警
            'description' => '设备心跳间隔超过阈值时触发告警'
        ],
        DeviceAlert::TYPE_TRIGGER_FAILED => [
            'enabled' => true,
            'threshold' => 5, // 5次失败
            'level_thresholds' => [
                DeviceAlert::LEVEL_LOW => 5,        // 5次
                DeviceAlert::LEVEL_MEDIUM => 10,    // 10次
                DeviceAlert::LEVEL_HIGH => 20,      // 20次
                DeviceAlert::LEVEL_CRITICAL => 50   // 50次
            ],
            'notification_channels' => ['system'],
            'suppress_time' => 1800, // 30分钟内不重复告警
            'description' => '设备触发失败次数超过阈值时触发告警'
        ]
    ];

    /**
     * 获取告警规则
     *
     * @param int $merchantId 商家ID
     * @param string $alertType 告警类型
     * @return array
     */
    public function getRule(int $merchantId, string $alertType): array
    {
        // 先从缓存获取
        $cacheKey = self::CACHE_PREFIX . "merchant_{$merchantId}_{$alertType}";
        $rule = Cache::get($cacheKey);

        if ($rule !== null) {
            return $rule;
        }

        // 获取商家自定义规则
        $customRule = $this->getCustomRule($merchantId, $alertType);

        // 获取默认规则
        $defaultRule = $this->defaultRules[$alertType] ?? [];

        // 合并规则（自定义规则优先）
        $rule = array_merge($defaultRule, $customRule);

        // 缓存规则
        Cache::set($cacheKey, $rule, self::CACHE_TTL);

        return $rule;
    }

    /**
     * 获取所有告警规则
     *
     * @param int $merchantId 商家ID
     * @return array
     */
    public function getAllRules(int $merchantId): array
    {
        $rules = [];

        foreach (array_keys($this->defaultRules) as $alertType) {
            $rules[$alertType] = $this->getRule($merchantId, $alertType);
        }

        return $rules;
    }

    /**
     * 设置告警规则
     *
     * @param int $merchantId 商家ID
     * @param string $alertType 告警类型
     * @param array $rule 规则配置
     * @return bool
     */
    public function setRule(int $merchantId, string $alertType, array $rule): bool
    {
        try {
            // 验证规则格式
            $this->validateRule($alertType, $rule);

            // 保存自定义规则
            $this->saveCustomRule($merchantId, $alertType, $rule);

            // 清除缓存
            $cacheKey = self::CACHE_PREFIX . "merchant_{$merchantId}_{$alertType}";
            Cache::delete($cacheKey);

            Log::info('告警规则已更新', [
                'merchant_id' => $merchantId,
                'alert_type' => $alertType,
                'rule' => $rule
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('设置告警规则失败', [
                'merchant_id' => $merchantId,
                'alert_type' => $alertType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 批量设置告警规则
     *
     * @param int $merchantId 商家ID
     * @param array $rules 规则配置数组
     * @return array
     */
    public function setBatchRules(int $merchantId, array $rules): array
    {
        $results = [];

        foreach ($rules as $alertType => $rule) {
            $result = $this->setRule($merchantId, $alertType, $rule);
            $results[$alertType] = [
                'success' => $result,
                'message' => $result ? '设置成功' : '设置失败'
            ];
        }

        return $results;
    }

    /**
     * 重置告警规则为默认值
     *
     * @param int $merchantId 商家ID
     * @param string $alertType 告警类型（可选）
     * @return bool
     */
    public function resetRule(int $merchantId, string $alertType = null): bool
    {
        try {
            if ($alertType) {
                // 重置指定类型的规则
                $this->deleteCustomRule($merchantId, $alertType);
                $cacheKey = self::CACHE_PREFIX . "merchant_{$merchantId}_{$alertType}";
                Cache::delete($cacheKey);
            } else {
                // 重置所有规则
                foreach (array_keys($this->defaultRules) as $type) {
                    $this->deleteCustomRule($merchantId, $type);
                    $cacheKey = self::CACHE_PREFIX . "merchant_{$merchantId}_{$type}";
                    Cache::delete($cacheKey);
                }
            }

            Log::info('告警规则已重置', [
                'merchant_id' => $merchantId,
                'alert_type' => $alertType ?: 'all'
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('重置告警规则失败', [
                'merchant_id' => $merchantId,
                'alert_type' => $alertType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 启用/禁用告警规则
     *
     * @param int $merchantId 商家ID
     * @param string $alertType 告警类型
     * @param bool $enabled 是否启用
     * @return bool
     */
    public function toggleRule(int $merchantId, string $alertType, bool $enabled): bool
    {
        $rule = $this->getRule($merchantId, $alertType);
        $rule['enabled'] = $enabled;

        return $this->setRule($merchantId, $alertType, $rule);
    }

    /**
     * 获取告警规则模板
     *
     * @return array
     */
    public function getRuleTemplates(): array
    {
        return [
            'basic' => [
                'name' => '基础告警',
                'description' => '适用于一般场景的基础告警配置',
                'rules' => $this->getBasicTemplate()
            ],
            'strict' => [
                'name' => '严格告警',
                'description' => '更严格的告警阈值，适用于重要设备',
                'rules' => $this->getStrictTemplate()
            ],
            'relaxed' => [
                'name' => '宽松告警',
                'description' => '较宽松的告警阈值，适用于测试环境',
                'rules' => $this->getRelaxedTemplate()
            ]
        ];
    }

    /**
     * 应用告警规则模板
     *
     * @param int $merchantId 商家ID
     * @param string $templateName 模板名称
     * @return bool
     */
    public function applyTemplate(int $merchantId, string $templateName): bool
    {
        $templates = $this->getRuleTemplates();

        if (!isset($templates[$templateName])) {
            Log::error('告警规则模板不存在', ['template' => $templateName]);
            return false;
        }

        $rules = $templates[$templateName]['rules'];
        $results = $this->setBatchRules($merchantId, $rules);

        $success = true;
        foreach ($results as $result) {
            if (!$result['success']) {
                $success = false;
                break;
            }
        }

        Log::info('应用告警规则模板', [
            'merchant_id' => $merchantId,
            'template' => $templateName,
            'success' => $success
        ]);

        return $success;
    }

    /**
     * 验证告警规则格式
     *
     * @param string $alertType
     * @param array $rule
     * @throws \InvalidArgumentException
     */
    protected function validateRule(string $alertType, array $rule): void
    {
        // 检查必需字段
        $requiredFields = ['enabled'];
        foreach ($requiredFields as $field) {
            if (!isset($rule[$field])) {
                throw new \InvalidArgumentException("缺少必需字段: {$field}");
            }
        }

        // 检查告警类型是否有效
        if (!isset($this->defaultRules[$alertType])) {
            throw new \InvalidArgumentException("无效的告警类型: {$alertType}");
        }

        // 检查通知渠道
        if (isset($rule['notification_channels'])) {
            $validChannels = [
                DeviceAlert::CHANNEL_WECHAT,
                DeviceAlert::CHANNEL_SMS,
                DeviceAlert::CHANNEL_EMAIL,
                DeviceAlert::CHANNEL_WEBHOOK,
                DeviceAlert::CHANNEL_SYSTEM
            ];

            foreach ($rule['notification_channels'] as $channel) {
                if (!in_array($channel, $validChannels)) {
                    throw new \InvalidArgumentException("无效的通知渠道: {$channel}");
                }
            }
        }

        // 检查告警级别阈值
        if (isset($rule['level_thresholds'])) {
            $validLevels = [
                DeviceAlert::LEVEL_LOW,
                DeviceAlert::LEVEL_MEDIUM,
                DeviceAlert::LEVEL_HIGH,
                DeviceAlert::LEVEL_CRITICAL
            ];

            foreach (array_keys($rule['level_thresholds']) as $level) {
                if (!in_array($level, $validLevels)) {
                    throw new \InvalidArgumentException("无效的告警级别: {$level}");
                }
            }
        }
    }

    /**
     * 获取自定义规则
     *
     * @param int $merchantId
     * @param string $alertType
     * @return array
     */
    protected function getCustomRule(int $merchantId, string $alertType): array
    {
        // 这里可以从数据库、配置文件等地方获取自定义规则
        // 暂时从缓存获取
        $cacheKey = "custom_alert_rule:merchant_{$merchantId}:{$alertType}";
        return Cache::get($cacheKey, []);
    }

    /**
     * 保存自定义规则
     *
     * @param int $merchantId
     * @param string $alertType
     * @param array $rule
     */
    protected function saveCustomRule(int $merchantId, string $alertType, array $rule): void
    {
        // 这里可以保存到数据库、配置文件等
        // 暂时保存到缓存
        $cacheKey = "custom_alert_rule:merchant_{$merchantId}:{$alertType}";
        Cache::set($cacheKey, $rule, 7 * 24 * 3600); // 保存7天
    }

    /**
     * 删除自定义规则
     *
     * @param int $merchantId
     * @param string $alertType
     */
    protected function deleteCustomRule(int $merchantId, string $alertType): void
    {
        $cacheKey = "custom_alert_rule:merchant_{$merchantId}:{$alertType}";
        Cache::delete($cacheKey);
    }

    /**
     * 获取基础模板
     *
     * @return array
     */
    protected function getBasicTemplate(): array
    {
        return $this->defaultRules;
    }

    /**
     * 获取严格模板
     *
     * @return array
     */
    protected function getStrictTemplate(): array
    {
        $rules = $this->defaultRules;

        // 调整为更严格的阈值
        $rules[DeviceAlert::TYPE_OFFLINE]['threshold'] = 300; // 5分钟
        $rules[DeviceAlert::TYPE_LOW_BATTERY]['threshold'] = 30; // 30%
        $rules[DeviceAlert::TYPE_RESPONSE_TIMEOUT]['threshold'] = 3000; // 3秒
        $rules[DeviceAlert::TYPE_HEARTBEAT]['threshold'] = 180; // 3分钟

        // 增加更多通知渠道
        foreach ($rules as &$rule) {
            if (!in_array('sms', $rule['notification_channels'])) {
                $rule['notification_channels'][] = 'sms';
            }
        }

        return $rules;
    }

    /**
     * 获取宽松模板
     *
     * @return array
     */
    protected function getRelaxedTemplate(): array
    {
        $rules = $this->defaultRules;

        // 调整为更宽松的阈值
        $rules[DeviceAlert::TYPE_OFFLINE]['threshold'] = 1800; // 30分钟
        $rules[DeviceAlert::TYPE_LOW_BATTERY]['threshold'] = 10; // 10%
        $rules[DeviceAlert::TYPE_RESPONSE_TIMEOUT]['threshold'] = 10000; // 10秒
        $rules[DeviceAlert::TYPE_HEARTBEAT]['threshold'] = 900; // 15分钟

        // 只保留系统通知
        foreach ($rules as &$rule) {
            $rule['notification_channels'] = ['system'];
        }

        return $rules;
    }

    /**
     * 清除所有规则缓存
     *
     * @param int $merchantId
     */
    public function clearRuleCache(int $merchantId): void
    {
        foreach (array_keys($this->defaultRules) as $alertType) {
            $cacheKey = self::CACHE_PREFIX . "merchant_{$merchantId}_{$alertType}";
            Cache::delete($cacheKey);
        }

        Log::info('告警规则缓存已清除', ['merchant_id' => $merchantId]);
    }

    /**
     * 获取规则统计信息
     *
     * @param int $merchantId
     * @return array
     */
    public function getRuleStats(int $merchantId): array
    {
        $rules = $this->getAllRules($merchantId);
        $stats = [
            'total' => count($rules),
            'enabled' => 0,
            'disabled' => 0,
            'has_custom' => 0,
            'notification_channels' => []
        ];

        foreach ($rules as $alertType => $rule) {
            if ($rule['enabled']) {
                $stats['enabled']++;
            } else {
                $stats['disabled']++;
            }

            // 检查是否有自定义配置
            $customRule = $this->getCustomRule($merchantId, $alertType);
            if (!empty($customRule)) {
                $stats['has_custom']++;
            }

            // 统计通知渠道
            foreach ($rule['notification_channels'] as $channel) {
                if (!isset($stats['notification_channels'][$channel])) {
                    $stats['notification_channels'][$channel] = 0;
                }
                $stats['notification_channels'][$channel]++;
            }
        }

        return $stats;
    }
}