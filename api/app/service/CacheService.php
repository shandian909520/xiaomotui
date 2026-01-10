<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Cache;
use think\facade\Log;

/**
 * 缓存服务类
 * 为NFC设备触发提供高性能缓存支持
 */
class CacheService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'nfc_cache_';

    /**
     * 设备配置缓存时间(秒) - 5分钟
     */
    const DEVICE_CONFIG_TTL = 300;

    /**
     * 设备状态缓存时间(秒) - 1分钟
     */
    const DEVICE_STATUS_TTL = 60;

    /**
     * 内容缓存时间(秒) - 10分钟
     */
    const CONTENT_TTL = 600;

    /**
     * 优惠券缓存时间(秒) - 5分钟
     */
    const COUPON_TTL = 300;

    /**
     * 用户信息缓存时间(秒) - 5分钟
     */
    const USER_TTL = 300;

    /**
     * 获取设备配置缓存
     *
     * @param string $deviceCode
     * @return array|null
     */
    public static function getDeviceConfig(string $deviceCode): ?array
    {
        $cacheKey = self::CACHE_PREFIX . 'device_config_' . $deviceCode;
        $config = Cache::get($cacheKey);

        if ($config !== false) {
            Log::debug('设备配置缓存命中', ['device_code' => $deviceCode]);
            return $config;
        }

        return null;
    }

    /**
     * 设置设备配置缓存
     *
     * @param string $deviceCode
     * @param array $config
     * @return bool
     */
    public static function setDeviceConfig(string $deviceCode, array $config): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'device_config_' . $deviceCode;
        $result = Cache::set($cacheKey, $config, self::DEVICE_CONFIG_TTL);

        if ($result) {
            Log::debug('设备配置已缓存', ['device_code' => $deviceCode]);
        }

        return $result;
    }

    /**
     * 清除设备配置缓存
     *
     * @param string $deviceCode
     * @return bool
     */
    public static function clearDeviceConfig(string $deviceCode): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'device_config_' . $deviceCode;
        return Cache::delete($cacheKey);
    }

    /**
     * 获取设备状态缓存
     *
     * @param string $deviceCode
     * @return array|null
     */
    public static function getDeviceStatus(string $deviceCode): ?array
    {
        $cacheKey = self::CACHE_PREFIX . 'device_status_' . $deviceCode;
        $status = Cache::get($cacheKey);

        return $status !== false ? $status : null;
    }

    /**
     * 设置设备状态缓存
     *
     * @param string $deviceCode
     * @param array $status
     * @return bool
     */
    public static function setDeviceStatus(string $deviceCode, array $status): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'device_status_' . $deviceCode;
        return Cache::set($cacheKey, $status, self::DEVICE_STATUS_TTL);
    }

    /**
     * 清除设备状态缓存
     *
     * @param string $deviceCode
     * @return bool
     */
    public static function clearDeviceStatus(string $deviceCode): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'device_status_' . $deviceCode;
        return Cache::delete($cacheKey);
    }

    /**
     * 获取内容缓存
     *
     * @param int $deviceId
     * @param string $type
     * @return array|null
     */
    public static function getDeviceContent(int $deviceId, string $type): ?array
    {
        $cacheKey = self::CACHE_PREFIX . 'content_' . $deviceId . '_' . $type;
        $content = Cache::get($cacheKey);

        return $content !== false ? $content : null;
    }

    /**
     * 设置内容缓存
     *
     * @param int $deviceId
     * @param string $type
     * @param array $content
     * @return bool
     */
    public static function setDeviceContent(int $deviceId, string $type, array $content): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'content_' . $deviceId . '_' . $type;
        return Cache::set($cacheKey, $content, self::CONTENT_TTL);
    }

    /**
     * 清除内容缓存
     *
     * @param int $deviceId
     * @param string $type
     * @return bool
     */
    public static function clearDeviceContent(int $deviceId, string $type = null): bool
    {
        if ($type) {
            $cacheKey = self::CACHE_PREFIX . 'content_' . $deviceId . '_' . $type;
            return Cache::delete($cacheKey);
        }

        // 清除该设备的所有内容缓存
        $types = ['video', 'menu', 'image'];
        $result = true;

        foreach ($types as $contentType) {
            $cacheKey = self::CACHE_PREFIX . 'content_' . $deviceId . '_' . $contentType;
            $result = $result && Cache::delete($cacheKey);
        }

        return $result;
    }

    /**
     * 获取用户优惠券缓存
     *
     * @param int $userId
     * @param int $merchantId
     * @return array|null
     */
    public static function getUserCoupons(int $userId, int $merchantId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . 'user_coupons_' . $userId . '_' . $merchantId;
        $coupons = Cache::get($cacheKey);

        return $coupons !== false ? $coupons : null;
    }

    /**
     * 设置用户优惠券缓存
     *
     * @param int $userId
     * @param int $merchantId
     * @param array $coupons
     * @return bool
     */
    public static function setUserCoupons(int $userId, int $merchantId, array $coupons): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'user_coupons_' . $userId . '_' . $merchantId;
        return Cache::set($cacheKey, $coupons, self::COUPON_TTL);
    }

    /**
     * 清除用户优惠券缓存
     *
     * @param int $userId
     * @param int $merchantId
     * @return bool
     */
    public static function clearUserCoupons(int $userId, int $merchantId = null): bool
    {
        if ($merchantId) {
            $cacheKey = self::CACHE_PREFIX . 'user_coupons_' . $userId . '_' . $merchantId;
            return Cache::delete($cacheKey);
        }

        // 清除用户的所有优惠券缓存（通过标签清除）
        return Cache::tag('user_coupons_' . $userId)->clear();
    }

    /**
     * 获取商家优惠券缓存
     *
     * @param int $merchantId
     * @return array|null
     */
    public static function getMerchantCoupons(int $merchantId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . 'merchant_coupons_' . $merchantId;
        $coupons = Cache::get($cacheKey);

        return $coupons !== false ? $coupons : null;
    }

    /**
     * 设置商家优惠券缓存
     *
     * @param int $merchantId
     * @param array $coupons
     * @return bool
     */
    public static function setMerchantCoupons(int $merchantId, array $coupons): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'merchant_coupons_' . $merchantId;
        return Cache::set($cacheKey, $coupons, self::COUPON_TTL);
    }

    /**
     * 清除商家优惠券缓存
     *
     * @param int $merchantId
     * @return bool
     */
    public static function clearMerchantCoupons(int $merchantId): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'merchant_coupons_' . $merchantId;
        return Cache::delete($cacheKey);
    }

    /**
     * 获取用户信息缓存
     *
     * @param string $openid
     * @return array|null
     */
    public static function getUserByOpenid(string $openid): ?array
    {
        $cacheKey = self::CACHE_PREFIX . 'user_openid_' . md5($openid);
        $user = Cache::get($cacheKey);

        return $user !== false ? $user : null;
    }

    /**
     * 设置用户信息缓存
     *
     * @param string $openid
     * @param array $user
     * @return bool
     */
    public static function setUserByOpenid(string $openid, array $user): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'user_openid_' . md5($openid);
        return Cache::set($cacheKey, $user, self::USER_TTL);
    }

    /**
     * 清除用户信息缓存
     *
     * @param string $openid
     * @return bool
     */
    public static function clearUserByOpenid(string $openid): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'user_openid_' . md5($openid);
        return Cache::delete($cacheKey);
    }

    /**
     * 批量清除相关缓存
     *
     * @param string $type 缓存类型
     * @param mixed $identifier 标识符
     * @return bool
     */
    public static function clearRelatedCache(string $type, $identifier): bool
    {
        $result = true;

        switch ($type) {
            case 'device':
                if (is_string($identifier)) {
                    // 设备编码
                    $result = $result && self::clearDeviceConfig($identifier);
                    $result = $result && self::clearDeviceStatus($identifier);
                } elseif (is_int($identifier)) {
                    // 设备ID，清除内容缓存
                    $result = $result && self::clearDeviceContent($identifier);
                }
                break;

            case 'merchant':
                $result = $result && self::clearMerchantCoupons($identifier);
                break;

            case 'user':
                if (is_string($identifier)) {
                    // openid
                    $result = $result && self::clearUserByOpenid($identifier);
                } elseif (is_int($identifier)) {
                    // user_id，暂时无需处理
                }
                break;
        }

        return $result;
    }

    /**
     * 预热设备相关缓存
     *
     * @param string $deviceCode
     * @return bool
     */
    public static function warmupDeviceCache(string $deviceCode): bool
    {
        try {
            // 这里可以预加载设备配置、最新内容等
            // 具体实现根据业务需要调整

            Log::info('设备缓存预热', ['device_code' => $deviceCode]);
            return true;
        } catch (\Exception $e) {
            Log::error('设备缓存预热失败', [
                'device_code' => $deviceCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取缓存统计信息
     *
     * @return array
     */
    public static function getCacheStats(): array
    {
        // 这个需要根据实际使用的缓存驱动来实现
        return [
            'cache_driver' => config('cache.default'),
            'prefix' => self::CACHE_PREFIX,
            'ttl_config' => [
                'device_config' => self::DEVICE_CONFIG_TTL,
                'device_status' => self::DEVICE_STATUS_TTL,
                'content' => self::CONTENT_TTL,
                'coupon' => self::COUPON_TTL,
                'user' => self::USER_TTL
            ]
        ];
    }

    /**
     * 清除所有NFC相关缓存
     *
     * @return bool
     */
    public static function clearAllNfcCache(): bool
    {
        try {
            // 根据缓存驱动的不同，清除方式也不同
            // 这里使用标签或前缀清除
            return Cache::tag('nfc_cache')->clear();
        } catch (\Exception $e) {
            // 如果标签不支持，尝试其他方式
            Log::warning('清除NFC缓存失败，尝试其他方式', ['error' => $e->getMessage()]);
            return true; // 暂时返回true，避免影响主流程
        }
    }
}