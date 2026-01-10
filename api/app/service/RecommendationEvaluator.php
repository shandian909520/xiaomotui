<?php
declare(strict_types=1);

namespace app\service;

use app\model\MaterialUsageLog;
use app\model\MaterialRating;
use app\model\RecommendationCache;
use think\facade\Db;

/**
 * 推荐效果评估服务
 * 评估推荐系统的性能和效果
 */
class RecommendationEvaluator
{
    /**
     * 计算准确率（Precision）
     * 推荐的模板中用户实际使用的比例
     *
     * @param int $userId 用户ID
     * @param array $recommendedIds 推荐的模板ID列表
     * @param int $days 统计天数
     * @return float
     */
    public static function calculatePrecision(int $userId, array $recommendedIds, int $days = 7): float
    {
        if (empty($recommendedIds)) {
            return 0.0;
        }

        // 获取用户在推荐后实际使用的模板
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $actualUsed = MaterialUsageLog::where('user_id', $userId)
            ->where('create_time', '>=', $startDate)
            ->whereIn('template_id', $recommendedIds)
            ->group('template_id')
            ->column('template_id');

        return count($actualUsed) / count($recommendedIds);
    }

    /**
     * 计算召回率（Recall）
     * 用户实际使用的模板中被推荐的比例
     *
     * @param int $userId 用户ID
     * @param array $recommendedIds 推荐的模板ID列表
     * @param int $days 统计天数
     * @return float
     */
    public static function calculateRecall(int $userId, array $recommendedIds, int $days = 7): float
    {
        // 获取用户实际使用的所有模板
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $actualUsedAll = MaterialUsageLog::where('user_id', $userId)
            ->where('create_time', '>=', $startDate)
            ->group('template_id')
            ->column('template_id');

        if (empty($actualUsedAll)) {
            return 0.0;
        }

        // 计算交集
        $intersection = array_intersect($recommendedIds, $actualUsedAll);

        return count($intersection) / count($actualUsedAll);
    }

    /**
     * 计算F1分数
     * Precision和Recall的调和平均数
     *
     * @param float $precision 准确率
     * @param float $recall 召回率
     * @return float
     */
    public static function calculateF1Score(float $precision, float $recall): float
    {
        if ($precision + $recall == 0) {
            return 0.0;
        }

        return 2 * ($precision * $recall) / ($precision + $recall);
    }

    /**
     * 计算覆盖率（Coverage）
     * 推荐系统能够推荐的模板占总模板的比例
     *
     * @param int $days 统计天数
     * @return float
     */
    public static function calculateCoverage(int $days = 30): float
    {
        // 获取推荐缓存中出现过的所有模板
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $recommendedTemplates = [];
        $caches = RecommendationCache::where('create_time', '>=', $startDate)
            ->select();

        foreach ($caches as $cache) {
            $recommendations = $cache->recommendations;
            foreach ($recommendations as $rec) {
                if (isset($rec['id'])) {
                    $recommendedTemplates[$rec['id']] = true;
                }
            }
        }

        // 获取总模板数
        $totalTemplates = \app\model\ContentTemplate::where('status', 'enabled')->count();

        if ($totalTemplates == 0) {
            return 0.0;
        }

        return count($recommendedTemplates) / $totalTemplates;
    }

    /**
     * 计算多样性（Diversity）
     * 推荐结果的多样性程度
     *
     * @param array $recommendations 推荐列表
     * @return float
     */
    public static function calculateDiversity(array $recommendations): float
    {
        return RecommendationEngine::calculateDiversityScore($recommendations);
    }

    /**
     * 计算新颖性（Novelty）
     * 推荐的模板中用户之前未使用过的比例
     *
     * @param int $userId 用户ID
     * @param array $recommendedIds 推荐的模板ID列表
     * @return float
     */
    public static function calculateNovelty(int $userId, array $recommendedIds): float
    {
        if (empty($recommendedIds)) {
            return 0.0;
        }

        // 获取用户历史使用的模板
        $usedTemplates = MaterialUsageLog::where('user_id', $userId)
            ->group('template_id')
            ->column('template_id');

        // 计算未使用过的模板数量
        $novelTemplates = array_diff($recommendedIds, $usedTemplates);

        return count($novelTemplates) / count($recommendedIds);
    }

