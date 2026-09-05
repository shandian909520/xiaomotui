<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 任务包内动作模型
 * @property int $id
 * @property int $bundle_id 任务包ID
 * @property string $plugin_key 插件标识
 * @property int $sort_order 排序
 * @property string $action_name 动作显示名
 * @property string|null $action_icon 图标
 * @property array|null $action_config 插件私有配置
 * @property int $required 是否必做
 */
class TaskAction extends Model
{
    protected $table = 'xmt_task_actions';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'         => 'integer',
        'bundle_id'  => 'integer',
        'sort_order' => 'integer',
        'required'   => 'integer',
    ];

    protected $json = ['action_config'];
    protected $jsonAssoc = true;

    protected $field = [
        'bundle_id', 'plugin_key', 'sort_order', 'action_name',
        'action_icon', 'action_config', 'required',
    ];
}
