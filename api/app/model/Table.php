<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 桌台模型
 * @property int $id 桌台ID
 * @property int $merchant_id 所属商家ID
 * @property string $table_number 桌号
 * @property int $capacity 容纳人数
 * @property string $area 区域
 * @property string $qr_code 二维码
 * @property string $status 桌台状态
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class Table extends Model
{
    protected $name = 'tables';

    // 设置字段信息
    protected $schema = [
        'id'           => 'int',
        'merchant_id'  => 'int',
        'table_number' => 'string',
        'capacity'     => 'int',
        'area'         => 'string',
        'qr_code'      => 'string',
        'status'       => 'string',
        'create_time'  => 'datetime',
        'update_time'  => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id'          => 'integer',
        'merchant_id' => 'integer',
        'capacity'    => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'table_number', 'capacity', 'area', 'qr_code', 'status'
    ];

    /**
     * 桌台状态常量
     */
    const STATUS_AVAILABLE = 'AVAILABLE';  // 空闲
    const STATUS_OCCUPIED = 'OCCUPIED';    // 使用中
    const STATUS_CLEANING = 'CLEANING';    // 清理中

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $status = [
            self::STATUS_AVAILABLE => '空闲',
            self::STATUS_OCCUPIED => '使用中',
            self::STATUS_CLEANING => '清理中'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 检查是否可用
     */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * 检查是否被占用
     */
    public function isOccupied(): bool
    {
        return $this->status === self::STATUS_OCCUPIED;
    }

    /**
     * 检查是否清理中
     */
    public function isCleaning(): bool
    {
        return $this->status === self::STATUS_CLEANING;
    }

    /**
     * 设置为使用中
     */
    public function setOccupied(): bool
    {
        $this->status = self::STATUS_OCCUPIED;
        return $this->save();
    }

    /**
     * 设置为空闲
     */
    public function setAvailable(): bool
    {
        $this->status = self::STATUS_AVAILABLE;
        return $this->save();
    }

    /**
     * 设置为清理中
     */
    public function setCleaning(): bool
    {
        $this->status = self::STATUS_CLEANING;
        return $this->save();
    }

    /**
     * 根据桌号查找
     */
    public static function findByTableNumber(int $merchantId, string $tableNumber)
    {
        return static::where('merchant_id', $merchantId)
            ->where('table_number', $tableNumber)
            ->find();
    }

    /**
     * 获取商家的所有桌台
     */
    public static function getByMerchantId(int $merchantId, array $where = [])
    {
        $query = static::where('merchant_id', $merchantId);

        if (!empty($where)) {
            $query = $query->where($where);
        }

        return $query->order('table_number', 'asc')->select();
    }

    /**
     * 获取可用桌台
     */
    public static function getAvailableTables(int $merchantId, ?string $area = null)
    {
        $query = static::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_AVAILABLE);

        if ($area !== null) {
            $query = $query->where('area', $area);
        }

        return $query->order('table_number', 'asc')->select();
    }

    /**
     * 获取占用桌台
     */
    public static function getOccupiedTables(int $merchantId, ?string $area = null)
    {
        $query = static::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_OCCUPIED);

        if ($area !== null) {
            $query = $query->where('area', $area);
        }

        return $query->order('table_number', 'asc')->select();
    }

    /**
     * 所属商家关联
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * 用餐会话关联
     */
    public function diningSessions()
    {
        return $this->hasMany(DiningSession::class, 'table_id');
    }

    /**
     * 当前会话关联（正在进行的会话）
     */
    public function currentSession()
    {
        return $this->hasOne(DiningSession::class, 'table_id')
            ->where('status', DiningSession::STATUS_ACTIVE);
    }

    /**
     * 服务呼叫关联
     */
    public function serviceCalls()
    {
        return $this->hasMany(ServiceCall::class, 'table_id');
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'merchant_id' => 'require|integer|>:0',
            'table_number' => 'require|max:20',
            'capacity' => 'require|integer|between:1,20',
            'area' => 'max:50',
            'qr_code' => 'max:255',
            'status' => 'in:AVAILABLE,OCCUPIED,CLEANING',
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
            'table_number.require' => '桌号不能为空',
            'table_number.max' => '桌号长度不能超过20个字符',
            'capacity.require' => '容纳人数不能为空',
            'capacity.integer' => '容纳人数必须是整数',
            'capacity.between' => '容纳人数必须在1-20之间',
            'area.max' => '区域长度不能超过50个字符',
            'qr_code.max' => '二维码长度不能超过255个字符',
            'status.in' => '状态值无效',
        ];
    }
}