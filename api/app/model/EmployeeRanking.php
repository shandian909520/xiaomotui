<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class EmployeeRanking extends Model
{
    protected $name = 'employee_rankings';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';
    protected $updateTime = false;

    protected $type = [
        'id'           => 'integer',
        'merchant_id'  => 'integer',
        'employee_id'  => 'integer',
        'score'        => 'integer',
        'rank_num'     => 'integer',
        'period_start' => 'datetime',
        'period_end'   => 'datetime',
        'create_time'  => 'datetime',
    ];
}
