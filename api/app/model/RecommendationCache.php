<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 推荐结果缓存模型
 * @property int $id 缓存ID
 * @property string $cache_key 缓存键
 * @property int $merchant_id 商家ID
 * @property int $user_id 用户ID
 * @property array $context 推荐上下文
 * @property array $recommendations 推荐结果
 * @property string $algorithm 推荐算法
 * @property string $expire_time 过期时间
 * @property string $create_time 创建时间
 */
class RecommendationCache extends Model
{
    protected $name = 'recommendation_cache';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'cache_key'       => 'string',
        'merchant_id'     => 'int',
        'user_id'         => 'int',
        'context'         => 'json',
        'recommendations' => 'json',
        'algorithm'       => 'string',
        'expire_time'     => 'datetime',
        'create_time'     => 'datetime',
    ];

    // 自动时间戳（只有创建时间）
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    // 隐藏字段
    protected $hidden = [];

    // 字段类型转换
    protected $type = [
        'id'              => 'integer',
        'merchant_id'     => 'integer',
        'user_id'         => 'integer',
        'context'         => 'array',
        'recommendations' => 'array',
        'expire_time'     => 'timestamp',
        'create_time'     => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['context', 'recommendations'];

    // 只读字段
    protected $readonly = ['id', 'cache_key', 'create_time'];

    // 允许批量赋值的字段
    protected $field = [
        'cache_key', 'merchant_id', 'user_id', 'context',
        'recommendations', 'algorithm', 'expire_time'
    ];

    /**
     * 推荐算法常量
     */
    const ALGORITHM_COLLABORATIVE = 'collaborative';      // 协同过滤
    const ALGORITHM_CONTENT_BASED = 'content_based';      // 内容过滤
    const ALGORITHM_POPULARITY = 'popularity';            // 热度排序
    const ALGORITHM_PERSONALIZED = 'personalized';        // 个性化推荐
    const ALGORITHM_HYBRID = 'hybrid';                    // 混合推荐

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * 是否过期
     */
    public function isExpired(): bool
    {
        return strtotime($this->expire_time) < time();
    }

    /**
     * 生成缓存键
     *
     * @param array $params 参数
     * @return string
     */
    public static function generateCacheKey(array $params): string
    {
        ksort($params);
        $str = json_encode($params);
        return md5($str);
    }

    /**
     * 获取缓存
     *
     * @param string $cacheKey 缓存键
     * @return RecommendationCache|null
     */
    public static function getCache(string $cacheKey): ?RecommendationCache
    {
        $cache = static::where('cache_key', $cacheKey)
            ->where('expire_time', '>', date('Y-m-d H:i:s'))
            ->find();

        return $cache;
    }

    /**
     * 设置缓存
     *
     * @param array $data 缓存数据
     * @param int $ttl 过期时间（秒）
     * @return RecommendationCache|null
     */
    public static function setCache(array $data, int $ttl = 3600): ?RecommendationCache
    {
        $cacheKey = $data['cache_key'];
        $expireTime = date('Y-m-d H:i:s', time() + $ttl);

        // 删除旧缓存
        static::where('cache_key', $cacheKey)->delete();

        // 创建新缓存
        $cache = new static();
        $cache->cache_key = $cacheKey;
        $cache->merchant_id = $data['merchant_id'] ?? null;
        $cache->user_id = $data['user_id'] ?? null;
        $cache->context = $data['context'] ?? [];
        $cache->recommendations = $data['recommendations'];
        $cache->algorithm = $data['algorithm'] ?? self::ALGORITHM_HYBRID;
        $cache->expire_time = $expireTime;

        return $cache->save() ? $cache : null;
    }

    /**
     * 删除缓存
     *
     * @param string $cacheKey 缓存键
     * @return bool
     */
    public static function deleteCache(string $cacheKey): bool
    {
        return static::where('cache_key', $cacheKey)->delete() > 0;
    }

    /**
     * 清除过期缓存
     *
     * @return int 清除的数量
     */
    public static function clearExpiredCache(): int
    {
        return static::where('expire_time', '<', date('Y-m-d H:i:s'))->delete();
    }

    /**
     * 清除用户的所有缓存
     *
     * @param int $userId 用户ID
     * @return int
     */
    public static function clearUserCache(int $userId): int
    {
        return static::where('user_id', $userId)->delete();
    }

    /**
     * 清除商家的所有缓存
     *
     * @param int $merchantId 商家ID
     * @return int
     */
    public static function clearMerchantCache(int $merchantId): int
    {
        return static::where('merchant_id', $merchantId)->delete();
    }

    /**
     * 清除模板相关的缓存
     *
     * @param int $templateId 模板ID
     * @return int
     */
    public static function clearTemplateCache(int $templateId): int
    {
        // 查找包含该模板的缓存
        $caches = static::select();
        $count = 0;

        foreach ($caches as $cache) {
            $recommendations = $cache->recommendations;
            foreach ($recommendations as $rec) {
                if (isset($rec['template_id']) && $rec['template_id'] == $templateId) {
                    $cache->delete();
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * 获取缓存统计
     *
     * @return array
     */
    public static function getCacheStats(): array
    {
        $total = static::count();
        $expired = static::where('expire_time', '<', date('Y-m-d H:i:s'))->count();
        $valid = $total - $expired;

        // 按算法统计
        $algorithmStats = static::field('algorithm, count(*) as count')
            ->where('expire_time', '>', date('Y-m-d H:i:s'))
            ->group('algorithm')
            ->select()
            ->toArray();

        $algorithmCount = [];
        foreach ($algorithmStats as $stat) {
            $algorithmCount[$stat['algorithm']] = $stat['count'];
        }

        return [
            'total' => $total,
            'valid' => $valid,
            'expired' => $expired,
            'algorithm_count' => $algorithmCount,
        ];
    }

    /**
     * 获取算法选项
     */
    public static function getAlgorithmOptions(): array
    {
        return [
            self::ALGORITHM_COLLABORATIVE => '协同过滤',
            self::ALGORITHM_CONTENT_BASED => '内容过滤',
            self::ALGORITHM_POPULARITY => '热度排序',
            self::ALGORITHM_PERSONALIZED => '个性化推荐',
            self::ALGORITHM_HYBRID => '混合推荐',
        ];
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'cache_key' => 'require|max:64',
            'merchant_id' => 'integer|>:0',
            'user_id' => 'integer|>:0',
            'recommendations' => 'require',
            'algorithm' => 'in:collaborative,content_based,popularity,personalized,hybrid',
            'expire_time' => 'require|date',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'cache_key.require' => '缓存键不能为空',
            'cache_key.max' => '缓存键长度不能超过64个字符',
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.>' => '用户ID必须大于0',
            'recommendations.require' => '推荐结果不能为空',
            'algorithm.in' => '推荐算法值无效',
            'expire_time.require' => '过期时间不能为空',
            'expire_time.date' => '过期时间格式不正确',
        ];
    }
}