<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 内容验证器
 */
class Content extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule = [
        // 内容生成相关
        'merchant_id' => 'require|integer|>:0',
        'device_id' => 'integer|>:0',
        'template_id' => 'integer|>:0',
        'type' => 'require|in:VIDEO,TEXT,IMAGE,video,text,image',
        'input_data' => 'array',

        // 任务状态查询相关
        'task_id' => 'require|integer|>:0',
        'task_ids' => 'require|taskIdList',

        // 模板列表相关
        'page' => 'integer|between:1,1000',
        'limit' => 'integer|between:1,100',
        'category' => 'max:50',
        'style' => 'max:50',
        'keyword' => 'max:100',
        'include_system' => 'boolean',
        'sort' => 'in:usage_count,create_time,name,update_time',

        // 任务历史相关
        'status' => 'in:pending,processing,completed,failed',
        'start_date' => 'date',
        'end_date' => 'date|after:start_date',

        // 重新生成相关
        'regenerate_reason' => 'max:200',
        'adjust_params' => 'array',

        // 取消任务相关
        'cancel_reason' => 'max:200'
    ];

    /**
     * 定义错误信息
     */
    protected $message = [
        // 内容生成相关
        'merchant_id.require' => '商家ID不能为空',
        'merchant_id.integer' => '商家ID必须是整数',
        'merchant_id.>' => '商家ID必须大于0',
        'device_id.integer' => '设备ID必须是整数',
        'device_id.>' => '设备ID必须大于0',
        'template_id.integer' => '模板ID必须是整数',
        'template_id.>' => '模板ID必须大于0',
        'type.require' => '内容类型不能为空',
        'type.in' => '内容类型必须是VIDEO、TEXT或IMAGE之一',
        'input_data.array' => '输入数据必须是数组格式',

        // 任务状态查询相关
        'task_id.require' => '任务ID不能为空',
        'task_id.integer' => '任务ID必须是整数',
        'task_id.>' => '任务ID必须大于0',
        'task_ids.require' => '任务ID列表不能为空',
        'task_ids.taskIdList' => '任务ID列表格式不正确',

        // 模板列表相关
        'page.integer' => '页码必须是整数',
        'page.between' => '页码必须在1-1000之间',
        'limit.integer' => '每页数量必须是整数',
        'limit.between' => '每页数量必须在1-100之间',
        'category.max' => '分类名称长度不能超过50个字符',
        'style.max' => '风格标签长度不能超过50个字符',
        'keyword.max' => '搜索关键词长度不能超过100个字符',
        'include_system.boolean' => '是否包含系统模板必须是布尔值',
        'sort.in' => '排序字段必须是usage_count、create_time、name或update_time之一',

        // 任务历史相关
        'status.in' => '状态必须是pending、processing、completed或failed之一',
        'start_date.date' => '开始日期格式不正确',
        'end_date.date' => '结束日期格式不正确',
        'end_date.after' => '结束日期必须晚于开始日期',

        // 重新生成相关
        'regenerate_reason.max' => '重新生成原因长度不能超过200个字符',
        'adjust_params.array' => '调整参数必须是数组格式',

        // 取消任务相关
        'cancel_reason.max' => '取消原因长度不能超过200个字符'
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'generate' => ['merchant_id', 'device_id', 'type', 'template_id', 'input_data'],
        'taskStatus' => ['task_id'],
        'batchTaskStatus' => ['task_ids'],
        'templates' => ['page', 'limit', 'type', 'category', 'style', 'keyword', 'include_system', 'sort'],
        'taskHistory' => ['page', 'limit', 'type', 'status', 'device_id', 'start_date', 'end_date'],
        'regenerate' => ['task_id', 'regenerate_reason', 'adjust_params'],
        'cancelTask' => ['task_id', 'cancel_reason']
    ];

    /**
     * 自定义验证规则 - 验证任务ID列表格式
     */
    protected function taskIdList($value, $rule, $data = [])
    {
        if (empty($value)) {
            return '任务ID列表不能为空';
        }

        // 支持逗号分隔的ID列表
        $ids = explode(',', $value);

        if (count($ids) > 50) {
            return '单次最多查询50个任务';
        }

        foreach ($ids as $id) {
            $id = trim($id);
            if (!is_numeric($id) || $id <= 0) {
                return '任务ID列表包含无效的ID：' . $id;
            }
        }

        return true;
    }

    /**
     * 自定义验证规则 - 验证内容类型配置
     */
    protected function contentTypeConfig($value, $rule, $data = [])
    {
        if (empty($value) || !is_array($value)) {
            return true; // 允许为空，由其他规则处理
        }

        $type = $data['type'] ?? '';

        switch ($type) {
            case 'video':
                return $this->validateVideoConfig($value);
            case 'menu':
                return $this->validateMenuConfig($value);
            case 'image':
                return $this->validateImageConfig($value);
            default:
                return '未知的内容类型';
        }
    }

    /**
     * 验证视频配置
     */
    private function validateVideoConfig(array $config): bool|string
    {
        $requiredFields = ['duration', 'resolution'];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field])) {
                return "视频配置缺少必要字段：{$field}";
            }
        }

        // 验证时长（秒）
        if (!is_numeric($config['duration']) || $config['duration'] < 1 || $config['duration'] > 300) {
            return '视频时长必须在1-300秒之间';
        }

        // 验证分辨率
        $validResolutions = ['720p', '1080p', '4k'];
        if (!in_array($config['resolution'], $validResolutions)) {
            return '视频分辨率必须是720p、1080p或4k';
        }

        return true;
    }

    /**
     * 验证菜单配置
     */
    private function validateMenuConfig(array $config): bool|string
    {
        if (!isset($config['items']) || !is_array($config['items'])) {
            return '菜单配置必须包含items数组';
        }

        if (count($config['items']) === 0) {
            return '菜单项不能为空';
        }

        if (count($config['items']) > 50) {
            return '菜单项不能超过50个';
        }

        foreach ($config['items'] as $index => $item) {
            if (!is_array($item)) {
                return "菜单项{$index}必须是数组格式";
            }

            if (empty($item['name'])) {
                return "菜单项{$index}缺少名称";
            }

            if (mb_strlen($item['name']) > 50) {
                return "菜单项{$index}名称长度不能超过50个字符";
            }
        }

        return true;
    }

    /**
     * 验证图片配置
     */
    private function validateImageConfig(array $config): bool|string
    {
        // 验证图片尺寸
        if (isset($config['width']) && (!is_numeric($config['width']) || $config['width'] < 100 || $config['width'] > 4000)) {
            return '图片宽度必须在100-4000像素之间';
        }

        if (isset($config['height']) && (!is_numeric($config['height']) || $config['height'] < 100 || $config['height'] > 4000)) {
            return '图片高度必须在100-4000像素之间';
        }

        // 验证图片格式
        if (isset($config['format'])) {
            $validFormats = ['jpeg', 'jpg', 'png', 'webp'];
            if (!in_array(strtolower($config['format']), $validFormats)) {
                return '图片格式必须是jpeg、jpg、png或webp';
            }
        }

        return true;
    }

    /**
     * 自定义验证规则 - 验证设备权限
     */
    protected function devicePermission($value, $rule, $data = [])
    {
        if (empty($value) || !is_numeric($value)) {
            return true; // 交给其他规则处理
        }

        // 检查设备是否存在并且用户有权限使用
        $device = \app\model\NfcDevice::find($value);
        if (!$device) {
            return '设备不存在';
        }

        // 这里可以添加更多的权限检查逻辑
        // 例如检查用户是否有权使用该设备

        return true;
    }

    /**
     * 自定义验证规则 - 验证模板权限
     */
    protected function templatePermission($value, $rule, $data = [])
    {
        if (empty($value) || !is_numeric($value)) {
            return true; // 允许不指定模板
        }

        // 检查模板是否存在并且可用
        $template = \app\model\ContentTemplate::find($value);
        if (!$template) {
            return '模板不存在';
        }

        if ($template->status !== \app\model\ContentTemplate::STATUS_ENABLED) {
            return '模板已被禁用';
        }

        // 检查类型是否匹配
        $type = strtoupper($data['type'] ?? '');
        if ($template->type !== $type) {
            return '模板类型与内容类型不匹配';
        }

        return true;
    }

    /**
     * 验证场景定制规则 - 内容生成
     */
    public function sceneGenerate()
    {
        return $this->only(['merchant_id', 'device_id', 'type', 'template_id', 'input_data'])
                   ->append('device_id', 'devicePermission')
                   ->append('template_id', 'templatePermission');
    }

    /**
     * 验证场景定制规则 - 模板列表
     */
    public function sceneTemplates()
    {
        return $this->only(['page', 'limit', 'type', 'category', 'style', 'keyword', 'include_system', 'sort']);
    }

    /**
     * 自定义验证规则 - 验证布尔值
     */
    protected function boolean($value, $rule, $data = [])
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            return in_array($lower, ['true', 'false', '1', '0', 'yes', 'no']);
        }

        if (is_numeric($value)) {
            return in_array($value, [0, 1]);
        }

        return false;
    }

    /**
     * 自定义验证规则 - 验证日期范围
     */
    protected function dateRange($value, $rule, $data = [])
    {
        if (empty($data['start_date']) || empty($data['end_date'])) {
            return true;
        }

        $start = strtotime($data['start_date']);
        $end = strtotime($data['end_date']);

        if ($start === false || $end === false) {
            return '日期格式不正确';
        }

        // 限制查询范围不超过1年
        $maxRange = 365 * 24 * 3600; // 1年的秒数
        if (($end - $start) > $maxRange) {
            return '查询时间范围不能超过1年';
        }

        return true;
    }

    /**
     * 验证场景定制规则 - 任务历史
     */
    public function sceneTaskHistory()
    {
        return $this->only(['page', 'limit', 'type', 'status', 'device_id', 'start_date', 'end_date'])
                   ->append('end_date', 'dateRange');
    }
}