<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 商家模型
 * @property int $id 商家ID
 * @property int $user_id 关联用户ID
 * @property string $name 商家名称
 * @property string $category 商家类别
 * @property string $address 地址
 * @property float $longitude 经度
 * @property float $latitude 纬度
 * @property string $phone 联系电话
 * @property string $description 商家描述
 * @property string $logo 商家logo
 * @property array $business_hours 营业时间
 * @property int $status 状态 0禁用 1正常 2审核中
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class Merchant extends Model
{
    protected $table = 'xmt_merchants';

    // 主键
    protected $pk = 'id';

    // 设置字段信息
    protected $schema = [
        'id'             => 'int',
        'user_id'        => 'int',
        'name'           => 'string',
        'category'       => 'string',
        'address'        => 'string',
        'longitude'      => 'float',
        'latitude'       => 'float',
        'phone'          => 'string',
        'description'    => 'string',
        'logo'           => 'string',
        'business_hours' => 'json',
        'status'         => 'int',
        'reject_reason'  => 'string',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 字段类型转换
    protected $type = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'longitude'      => 'float',
        'latitude'       => 'float',
        'business_hours' => 'json',
        'status'         => 'integer',
        'create_time'    => 'timestamp',
        'update_time'    => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'user_id', 'name', 'category', 'address',
        'longitude', 'latitude', 'phone', 'description',
        'logo', 'business_hours', 'status', 'reject_reason'
    ];

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;      // 禁用
    const STATUS_ACTIVE = 1;        // 正常
    const STATUS_UNDER_REVIEW = 2;  // 审核中

    /**
     * 状态文本映射
     */
    protected static $statusText = [
        self::STATUS_DISABLED => '已禁用',
        self::STATUS_ACTIVE => '正常',
        self::STATUS_UNDER_REVIEW => '审核中',
    ];

    /**
     * 常见商家类别
     */
    const CATEGORY_RESTAURANT = '餐饮';
    const CATEGORY_RETAIL = '零售';
    const CATEGORY_SERVICE = '服务';
    const CATEGORY_ENTERTAINMENT = '娱乐';
    const CATEGORY_EDUCATION = '教育';
    const CATEGORY_HEALTHCARE = '医疗';
    const CATEGORY_HOTEL = '酒店';
    const CATEGORY_OTHER = '其他';

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusText[$data['status']] ?? '未知';
    }

    /**
     * 完整地址获取器
     */
    public function getFullAddressAttr($value, $data): string
    {
        return $data['address'] ?? '';
    }

    /**
     * Logo URL获取器 - 处理相对路径转换为完整URL
     */
    public function getLogoUrlAttr($value, $data): string
    {
        if (empty($data['logo'])) {
            return '';
        }

        // 如果已经是完整URL，直接返回
        if (strpos($data['logo'], 'http') === 0) {
            return $data['logo'];
        }

        // 如果是相对路径，转换为完整URL
        return request()->domain() . $data['logo'];
    }

    /**
     * 坐标获取器 - 返回经纬度数组
     */
    public function getCoordinatesAttr($value, $data): array
    {
        return [
            'longitude' => $data['longitude'] ?? null,
            'latitude' => $data['latitude'] ?? null,
        ];
    }

    /**
     * 营业时间格式化获取器
     */
    public function getBusinessHoursTextAttr($value, $data): string
    {
        if (empty($data['business_hours'])) {
            return '未设置';
        }

        $hours = is_string($data['business_hours'])
            ? json_decode($data['business_hours'], true)
            : $data['business_hours'];

        if (empty($hours)) {
            return '未设置';
        }

        // 如果是简单的字符串格式
        if (isset($hours['open']) && isset($hours['close'])) {
            return $hours['open'] . ' - ' . $hours['close'];
        }

        // 如果是按星期设置的格式
        $result = [];
        foreach ($hours as $day => $time) {
            if (is_array($time) && isset($time['open']) && isset($time['close'])) {
                $result[] = $day . ': ' . $time['open'] . ' - ' . $time['close'];
            }
        }

        return !empty($result) ? implode(', ', $result) : '未设置';
    }

    /**
     * 检查商家是否正常营业
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 检查商家是否已禁用
     */
    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * 检查商家是否审核中
     */
    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    /**
     * 更新商家状态
     */
    public function updateStatus(int $status): bool
    {
        if (!in_array($status, [self::STATUS_DISABLED, self::STATUS_ACTIVE, self::STATUS_UNDER_REVIEW])) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    /**
     * 计算与给定坐标的距离（单位：公里）
     * 使用Haversine公式
     */
    public function getDistance(float $lat, float $lon): float
    {
        if (empty($this->latitude) || empty($this->longitude)) {
            return 0.0;
        }

        $earthRadius = 6371; // 地球半径（公里）

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lon);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * 查询作用域：正常营业的商家
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 查询作用域：按类别筛选
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * 查询作用域：按状态筛选
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 查询作用域：附近商家
     * @param mixed $query 查询对象
     * @param float $latitude 纬度
     * @param float $longitude 经度
     * @param float $radius 半径（公里），默认5公里
     */
    public function scopeNearby($query, float $latitude, float $longitude, float $radius = 5)
    {
        // 计算经纬度范围（粗略计算，用于初步筛选）
        // 1度纬度约等于111公里
        // 1度经度约等于111 * cos(纬度) 公里
        $latDelta = $radius / 111;
        $lonDelta = $radius / (111 * cos(deg2rad($latitude)));

        $minLat = $latitude - $latDelta;
        $maxLat = $latitude + $latDelta;
        $minLon = $longitude - $lonDelta;
        $maxLon = $longitude + $lonDelta;

        return $query->where('latitude', '>=', $minLat)
                     ->where('latitude', '<=', $maxLat)
                     ->where('longitude', '>=', $minLon)
                     ->where('longitude', '<=', $maxLon)
                     ->where('latitude', 'not null')
                     ->where('longitude', 'not null');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联NFC设备
     */
    public function nfcDevices()
    {
        return $this->hasMany(NfcDevice::class, 'merchant_id');
    }

    /**
     * 关联优惠券
     */
    public function coupons()
    {
        return $this->hasMany(Coupon::class, 'merchant_id');
    }

    /**
     * 关联内容模板
     */
    public function contentTemplates()
    {
        return $this->hasMany(ContentTemplate::class, 'merchant_id');
    }

    /**
     * 关联内容任务
     */
    public function contentTasks()
    {
        return $this->hasMany(ContentTask::class, 'merchant_id');
    }

    /**
     * 根据用户ID获取商家列表
     */
    public static function getByUserId(int $userId, array $where = [])
    {
        $query = static::where('user_id', $userId);

        if (!empty($where)) {
            $query = $query->where($where);
        }

        return $query->select();
    }

    /**
     * 根据类别获取商家列表
     */
    public static function getByCategory(string $category, array $where = [])
    {
        $query = static::where('category', $category);

        if (!empty($where)) {
            $query = $query->where($where);
        }

        return $query->select();
    }

    /**
     * 获取附近的商家
     * @param float $latitude 纬度
     * @param float $longitude 经度
     * @param float $radius 半径（公里）
     * @param int $limit 限制数量
     * @return array
     */
    public static function getNearbyMerchants(float $latitude, float $longitude, float $radius = 5, int $limit = 20): array
    {
        $merchants = static::nearby($latitude, $longitude, $radius)
                          ->active()
                          ->limit($limit)
                          ->select();

        $result = [];
        foreach ($merchants as $merchant) {
            $distance = $merchant->getDistance($latitude, $longitude);
            if ($distance <= $radius) {
                $merchantArray = $merchant->toArray();
                $merchantArray['distance'] = $distance;
                $result[] = $merchantArray;
            }
        }

        // 按距离排序
        usort($result, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $result;
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'user_id' => 'require|integer|>:0',
            'name' => 'require|max:100',
            'category' => 'require|max:50',
            'address' => 'require|max:255',
            'longitude' => 'float|between:-180,180',
            'latitude' => 'float|between:-90,90',
            'phone' => 'max:20',
            'description' => 'max:1000',
            'logo' => 'max:255',
            'status' => 'in:0,1,2'
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
            'name.require' => '商家名称不能为空',
            'name.max' => '商家名称长度不能超过100个字符',
            'category.require' => '商家类别不能为空',
            'category.max' => '商家类别长度不能超过50个字符',
            'address.require' => '地址不能为空',
            'address.max' => '地址长度不能超过255个字符',
            'longitude.float' => '经度必须是数字',
            'longitude.between' => '经度值必须在-180到180之间',
            'latitude.float' => '纬度必须是数字',
            'latitude.between' => '纬度值必须在-90到90之间',
            'phone.max' => '联系电话长度不能超过20个字符',
            'description.max' => '商家描述长度不能超过1000个字符',
            'logo.max' => 'Logo路径长度不能超过255个字符',
            'status.in' => '状态值无效'
        ];
    }
}
