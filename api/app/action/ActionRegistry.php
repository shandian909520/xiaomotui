<?php
declare (strict_types = 1);

namespace app\action;

use app\contract\ActionPluginInterface;
use think\facade\Config;

/**
 * 动作插件注册表
 * 插件清单由 config/action_plugins.php 配置驱动
 */
class ActionRegistry
{
    /** @var array<string, ActionPluginInterface> 已实例化插件 */
    private static array $instances = [];

    /**
     * 获取插件实例
     * @throws \Exception 插件未注册
     */
    public static function get(string $pluginKey): ActionPluginInterface
    {
        if (isset(self::$instances[$pluginKey])) {
            return self::$instances[$pluginKey];
        }

        $map = Config::get('action_plugins.plugins', []);
        if (!isset($map[$pluginKey])) {
            throw new \Exception("动作插件未注册: {$pluginKey}");
        }

        $class = $map[$pluginKey];
        if (!class_exists($class)) {
            throw new \Exception("动作插件类不存在: {$class}");
        }
        if (!is_subclass_of($class, ActionPluginInterface::class)) {
            throw new \Exception("动作插件未实现契约: {$class}");
        }

        return self::$instances[$pluginKey] = new $class();
    }

    /**
     * 是否已注册
     */
    public static function has(string $pluginKey): bool
    {
        return isset(Config::get('action_plugins.plugins', [])[$pluginKey]);
    }

    /**
     * 全部插件元信息（后台配置表单动态渲染用）
     */
    public static function allMeta(): array
    {
        $result = [];
        foreach (array_keys(Config::get('action_plugins.plugins', [])) as $key) {
            $plugin = self::get($key);
            $result[$key] = array_merge(
                ['key' => $key],
                $plugin->meta(),
                $plugin->capability()
            );
        }
        return $result;
    }
}
