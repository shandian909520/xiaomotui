<?php
declare (strict_types = 1);

namespace app\action;

use app\contract\ActionPluginInterface;
use app\model\TaskAction;
use app\model\TaskInstance;

/**
 * 动作插件基类：提供通用默认实现
 */
abstract class AbstractActionPlugin implements ActionPluginInterface
{
    /**
     * 插件默认能力，子类可覆盖
     */
    public function capability(): array
    {
        return [
            'verify_method'         => 'trust',
            'fallback_verify_method'=> null,
            'need_proof'            => true,
            'env'                   => ['wechat_h5', 'browser'],
        ];
    }

    /**
     * 读取动作私有配置（action_config JSON）
     */
    protected function config(TaskAction $action, string $key, $default = null)
    {
        $config = $action->action_config ?? [];
        return $config[$key] ?? $default;
    }

    /**
     * 通用卡片渲染：默认无跳转，仅展示引导
     */
    public function renderCard(TaskInstance $instance, TaskAction $action): array
    {
        return [
            'jump_type'   => 'none',
            'scheme_url'  => null,
            'qrcode_url'  => null,
            'copy_text'   => null,
            'guide_steps' => [],
        ];
    }

    /**
     * 默认开始逻辑：标记 STARTED 并返回卡片数据
     */
    public function start(TaskInstance $instance, TaskAction $action): array
    {
        return $this->renderCard($instance, $action);
    }

    /**
     * 默认回滚：空操作（多数动作无副作用）
     */
    public function rollback(TaskInstance $instance, TaskAction $action): void
    {
    }
}
