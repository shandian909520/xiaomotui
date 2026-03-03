<?php
declare(strict_types=1);

namespace app\service\content_moderation;

use think\facade\Config;
use think\facade\Log;
use think\facade\Cache;

/**
 * 内容审核服务商工厂
 * 负责创建和管理审核服务商实例
 */
class ModerationProviderFactory
{
    /**
     * @var array 服务商实例缓存
     */
    private static array $instances = [];

    /**
     * @var array 服务商黑名单(失败的会暂时加入黑名单)
     */
    private static array $blacklist = [];

    /**
     * @var int 黑名单默认TTL(秒)
     */
    private const BLACKLIST_TTL = 3600;

    /**
     * 创建服务商实例
     *
     * @param string $providerName 服务商名称: baidu|aliyun|tencent
     * @return ModerationProviderInterface|null
     */
    public static function create(string $providerName): ?ModerationProviderInterface
    {
        // 检查黑名单
        if (self::isBlacklisted($providerName)) {
            Log::info('服务商在黑名单中,跳过使用', ['provider' => $providerName]);
            return null;
        }

        // 从缓存获取
        if (isset(self::$instances[$providerName])) {
            return self::$instances[$providerName];
        }

        try {
            $instance = null;

            switch ($providerName) {
                case 'baidu':
                    $instance = new BaiduModerationProvider();
                    break;
                case 'aliyun':
                    $instance = new AliyunModerationProvider();
                    break;
                case 'tencent':
                    $instance = new TencentModerationProvider();
                    break;
                default:
                    Log::warning('未知的内容审核服务商', ['provider' => $providerName]);
                    return null;
            }

            // 检查服务商是否可用
            if ($instance && !$instance->isAvailable()) {
                Log::warning('内容审核服务商配置不完整', ['provider' => $providerName]);
                return null;
            }

            // 缓存实例
            if ($instance) {
                self::$instances[$providerName] = $instance;
            }

            return $instance;

        } catch (\Exception $e) {
            Log::error('创建内容审核服务商失败', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * 获取可用的服务商列表(按优先级排序)
     *
     * @param string $contentType 内容类型: text|image|video|audio
     * @return ModerationProviderInterface[]
     */
    public static function getAvailableProviders(string $contentType = 'text'): array
    {
        $config = Config::get('content_moderation.' . $contentType, []);
        $providersConfig = $config['providers'] ?? [];

        if (empty($providersConfig)) {
            Log::warning('未找到内容类型的服务商配置', ['type' => $contentType]);
            return [];
        }

        $providers = [];

        foreach ($providersConfig as $providerName => $providerConfig) {
            // 检查是否启用
            if (!($providerConfig['enabled'] ?? false)) {
                continue;
            }

            $instance = self::create($providerName);
            if ($instance) {
                $priority = $providerConfig['priority'] ?? 999;
                $providers[$priority] = $instance;
            }
        }

        // 按优先级排序
        ksort($providers);

        return array_values($providers);
    }

    /**
     * 获取默认服务商
     *
     * @param string $contentType 内容类型
     * @return ModerationProviderInterface|null
     */
    public static function getDefaultProvider(string $contentType = 'text'): ?ModerationProviderInterface
    {
        $providers = self::getAvailableProviders($contentType);
        return $providers[0] ?? null;
    }

    /**
     * 将服务商加入黑名单
     *
     * @param string $providerName 服务商名称
     * @param int|null $ttl 黑名单时间(秒),null表示使用默认值
     * @return void
     */
    public static function addToBlacklist(string $providerName, ?int $ttl = null): void
    {
        $ttl = $ttl ?? self::BLACKLIST_TTL;
        self::$blacklist[$providerName] = time() + $ttl;

        // 同时缓存到Redis,用于多实例共享
        $cacheKey = 'moderation_provider_blacklist:' . $providerName;
        Cache::set($cacheKey, true, $ttl);

        Log::warning('服务商已加入黑名单', [
            'provider' => $providerName,
            'ttl' => $ttl,
        ]);
    }

    /**
     * 检查服务商是否在黑名单中
     *
     * @param string $providerName 服务商名称
     * @return bool
     */
    public static function isBlacklisted(string $providerName): bool
    {
        // 检查内存黑名单
        if (isset(self::$blacklist[$providerName])) {
            if (time() < self::$blacklist[$providerName]) {
                return true;
            }
            // 过期移除
            unset(self::$blacklist[$providerName]);
        }

        // 检查缓存黑名单
        $cacheKey = 'moderation_provider_blacklist:' . $providerName;
        if (Cache::get($cacheKey)) {
            return true;
        }

        return false;
    }

    /**
     * 从黑名单移除服务商
     *
     * @param string $providerName 服务商名称
     * @return void
     */
    public static function removeFromBlacklist(string $providerName): void
    {
        unset(self::$blacklist[$providerName]);

        $cacheKey = 'moderation_provider_blacklist:' . $providerName;
        Cache::delete($cacheKey);

        Log::info('服务商已从黑名单移除', ['provider' => $providerName]);
    }

    /**
     * 清空所有黑名单
     *
     * @return void
     */
    public static function clearBlacklist(): void
    {
        self::$blacklist = [];

        // 清除所有相关缓存
        $cacheKeys = Cache::get('moderation_provider_blacklist:*');
        foreach ($cacheKeys as $key) {
            Cache::delete($key);
        }

        Log::info('已清空所有服务商黑名单');
    }

    /**
     * 重置服务商实例缓存
     *
     * @param string|null $providerName 服务商名称,null表示重置所有
     * @return void
     */
    public static function reset(?string $providerName = null): void
    {
        if ($providerName === null) {
            self::$instances = [];
            Log::info('已重置所有服务商实例');
        } elseif (isset(self::$instances[$providerName])) {
            unset(self::$instances[$providerName]);
            Log::info('已重置服务商实例', ['provider' => $providerName]);
        }
    }

    /**
     * 获取所有服务商状态
     *
     * @return array
     */
    public static function getProvidersStatus(): array
    {
        $status = [];
        $providers = ['baidu', 'aliyun', 'tencent'];

        foreach ($providers as $providerName) {
            $instance = self::create($providerName);
            $status[$providerName] = [
                'available' => $instance !== null && $instance->isAvailable(),
                'blacklisted' => self::isBlacklisted($providerName),
                'priority' => $instance ? $instance->getPriority() : null,
            ];
        }

        return $status;
    }
}
