<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 发布验证器
 */
class PublishValidate extends Validate
{
    protected $rule = [
        // 发布任务
        'content_task_id' => 'require|integer|>:0',
        'platforms' => 'require|array|platformList',
        'scheduled_time' => 'dateFormat:Y-m-d H:i:s',

        // 平台配置项
        'platform' => 'require|in:douyin,kuaishou,xiaohongshu,weibo,bilibili',
        'account_id' => 'require|integer|>:0',
        'title' => 'max:100',
        'tags' => 'array',
        'location' => 'max:100',
        'cover_url' => 'max:500',
        'privacy' => 'in:PUBLIC,PRIVATE,FRIENDS',

        // 任务列表查询
        'page' => 'integer|between:1,1000',
        'limit' => 'integer|between:1,100',
        'status' => 'in:PENDING,PROCESSING,SUCCESS,FAILED,PARTIAL_SUCCESS',
        'content_task_id_query' => 'integer|>:0',
        'start_date' => 'date',
        'end_date' => 'date',
        'sort' => 'in:create_time,update_time,status',
        'order' => 'in:asc,desc',

        // 定时任务更新
        'task_id' => 'require|integer|>:0',
    ];

    protected $message = [
        // 发布任务
        'content_task_id.require' => '内容任务ID不能为空',
        'content_task_id.integer' => '内容任务ID必须是整数',
        'content_task_id.>' => '内容任务ID必须大于0',
        'platforms.require' => '发布平台列表不能为空',
        'platforms.array' => '发布平台列表必须是数组格式',
        'platforms.platformList' => '发布平台列表格式不正确',
        'scheduled_time.dateFormat' => '定时发布时间格式不正确',

        // 平台配置项
        'platform.require' => '平台名称不能为空',
        'platform.in' => '不支持的平台类型',
        'account_id.require' => '平台账号ID不能为空',
        'account_id.integer' => '平台账号ID必须是整数',
        'account_id.>' => '平台账号ID必须大于0',
        'title.max' => '标题长度不能超过100个字符',
        'tags.array' => '标签必须是数组格式',
        'location.max' => '位置信息长度不能超过100个字符',
        'cover_url.max' => '封面URL长度不能超过500个字符',
        'privacy.in' => '隐私设置必须是PUBLIC、PRIVATE或FRIENDS',

        // 任务列表查询
        'page.integer' => '页码必须是整数',
        'page.between' => '页码必须在1-1000之间',
        'limit.integer' => '每页数量必须是整数',
        'limit.between' => '每页数量必须在1-100之间',
        'status.in' => '任务状态不正确',
        'content_task_id_query.integer' => '内容任务ID必须是整数',
        'content_task_id_query.>' => '内容任务ID必须大于0',
        'start_date.date' => '开始日期格式不正确',
        'end_date.date' => '结束日期格式不正确',
        'sort.in' => '排序字段不正确',
        'order.in' => '排序方向不正确',

        // 定时任务更新
        'task_id.require' => '任务ID不能为空',
        'task_id.integer' => '任务ID必须是整数',
        'task_id.>' => '任务ID必须大于0',
    ];

    protected $scene = [
        'create' => ['content_task_id', 'platforms', 'scheduled_time'],
        'taskList' => ['page', 'limit', 'status', 'content_task_id_query', 'start_date', 'end_date', 'sort', 'order'],
        'platformAuth' => ['platform'],
    ];

    protected function platformList($value, $rule, $data = [])
    {
        if (!is_array($value) || empty($value)) {
            return '发布平台列表不能为空';
        }

        if (count($value) > 5) {
            return '单次最多发布到5个平台';
        }

        $validPlatforms = ['douyin', 'kuaishou', 'xiaohongshu', 'weibo', 'bilibili',
                          'DOUYIN', 'KUAISHOU', 'XIAOHONGSHU', 'WEIBO', 'BILIBILI'];

        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                return "平台配置第{$index}项必须是数组格式";
            }

            if (empty($item['platform'])) {
                return "平台配置第{$index}项缺少平台名称";
            }

            $platform = strtoupper($item['platform']);
            $validUpper = ['DOUYIN', 'KUAISHOU', 'XIAOHONGSHU', 'WEIBO', 'BILIBILI'];

            if (!in_array($platform, $validUpper)) {
                return "平台配置第{$index}项平台类型不支持: {$item['platform']}";
            }

            if (empty($item['account_id'])) {
                return "平台配置第{$index}项缺少平台账号ID";
            }
        }

        return true;
    }
}