    /**
     * 计算用户满意度
     * 基于用户评分和反馈
     *
     * @param int $userId 用户ID
     * @param array $recommendedIds 推荐的模板ID列表
     * @param int $days 统计天数
     * @return float
     */
    public static function calculateSatisfaction(int $userId, array $recommendedIds, int $days = 7): float
    {
        if (empty($recommendedIds)) {
            return 0.0;
        }

        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // 获取用户对推荐模板的评分
        $ratings = MaterialRating::where('user_id', $userId)
            ->whereIn('template_id', $recommendedIds)
            ->where('create_time', '>=', $startDate)
            ->avg('rating');

        if (!$ratings) {
            return 0.0;
        }

        // 转换为0-1范围
        return $ratings / 5.0;
    }

    /**
     * 计算点击率（CTR）
     * 推荐后用户点击使用的比例
     *
     * @param int $userId 用户ID
     * @param array $recommendedIds 推荐的模板ID列表
     * @param int $days 统计天数
     * @return float
     */
    public static function calculateCTR(int $userId, array $recommendedIds, int $days = 7): float
    {
        if (empty($recommendedIds)) {
            return 0.0;
        }

        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // 统计点击使用的模板数
        $clicked = MaterialUsageLog::where('user_id', $userId)
            ->whereIn('template_id', $recommendedIds)
            ->where('create_time', '>=', $startDate)
            ->group('template_id')
            ->count();

        return $clicked / count($recommendedIds);
    }

    /**
     * 获取综合评估报告
     *
     * @param int $userId 用户ID
     * @param array $recommendedIds 推荐的模板ID列表
     * @param array $recommendations 推荐详情（包含模板信息）
     * @param int $days 统计天数
     * @return array
     */
    public static function getEvaluationReport(
        int $userId,
        array $recommendedIds,
        array $recommendations = [],
        int $days = 7
    ): array {
        $precision = self::calculatePrecision($userId, $recommendedIds, $days);
        $recall = self::calculateRecall($userId, $recommendedIds, $days);
        $f1Score = self::calculateF1Score($precision, $recall);
        $novelty = self::calculateNovelty($userId, $recommendedIds);
        $ctr = self::calculateCTR($userId, $recommendedIds, $days);
        $satisfaction = self::calculateSatisfaction($userId, $recommendedIds, $days);

        $report = [
            'metrics' => [
                'precision' => round($precision, 4),
                'recall' => round($recall, 4),
                'f1_score' => round($f1Score, 4),
                'novelty' => round($novelty, 4),
                'ctr' => round($ctr, 4),
                'satisfaction' => round($satisfaction, 4),
            ],
            'user_id' => $userId,
            'recommended_count' => count($recommendedIds),
            'evaluation_period' => $days . ' days',
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // 如果提供了推荐详情，计算多样性
        if (!empty($recommendations)) {
            $diversity = self::calculateDiversity($recommendations);
            $report['metrics']['diversity'] = round($diversity, 4);
        }

        // 计算综合得分
        $report['overall_score'] = round(
            ($precision * 0.25 +
             $recall * 0.15 +
             $f1Score * 0.20 +
             $novelty * 0.10 +
             $ctr * 0.15 +
             $satisfaction * 0.15),
            4
        );

        return $report;
    }

    /**
     * 获取算法对比报告
     * 对比不同推荐算法的效果
     *
     * @param int $userId 用户ID
     * @param int $days 统计天数
     * @return array
     */
    public static function getAlgorithmComparison(int $userId, int $days = 30): array
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // 获取不同算法的推荐缓存
        $caches = RecommendationCache::where('user_id', $userId)
            ->where('create_time', '>=', $startDate)
            ->select();

        $algorithmStats = [];

        foreach ($caches as $cache) {
            $algorithm = $cache->algorithm;

            if (!isset($algorithmStats[$algorithm])) {
                $algorithmStats[$algorithm] = [
                    'count' => 0,
                    'total_recommendations' => 0,
                    'template_ids' => [],
                ];
            }

            $algorithmStats[$algorithm]['count']++;
            $algorithmStats[$algorithm]['total_recommendations'] += count($cache->recommendations);

            foreach ($cache->recommendations as $rec) {
                if (isset($rec['id'])) {
                    $algorithmStats[$algorithm]['template_ids'][] = $rec['id'];
                }
            }
        }

        // 计算每个算法的指标
        $comparison = [];

        foreach ($algorithmStats as $algorithm => $stats) {
            $templateIds = array_unique($stats['template_ids']);

            $comparison[$algorithm] = [
                'algorithm' => $algorithm,
                'usage_count' => $stats['count'],
                'avg_recommendations' => round($stats['total_recommendations'] / max(1, $stats['count']), 2),
                'unique_templates' => count($templateIds),
                'precision' => round(self::calculatePrecision($userId, $templateIds, $days), 4),
                'recall' => round(self::calculateRecall($userId, $templateIds, $days), 4),
                'novelty' => round(self::calculateNovelty($userId, $templateIds), 4),
                'ctr' => round(self::calculateCTR($userId, $templateIds, $days), 4),
            ];
        }

        return $comparison;
    }

