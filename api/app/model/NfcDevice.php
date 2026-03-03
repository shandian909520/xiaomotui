<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * NFC设备模型
 * @property int $id 设备ID
 * @property int $merchant_id 所属商家ID
 * @property string $device_code 设备编码
 * @property string $device_name 设备名称
 * @property string $location 设备位置
 * @property string $type 设备类型 TABLE/WALL/COUNTER/ENTRANCE
 * @property string $trigger_mode 触发模式 VIDEO/COUPON/WIFI/CONTACT/MENU
 * @property int $template_id 内容模板ID
 * @property string $redirect_url 跳转链接
 * @property string $wifi_ssid WiFi名称
 * @property string $wifi_password WiFi密码
 * @property int $status 状态 0离线 1在线 2维护
 * @property int $battery_level 电池电量
 * @property string $last_heartbeat 最后心跳时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class NfcDevice extends Model
{
    protected $table = 'xmt_nfc_devices';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'merchant_id'     => 'int',
        'device_code'     => 'string',
        'device_name'     => 'string',
        'location'        => 'string',
        'type'            => 'string',
        'trigger_mode'    => 'string',
        'template_id'     => 'int',
        'redirect_url'    => 'string',
        'group_buy_config' => 'json',
        'wifi_ssid'       => 'string',
        'wifi_password'   => 'string',
        'promo_video_id'  => 'int',
        'promo_copywriting' => 'string',
        'promo_tags'      => 'json',
        'promo_reward_coupon_id' => 'int',
        'status'          => 'int',
        'battery_level'   => 'int',
        'last_heartbeat'  => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';

    // 隐藏字段（WiFi密码永远不直接返回）
    protected $hidden = ['wifi_password'];

    // 字段类型转换
    protected $type = [
        'id'               => 'integer',
        'merchant_id'      => 'integer',
        'template_id'      => 'integer',
        'status'           => 'integer',
        'battery_level'    => 'integer',
        'group_buy_config' => 'json',
        'promo_video_id'   => 'integer',
        'promo_tags'       => 'json',
        'promo_reward_coupon_id' => 'integer',
        'last_heartbeat'   => 'datetime',
        'create_time'      => 'datetime',
        'update_time'      => 'datetime',
    ];

    /**
     * WiFi密码加密存储 - 修改器
     * 自动加密保存到数据库
     */
    public function setWifiPasswordAttr($value)
    {
        if (empty($value)) {
            return '';
        }
        // 使用ThinkPHP内置加密方法
        return encrypt($value);
    }

    /**
     * WiFi密码解密 - 访问器
     * 从数据库读取时不再自动解密，返回密文
     * 安全性改进：移除自动解密，防止敏感信息泄露
     */
    public function getWifiPasswordAttr($value)
    {
        // 不再自动解密，返回空字符串
        // 如需解密，请使用 getDecryptedWifiPassword() 方法
        return '';
    }

    /**
     * 获取解密后的WiFi密码
     * 需要显式调用，防止意外泄露
     *
     * @return string 解密后的密码或空字符串
     */
    public function getDecryptedWifiPassword(): string
    {
        if (empty($this->wifi_password)) {
            return '';
        }
        try {
            return decrypt($this->wifi_password);
        } catch (\Exception $e) {
            \think\facade\Log::error('WiFi密码解密失败', [
                'device_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    // 只读字段
    protected $readonly = ['device_code'];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'device_code', 'device_name', 'location', 'type',
        'trigger_mode', 'template_id', 'redirect_url', 'group_buy_config',
        'wifi_ssid', 'wifi_password',
        'promo_video_id', 'promo_copywriting', 'promo_tags', 'promo_reward_coupon_id',
        'status', 'battery_level', 'last_heartbeat'
    ];

    /**
     * 设备状态常量
     */
    const STATUS_OFFLINE = 0;     // 离线
    const STATUS_ONLINE = 1;      // 在线
    const STATUS_MAINTENANCE = 2; // 维护

    /**
     * 设备类型常量
     */
    const TYPE_TABLE = 'TABLE';       // 桌贴
    const TYPE_WALL = 'WALL';         // 墙贴
    const TYPE_COUNTER = 'COUNTER';   // 台面
    const TYPE_ENTRANCE = 'ENTRANCE'; // 门口

    /**
     * 触发模式常量
     */
    const TRIGGER_VIDEO = 'VIDEO';       // 视频展示
    const TRIGGER_COUPON = 'COUPON';     // 优惠券
    const TRIGGER_WIFI = 'WIFI';         // WiFi连接
    const TRIGGER_CONTACT = 'CONTACT';   // 联系方式
    const TRIGGER_MENU = 'MENU';         // 菜单展示
    const TRIGGER_GROUP_BUY = 'GROUP_BUY'; // 团购跳转
    const TRIGGER_PROMO = 'PROMO';           // 消费者推广

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $status = [
            self::STATUS_OFFLINE => '离线',
            self::STATUS_ONLINE => '在线',
            self::STATUS_MAINTENANCE => '维护中'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 设备类型获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        $types = [
            self::TYPE_TABLE => '桌贴',
            self::TYPE_WALL => '墙贴',
            self::TYPE_COUNTER => '台面',
            self::TYPE_ENTRANCE => '门口'
        ];
        return $types[$data['type']] ?? '未知';
    }

    /**
     * 触发模式获取器
     */
    public function getTriggerModeTextAttr($value, $data): string
    {
        $modes = [
            self::TRIGGER_VIDEO => '视频展示',
            self::TRIGGER_COUPON => '优惠券',
            self::TRIGGER_WIFI => 'WiFi连接',
            self::TRIGGER_CONTACT => '联系方式',
            self::TRIGGER_MENU => '菜单展示',
            self::TRIGGER_GROUP_BUY => '团购跳转',
            self::TRIGGER_PROMO => '消费者推广'
        ];
        return $modes[$data['trigger_mode']] ?? '未知';
    }

    /**
     * 电池电量获取器 - 带电量状态
     */
    public function getBatteryStatusAttr($value, $data): string
    {
        $level = $data['battery_level'] ?? 0;

        if ($level > 80) {
            return '电量充足';
        } elseif ($level > 30) {
            return '电量正常';
        } elseif ($level > 10) {
            return '电量偏低';
        } else {
            return '电量不足';
        }
    }

    /**
     * 设备在线状态检查器
     */
    public function getIsOnlineAttr($value, $data): bool
    {
        // 如果状态不是在线，直接返回false
        if ($data['status'] != self::STATUS_ONLINE) {
            return false;
        }

        // 检查心跳时间，超过5分钟无心跳视为离线
        if (empty($data['last_heartbeat'])) {
            return false;
        }

        $lastHeartbeat = strtotime($data['last_heartbeat']);
        $currentTime = time();

        // 5分钟 = 300秒
        return ($currentTime - $lastHeartbeat) <= 300;
    }

    /**
     * 更新心跳时间
     */
    public function updateHeartbeat(): bool
    {
        $this->last_heartbeat = date('Y-m-d H:i:s');

        // 如果设备状态是离线，自动设为在线
        if ($this->status == self::STATUS_OFFLINE) {
            $this->status = self::STATUS_ONLINE;
        }

        return $this->save();
    }

    /**
     * 更新电池电量
     */
    public function updateBatteryLevel(int $level): bool
    {
        if ($level < 0 || $level > 100) {
            return false;
        }

        $this->battery_level = $level;
        return $this->save();
    }

    /**
     * 设置设备状态
     */
    public function setDeviceStatus(int $status): bool
    {
        if (!in_array($status, [self::STATUS_OFFLINE, self::STATUS_ONLINE, self::STATUS_MAINTENANCE])) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    /**
     * 检查设备是否在线
     */
    public function isOnline(): bool
    {
        return $this->getIsOnlineAttr(null, $this->getData());
    }

    /**
     * 检查设备是否离线
     */
    public function isOffline(): bool
    {
        return !$this->isOnline();
    }

    /**
     * 检查设备是否处于维护状态
     */
    public function isMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    /**
     * 检查电池电量是否过低
     */
    public function isLowBattery(): bool
    {
        return $this->battery_level !== null && $this->battery_level <= 20;
    }

    /**
     * 根据设备编码查找设备
     */
    public static function findByDeviceCode(string $deviceCode)
    {
        return static::where('device_code', $deviceCode)->find();
    }

    /**
     * 根据设备编码查找设备 (别名方法)
     * @param string $code 设备编码
     * @return NfcDevice|null
     */
    public static function findByCode(string $code)
    {
        return static::findByDeviceCode($code);
    }

    /**
     * 根据商家ID获取设备列表
     */
    public static function getByMerchantId(int $merchantId, array $where = [])
    {
        $query = static::where('merchant_id', $merchantId);

        if (!empty($where)) {
            $query = $query->where($where);
        }

        return $query->select();
    }

    /**
     * 获取在线设备列表
     */
    public static function getOnlineDevices(int $merchantId = null)
    {
        $query = static::where('status', self::STATUS_ONLINE);

        if ($merchantId !== null) {
            $query = $query->where('merchant_id', $merchantId);
        }

        // 只返回5分钟内有心跳的设备
        $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300);
        $query = $query->where('last_heartbeat', '>=', $fiveMinutesAgo);

        return $query->select();
    }

    /**
     * 获取离线设备列表
     */
    public static function getOfflineDevices(int $merchantId = null)
    {
        $query = static::where(function($query) {
            $query->where('status', self::STATUS_OFFLINE)
                  ->whereOr(function($query) {
                      $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300);
                      $query->where('status', self::STATUS_ONLINE)
                            ->where('last_heartbeat', '<', $fiveMinutesAgo);
                  });
        });

        if ($merchantId !== null) {
            $query = $query->where('merchant_id', $merchantId);
        }

        return $query->select();
    }

    /**
     * 所属商家关联
     */
    public function merchant()
    {
        return $this->belongsTo(\app\model\Merchant::class);
    }

    /**
     * 内容任务关联 - 一个设备可以有多个内容任务
     */
    public function contentTasks()
    {
        return $this->hasMany(\app\model\ContentTask::class, 'device_id');
    }

    /**
     * 内容模板关联
     */
    public function template()
    {
        return $this->belongsTo(\app\model\ContentTemplate::class, 'template_id');
    }

    /**
     * 推广视频模板关联
     */
    public function promoVideo()
    {
        return $this->belongsTo(\app\model\ContentTemplate::class, 'promo_video_id');
    }

    /**
     * 推广奖励优惠券关联
     */
    public function promoRewardCoupon()
    {
        return $this->belongsTo(\app\model\Coupon::class, 'promo_reward_coupon_id');
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'merchant_id' => 'require|integer|>:0',
            'device_code' => 'require|max:32|unique:nfc_devices',
            'device_name' => 'require|max:100',
            'location' => 'max:100',
            'type' => 'in:TABLE,WALL,COUNTER,ENTRANCE',
            'trigger_mode' => 'in:VIDEO,COUPON,WIFI,CONTACT,MENU,GROUP_BUY,PROMO',
            'template_id' => 'integer|>:0',
            'redirect_url' => 'url|max:255',
            'wifi_ssid' => 'max:50',
            'wifi_password' => 'max:50',
            'status' => 'in:0,1,2',
            'battery_level' => 'integer|between:0,100',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'merchant_id.require' => '商家ID不能为空',
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'device_code.require' => '设备编码不能为空',
            'device_code.max' => '设备编码长度不能超过32个字符',
            'device_code.unique' => '设备编码已存在',
            'device_name.require' => '设备名称不能为空',
            'device_name.max' => '设备名称长度不能超过100个字符',
            'location.max' => '设备位置长度不能超过100个字符',
            'type.in' => '设备类型值无效',
            'trigger_mode.in' => '触发模式值无效',
            'template_id.integer' => '模板ID必须是整数',
            'template_id.>' => '模板ID必须大于0',
            'redirect_url.url' => '跳转链接格式不正确',
            'redirect_url.max' => '跳转链接长度不能超过255个字符',
            'wifi_ssid.max' => 'WiFi名称长度不能超过50个字符',
            'wifi_password.max' => 'WiFi密码长度不能超过50个字符',
            'status.in' => '状态值无效',
            'battery_level.integer' => '电池电量必须是整数',
            'battery_level.between' => '电池电量必须在0-100之间',
        ];
    }
}