<?php
declare (strict_types = 1);

namespace app\action;

/**
 * 动作插件派发器
 */
class ActionDispatcher
{
    /**
     * 派发调用插件方法
     * dispatch('douyin_publish', 'start', [$instance, $action])
     */
    public static function dispatch(string $pluginKey, string $method, array $args = [])
    {
        $plugin = ActionRegistry::get($pluginKey);
        if (!method_exists($plugin, $method)) {
            throw new \Exception("插件方法不存在: {$pluginKey}::{$method}");
        }
        return call_user_func_array([$plugin, $method], $args);
    }
}
