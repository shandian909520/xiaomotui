<?php
declare (strict_types = 1);

namespace app\contract;

use app\model\TaskAction;
use app\model\TaskInstance;

/**
 * 任务动作插件契约
 * 所有"做动作→得奖励"类业务实现此接口
 */
interface ActionPluginInterface
{
    /**
     * 插件唯一标识（对应 task_actions.plugin_key）
     */
    public static function key(): string;

    /**
     * 展示元信息：name / icon / description / platform
     */
    public function meta(): array;

    /**
     * 能力声明
     * - verify_method: callback|trust|system（回调核验/信任模式/系统直判）
     * - fallback_verify_method: 回调未配置时降级的验证方式（默认 trust）
     * - need_proof: 是否需要上传凭证
     * - env: 支持的执行环境 wechat_h5|browser|mp_weixin
     */
    public function capability(): array;

    /**
     * 渲染动作卡片数据（落地页 ActionCard 组件所需）
     * 返回: {jump_type: scheme|qrcode|none, scheme_url, qrcode_url, copy_text, guide_steps[]}
     */
    public function renderCard(TaskInstance $instance, TaskAction $action): array;

    /**
     * 用户开始执行动作
     * 返回跳转/引导数据，并负责更新动作状态为 STARTED
     */
    public function start(TaskInstance $instance, TaskAction $action): array;

    /**
     * 验证动作完成
     * - callback: 平台回调驱动
     * - trust: 凭证审核驱动（此处只做查重入队）
     * - system: 系统直接判定
     * 返回 true=完成；false=待验证/未通过
     */
    public function verify(TaskInstance $instance, TaskAction $action, array $payload = []): bool;

    /**
     * 回滚（任务过期/审核驳回时恢复状态）
     */
    public function rollback(TaskInstance $instance, TaskAction $action): void;
}