    /**
     * A/B测试结果分析
     *
     * @param string $algorithmA 算法A
     * @param string $algorithmB 算法B
     * @param int $days 统计天数
     * @return array
     */
    public static function abTestAnalysis(string $algorithmA, string $algorithmB, int $days = 30): array
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // 获取使用算法A的用户数据
        $cachesA = RecommendationCache::where('algorithm', $algorithmA)
            ->where('create_time', '>=', $startDate)
            ->select();

        // 获取使用算法B的用户数据
        $cachesB = RecommendationCache::where('algorithm', $algorithmB)
            ->where('create_time', '>=', $startDate)
            ->select();

        $groupA = self::aggregateGroupMetrics($cachesA, $days);
        $groupB = self::aggregateGroupMetrics($cachesB, $days);

        return [
            'algorithm_a' => $algorithmA,
            'algorithm_b' => $algorithmB,
            'group_a' => $groupA,
            'group_b' => $groupB,
            'improvement' => [
                'precision' => round(($groupA['avg_precision'] - $groupB['avg_precision']) / max(0.01, $groupB['avg_precision']) * 100, 2) . '%',
                'recall' => round(($groupA['avg_recall'] - $groupB['avg_recall']) / max(0.01, $groupB['avg_recall']) * 100, 2) . '%',
                'ctr' => round(($groupA['avg_ctr'] - $groupB['avg_ctr']) / max(0.01, $groupB['avg_ctr']) * 100, 2) . '%',
            ],
            'period' => $days . ' days',
        ];
    }

    /**
     * 聚合组指标
     *
     * @param \think\Collection $caches 缓存集合
     * @param int $days 统计天数
     * @return array
     */
    private static function aggregateGroupMetrics($caches, int $days): array
    {
        $userMetrics = [];

        foreach ($caches as $cache) {
            $userId = $cache->user_id;
            $templateIds = array_column($cache->recommendations, 'id');

            if (!isset($userMetrics[$userId])) {
                $userMetrics[$userId] = [
                    'precision' => [],
                    'recall' => [],
                    'ctr' => [],
                ];
            }

            $userMetrics[$userId]['precision'][] = self::calculatePrecision($userId, $templateIds, $days);
            $userMetrics[$userId]['recall'][] = self::calculateRecall($userId, $templateIds, $days);
            $userMetrics[$userId]['ctr'][] = self::calculateCTR($userId, $templateIds, $days);
        }

        // 计算平均值
        $avgPrecision = 0;
        $avgRecall = 0;
        $avgCtr = 0;
        $userCount = count($userMetrics);

        foreach ($userMetrics as $metrics) {
            $avgPrecision += array_sum($metrics['precision']) / max(1, count($metrics['precision']));
            $avgRecall += array_sum($metrics['recall']) / max(1, count($metrics['recall']));
            $avgCtr += array_sum($metrics['ctr']) / max(1, count($metrics['ctr']));
        }

        return [
            'user_count' => $userCount,
            'avg_precision' => $userCount > 0 ? round($avgPrecision / $userCount, 4) : 0,
            'avg_recall' => $userCount > 0 ? round($avgRecall / $userCount, 4) : 0,
            'avg_ctr' => $userCount > 0 ? round($avgCtr / $userCount, 4) : 0,
        ];
    }
}
