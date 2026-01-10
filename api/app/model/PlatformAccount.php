<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 平台账号模型
 * @property int $id 账号ID
 * @property int $user_id 用户ID
 * @property string $platform 平台类型
 * @property string $platform_uid 平台用户ID
 * @property string $platform_name 平台昵称
 * @property string $access_token 访问令牌
 * @property string $refresh_token 刷新令牌
 * @property string $expires_time 令牌过期时间
 * @property string $avatar 头像
 * @property int $follower_count 粉丝数
 * @property int $status 状态 0失效 1正常
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class PlatformAccount extends Model
{
    protected $name = 'platform_accounts';

    // 设置字段信息
    protected $schema = [
        'id'             => 'int',
        'user_id'        => 'int',
        'platform'       => 'string',
        'platform_uid'   => 'string',
        'platform_name'  => 'string',
        'access_token'   => 'text',
        'refresh_token'  => 'text',
        'expires_time'   => 'datetime',
        'avatar'         => 'string',
        'follower_count' => 'int',
        'status'         => 'int',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 隐藏字段
    protected $hidden = ['access_token', 'refresh_token'];

    // 字段类型转换
    protected $type = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'follower_count' => 'integer',
        'status'         => 'integer',
        'expires_time'   => 'timestamp',
        'create_time'    => 'timestamp',
        'update_time'    => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'user_id', 'platform', 'platform_uid', 'platform_name',
        'access_token', 'refresh_token', 'expires_time', 'avatar',
        'follower_count', 'status'
    ];

    /**
     * 平台类型常量
     */
    const PLATFORM_DOUYIN = 'DOUYIN';           // 抖音
    const PLATFORM_XIAOHONGSHU = 'XIAOHONGSHU'; // 小红书
    const PLATFORM_WECHAT = 'WECHAT';           // 微信
    const PLATFORM_WEIBO = 'WEIBO';             // 微博

    /**
     * 状态常量
     */
    const STATUS_INVALID = 0;  // 失效
    const STATUS_VALID = 1;    // 正常

    /**
     * 平台类型获取器
     */
    public function getPlatformTextAttr($value, $data): string
    {
        $platforms = [
            self::PLATFORM_DOUYIN => '抖音',
            self::PLATFORM_XIAOHONGSHU => '小红书',
            self::PLATFORM_WECHAT => '微信',
            self::PLATFORM_WEIBO => '微博'
        ];
        return $platforms[$data['platform']] ?? '未知';
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statuses = [
            self::STATUS_INVALID => '失效',
            self::STATUS_VALID => '正常'
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    /**
     * 检查令牌是否过期
     *
     * @return bool
     */
    public function isTokenExpired(): bool
    {
        if (empty($this->expires_time)) {
            return true;
        }

        return strtotime($this->expires_time) <= time();
    }

    /**
     * 检查账号是否有效
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID && !$this->isTokenExpired();
    }

    /**
     * 更新访问令牌
     *
     * @param string $accessToken 访问令牌
     * @param string $refreshToken 刷新令牌
     * @param int $expiresIn 过期时间（秒）
     * @return bool
     */
    public function updateToken(string $accessToken, string $refreshToken = '', int $expiresIn = 7200): bool
    {
        $this->access_token = $accessToken;

        if ($refreshToken) {
            $this->refresh_token = $refreshToken;
        }

        $this->expires_time = date('Y-m-d H:i:s', time() + $expiresIn);
        $this->status = self::STATUS_VALID;

        return $this->save();
    }

    /**
     * 标记为失效
     *
     * @return bool
     */
    public function markAsInvalid(): bool
    {
        $this->status = self::STATUS_INVALID;
        return $this->save();
    }

    /**
     * 更新账号信息
     *
     * @param array $data 账号数据
     * @return bool
     */
    public function updateAccountInfo(array $data): bool
    {
        if (isset($data['platform_name'])) {
            $this->platform_name = $data['platform_name'];
        }

        if (isset($data['avatar'])) {
            $this->avatar = $data['avatar'];
        }

        if (isset($data['follower_count'])) {
            $this->follower_count = $data['follower_count'];
        }

        return $this->save();
    }

    /**
     * 根据用户和平台查找账号
     *
     * @param int $userId 用户ID
     * @param string $platform 平台类型
     * @return PlatformAccount|null
     */
    public static function findByUserAndPlatform(int $userId, string $platform)
    {
        return static::where('user_id', $userId)
            ->where('platform', $platform)
            ->find();
    }

    /**
     * 根据平台用户ID查找账号
     *
     * @param string $platform 平台类型
     * @param string $platformUid 平台用户ID
     * @return PlatformAccount|null
     */
    public static function findByPlatformUid(string $platform, string $platformUid)
    {
        return static::where('platform', $platform)
            ->where('platform_uid', $platformUid)
            ->find();
    }

    /**
     * 获取用户的所有平台账号
     *
     * @param int $userId 用户ID
     * @param bool $validOnly 是否只返回有效账号
     * @return array
     */
    public static function getUserAccounts(int $userId, bool $validOnly = false): array
    {
        $query = static::where('user_id', $userId);

        if ($validOnly) {
            $query->where('status', self::STATUS_VALID)
                  ->where('expires_time', '>', date('Y-m-d H:i:s'));
        }

        return $query->order('create_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取用户绑定的平台列表
     *
     * @param int $userId 用户ID
     * @return array
     */
    public static function getUserPlatforms(int $userId): array
    {
        $accounts = static::where('user_id', $userId)
            ->where('status', self::STATUS_VALID)
            ->field('platform')
            ->select()
            ->toArray();

        return array_column($accounts, 'platform');
    }

    /**
     * 创建或更新平台账号
     *
     * @param int $userId 用户ID
     * @param string $platform 平台类型
     * @param array $data 账号数据
     * @return PlatformAccount
     */
    public static function createOrUpdate(int $userId, string $platform, array $data): PlatformAccount
    {
        $account = static::findByUserAndPlatform($userId, $platform);

        if (!$account) {
            $account = new static();
            $account->user_id = $userId;
            $account->platform = $platform;
        }

        $account->platform_uid = $data['platform_uid'] ?? '';
        $account->platform_name = $data['platform_name'] ?? '';
        $account->access_token = $data['access_token'] ?? '';
        $account->refresh_token = $data['refresh_token'] ?? '';
        $account->avatar = $data['avatar'] ?? '';
        $account->follower_count = $data['follower_count'] ?? 0;
        $account->status = self::STATUS_VALID;

        // 设置过期时间
        if (isset($data['expires_in'])) {
            $account->expires_time = date('Y-m-d H:i:s', time() + $data['expires_in']);
        } elseif (isset($data['expires_time'])) {
            $account->expires_time = $data['expires_time'];
        } else {
            $account->expires_time = date('Y-m-d H:i:s', time() + 7200); // 默认2小时
        }

        $account->save();

        return $account;
    }

    /**
     * 解绑平台账号
     *
     * @param int $userId 用户ID
     * @param string $platform 平台类型
     * @return bool
     */
    public static function unbind(int $userId, string $platform): bool
    {
        $account = static::findByUserAndPlatform($userId, $platform);

        if ($account) {
            return $account->delete();
        }

        return false;
    }

    /**
     * 批量检查并更新过期账号
     *
     * @return int 更新的账号数量
     */
    public static function checkAndUpdateExpired(): int
    {
        $expiredAccounts = static::where('status', self::STATUS_VALID)
            ->where('expires_time', '<=', date('Y-m-d H:i:s'))
            ->select();

        $count = 0;

        foreach ($expiredAccounts as $account) {
            $account->status = self::STATUS_INVALID;
            if ($account->save()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 获取平台账号统计
     *
     * @param int $userId 可选，指定用户
     * @return array
     */
    public static function getAccountStats(int $userId = null): array
    {
        $query = static::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $total = $query->count();
        $valid = $query->where('status', self::STATUS_VALID)->count();
        $invalid = $query->where('status', self::STATUS_INVALID)->count();

        // 按平台统计
        $platformStats = static::field('platform, count(*) as count')
            ->when($userId, function($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->group('platform')
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'valid' => $valid,
            'invalid' => $invalid,
            'by_platform' => $platformStats
        ];
    }

    /**
     * 获取即将过期的账号
     *
     * @param int $hours 小时数，默认24小时
     * @return array
     */
    public static function getExpiringSoon(int $hours = 24): array
    {
        $expiryTime = date('Y-m-d H:i:s', time() + $hours * 3600);

        return static::where('status', self::STATUS_VALID)
            ->where('expires_time', '>', date('Y-m-d H:i:s'))
            ->where('expires_time', '<=', $expiryTime)
            ->select()
            ->toArray();
    }

    /**
     * 获取支持的平台列表
     *
     * @return array
     */
    public static function getSupportedPlatforms(): array
    {
        return [
            self::PLATFORM_DOUYIN => [
                'name' => '抖音',
                'icon' => 'douyin.png',
                'description' => '短视频分享平台',
                'features' => ['视频发布', '直播', '商品橱窗']
            ],
            self::PLATFORM_XIAOHONGSHU => [
                'name' => '小红书',
                'icon' => 'xiaohongshu.png',
                'description' => '生活方式分享社区',
                'features' => ['图文发布', '视频发布', '笔记']
            ],
            self::PLATFORM_WECHAT => [
                'name' => '微信',
                'icon' => 'wechat.png',
                'description' => '社交通讯平台',
                'features' => ['朋友圈', '公众号', '小程序']
            ],
            self::PLATFORM_WEIBO => [
                'name' => '微博',
                'icon' => 'weibo.png',
                'description' => '社交媒体平台',
                'features' => ['微博发布', '长图文', '视频']
            ]
        ];
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'user_id' => 'require|integer|>:0',
            'platform' => 'require|in:DOUYIN,XIAOHONGSHU,WECHAT,WEIBO',
            'platform_uid' => 'require|max:100',
            'platform_name' => 'max:100',
            'access_token' => 'require',
            'avatar' => 'url|max:255',
            'follower_count' => 'integer|>=:0',
            'status' => 'in:0,1'
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'user_id.require' => '用户ID不能为空',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.>' => '用户ID必须大于0',
            'platform.require' => '平台类型不能为空',
            'platform.in' => '平台类型值无效',
            'platform_uid.require' => '平台用户ID不能为空',
            'platform_uid.max' => '平台用户ID长度不能超过100个字符',
            'platform_name.max' => '平台昵称长度不能超过100个字符',
            'access_token.require' => '访问令牌不能为空',
            'avatar.url' => '头像URL格式不正确',
            'avatar.max' => '头像URL长度不能超过255个字符',
            'follower_count.integer' => '粉丝数必须是整数',
            'follower_count.>=' => '粉丝数不能为负数',
            'status.in' => '状态值无效'
        ];
    }
}