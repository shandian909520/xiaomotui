<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 用户模型
 * @property int $id 用户ID
 * @property string $openid 微信openid
 * @property string $unionid 微信unionid
 * @property string $phone 手机号
 * @property string $nickname 昵称
 * @property string $avatar 头像
 * @property int $gender 性别 0未知 1男 2女
 * @property string $member_level 会员等级 BASIC/VIP/PREMIUM
 * @property int $points 积分
 * @property int $status 状态 0禁用 1正常
 * @property string $last_login_time 最后登录时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class User extends Model
{
    protected $table = 'xmt_user';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'openid'          => 'string',
        'unionid'         => 'string',
        'phone'           => 'string',
        'nickname'        => 'string',
        'avatar'          => 'string',
        'gender'          => 'int',
        'member_level'    => 'string',
        'points'          => 'int',
        'status'          => 'int',
        'last_login_time' => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 隐藏字段
    protected $hidden = [];

    // 字段类型转换
    protected $type = [
        'id'              => 'integer',
        'gender'          => 'integer',
        'points'          => 'integer',
        'status'          => 'integer',
        'last_login_time' => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
    ];

    // 只读字段
    protected $readonly = ['openid'];

    // 允许批量赋值的字段
    protected $field = [
        'openid', 'unionid', 'phone', 'nickname', 'avatar',
        'gender', 'member_level', 'points', 'status', 'last_login_time'
    ];

    /**
     * 会员等级常量
     */
    const MEMBER_LEVEL_BASIC = 'BASIC';
    const MEMBER_LEVEL_VIP = 'VIP';
    const MEMBER_LEVEL_PREMIUM = 'PREMIUM';

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;
    const STATUS_NORMAL = 1;

    /**
     * 性别常量
     */
    const GENDER_UNKNOWN = 0;
    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $status = [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_NORMAL => '正常'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 性别获取器
     */
    public function getGenderTextAttr($value, $data): string
    {
        $gender = [
            self::GENDER_UNKNOWN => '未知',
            self::GENDER_MALE => '男',
            self::GENDER_FEMALE => '女'
        ];
        return $gender[$data['gender']] ?? '未知';
    }

    /**
     * 会员等级获取器
     */
    public function getMemberLevelTextAttr($value, $data): string
    {
        $levels = [
            self::MEMBER_LEVEL_BASIC => '基础会员',
            self::MEMBER_LEVEL_VIP => 'VIP会员',
            self::MEMBER_LEVEL_PREMIUM => '高级会员'
        ];
        return $levels[$data['member_level']] ?? '未知';
    }

    /**
     * 头像获取器 - 处理相对路径转换为完整URL
     */
    public function getAvatarAttr($value): string
    {
        if (empty($value)) {
            return '';
        }

        // 如果已经是完整URL，直接返回
        if (strpos($value, 'http') === 0) {
            return $value;
        }

        // 如果是相对路径，转换为完整URL
        return request()->domain() . $value;
    }

    /**
     * 更新最后登录时间
     */
    public function updateLastLoginTime(): bool
    {
        $this->last_login_time = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * 增加积分
     */
    public function addPoints(int $points, string $reason = ''): bool
    {
        if ($points <= 0) {
            return false;
        }

        $this->points = $this->points + $points;
        return $this->save();
    }

    /**
     * 扣减积分
     */
    public function deductPoints(int $points, string $reason = ''): bool
    {
        if ($points <= 0 || $this->points < $points) {
            return false;
        }

        $this->points = $this->points - $points;
        return $this->save();
    }

    /**
     * 检查是否为VIP会员
     */
    public function isVip(): bool
    {
        return in_array($this->member_level, [self::MEMBER_LEVEL_VIP, self::MEMBER_LEVEL_PREMIUM]);
    }

    /**
     * 检查是否为高级会员
     */
    public function isPremium(): bool
    {
        return $this->member_level === self::MEMBER_LEVEL_PREMIUM;
    }

    /**
     * 根据openid查找用户
     */
    public static function findByOpenid(string $openid)
    {
        return static::where('openid', $openid)->find();
    }

    /**
     * 根据unionid查找用户
     */
    public static function findByUnionid(string $unionid)
    {
        return static::where('unionid', $unionid)->find();
    }

    /**
     * 根据手机号查找用户
     */
    public static function findByPhone(string $phone)
    {
        return static::where('phone', $phone)->find();
    }

    /**
     * 用户商家关联 - 一个用户可以拥有多个商家
     */
    public function merchants()
    {
        return $this->hasMany(\app\model\Merchant::class);
    }

    /**
     * 用户内容任务关联
     */
    public function contentTasks()
    {
        return $this->hasMany(\app\model\ContentTask::class);
    }

    /**
     * 用户优惠券关联
     */
    public function userCoupons()
    {
        return $this->hasMany(\app\model\UserCoupon::class);
    }

    /**
     * 用户平台账号关联
     */
    public function platformAccounts()
    {
        return $this->hasMany(\app\model\PlatformAccount::class);
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'openid' => 'require|max:64',
            'unionid' => 'max:64',
            'phone' => 'mobile',
            'nickname' => 'max:50',
            'avatar' => 'max:255',
            'gender' => 'in:0,1,2',
            'member_level' => 'in:BASIC,VIP,PREMIUM',
            'points' => 'integer|>=:0',
            'status' => 'in:0,1',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'openid.require' => 'openid不能为空',
            'openid.max' => 'openid长度不能超过64个字符',
            'unionid.max' => 'unionid长度不能超过64个字符',
            'phone.mobile' => '手机号格式不正确',
            'nickname.max' => '昵称长度不能超过50个字符',
            'avatar.max' => '头像URL长度不能超过255个字符',
            'gender.in' => '性别值无效',
            'member_level.in' => '会员等级值无效',
            'points.integer' => '积分必须是整数',
            'points.>=' => '积分不能为负数',
            'status.in' => '状态值无效',
        ];
    }
}