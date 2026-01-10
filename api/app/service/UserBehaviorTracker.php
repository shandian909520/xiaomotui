<?php
declare(strict_types=1);

namespace app\service;

use app\model\MaterialUsageLog;
use app\model\MaterialRating;
use app\model\ContentFeedback;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;

/**
 * 用户行为追踪服务
 * 记录和分析用户行为，为推荐系统提供数据支持
 */
class UserBehaviorTracker
{
    /**
     * 行为类型常量
     */
    const ACTION_VIEW = 'view';           // 浏览
    const ACTION_USE = 'use';             // 使用
    const ACTION_RATE = 'rate';           // 评分
    const ACTION_FEEDBACK = 'feedback';   // 反馈
    const ACTION_FAVORITE = 'favorite';   // 收藏
    const ACTION_SHARE = 'share';         // 分享

    /**
     * 记录用户行为
     *
     * @param int $userId 用户ID
     * @param string $action 行为类型
     * @param array $data 行为数据
     * @return bool
     */
    public static function track(int $userId, string $action, array $data): bool
    {
        try {
            $behavior = [
                'user_id' => $userId,
                'action' => $action,
                'template_id' => $data['template_id'] ?? null,
                'content_id' => $data['content_id'] ?? null,
                'value' => $data['value'] ?? null,
                'metadata' => json_encode($data['metadata'] ?? []),
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            // 保存到数据库（这里简化处理，实际应有专门的表）
            // 同时更新缓存的用户行为数据
            self::updateUserProfile($userId, $action, $data);

            return true;
        } catch (\Exception $e) {
            Log::error('用户行为追踪失败: ' . $e->getMessage(), $behavior ?? []);
            return false;
        }
    }

    /**
     * 更新用户画像
     *
     * @param int $userId 用户ID
     * @param string $action 行为类型
     * @param array $data 行为数据
     */
    private static function updateUserProfile(int $userId, string $action, array $data): void
    {
        $cacheKey = "user_profile:{$userId}";
        $profile = Cache::get($cacheKey, []);

        // 更新行为计数
        if (!isset($profile['actions'])) {
            $profile['actions'] = [];
        }

        if (!isset($profile['actions'][$action])) {
            $profile['actions'][$action] = 0;
        }

        $profile['actions'][$action]++;

        // 更新偏好（类型、分类、风格）
        if (isset($data['template_id'])) {
            self::updatePreferences($profile, $data['template_id']);
        }

        // 更新最后活跃时间
        $profile['last_active'] = time();

        // 保存到缓存（7天过期）
        Cache::set($cacheKey, $profile, 7 * 24 * 3600);
    }

    /**
     * 更新用户偏好
     *
     * @param array $profile 用户画像
     * @param int $templateId 模板ID
     */
    private static function updatePreferences(array &$profile, int $templateId): void
    {
        $template = \app\model\ContentTemplate::find($templateId);

        if (!$template) {
            return;
        }

        // 初始化偏好统计
        if (!isset($profile['preferences'])) {
            $profile['preferences'] = [
                'types' => [],
                'categories' => [],
                'styles' => [],
            ];
        }

        // 更新类型偏好
        $type = $template->type;
        if (!isset($profile['preferences']['types'][$type])) {
            $profile['preferences']['types'][$type] = 0;
        }
        $profile['preferences']['types'][$type]++;

        // 更新分类偏好
        $category = $template->category;
        if ($category && !isset($profile['preferences']['categories'][$category])) {
            $profile['preferences']['categories'][$category] = 0;
        }
        if ($category) {
            $profile['preferences']['categories'][$category]++;
        }

        // 更新风格偏好
        $style = $template->style;
        if ($style && !isset($profile['preferences']['styles'][$style])) {
            $profile['preferences']['styles'][$style] = 0;
        }
        if ($style) {
            $profile['preferences']['styles'][$style]++;
        }
    }

    /**
     * 获取用户画像
     *
     * @param int $userId 用户ID
     * @return array
     */
    public static function getUserProfile(int $userId): array
    {
        $cacheKey = "user_profile:{$userId}";
        $profile = Cache::get($cacheKey);

        if ($profile) {
            return $profile;
        }

        // 从数据库重建用户画像
        $profile = self::buildUserProfile($userId);

        // 缓存7天
        Cache::set($cacheKey, $profile, 7 * 24 * 3600);

        return $profile;
    }

    /**
     * 从数据库构建用户画像
     *
     * @param int $userId 用户ID
     * @return array
     */
    private static function buildUserProfile(int $userId): array
    {
        $profile = [
            'user_id' => $userId,
            'actions' => [],
            'preferences' => [
                'types' => [],
                'categories' => [],
                'styles' => [],
            ],
            'last_active' => null,
        ];

        // 统计使用记录
        $usageLogs = MaterialUsageLog::where('user_id', $userId)
            ->with('template')
            ->select();

        $profile['actions']['use'] = count($usageLogs);

        foreach ($usageLogs as $log) {
            if ($log->template) {
                $type = $log->template->type;
                $category = $log->template->category;
                $style = $log->template->style;

                if ($type) {
                    $profile['preferences']['types'][$type] =
                        ($profile['preferences']['types'][$type] ?? 0) + 1;
                }

                if ($category) {
                    $profile['preferences']['categories'][$category] =
                        ($profile['preferences']['categories'][$category] ?? 0) + 1;
                }

                if ($style) {
                    $profile['preferences']['styles'][$style] =
                        ($profile['preferences']['styles'][$style] ?? 0) + 1;
                }
            }

            $profile['last_active'] = strtotime($log->create_time);
        }

        // 统计评分记录
        $ratings = MaterialRating::where('user_id', $userId)->count();
        $profile['actions']['rate'] = $ratings;

        // 统计反馈记录
        $feedbacks = ContentFeedback::where('user_id', $userId)->count();
        $profile['actions']['feedback'] = $feedbacks;

        return $profile;
    }

    /**
     * 获取用户活跃度分数
     *
     * @param int $userId 用户ID
     * @return float 活跃度分数 (0-1)
     */
    public static function getUserActivityScore(int $userId): float
    {
        $profile = self::getUserProfile($userId);

        if (empty($profile['last_active'])) {
            return 0.0;
        }

        // 计算距离上次活跃的天数
        $daysSinceActive = (time() - $profile['last_active']) / 86400;

        // 使用指数衰减函数
        $activityScore = exp(-$daysSinceActive / 30);  // 30天半衰期

        // 考虑行为总数
        $totalActions = array_sum($profile['actions']);
        $actionScore = min(1.0, $totalActions / 100);  // 100次行为为满分

        // 综合分数
        return ($activityScore * 0.6 + $actionScore * 0.4);
    }

    /**
     * 获取用户偏好标签
     *
     * @param int $userId 用户ID
     * @param int $limit TOP N
     * @return array
     */
    public static function getUserPreferenceTags(int $userId, int $limit = 5): array
    {
        $profile = self::getUserProfile($userId);

        $tags = [];

        // 获取最喜欢的类型
        if (!empty($profile['preferences']['types'])) {
            arsort($profile['preferences']['types']);
            $topTypes = array_slice($profile['preferences']['types'], 0, $limit, true);
            foreach ($topTypes as $type => $count) {
                $tags[] = [
                    'type' => 'content_type',
                    'value' => $type,
                    'weight' => $count,
                ];
            }
        }

        // 获取最喜欢的分类
        if (!empty($profile['preferences']['categories'])) {
            arsort($profile['preferences']['categories']);
            $topCategories = array_slice($profile['preferences']['categories'], 0, $limit, true);
            foreach ($topCategories as $category => $count) {
                $tags[] = [
                    'type' => 'category',
                    'value' => $category,
                    'weight' => $count,
                ];
            }
        }

        // 获取最喜欢的风格
        if (!empty($profile['preferences']['styles'])) {
            arsort($profile['preferences']['styles']);
            $topStyles = array_slice($profile['preferences']['styles'], 0, $limit, true);
            foreach ($topStyles as $style => $count) {
                $tags[] = [
                    'type' => 'style',
                    'value' => $style,
                    'weight' => $count,
                ];
            }
        }

        return $tags;
    }

    /**
     * 清除用户画像缓存
     *
     * @param int $userId 用户ID
     * @return bool
     */
    public static function clearUserProfile(int $userId): bool
    {
        $cacheKey = "user_profile:{$userId}";
        return Cache::delete($cacheKey);
    }
}
