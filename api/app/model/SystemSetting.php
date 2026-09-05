<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Cache;

/**
 * 系统设置模型
 * @property int $id
 * @property string $group 分组
 * @property string $key 键名
 * @property string $value 键值
 * @property string $create_time
 * @property string $update_time
 */
class SystemSetting extends Model
{
    protected $table = 'xmt_system_settings';

    protected $autoWriteTimestamp = true;

    protected $schema = [
        'id'          => 'int',
        'group'       => 'string',
        'key'         => 'string',
        'value'       => 'string',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    const CACHE_PREFIX = 'system_setting:';
    const CACHE_TTL = 3600;

    /**
     * 获取设置值
     */
    public static function getSetting(string $key, string $group = '', mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . ($group ? "{$group}:" : '') . $key;
        $value = Cache::get($cacheKey);

        if ($value !== null) {
            return $value;
        }

        $query = self::where('key', $key);
        if ($group) {
            $query->where('group', $group);
        }
        $setting = $query->find();

        if (!$setting) {
            return $default;
        }

        $decoded = json_decode($setting->value, true);
        $value = $decoded !== null ? $decoded : $setting->value;

        Cache::set($cacheKey, $value, self::CACHE_TTL);

        return $value;
    }

    /**
     * 设置值
     */
    public static function setSetting(string $key, mixed $value, string $group = 'general'): bool
    {
        $encodedValue = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string)$value;

        $setting = self::where('key', $key)->where('group', $group)->find();

        if ($setting) {
            $setting->value = $encodedValue;
            $result = $setting->save();
        } else {
            $result = self::create([
                'group' => $group,
                'key'   => $key,
                'value' => $encodedValue,
            ]);
        }

        $cacheKey = self::CACHE_PREFIX . ($group !== 'general' ? "{$group}:" : '') . $key;
        Cache::set($cacheKey, $value, self::CACHE_TTL);

        return (bool)$result;
    }

    /**
     * 批量保存设置
     */
    public static function batchSave(array $settings, string $group = 'general'): bool
    {
        foreach ($settings as $key => $value) {
            self::setSetting($key, $value, $group);
        }
        return true;
    }

    /**
     * 获取分组的所有设置
     */
    public static function getGroupSettings(string $group): array
    {
        $settings = self::where('group', $group)->select();
        $result = [];
        foreach ($settings as $setting) {
            $decoded = json_decode($setting->value, true);
            $result[$setting->key] = $decoded !== null ? $decoded : $setting->value;
        }
        return $result;
    }

    /**
     * 删除设置
     */
    public static function deleteSetting(string $key, string $group = ''): bool
    {
        $query = self::where('key', $key);
        if ($group) {
            $query->where('group', $group);
        }
        $result = $query->delete();

        $cacheKey = self::CACHE_PREFIX . ($group ? "{$group}:" : '') . $key;
        Cache::delete($cacheKey);

        return (bool)$result;
    }
}
