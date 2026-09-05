<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class StoreImportTask extends Model
{
    public const IMPORT_TYPE_STORE = 'store';
    public const IMPORT_TYPE_POI = 'poi';

    protected $name = 'store_import_tasks';

    protected $autoWriteTimestamp = true;

    protected $type = [
        'id'            => 'integer',
        'merchant_id'   => 'integer',
        'total_count'   => 'integer',
        'success_count' => 'integer',
        'fail_count'    => 'integer',
        'create_time'   => 'datetime',
        'update_time'   => 'datetime',
    ];

    public function getStatusTextAttr($value, $data): string
    {
        return match ($data['status'] ?? '') {
            'pending'    => '等待中',
            'processing' => '处理中',
            'completed'  => '已完成',
            'failed'     => '失败',
            default      => '未知',
        };
    }
}
