<?php
declare (strict_types = 1);

namespace app\service;

use app\model\NfcDevice;
use app\model\User;
use think\facade\Log;
use think\facade\Cache;
use think\exception\ValidateException;

/**
 * WiFi连接服务类
 * 处理NFC设备触发的WiFi自动连接功能
 */
class WifiService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'wifi_';

    /**
     * WiFi配置缓存时间(秒) - 10分钟
     */
    const CONFIG_CACHE_TTL = 600;

    /**
     * 连接记录缓存时间(秒) - 1小时
     */
    const CONNECTION_CACHE_TTL = 3600;

    /**
     * 访问频率限制时间窗口(秒) - 1分钟
     */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * 访问频率限制次数
     */
    const RATE_LIMIT_MAX = 10;

    /**
     * WiFi加密类型常量
     */
    const ENCRYPTION_NONE = 'nopass';
    const ENCRYPTION_WEP = 'WEP';
    const ENCRYPTION_WPA = 'WPA';
    const ENCRYPTION_WPA2 = 'WPA2';
    const ENCRYPTION_WPA3 = 'WPA3';

    /**
     * 平台类型常量
     */
    const PLATFORM_IOS = 'ios';
    const PLATFORM_ANDROID = 'android';
    const PLATFORM_WECHAT = 'wechat';

    /**
     * 生成WiFi配置信息
     *
     * @param NfcDevice $device NFC设备
     * @param string $platform 平台类型 (ios/android/wechat)
     * @param User|null $user 用户信息(可选)
     * @return array
     * @throws ValidateException
     */
    public function generateWifiConfig(NfcDevice $device, string $platform = self::PLATFORM_WECHAT, ?User $user = null): array
    {
        // 验证设备WiFi配置
        if (empty($device->wifi_ssid)) {
            throw new ValidateException('设备未配置WiFi信息');
        }

        // 验证SSID格式
        $this->validateSSID($device->wifi_ssid);

        // 确定加密类型
        $encryptionType = $this->determineEncryptionType($device->wifi_password);

        // 验证密码强度(如果有密码)
        if (!empty($device->wifi_password)) {
            $this->validatePassword($device->wifi_password, $encryptionType);
        }

        // 检查访问频率限制
        if ($user) {
            $this->checkRateLimit($user->id, $device->id);
        }

        // 根据平台生成对应格式
        $config = match ($platform) {
            self::PLATFORM_IOS => $this->generateIOSConfig($device, $encryptionType),
            self::PLATFORM_ANDROID => $this->generateAndroidConfig($device, $encryptionType),
            self::PLATFORM_WECHAT => $this->generateWechatConfig($device, $encryptionType),
            default => throw new ValidateException('不支持的平台类型'),
        };

        // 记录连接请求
        if ($user) {
            $this->recordConnectionRequest($user->id, $device->id, $platform);
        }

        // 记录日志
        Log::info('生成WiFi配置', [
            'device_id' => $device->id,
            'device_code' => $device->device_code,
            'ssid' => $device->wifi_ssid,
            'platform' => $platform,
            'user_id' => $user ? $user->id : null,
            'encryption' => $encryptionType
        ]);

        return $config;
    }

    /**
     * 生成iOS配置描述文件(mobileconfig)
     *
     * @param NfcDevice $device
     * @param string $encryptionType
     * @return array
     */
    protected function generateIOSConfig(NfcDevice $device, string $encryptionType): array
    {
        // iOS需要生成mobileconfig文件
        $uuid = $this->generateUUID();
        $payloadUUID = $this->generateUUID();

        // 构建mobileconfig内容
        $mobileconfig = $this->buildMobileconfig([
            'PayloadDisplayName' => '无线网络配置 - ' . $device->wifi_ssid,
            'PayloadDescription' => '连接到 ' . $device->wifi_ssid,
            'PayloadIdentifier' => 'com.xiaomotui.wifi.' . $device->id,
            'PayloadUUID' => $uuid,
            'PayloadType' => 'Configuration',
            'PayloadVersion' => 1,
            'PayloadContent' => [
                [
                    'PayloadType' => 'com.apple.wifi.managed',
                    'PayloadVersion' => 1,
                    'PayloadIdentifier' => 'com.xiaomotui.wifi.network.' . $device->id,
                    'PayloadUUID' => $payloadUUID,
                    'PayloadDisplayName' => $device->wifi_ssid,
                    'SSID_STR' => $device->wifi_ssid,
                    'HIDDEN_NETWORK' => false,
                    'AutoJoin' => true,
                    'EncryptionType' => $this->mapEncryptionForIOS($encryptionType),
                    'Password' => $device->wifi_password ?: '',
                ]
            ]
        ]);

        // 生成下载链接
        $configFileUrl = $this->saveMobileconfig($device->id, $mobileconfig);

        return [
            'platform' => self::PLATFORM_IOS,
            'format' => 'mobileconfig',
            'ssid' => $device->wifi_ssid,
            'encryption' => $encryptionType,
            'config_url' => $configFileUrl,
            'download_url' => $configFileUrl,
            'install_guide' => [
                'step1' => '点击下载配置文件',
                'step2' => '在"设置"中找到"已下载描述文件"',
                'step3' => '点击"安装"并按照提示完成',
                'step4' => 'WiFi将自动连接'
            ],
            'notes' => [
                'iOS 11及以上版本支持',
                '需要在设置中允许安装描述文件',
                '首次安装需要输入设备密码确认'
            ]
        ];
    }

    /**
     * 生成Android配置URI格式
     *
     * @param NfcDevice $device
     * @param string $encryptionType
     * @return array
     */
    protected function generateAndroidConfig(NfcDevice $device, string $encryptionType): array
    {
        // Android使用标准WiFi URI格式
        // WIFI:T:加密类型;S:SSID;P:密码;H:是否隐藏;;
        $wifiUri = sprintf(
            'WIFI:T:%s;S:%s;P:%s;H:%s;;',
            $this->mapEncryptionForAndroid($encryptionType),
            $this->escapeWifiString($device->wifi_ssid),
            $this->escapeWifiString($device->wifi_password ?: ''),
            'false'
        );

        // 生成二维码
        $qrCodeUrl = $this->generateQRCode($wifiUri, $device->id);

        return [
            'platform' => self::PLATFORM_ANDROID,
            'format' => 'wifi_uri',
            'ssid' => $device->wifi_ssid,
            'encryption' => $encryptionType,
            'uri' => $wifiUri,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_data' => $wifiUri,
            'install_guide' => [
                'step1' => '使用相机或WiFi设置扫描二维码',
                'step2' => '系统会自动识别WiFi配置',
                'step3' => '点击"连接"即可',
                'step4' => '部分设备支持NFC直接连接'
            ],
            'notes' => [
                'Android 10及以上版本原生支持二维码连接',
                '部分设备需要第三方二维码扫描应用',
                '支持NFC快速连接功能'
            ]
        ];
    }

    /**
     * 生成微信小程序配置格式
     *
     * @param NfcDevice $device
     * @param string $encryptionType
     * @return array
     */
    protected function generateWechatConfig(NfcDevice $device, string $encryptionType): array
    {
        // 微信小程序返回配置信息供小程序调用
        $wifiUri = sprintf(
            'WIFI:T:%s;S:%s;P:%s;H:%s;;',
            $this->mapEncryptionForAndroid($encryptionType),
            $this->escapeWifiString($device->wifi_ssid),
            $this->escapeWifiString($device->wifi_password ?: ''),
            'false'
        );

        // 生成二维码
        $qrCodeUrl = $this->generateQRCode($wifiUri, $device->id);

        return [
            'platform' => self::PLATFORM_WECHAT,
            'format' => 'mixed',
            'ssid' => $device->wifi_ssid,
            'password' => $this->shouldShowPassword() ? $device->wifi_password : '******',
            'encryption' => $encryptionType,
            'encryption_text' => $this->getEncryptionText($encryptionType),
            'hidden' => false,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_data' => $wifiUri,
            'connection_guide' => [
                'title' => '连接WiFi',
                'subtitle' => '扫描二维码或手动连接',
                'methods' => [
                    [
                        'type' => 'qrcode',
                        'title' => '扫描二维码',
                        'description' => '使用相机或WiFi设置扫描二维码自动连接',
                        'steps' => [
                            '保存二维码到相册',
                            '打开手机相机或WiFi设置',
                            '扫描二维码即可连接'
                        ]
                    ],
                    [
                        'type' => 'manual',
                        'title' => '手动连接',
                        'description' => '在WiFi设置中手动输入信息',
                        'steps' => [
                            '打开手机WiFi设置',
                            '选择网络: ' . $device->wifi_ssid,
                            '输入密码并连接'
                        ]
                    ]
                ]
            ],
            'network_info' => [
                'type' => '商用WiFi',
                'speed' => $this->estimateNetworkSpeed(),
                'coverage' => '全场覆盖',
                'time_limit' => '无时长限制',
                'device_limit' => '每人最多连接3台设备'
            ],
            'tips' => [
                '连接成功后可享受免费上网服务',
                '为保证网络质量，请合理使用',
                '如连接失败，请联系店员协助'
            ],
            'security_notes' => [
                '采用' . $this->getEncryptionText($encryptionType) . '加密',
                '网络数据安全传输',
                '请勿在公共WiFi下进行敏感操作'
            ]
        ];
    }

    /**
     * 验证SSID格式
     *
     * @param string $ssid
     * @throws ValidateException
     */
    protected function validateSSID(string $ssid): void
    {
        // SSID长度验证 (1-32字符)
        if (strlen($ssid) < 1 || strlen($ssid) > 32) {
            throw new ValidateException('WiFi名称长度必须在1-32个字符之间');
        }

        // SSID不能包含特殊控制字符
        if (preg_match('/[\x00-\x1F\x7F]/', $ssid)) {
            throw new ValidateException('WiFi名称包含非法字符');
        }
    }

    /**
     * 验证密码强度
     *
     * @param string $password
     * @param string $encryptionType
     * @throws ValidateException
     */
    protected function validatePassword(string $password, string $encryptionType): void
    {
        if ($encryptionType === self::ENCRYPTION_NONE) {
            return; // 无密码网络不需要验证
        }

        // WEP密码长度验证 (5或13个ASCII字符, 或10或26个十六进制字符)
        if ($encryptionType === self::ENCRYPTION_WEP) {
            $len = strlen($password);
            if (!in_array($len, [5, 10, 13, 26])) {
                throw new ValidateException('WEP密码长度必须是5、10、13或26个字符');
            }
        }

        // WPA/WPA2/WPA3密码长度验证 (8-63字符)
        if (in_array($encryptionType, [self::ENCRYPTION_WPA, self::ENCRYPTION_WPA2, self::ENCRYPTION_WPA3])) {
            $len = strlen($password);
            if ($len < 8 || $len > 63) {
                throw new ValidateException('WPA密码长度必须在8-63个字符之间');
            }
        }
    }

    /**
     * 确定加密类型
     *
     * @param string|null $password
     * @return string
     */
    protected function determineEncryptionType(?string $password): string
    {
        // 如果没有密码，则为开放网络
        if (empty($password)) {
            return self::ENCRYPTION_NONE;
        }

        // 默认使用WPA2加密(最常见且安全性较好)
        return self::ENCRYPTION_WPA2;
    }

    /**
     * 检查访问频率限制
     *
     * @param int $userId
     * @param int $deviceId
     * @throws ValidateException
     */
    protected function checkRateLimit(int $userId, int $deviceId): void
    {
        $cacheKey = self::CACHE_PREFIX . 'rate_limit_' . $userId . '_' . $deviceId;
        $requestCount = Cache::get($cacheKey, 0);

        if ($requestCount >= self::RATE_LIMIT_MAX) {
            throw new ValidateException('访问过于频繁，请稍后再试');
        }

        // 增加计数
        Cache::set($cacheKey, $requestCount + 1, self::RATE_LIMIT_WINDOW);
    }

    /**
     * 记录连接请求
     *
     * @param int $userId
     * @param int $deviceId
     * @param string $platform
     * @return bool
     */
    protected function recordConnectionRequest(int $userId, int $deviceId, string $platform): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'connection_' . date('Ymd') . '_' . $userId . '_' . $deviceId;

        $record = [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'platform' => $platform,
            'request_time' => date('Y-m-d H:i:s'),
            'ip' => request()->ip(),
            'user_agent' => request()->header('user-agent', '')
        ];

        // 缓存连接记录
        Cache::set($cacheKey, $record, self::CONNECTION_CACHE_TTL);

        // 记录日志
        Log::info('WiFi连接请求', $record);

        return true;
    }

    /**
     * 记录连接反馈
     *
     * @param int $userId
     * @param int $deviceId
     * @param bool $success
     * @param string $message
     * @return bool
     */
    public function recordConnectionFeedback(int $userId, int $deviceId, bool $success, string $message = ''): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'feedback_' . date('Ymd') . '_' . $userId . '_' . $deviceId;

        $feedback = [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'success' => $success,
            'message' => $message,
            'feedback_time' => date('Y-m-d H:i:s')
        ];

        // 缓存反馈记录
        Cache::set($cacheKey, $feedback, self::CONNECTION_CACHE_TTL);

        // 记录日志
        Log::info('WiFi连接反馈', $feedback);

        return true;
    }

    /**
     * 获取连接统计信息
     *
     * @param int $deviceId
     * @param string $date 日期 (Y-m-d格式)
     * @return array
     */
    public function getConnectionStats(int $deviceId, string $date = ''): array
    {
        if (empty($date)) {
            $date = date('Y-m-d');
        }

        // 这里可以从缓存或数据库中获取统计信息
        // 暂时返回模拟数据
        return [
            'device_id' => $deviceId,
            'date' => $date,
            'total_requests' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'success_rate' => 0.0,
            'platforms' => [
                'ios' => 0,
                'android' => 0,
                'wechat' => 0
            ]
        ];
    }

    /**
     * 构建iOS mobileconfig文件内容
     *
     * @param array $config
     * @return string
     */
    protected function buildMobileconfig(array $config): string
    {
        // 使用plist格式(XML)
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' . "\n";
        $xml .= '<plist version="1.0">' . "\n";
        $xml .= $this->arrayToPlist($config);
        $xml .= '</plist>';

        return $xml;
    }

    /**
     * 将数组转换为plist格式
     *
     * @param mixed $data
     * @param int $indent
     * @return string
     */
    protected function arrayToPlist($data, int $indent = 0): string
    {
        $indentStr = str_repeat('  ', $indent);
        $xml = '';

        if (is_array($data)) {
            // 判断是否为关联数组
            if (array_keys($data) !== range(0, count($data) - 1)) {
                // 关联数组 -> dict
                $xml .= $indentStr . "<dict>\n";
                foreach ($data as $key => $value) {
                    $xml .= $indentStr . "  <key>" . htmlspecialchars($key) . "</key>\n";
                    $xml .= $this->arrayToPlist($value, $indent + 1);
                }
                $xml .= $indentStr . "</dict>\n";
            } else {
                // 索引数组 -> array
                $xml .= $indentStr . "<array>\n";
                foreach ($data as $value) {
                    $xml .= $this->arrayToPlist($value, $indent + 1);
                }
                $xml .= $indentStr . "</array>\n";
            }
        } elseif (is_bool($data)) {
            $xml .= $indentStr . ($data ? '<true/>' : '<false/>') . "\n";
        } elseif (is_int($data)) {
            $xml .= $indentStr . '<integer>' . $data . '</integer>' . "\n";
        } elseif (is_float($data)) {
            $xml .= $indentStr . '<real>' . $data . '</real>' . "\n";
        } else {
            $xml .= $indentStr . '<string>' . htmlspecialchars((string)$data) . '</string>' . "\n";
        }

        return $xml;
    }

    /**
     * 保存mobileconfig文件
     *
     * @param int $deviceId
     * @param string $content
     * @return string 返回文件URL
     */
    protected function saveMobileconfig(int $deviceId, string $content): string
    {
        // 生成文件路径
        $filename = 'wifi_config_' . $deviceId . '_' . time() . '.mobileconfig';
        $directory = root_path('public') . 'uploads/wifi/';

        // 确保目录存在
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filepath = $directory . $filename;

        // 保存文件
        file_put_contents($filepath, $content);

        // 返回URL
        return config('app.domain') . '/uploads/wifi/' . $filename;
    }

    /**
     * 生成二维码
     *
     * @param string $data
     * @param int $deviceId
     * @return string 返回二维码URL
     */
    protected function generateQRCode(string $data, int $deviceId): string
    {
        // 这里可以集成二维码生成库(如 endroid/qr-code)
        // 暂时返回在线生成服务的URL
        $encodedData = urlencode($data);

        // 使用项目域名生成二维码(假设有二维码生成接口)
        return config('app.domain') . '/qr/generate?data=' . $encodedData . '&size=300&device=' . $deviceId;
    }

    /**
     * 生成UUID
     *
     * @return string
     */
    protected function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * 转义WiFi字符串中的特殊字符
     *
     * @param string $str
     * @return string
     */
    protected function escapeWifiString(string $str): string
    {
        // WiFi URI格式中需要转义的字符: \ " ; , :
        $search = ['\\', '"', ';', ',', ':'];
        $replace = ['\\\\', '\\"', '\\;', '\\,', '\\:'];
        return str_replace($search, $replace, $str);
    }

    /**
     * 映射加密类型到iOS格式
     *
     * @param string $encryptionType
     * @return string
     */
    protected function mapEncryptionForIOS(string $encryptionType): string
    {
        return match ($encryptionType) {
            self::ENCRYPTION_NONE => 'None',
            self::ENCRYPTION_WEP => 'WEP',
            self::ENCRYPTION_WPA => 'WPA',
            self::ENCRYPTION_WPA2, self::ENCRYPTION_WPA3 => 'WPA2',
            default => 'WPA2',
        };
    }

    /**
     * 映射加密类型到Android格式
     *
     * @param string $encryptionType
     * @return string
     */
    protected function mapEncryptionForAndroid(string $encryptionType): string
    {
        return match ($encryptionType) {
            self::ENCRYPTION_NONE => 'nopass',
            self::ENCRYPTION_WEP => 'WEP',
            self::ENCRYPTION_WPA, self::ENCRYPTION_WPA2, self::ENCRYPTION_WPA3 => 'WPA',
            default => 'WPA',
        };
    }

    /**
     * 获取加密类型文本
     *
     * @param string $encryptionType
     * @return string
     */
    protected function getEncryptionText(string $encryptionType): string
    {
        return match ($encryptionType) {
            self::ENCRYPTION_NONE => '开放网络',
            self::ENCRYPTION_WEP => 'WEP加密',
            self::ENCRYPTION_WPA => 'WPA加密',
            self::ENCRYPTION_WPA2 => 'WPA2加密',
            self::ENCRYPTION_WPA3 => 'WPA3加密',
            default => '加密网络',
        };
    }

    /**
     * 是否显示密码
     *
     * @return bool
     */
    protected function shouldShowPassword(): bool
    {
        // 可以根据配置或权限决定是否显示完整密码
        return config('wifi.show_password', false);
    }

    /**
     * 估算网络速度
     *
     * @return string
     */
    protected function estimateNetworkSpeed(): string
    {
        // 这里可以根据实际情况返回网络速度信息
        return '高速';
    }

    /**
     * 验证WiFi配置有效性
     *
     * @param string $ssid
     * @param string|null $password
     * @param string|null $encryptionType
     * @return array
     */
    public function validateWifiConfig(string $ssid, ?string $password = null, ?string $encryptionType = null): array
    {
        $errors = [];
        $warnings = [];

        try {
            // 验证SSID
            $this->validateSSID($ssid);
        } catch (ValidateException $e) {
            $errors[] = $e->getMessage();
        }

        // 确定加密类型
        if (!$encryptionType) {
            $encryptionType = $this->determineEncryptionType($password);
        }

        // 验证密码
        if ($password) {
            try {
                $this->validatePassword($password, $encryptionType);
            } catch (ValidateException $e) {
                $errors[] = $e->getMessage();
            }

            // 检查密码强度
            if (strlen($password) < 12) {
                $warnings[] = '建议使用12位及以上的密码以提高安全性';
            }
        } else {
            if ($encryptionType !== self::ENCRYPTION_NONE) {
                $errors[] = '加密网络必须设置密码';
            } else {
                $warnings[] = '开放网络存在安全风险，建议使用加密';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'ssid' => $ssid,
            'encryption' => $encryptionType,
            'encryption_text' => $this->getEncryptionText($encryptionType)
        ];
    }

    /**
     * 清除WiFi配置缓存
     *
     * @param int $deviceId
     * @return bool
     */
    public function clearWifiConfigCache(int $deviceId): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'config_' . $deviceId;
        return Cache::delete($cacheKey);
    }

    /**
     * 获取WiFi服务统计信息
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getWifiServiceStats(int $merchantId, string $startDate = '', string $endDate = ''): array
    {
        if (empty($startDate)) {
            $startDate = date('Y-m-d', strtotime('-7 days'));
        }
        if (empty($endDate)) {
            $endDate = date('Y-m-d');
        }

        // 这里可以从数据库或缓存中统计数据
        // 暂时返回模拟结构
        return [
            'merchant_id' => $merchantId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_requests' => 0,
            'total_success' => 0,
            'total_failure' => 0,
            'success_rate' => 0.0,
            'daily_stats' => [],
            'platform_distribution' => [
                'ios' => 0,
                'android' => 0,
                'wechat' => 0
            ],
            'peak_hours' => []
        ];
    }
}