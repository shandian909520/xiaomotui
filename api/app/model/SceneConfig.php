<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 场景配置模型
 * @property int $id
 * @property int $merchant_id 商家ID
 * @property int $store_id 门店ID（关联xmt_stores）
 * @property string $store_name 门店名称
 * @property int $status 状态 0禁用 1正常
 * @property bool $scan_enabled 扫码触发
 * @property array $platform_config 平台配置
 * @property array $graphic_config 图文配置
 * @property array $review_config 评价配置
 * @property array $checkin_config 签到配置
 * @property array $follow_config 关注配置
 * @property array $like_share_config 点赞分享配置
 * @property array $groupbuy_config 团购配置
 * @property array $wifi_config WiFi配置
 * @property array $wechat_card_config 微信卡券配置
 * @property array $custom_link_config 自定义链接配置
 * @property array $edaijia_config e代驾配置
 * @property array $touch_config 触控配置
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class SceneConfig extends Model
{
    protected $table = 'xmt_scene_configs';

    protected $pk = 'id';

    protected $schema = [
        'id'                   => 'int',
        'merchant_id'          => 'int',
        'store_id'             => 'int',
        'store_name'           => 'string',
        'status'               => 'int',
        'scan_enabled'         => 'int',
        'platform_config'      => 'json',
        'graphic_config'       => 'json',
        'review_config'        => 'json',
        'checkin_config'       => 'json',
        'follow_config'        => 'json',
        'like_share_config'    => 'json',
        'groupbuy_config'      => 'json',
        'wifi_config'          => 'json',
        'wechat_card_config'   => 'json',
        'custom_link_config'   => 'json',
        'edaijia_config'       => 'json',
        'touch_config'         => 'json',
        'create_time'          => 'datetime',
        'update_time'          => 'datetime',
    ];

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'id'                   => 'integer',
        'merchant_id'          => 'integer',
        'store_id'             => 'integer',
        'status'               => 'integer',
        'scan_enabled'         => 'integer',
        'platform_config'      => 'json',
        'graphic_config'       => 'json',
        'review_config'        => 'json',
        'checkin_config'       => 'json',
        'follow_config'        => 'json',
        'like_share_config'    => 'json',
        'groupbuy_config'      => 'json',
        'wifi_config'          => 'json',
        'wechat_card_config'   => 'json',
        'custom_link_config'   => 'json',
        'edaijia_config'       => 'json',
        'touch_config'         => 'json',
        'create_time'          => 'timestamp',
        'update_time'          => 'timestamp',
    ];

    protected $field = [
        'merchant_id', 'store_id', 'store_name', 'status',
        'scan_enabled', 'platform_config', 'graphic_config',
        'review_config', 'checkin_config', 'follow_config',
        'like_share_config', 'groupbuy_config', 'wifi_config',
        'wechat_card_config', 'custom_link_config', 'edaijia_config',
        'touch_config',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ACTIVE = 1;

    protected static $statusText = [
        self::STATUS_DISABLED => '已禁用',
        self::STATUS_ACTIVE => '正常',
    ];

    /**
     * 矩阵配置项列
     */
    const CONFIG_COLUMNS = [
        'scan_enabled',
        'platform_config',
        'graphic_config',
        'review_config',
        'checkin_config',
        'follow_config',
        'like_share_config',
        'groupbuy_config',
        'wifi_config',
        'wechat_card_config',
        'custom_link_config',
        'edaijia_config',
        'touch_config',
    ];

    /**
     * 配置项中文名映射
     */
    const CONFIG_LABELS = [
        'scan_enabled'        => '扫码触发',
        'platform_config'     => '平台配置',
        'graphic_config'      => '图文配置',
        'review_config'       => '评价配置',
        'checkin_config'      => '签到配置',
        'follow_config'       => '关注配置',
        'like_share_config'   => '点赞分享',
        'groupbuy_config'     => '团购配置',
        'wifi_config'         => 'WiFi配置',
        'wechat_card_config'  => '微信卡券',
        'custom_link_config'  => '自定义链接',
        'edaijia_config'      => 'e代驾',
        'touch_config'        => '触控配置',
    ];

    /**
     * 支持的平台列表
     */
    const PLATFORMS = [
        ['key' => 'douyin', 'name' => '抖音'],
        ['key' => 'meituan', 'name' => '美团'],
        ['key' => 'dianping', 'name' => '大众点评'],
        ['key' => 'xiaohongshu', 'name' => '小红书'],
        ['key' => 'kuaishou', 'name' => '快手'],
        ['key' => 'wechat', 'name' => '微信'],
        ['key' => 'eleme', 'name' => '饿了么'],
    ];

    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusText[$data['status']] ?? '未知';
    }

    /**
     * 获取某门店的矩阵摘要（哪些列有配置）
     */
    public function getConfigSummary(): array
    {
        $summary = [];
        foreach (self::CONFIG_COLUMNS as $col) {
            $val = $this->$col;
            if ($col === 'scan_enabled') {
                $summary[$col] = !empty($val);
            } else {
                $summary[$col] = !empty($val) && is_array($val) && count($val) > 0;
            }
        }
        return $summary;
    }
}
