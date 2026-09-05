<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 商家权益模型
 * @property int $id
 * @property int $merchant_id
 * @property string $version_type basic/standard/chain
 * @property int $store_quota 门店额度
 * @property int $store_used 已使用门店数
 * @property int $clip_power 剪辑算力
 * @property int $storage 存储空间(字节)
 * @property float $redpacket_balance 红包余额
 * @property string $expire_time 到期时间
 * @property string $create_time
 * @property string $update_time
 */
class MerchantBenefit extends Model
{
    protected $table = 'xmt_merchant_benefits';

    protected $pk = 'id';

    protected $schema = [
        'id'                => 'int',
        'merchant_id'       => 'int',
        'version_type'      => 'string',
        'store_quota'       => 'int',
        'store_used'        => 'int',
        'clip_power'        => 'int',
        'storage'           => 'int',
        'redpacket_balance' => 'float',
        'expire_time'       => 'datetime',
        'create_time'       => 'datetime',
        'update_time'       => 'datetime',
    ];

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'id'                => 'integer',
        'merchant_id'       => 'integer',
        'store_quota'       => 'integer',
        'store_used'        => 'integer',
        'clip_power'        => 'integer',
        'storage'           => 'integer',
        'redpacket_balance' => 'float',
        'expire_time'       => 'datetime',
        'create_time'       => 'datetime',
        'update_time'       => 'datetime',
    ];

    const VERSION_BASIC = 'basic';
    const VERSION_STANDARD = 'standard';
    const VERSION_CHAIN = 'chain';

    protected static array $versionText = [
        self::VERSION_BASIC    => '基础版',
        self::VERSION_STANDARD => '标准版',
        self::VERSION_CHAIN    => '连锁版',
    ];

    protected static array $versionStoreQuota = [
        self::VERSION_BASIC    => 1,
        self::VERSION_STANDARD => 5,
        self::VERSION_CHAIN    => 999,
    ];

    public function getVersionTextAttr($value, $data): string
    {
        return self::$versionText[$data['version_type']] ?? '未知';
    }

    public static function getVersionStoreQuota(string $version): int
    {
        return self::$versionStoreQuota[$version] ?? 1;
    }

    public static function getVersionTextMap(): array
    {
        return self::$versionText;
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public static function createForMerchant(int $merchantId, string $version = self::VERSION_BASIC): self
    {
        $benefit = new self();
        $benefit->merchant_id = $merchantId;
        $benefit->version_type = $version;
        $benefit->store_quota = self::getVersionStoreQuota($version);
        $benefit->store_used = 0;
        $benefit->clip_power = 0;
        $benefit->storage = 0;
        $benefit->redpacket_balance = 0;
        $benefit->expire_time = null;
        $benefit->save();

        return $benefit;
    }
}
