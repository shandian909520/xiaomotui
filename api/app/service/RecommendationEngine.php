<?php
declare(strict_types=1);

namespace app\service;

use app\model\ContentTemplate;
use app\model\MaterialUsageLog;
use app\model\MaterialRating;
use think\facade\Log;

/**
 * 推荐引擎核心算法类
 * 提供增强的推荐算法实现
 */
class RecommendationEngine
{
    /**
     * 计算用户间的余弦相似度
     *
     * @param int $userId1 用户1 ID
     * @param int $userId2 用户2 ID
     * @return float 相似度值 (0-1)
     */
    public static function calculateUserSimilarity(int $userId1, int $userId2): float
    {
        // 获取两个用户的模板使用记录
        $user1Templates = self::getUserTemplateVector($userId1);
        $user2Templates = self::getUserTemplateVector($userId2);

        if (empty($user1Templates) || empty($user2Templates)) {
            return 0.0;
        }

        // 获取共同模板
        $commonTemplates = array_intersect_key($user1Templates, $user2Templates);

        if (empty($commonTemplates)) {
            return 0.0;
        }

        // 计算余弦相似度
        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        foreach ($user1Templates as $templateId => $score1) {
            $score2 = $user2Templates[$templateId] ?? 0;
            $dotProduct += $score1 * $score2;
            $magnitude1 += $score1 * $score1;
        }

        foreach ($user2Templates as $score2) {
            $magnitude2 += $score2 * $score2;
        }

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($magnitude1) * sqrt($magnitude2));
    }

    /**
     * 获取用户的模板向量
     *
     * @param int $userId 用户ID
     * @return array [template_id => score]
     */
    private static function getUserTemplateVector(int $userId): array
    {
        $usageLogs = MaterialUsageLog::where('user_id', $userId)
            ->field('template_id, COUNT(*) as usage_count')
            ->group('template_id')
            ->select()
            ->toArray();

        $vector = [];
        foreach ($usageLogs as $log) {
            $templateId = $log['template_id'];
            $usageCount = $log['usage_count'];

            // 获取评分（如果有）
            $rating = MaterialRating::where('user_id', $userId)
                ->where('template_id', $templateId)
                ->value('rating');

            // 综合使用次数和评分计算分数
            if ($rating) {
                $vector[$templateId] = $usageCount * ($rating / 5.0);
            } else {
                $vector[$templateId] = $usageCount;
            }
        }

        return $vector;
    }

    /**
     * 计算模板间的相似度（基于TF-IDF）
     *
     * @param int $templateId1 模板1 ID
     * @param int $templateId2 模板2 ID
     * @return float 相似度值 (0-1)
     */
    public static function calculateTemplateSimilarity(int $templateId1, int $templateId2): float
    {
        $template1 = ContentTemplate::find($templateId1);
        $template2 = ContentTemplate::find($templateId2);

        if (!$template1 || !$template2) {
            return 0.0;
        }

        $score = 0.0;

        // 1. 类型匹配 (40%)
        if ($template1->type === $template2->type) {
            $score += 0.4;
        }

        // 2. 分类匹配 (30%)
        if ($template1->category === $template2->category) {
            $score += 0.3;
        }

        // 3. 风格匹配 (20%)
        if ($template1->style === $template2->style) {
            $score += 0.2;
        }

        // 4. 标签相似度 (10%)
        if (isset($template1->tags) && isset($template2->tags)) {
            $tags1 = is_array($template1->tags) ? $template1->tags : json_decode($template1->tags, true) ?? [];
            $tags2 = is_array($template2->tags) ? $template2->tags : json_decode($template2->tags, true) ?? [];

            $commonTags = array_intersect($tags1, $tags2);
            $totalTags = array_unique(array_merge($tags1, $tags2));

            if (count($totalTags) > 0) {
                $score += 0.1 * (count($commonTags) / count($totalTags));
            }
        }

        return $score;
    }

    /**
     * 基于项目的协同过滤（Item-Based CF）
     *
     * @param int $userId 用户ID
     * @param int $limit 返回数量
     * @return array 推荐的模板ID及分数
     */
    public static function itemBasedCollaborativeFiltering(int $userId, int $limit = 20): array
    {
        // 获取用户使用过的模板
        $userTemplatesData = MaterialUsageLog::where('user_id', $userId)
            ->field('template_id, COUNT(*) as usage_count')
            ->group('template_id')
            ->order('usage_count', 'desc')
            ->limit(50)  // 只考虑最常用的50个
            ->select()
            ->toArray();

        $userTemplates = [];
        foreach ($userTemplatesData as $item) {
            $userTemplates[$item['template_id']] = $item['usage_count'];
        }

        if (empty($userTemplates)) {
            return [];
        }

        $recommendations = [];

        // 对每个用户使用过的模板，找出相似的模板
        foreach ($userTemplates as $templateId => $usageCount) {
            // 找出与该模板相似的模板
            $similarTemplates = self::findSimilarTemplatesByUsage($templateId, 20);

            foreach ($similarTemplates as $similarTemplate) {
                $candidateId = $similarTemplate['template_id'];
                $similarity = $similarTemplate['similarity'];

                // 跳过用户已使用的模板
                if (isset($userTemplates[$candidateId])) {
                    continue;
                }

                // 累计推荐分数
                if (!isset($recommendations[$candidateId])) {
                    $recommendations[$candidateId] = 0;
                }

                $recommendations[$candidateId] += $usageCount * $similarity;
            }
        }

        // 排序并返回
        arsort($recommendations);
        return array_slice($recommendations, 0, $limit, true);
    }

    /**
     * 基于用户行为的协同过滤（User-Based CF）
     *
     * @param int $userId 用户ID
     * @param int $limit 返回数量
     * @return array 推荐的模板ID及分数
     */
    public static function userBasedCollaborativeFiltering(int $userId, int $limit = 20): array
    {
        // 找出相似用户
        $similarUsers = self::findTopSimilarUsers($userId, 10);

        if (empty($similarUsers)) {
            return [];
        }

        $recommendations = [];
        $userTemplates = array_keys(self::getUserTemplateVector($userId));

        // 收集相似用户喜欢的模板
        foreach ($similarUsers as $similarUserId => $similarity) {
            $templates = MaterialUsageLog::where('user_id', $similarUserId)
                ->field('template_id, COUNT(*) as usage_count')
                ->group('template_id')
                ->select()
                ->toArray();

            foreach ($templates as $template) {
                $templateId = $template['template_id'];

                // 跳过用户已使用的模板
                if (in_array($templateId, $userTemplates)) {
                    continue;
                }

                // 累计推荐分数
                if (!isset($recommendations[$templateId])) {
                    $recommendations[$templateId] = 0;
                }

                $recommendations[$templateId] += $similarity * $template['usage_count'];
            }
        }

        // 排序并返回
        arsort($recommendations);
        return array_slice($recommendations, 0, $limit, true);
    }

    /**
     * 找出最相似的用户
     *
     * @param int $userId 用户ID
     * @param int $limit 数量限制
     * @return array [user_id => similarity]
     */
    private static function findTopSimilarUsers(int $userId, int $limit = 10): array
    {
        // 获取所有有使用记录的用户
        $allUsers = MaterialUsageLog::where('user_id', '<>', $userId)
            ->group('user_id')
            ->column('user_id');

        $similarities = [];

        foreach ($allUsers as $otherUserId) {
            $similarity = self::calculateUserSimilarity($userId, $otherUserId);

            if ($similarity > 0.1) {  // 只保留相似度大于0.1的用户
                $similarities[$otherUserId] = $similarity;
            }
        }

        // 排序并返回TOP N
        arsort($similarities);
        return array_slice($similarities, 0, $limit, true);
    }

    /**
     * 基于使用记录查找相似模板
     *
     * @param int $templateId 模板ID
     * @param int $limit 数量限制
     * @return array
     */
    private static function findSimilarTemplatesByUsage(int $templateId, int $limit = 20): array
    {
        // 找出使用过该模板的用户
        $users = MaterialUsageLog::where('template_id', $templateId)
            ->group('user_id')
            ->column('user_id');

        if (empty($users)) {
            return [];
        }

        // 找出这些用户还使用过的其他模板
        $otherTemplates = MaterialUsageLog::whereIn('user_id', $users)
            ->where('template_id', '<>', $templateId)
            ->field('template_id, COUNT(DISTINCT user_id) as user_count')
            ->group('template_id')
            ->select()
            ->toArray();

        $totalUsers = count($users);
        $similarities = [];

        foreach ($otherTemplates as $template) {
            $candidateId = $template['template_id'];
            $commonUsers = $template['user_count'];

            // 计算Jaccard相似度
            $similarity = $commonUsers / $totalUsers;

            $similarities[] = [
                'template_id' => $candidateId,
                'similarity' => $similarity,
            ];
        }

        // 排序
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($similarities, 0, $limit);
    }

    /**
     * 矩阵分解推荐（简化的SVD）
     *
     * @param int $userId 用户ID
     * @param int $limit 返回数量
     * @return array 推荐分数
     */
    public static function matrixFactorization(int $userId, int $limit = 20): array
    {
        // 这是一个简化的实现，真正的SVD需要更复杂的计算
        // 这里使用加权的协同过滤作为替代

        $userBased = self::userBasedCollaborativeFiltering($userId, $limit);
        $itemBased = self::itemBasedCollaborativeFiltering($userId, $limit);

        // 合并两种结果
        $combined = [];

        foreach ($userBased as $templateId => $score) {
            $combined[$templateId] = $score * 0.6;  // 用户协同过滤权重60%
        }

        foreach ($itemBased as $templateId => $score) {
            if (isset($combined[$templateId])) {
                $combined[$templateId] += $score * 0.4;  // 项目协同过滤权重40%
            } else {
                $combined[$templateId] = $score * 0.4;
            }
        }

        arsort($combined);
        return array_slice($combined, 0, $limit, true);
    }

    /**
     * 计算多样性分数
     *
     * @param array $templates 模板列表
     * @return float 多样性分数 (0-1)
     */
    public static function calculateDiversityScore(array $templates): float
    {
        if (count($templates) <= 1) {
            return 1.0;
        }

        $types = array_column($templates, 'type');
        $categories = array_column($templates, 'category');
        $styles = array_column($templates, 'style');

        $typeUnique = count(array_unique($types)) / count($types);
        $categoryUnique = count(array_unique($categories)) / count($categories);
        $styleUnique = count(array_unique($styles)) / count($styles);

        return ($typeUnique + $categoryUnique + $styleUnique) / 3;
    }
}
