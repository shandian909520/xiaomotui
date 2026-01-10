<?php
declare(strict_types=1);

namespace app\controller;

use app\service\RecommendationService;
use app\service\RecommendationEngine;
use app\service\UserBehaviorTracker;
use app\service\RecommendationEvaluator;
use app\model\RecommendationCache;
use think\Response;
use think\facade\Log;

/**
 * 推荐系统控制器
 */
class Recommendation extends BaseController
{
    /**
     * RecommendationService实例
     */
    protected RecommendationService $recommendationService;

    /**
     * 初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->recommendationService = new RecommendationService();
    }

    /**
     * 获取推荐列表
     * GET /api/recommendation/list
     *
     * @return Response
     */
    public function index(): Response
    {
        try {
            $params = [
                'user_id' => $this->request->param('user_id/d', $this->userId ?? null),
                'merchant_id' => $this->request->param('merchant_id/d', $this->merchantId ?? null),
                'type' => $this->request->param('type', null),
                'limit' => $this->request->param('limit/d', 10),
                'algorithm' => $this->request->param('algorithm', 'hybrid'),
                'context' => $this->request->param('context/a', []),
            ];

            $result = $this->recommendationService->getRecommendations($params);

            // 记录用户行为
            if (isset($params['user_id']) && $params['user_id']) {
                UserBehaviorTracker::track($params['user_id'], UserBehaviorTracker::ACTION_VIEW, [
                    'type' => 'recommendation',
                    'algorithm' => $result['algorithm'],
                    'count' => $result['count'],
                ]);
            }

            return $this->success($result, '获取推荐成功');

        } catch (\Exception $e) {
            Log::error('获取推荐列表失败: ' . $e->getMessage(), [
                'params' => $params ?? [],
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('获取推荐失败：' . $e->getMessage());
        }
    }

    /**
     * 批量获取推荐
     * POST /api/recommendation/batch
     *
     * @return Response
     */
    public function batch(): Response
    {
        try {
            $batchParams = $this->request->param('batch/a', []);

            if (empty($batchParams)) {
                return $this->error('批量参数不能为空', 400);
            }

            $results = $this->recommendationService->batchGetRecommendations($batchParams);

            return $this->success([
                'count' => count($results),
                'results' => $results,
            ], '批量获取推荐成功');

        } catch (\Exception $e) {
            Log::error('批量获取推荐失败: ' . $e->getMessage());
            return $this->error('批量获取推荐失败：' . $e->getMessage());
        }
    }

    /**
     * 获取用户画像
     * GET /api/recommendation/profile
     *
     * @return Response
     */
    public function profile(): Response
    {
        try {
            $userId = $this->request->param('user_id/d', $this->userId ?? null);

            if (!$userId) {
                return $this->error('用户ID不能为空', 400);
            }

            $profile = UserBehaviorTracker::getUserProfile($userId);
            $activityScore = UserBehaviorTracker::getUserActivityScore($userId);
            $preferenceTags = UserBehaviorTracker::getUserPreferenceTags($userId, 10);

            return $this->success([
                'profile' => $profile,
                'activity_score' => $activityScore,
                'preference_tags' => $preferenceTags,
            ], '获取用户画像成功');

        } catch (\Exception $e) {
            Log::error('获取用户画像失败: ' . $e->getMessage());
            return $this->error('获取用户画像失败：' . $e->getMessage());
        }
    }

    /**
     * 获取模板相似度
     * GET /api/recommendation/similarity
     *
     * @return Response
     */
    public function similarity(): Response
    {
        try {
            $templateId1 = $this->request->param('template_id1/d');
            $templateId2 = $this->request->param('template_id2/d');

            if (!$templateId1 || !$templateId2) {
                return $this->error('模板ID不能为空', 400);
            }

            $similarity = RecommendationEngine::calculateTemplateSimilarity($templateId1, $templateId2);

            return $this->success([
                'template_id1' => $templateId1,
                'template_id2' => $templateId2,
                'similarity' => round($similarity, 4),
            ], '获取相似度成功');

        } catch (\Exception $e) {
            Log::error('获取模板相似度失败: ' . $e->getMessage());
            return $this->error('获取模板相似度失败：' . $e->getMessage());
        }
    }

    /**
     * 获取用户相似度
     * GET /api/recommendation/user-similarity
     *
     * @return Response
     */
    public function userSimilarity(): Response
    {
        try {
            $userId1 = $this->request->param('user_id1/d');
            $userId2 = $this->request->param('user_id2/d');

            if (!$userId1 || !$userId2) {
                return $this->error('用户ID不能为空', 400);
            }

            $similarity = RecommendationEngine::calculateUserSimilarity($userId1, $userId2);

            return $this->success([
                'user_id1' => $userId1,
                'user_id2' => $userId2,
                'similarity' => round($similarity, 4),
            ], '获取相似度成功');

        } catch (\Exception $e) {
            Log::error('获取用户相似度失败: ' . $e->getMessage());
            return $this->error('获取用户相似度失败：' . $e->getMessage());
        }
    }

    /**
     * 获取推荐评估报告
     * GET /api/recommendation/evaluation
     *
     * @return Response
     */
    public function evaluation(): Response
    {
        try {
            $userId = $this->request->param('user_id/d', $this->userId ?? null);
            $days = $this->request->param('days/d', 7);

            if (!$userId) {
                return $this->error('用户ID不能为空', 400);
            }

            // 获取最近的推荐缓存
            $cache = RecommendationCache::where('user_id', $userId)
                ->order('create_time', 'desc')
                ->find();

            if (!$cache) {
                return $this->error('未找到推荐记录', 404);
            }

            $recommendedIds = array_column($cache->recommendations, 'id');
            $report = RecommendationEvaluator::getEvaluationReport(
                $userId,
                $recommendedIds,
                $cache->recommendations,
                $days
            );

            return $this->success($report, '获取评估报告成功');

        } catch (\Exception $e) {
            Log::error('获取评估报告失败: ' . $e->getMessage());
            return $this->error('获取评估报告失败：' . $e->getMessage());
        }
    }

    /**
     * 获取算法对比报告
     * GET /api/recommendation/algorithm-comparison
     *
     * @return Response
     */
    public function algorithmComparison(): Response
    {
        try {
            $userId = $this->request->param('user_id/d', $this->userId ?? null);
            $days = $this->request->param('days/d', 30);

            if (!$userId) {
                return $this->error('用户ID不能为空', 400);
            }

            $comparison = RecommendationEvaluator::getAlgorithmComparison($userId, $days);

            return $this->success([
                'user_id' => $userId,
                'period' => $days . ' days',
                'comparison' => $comparison,
            ], '获取算法对比报告成功');

        } catch (\Exception $e) {
            Log::error('获取算法对比报告失败: ' . $e->getMessage());
            return $this->error('获取算法对比报告失败：' . $e->getMessage());
        }
    }

    /**
     * A/B测试分析
     * GET /api/recommendation/ab-test
     *
     * @return Response
     */
    public function abTest(): Response
    {
        try {
            $algorithmA = $this->request->param('algorithm_a', 'hybrid');
            $algorithmB = $this->request->param('algorithm_b', 'popularity');
            $days = $this->request->param('days/d', 30);

            $analysis = RecommendationEvaluator::abTestAnalysis($algorithmA, $algorithmB, $days);

            return $this->success($analysis, 'A/B测试分析成功');

        } catch (\Exception $e) {
            Log::error('A/B测试分析失败: ' . $e->getMessage());
            return $this->error('A/B测试分析失败：' . $e->getMessage());
        }
    }

    /**
     * 获取缓存统计
     * GET /api/recommendation/cache-stats
     *
     * @return Response
     */
    public function cacheStats(): Response
    {
        try {
            $stats = RecommendationCache::getCacheStats();

            return $this->success($stats, '获取缓存统计成功');

        } catch (\Exception $e) {
            Log::error('获取缓存统计失败: ' . $e->getMessage());
            return $this->error('获取缓存统计失败：' . $e->getMessage());
        }
    }

    /**
     * 清除缓存
     * POST /api/recommendation/clear-cache
     *
     * @return Response
     */
    public function clearCache(): Response
    {
        try {
            $cacheKey = $this->request->param('cache_key', null);
            $userId = $this->request->param('user_id/d', null);
            $merchantId = $this->request->param('merchant_id/d', null);

            $cleared = 0;

            if ($cacheKey) {
                $cleared = $this->recommendationService->clearCache($cacheKey);
            } elseif ($userId) {
                $cleared = RecommendationCache::clearUserCache($userId);
                UserBehaviorTracker::clearUserProfile($userId);
            } elseif ($merchantId) {
                $cleared = RecommendationCache::clearMerchantCache($merchantId);
            } else {
                // 清除过期缓存
                $cleared = RecommendationCache::clearExpiredCache();
            }

            return $this->success([
                'cleared' => $cleared,
            ], '清除缓存成功');

        } catch (\Exception $e) {
            Log::error('清除缓存失败: ' . $e->getMessage());
            return $this->error('清除缓存失败：' . $e->getMessage());
        }
    }

    /**
     * 记录用户行为
     * POST /api/recommendation/track
     *
     * @return Response
     */
    public function track(): Response
    {
        try {
            $userId = $this->request->param('user_id/d', $this->userId ?? null);
            $action = $this->request->param('action', '');
            $data = $this->request->param('data/a', []);

            if (!$userId || !$action) {
                return $this->error('用户ID和行为类型不能为空', 400);
            }

            $result = UserBehaviorTracker::track($userId, $action, $data);

            if ($result) {
                return $this->success(null, '行为记录成功');
            } else {
                return $this->error('行为记录失败');
            }

        } catch (\Exception $e) {
            Log::error('记录用户行为失败: ' . $e->getMessage());
            return $this->error('记录用户行为失败：' . $e->getMessage());
        }
    }

    /**
     * 获取推荐覆盖率
     * GET /api/recommendation/coverage
     *
     * @return Response
     */
    public function coverage(): Response
    {
        try {
            $days = $this->request->param('days/d', 30);

            $coverage = RecommendationEvaluator::calculateCoverage($days);

            return $this->success([
                'coverage' => round($coverage, 4),
                'period' => $days . ' days',
            ], '获取覆盖率成功');

        } catch (\Exception $e) {
            Log::error('获取覆盖率失败: ' . $e->getMessage());
            return $this->error('获取覆盖率失败：' . $e->getMessage());
        }
    }
}
