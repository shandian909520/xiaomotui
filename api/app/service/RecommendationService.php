<?php
declare (strict_types = 1);

namespace app\service;

use app\model\ContentTemplate;
use app\model\MaterialRating;
use app\model\MaterialUsageLog;
use app\model\MaterialPerformance;
use app\model\RecommendationCache;
use app\model\User;
use app\model\Merchant;
use think\facade\Config;
use think\facade\Log;

/**
 * 素材推荐服务类
 * 实现多种推荐算法，为用户提供智能化的素材推荐
 */
class RecommendationService
{
    /**
     * 推荐算法类型
     */
    const ALGORITHM_COLLABORATIVE = 'collaborative';      // 协同过滤
    const ALGORITHM_CONTENT_BASED = 'content_based';      // 内容过滤
    const ALGORITHM_POPULARITY = 'popularity';            // 热度排序
    const ALGORITHM_PERSONALIZED = 'personalized';        // 个性化推荐
    const ALGORITHM_HYBRID = 'hybrid';                    // 混合推荐

    /**
     * 配置
     */
    private array $config;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = Config::get('recommendation');
    }

    /**
     * 获取推荐列表（主入口）
     *
     * @param array $params 推荐参数
     * @return array
     */
    public function getRecommendations(array $params): array
    {
        $userId = $params['user_id'] ?? null;
        $merchantId = $params['merchant_id'] ?? null;
        $type = $params['type'] ?? null;
        $limit = $params['limit'] ?? $this->config['default_limit'];
        $algorithm = $params['algorithm'] ?? $this->config['default_algorithm'];
        $context = $params['context'] ?? [];

        // 生成缓存键
        $cacheKey = $this->generateCacheKey($params);

        // 尝试从缓存获取
        if ($this->config['cache']['enabled']) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached) {
                $this->logRecommendation('cache_hit', $algorithm, $cacheKey);
                return $cached;
            }
        }

        // 根据算法执行推荐
        $recommendations = $this->executeRecommendation($algorithm, [
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'type' => $type,
            'limit' => $limit,
            'context' => $context,
        ]);

        // 应用业务规则过滤
        $recommendations = $this->applyBusinessRules($recommendations, $userId, $merchantId);

        // 应用多样性优化
        if ($this->config['diversity']['enabled']) {
            $recommendations = $this->applyDiversity($recommendations, $limit);
        }

        // 限制返回数量
        $recommendations = array_slice($recommendations, 0, $limit);

        // 保存到缓存
        if ($this->config['cache']['enabled']) {
            $this->saveToCache($cacheKey, $recommendations, $algorithm, $userId, $merchantId, $context);
        }

        $this->logRecommendation('success', $algorithm, $cacheKey, count($recommendations));

        return [
            'algorithm' => $algorithm,
            'count' => count($recommendations),
            'recommendations' => $recommendations,
            'cache_key' => $cacheKey,
        ];
    }

    /**
     * 执行推荐算法
     *
     * @param string $algorithm 算法类型
     * @param array $params 参数
     * @return array
     */
    private function executeRecommendation(string $algorithm, array $params): array
    {
        return match($algorithm) {
            self::ALGORITHM_COLLABORATIVE => $this->collaborativeFiltering($params),
            self::ALGORITHM_CONTENT_BASED => $this->contentBasedFiltering($params),
            self::ALGORITHM_POPULARITY => $this->popularityRanking($params),
            self::ALGORITHM_PERSONALIZED => $this->personalizedRecommendation($params),
            self::ALGORITHM_HYBRID => $this->hybridRecommendation($params),
            default => $this->popularityRanking($params),
        };
    }

    /**
     * 协同过滤推荐
     * 基于相似用户的偏好推荐
     *
     * @param array $params 参数
     * @return array
     */
    private function collaborativeFiltering(array $params): array
    {
        $userId = $params['user_id'];
        $type = $params['type'] ?? null;
        $limit = $params['limit'] ?? 20;

        if (!$userId) {
            return $this->popularityRanking($params);
        }

        // 获取相似用户
        $similarUsers = $this->findSimilarUsers($userId, 10);

        if (empty($similarUsers)) {
            return $this->popularityRanking($params);
        }

        // 获取相似用户使用过的模板
        $templateScores = [];
        $userTemplates = $this->getUserUsedTemplates($userId);

        foreach ($similarUsers as $similarUser) {
            $templates = MaterialUsageLog::getUserFrequentTemplates($similarUser['user_id'], 10);

            foreach ($templates as $template) {
                $templateId = $template['template_id'];

                // 排除用户已使用的模板
                if (in_array($templateId, $userTemplates)) {
                    continue;
                }

                $similarity = $similarUser['similarity'];
                $usageCount = $template['usage_count'];

                if (!isset($templateScores[$templateId])) {
                    $templateScores[$templateId] = 0;
                }

                $templateScores[$templateId] += $similarity * $usageCount;
            }
        }

        // 排序并获取模板详情
        arsort($templateScores);
        $templateIds = array_keys(array_slice($templateScores, 0, $limit * 2));

        return $this->getTemplateDetails($templateIds, $type, $limit);
    }

    /**
     * 内容过滤推荐
     * 基于模板内容特征匹配
     *
     * @param array $params 参数
     * @return array
     */
    private function contentBasedFiltering(array $params): array
    {
        $userId = $params['user_id'];
        $merchantId = $params['merchant_id'];
        $type = $params['type'] ?? null;
        $limit = $params['limit'] ?? 20;

        // 获取用户历史偏好
        $userPreferences = $this->getUserPreferences($userId, $merchantId);

        // 构建查询条件
        $query = ContentTemplate::where('status', ContentTemplate::STATUS_ENABLED);

        if ($type) {
            $query->where('type', strtoupper($type));
        }

        // 根据用户偏好筛选
        if (!empty($userPreferences['categories'])) {
            $query->whereIn('category', $userPreferences['categories']);
        }

        if (!empty($userPreferences['styles'])) {
            $query->whereIn('style', $userPreferences['styles']);
        }

        // 排除已使用的模板
        $userTemplates = $this->getUserUsedTemplates($userId);
        if (!empty($userTemplates)) {
            $query->whereNotIn('id', $userTemplates);
        }

        // 排序并限制数量
        $templates = $query->order('usage_count', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return $this->enrichTemplateData($templates);
    }

    /**
     * 热度排序推荐
     * 基于使用次数和效果数据
     *
     * @param array $params 参数
     * @return array
     */
    private function popularityRanking(array $params): array
    {
        $type = $params['type'] ?? null;
        $merchantId = $params['merchant_id'] ?? null;
        $limit = $params['limit'] ?? 20;
        $days = $this->config['popularity']['days'];

        // 获取热门模板
        $topTemplates = MaterialPerformance::getTopTemplatesByUsage($limit * 2, $days);

        if (empty($topTemplates)) {
            // 如果没有统计数据，使用模板的 usage_count
            $query = ContentTemplate::where('status', ContentTemplate::STATUS_ENABLED);

            if ($type) {
                $query->where('type', strtoupper($type));
            }

            if ($merchantId) {
                $query->where(function($q) use ($merchantId) {
                    $q->whereNull('merchant_id')
                      ->whereOr('merchant_id', $merchantId);
                });
            } else {
                $query->whereNull('merchant_id');
            }

            $templates = $query->order('usage_count', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();

            return $this->enrichTemplateData($templates);
        }

        $templateIds = array_column($topTemplates, 'template_id');
        return $this->getTemplateDetails($templateIds, $type, $limit);
    }

    /**
     * 个性化推荐
     * 基于用户历史行为和偏好
     *
     * @param array $params 参数
     * @return array
     */
    private function personalizedRecommendation(array $params): array
    {
        $userId = $params['user_id'];
        $merchantId = $params['merchant_id'];
        $type = $params['type'] ?? null;
        $limit = $params['limit'] ?? 20;

        if (!$userId) {
            return $this->popularityRanking($params);
        }

        // 检查是否为新用户
        if ($this->isNewUser($userId)) {
            return $this->handleColdStart('user', $params);
        }

        $templateScores = [];

        // 1. 基于用户历史行为
        $frequentTemplates = MaterialUsageLog::getUserFrequentTemplates($userId, 20);
        $historyWeight = $this->config['personalized']['history_weight'];

        foreach ($frequentTemplates as $template) {
            $templateId = $template['template_id'];
            $usageCount = $template['usage_count'];

            // 获取相似模板
            $similarTemplates = $this->findSimilarTemplates($templateId, 10);

            foreach ($similarTemplates as $similarTemplate) {
                $similarId = $similarTemplate['template_id'];
                $similarity = $similarTemplate['similarity'];

                if (!isset($templateScores[$similarId])) {
                    $templateScores[$similarId] = 0;
                }

                $templateScores[$similarId] += $historyWeight * $usageCount * $similarity;
            }
        }

        // 2. 基于相似用户
        $similarUsers = $this->findSimilarUsers($userId, 5);
        $similarUsersWeight = $this->config['personalized']['similar_users_weight'];

        foreach ($similarUsers as $similarUser) {
            $templates = MaterialUsageLog::getUserFrequentTemplates($similarUser['user_id'], 10);

            foreach ($templates as $template) {
                $templateId = $template['template_id'];
                $similarity = $similarUser['similarity'];

                if (!isset($templateScores[$templateId])) {
                    $templateScores[$templateId] = 0;
                }

                $templateScores[$templateId] += $similarUsersWeight * $similarity;
            }
        }

        // 排序并获取模板详情
        arsort($templateScores);
        $templateIds = array_keys(array_slice($templateScores, 0, $limit * 2));

        return $this->getTemplateDetails($templateIds, $type, $limit);
    }

    /**
     * 混合推荐
     * 综合多种推荐算法
     *
     * @param array $params 参数
     * @return array
     */
    private function hybridRecommendation(array $params): array
    {
        $limit = $params['limit'] ?? 20;
        $weights = $this->config['weights'];

        // 执行多种算法
        $results = [];

        // 1. 协同过滤
        $collaborative = $this->collaborativeFiltering(array_merge($params, ['limit' => $limit * 2]));
        foreach ($collaborative as $template) {
            $id = $template['id'];
            if (!isset($results[$id])) {
                $results[$id] = ['template' => $template, 'score' => 0];
            }
            $results[$id]['score'] += $weights['similarity'];
        }

        // 2. 热度排序
        $popularity = $this->popularityRanking(array_merge($params, ['limit' => $limit * 2]));
        foreach ($popularity as $idx => $template) {
            $id = $template['id'];
            if (!isset($results[$id])) {
                $results[$id] = ['template' => $template, 'score' => 0];
            }
            // 使用频率权重 + 位置权重
            $positionWeight = 1 - ($idx / count($popularity));
            $results[$id]['score'] += $weights['usage_frequency'] * $positionWeight;
        }

        // 3. 个性化推荐
        if (!empty($params['user_id'])) {
            $personalized = $this->personalizedRecommendation(array_merge($params, ['limit' => $limit * 2]));
            foreach ($personalized as $template) {
                $id = $template['id'];
                if (!isset($results[$id])) {
                    $results[$id] = ['template' => $template, 'score' => 0];
                }
                $results[$id]['score'] += $weights['user_feedback'];
            }
        }

        // 4. 添加效果权重
        foreach ($results as $id => &$result) {
            $performance = $this->getTemplatePerformanceScore($id);
            $result['score'] += $weights['propagation'] * $performance;

            // 时效性权重
            $recencyScore = $this->getTemplateRecencyScore($id);
            $result['score'] += $weights['recency'] * $recencyScore;
        }

        // 排序
        uasort($results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // 提取模板数据
        $templates = array_map(function($item) {
            return $item['template'];
        }, $results);

        return array_values(array_slice($templates, 0, $limit));
    }

    /**
     * 查找相似用户
     *
     * @param int $userId 用户ID
     * @param int $limit 数量限制
     * @return array
     */
    private function findSimilarUsers(int $userId, int $limit = 10): array
    {
        // 获取用户使用的模板
        $userTemplates = MaterialUsageLog::where('user_id', $userId)
            ->field('template_id')
            ->group('template_id')
            ->column('template_id');

        if (empty($userTemplates)) {
            return [];
        }

        // 查找使用了相同模板的其他用户
        $otherUsers = MaterialUsageLog::whereIn('template_id', $userTemplates)
            ->where('user_id', '<>', $userId)
            ->field('user_id, count(*) as common_count')
            ->group('user_id')
            ->order('common_count', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        // 计算相似度
        $userTemplateCount = count($userTemplates);
        $similarUsers = [];

        foreach ($otherUsers as $user) {
            $otherUserId = $user['user_id'];
            $commonCount = $user['common_count'];

            // 简单的相似度计算：公共模板数 / 用户模板总数
            $similarity = $commonCount / $userTemplateCount;

            if ($similarity >= $this->config['collaborative_filtering']['similarity_threshold']) {
                $similarUsers[] = [
                    'user_id' => $otherUserId,
                    'similarity' => $similarity,
                    'common_count' => $commonCount,
                ];
            }
        }

        return $similarUsers;
    }

    /**
     * 查找相似模板
     *
     * @param int $templateId 模板ID
     * @param int $limit 数量限制
     * @return array
     */
    private function findSimilarTemplates(int $templateId, int $limit = 10): array
    {
        $template = ContentTemplate::find($templateId);

        if (!$template) {
            return [];
        }

        // 查找相同类型、分类或风格的模板
        $query = ContentTemplate::where('id', '<>', $templateId)
            ->where('status', ContentTemplate::STATUS_ENABLED);

        // 计算相似度分数
        $similarTemplates = $query->select()->toArray();
        $scored = [];

        foreach ($similarTemplates as $similar) {
            $score = 0;

            // 类型相同
            if ($similar['type'] === $template->type) {
                $score += 0.5;
            }

            // 分类相同
            if ($similar['category'] === $template->category) {
                $score += 0.3;
            }

            // 风格相同
            if ($similar['style'] === $template->style) {
                $score += 0.2;
            }

            if ($score >= $this->config['content_based']['similarity_threshold']) {
                $scored[] = [
                    'template_id' => $similar['id'],
                    'similarity' => $score,
                ];
            }
        }

        // 排序
        usort($scored, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($scored, 0, $limit);
    }

    /**
     * 获取用户使用过的模板ID列表
     *
     * @param int $userId 用户ID
     * @return array
     */
    private function getUserUsedTemplates(int $userId): array
    {
        return MaterialUsageLog::where('user_id', $userId)
            ->field('template_id')
            ->group('template_id')
            ->column('template_id');
    }

    /**
     * 获取用户偏好
     *
     * @param int $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @return array
     */
    private function getUserPreferences(int $userId, ?int $merchantId = null): array
    {
        // 获取用户常用的分类和风格
        $usageLogs = MaterialUsageLog::where('user_id', $userId)
            ->with('template')
            ->order('create_time', 'desc')
            ->limit(50)
            ->select();

        $categories = [];
        $styles = [];
        $types = [];

        foreach ($usageLogs as $log) {
            if ($log->template) {
                $categories[] = $log->template->category;
                $styles[] = $log->template->style;
                $types[] = $log->template->type;
            }
        }

        // 统计频率
        $categoryFreq = array_count_values($categories);
        $styleFreq = array_count_values($styles);
        $typeFreq = array_count_values($types);

        // 排序并获取前几名
        arsort($categoryFreq);
        arsort($styleFreq);
        arsort($typeFreq);

        return [
            'categories' => array_keys(array_slice($categoryFreq, 0, 3)),
            'styles' => array_keys(array_slice($styleFreq, 0, 3)),
            'types' => array_keys(array_slice($typeFreq, 0, 2)),
        ];
    }

    /**
     * 获取模板详情
     *
     * @param array $templateIds 模板ID数组
     * @param string|null $type 类型筛选
     * @param int $limit 数量限制
     * @return array
     */
    private function getTemplateDetails(array $templateIds, ?string $type = null, int $limit = 20): array
    {
        if (empty($templateIds)) {
            return [];
        }

        $query = ContentTemplate::whereIn('id', $templateIds)
            ->where('status', ContentTemplate::STATUS_ENABLED);

        if ($type) {
            $query->where('type', strtoupper($type));
        }

        $templates = $query->limit($limit)->select()->toArray();

        return $this->enrichTemplateData($templates);
    }

    /**
     * 丰富模板数据
     * 添加性能指标、评分等信息
     *
     * @param array $templates 模板数组
     * @return array
     */
    private function enrichTemplateData(array $templates): array
    {
        foreach ($templates as &$template) {
            $templateId = $template['id'];

            // 添加平均评分
            $template['avg_rating'] = MaterialRating::getTemplateAvgRating($templateId);

            // 添加最近7天的性能数据
            $performance = MaterialPerformance::getTemplatePerformance($templateId,
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d')
            );
            $template['performance'] = $performance;

            // 添加推荐分数（如果有）
            $template['recommendation_score'] = $template['recommendation_score'] ?? 0;
        }

        return $templates;
    }

    /**
     * 获取模板的性能分数
     *
     * @param int $templateId 模板ID
     * @return float
     */
    private function getTemplatePerformanceScore(int $templateId): float
    {
        $performance = MaterialPerformance::getTemplatePerformance($templateId,
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d')
        );

        // 综合考虑成功率、评分、转化率
        $successRate = $performance['success_rate'] / 100;
        $rating = $performance['avg_rating'] / 5;
        $conversion = $performance['avg_conversion_rate'] / 100;

        return ($successRate * 0.4 + $rating * 0.3 + $conversion * 0.3);
    }

    /**
     * 获取模板的时效性分数
     *
     * @param int $templateId 模板ID
     * @return float
     */
    private function getTemplateRecencyScore(int $templateId): float
    {
        $template = ContentTemplate::find($templateId);

        if (!$template) {
            return 0.0;
        }

        // 计算创建时间到现在的天数
        $createTime = strtotime($template->create_time);
        $now = time();
        $days = ($now - $createTime) / 86400;

        // 使用衰减函数计算分数
        $decayFactor = $this->config['popularity']['decay_factor'];
        $score = pow($decayFactor, $days / 30); // 以30天为单位衰减

        return $score;
    }

    /**
     * 应用业务规则过滤
     *
     * @param array $templates 模板数组
     * @param int|null $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @return array
     */
    private function applyBusinessRules(array $templates, ?int $userId = null, ?int $merchantId = null): array
    {
        $rules = $this->config['business_rules'];

        return array_filter($templates, function($template) use ($rules, $userId) {
            // 只推荐已启用的模板
            if ($rules['only_enabled'] && $template['status'] != ContentTemplate::STATUS_ENABLED) {
                return false;
            }

            // 最小评分要求
            if ($rules['min_rating'] > 0) {
                $avgRating = $template['avg_rating'] ?? 0;
                if ($avgRating < $rules['min_rating']) {
                    return false;
                }
            }

            // 排除最近使用的模板
            if ($rules['exclude_recent_days'] > 0 && $userId) {
                $recentDate = date('Y-m-d H:i:s', strtotime("-{$rules['exclude_recent_days']} days"));
                $recentlyUsed = MaterialUsageLog::where('user_id', $userId)
                    ->where('template_id', $template['id'])
                    ->where('create_time', '>=', $recentDate)
                    ->count();

                if ($recentlyUsed > 0) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * 应用多样性优化
     *
     * @param array $templates 模板数组
     * @param int $limit 数量限制
     * @return array
     */
    private function applyDiversity(array $templates, int $limit): array
    {
        if (count($templates) <= $limit) {
            return $templates;
        }

        $diversityRatio = $this->config['diversity']['ratio'];
        $diversityCount = (int)ceil($limit * $diversityRatio);
        $normalCount = $limit - $diversityCount;

        // 取前N个作为正常推荐
        $normalRecommendations = array_slice($templates, 0, $normalCount);

        // 从剩余中选择多样化的模板
        $remaining = array_slice($templates, $normalCount);
        $diverseRecommendations = $this->selectDiverseTemplates($remaining, $diversityCount, $normalRecommendations);

        return array_merge($normalRecommendations, $diverseRecommendations);
    }

    /**
     * 选择多样化的模板
     *
     * @param array $templates 模板数组
     * @param int $count 数量
     * @param array $existing 已选择的模板
     * @return array
     */
    private function selectDiverseTemplates(array $templates, int $count, array $existing): array
    {
        $selected = [];
        $existingTypes = array_column($existing, 'type');
        $existingCategories = array_column($existing, 'category');
        $existingStyles = array_column($existing, 'style');

        foreach ($templates as $template) {
            if (count($selected) >= $count) {
                break;
            }

            // 优先选择类型、分类、风格不同的模板
            $typeScore = in_array($template['type'], $existingTypes) ? 0 : 1;
            $categoryScore = in_array($template['category'], $existingCategories) ? 0 : 1;
            $styleScore = in_array($template['style'], $existingStyles) ? 0 : 1;

            $diversityScore = $typeScore + $categoryScore + $styleScore;

            if ($diversityScore > 0) {
                $selected[] = $template;
                $existingTypes[] = $template['type'];
                $existingCategories[] = $template['category'];
                $existingStyles[] = $template['style'];
            }
        }

        // 如果不够，随机选择剩余的
        if (count($selected) < $count) {
            $remaining = array_diff_key($templates, $selected);
            $needed = $count - count($selected);
            $randomKeys = array_rand($remaining, min($needed, count($remaining)));

            if (!is_array($randomKeys)) {
                $randomKeys = [$randomKeys];
            }

            foreach ($randomKeys as $key) {
                $selected[] = $remaining[$key];
            }
        }

        return $selected;
    }

    /**
     * 冷启动处理
     *
     * @param string $type 类型：user（新用户）或 template（新模板）
     * @param array $params 参数
     * @return array
     */
    private function handleColdStart(string $type, array $params): array
    {
        if ($type === 'user') {
            $strategy = $this->config['cold_start']['new_user_strategy'];

            return match($strategy) {
                'hot' => $this->popularityRanking($params),
                'random' => $this->randomRecommendation($params),
                'default' => $this->defaultTemplateRecommendation($params),
                default => $this->popularityRanking($params),
            };
        }

        return $this->popularityRanking($params);
    }

    /**
     * 随机推荐
     *
     * @param array $params 参数
     * @return array
     */
    private function randomRecommendation(array $params): array
    {
        $type = $params['type'] ?? null;
        $limit = $params['limit'] ?? 20;

        $query = ContentTemplate::where('status', ContentTemplate::STATUS_ENABLED);

        if ($type) {
            $query->where('type', strtoupper($type));
        }

        $templates = $query->orderRaw('RAND()')
            ->limit($limit)
            ->select()
            ->toArray();

        return $this->enrichTemplateData($templates);
    }

    /**
     * 默认模板推荐
     *
     * @param array $params 参数
     * @return array
     */
    private function defaultTemplateRecommendation(array $params): array
    {
        $type = $params['type'] ?? null;
        $limit = $params['limit'] ?? 20;

        // 获取系统默认模板
        $query = ContentTemplate::where('status', ContentTemplate::STATUS_ENABLED)
            ->whereNull('merchant_id')
            ->where('is_public', ContentTemplate::PUBLIC_YES);

        if ($type) {
            $query->where('type', strtoupper($type));
        }

        $templates = $query->order('usage_count', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return $this->enrichTemplateData($templates);
    }

    /**
     * 判断是否为新用户
     *
     * @param int $userId 用户ID
     * @return bool
     */
    private function isNewUser(int $userId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return true;
        }

        $days = $this->config['cold_start']['new_user_days'];
        $createTime = strtotime($user->create_time);
        $threshold = time() - ($days * 86400);

        return $createTime > $threshold;
    }

    /**
     * 生成缓存键
     *
     * @param array $params 参数
     * @return string
     */
    private function generateCacheKey(array $params): string
    {
        return RecommendationCache::generateCacheKey($params);
    }

    /**
     * 从缓存获取
     *
     * @param string $cacheKey 缓存键
     * @return array|null
     */
    private function getFromCache(string $cacheKey): ?array
    {
        $cache = RecommendationCache::getCache($cacheKey);

        if ($cache && !$cache->isExpired()) {
            return [
                'algorithm' => $cache->algorithm,
                'count' => count($cache->recommendations),
                'recommendations' => $cache->recommendations,
                'cache_key' => $cacheKey,
                'from_cache' => true,
            ];
        }

        return null;
    }

    /**
     * 保存到缓存
     *
     * @param string $cacheKey 缓存键
     * @param array $recommendations 推荐结果
     * @param string $algorithm 算法
     * @param int|null $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @param array $context 上下文
     * @return bool
     */
    private function saveToCache(
        string $cacheKey,
        array $recommendations,
        string $algorithm,
        ?int $userId = null,
        ?int $merchantId = null,
        array $context = []
    ): bool {
        $ttl = $this->config['cache']['ttl'];

        $cache = RecommendationCache::setCache([
            'cache_key' => $cacheKey,
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'context' => $context,
            'recommendations' => $recommendations,
            'algorithm' => $algorithm,
        ], $ttl);

        return $cache !== null;
    }

    /**
     * 记录推荐日志
     *
     * @param string $event 事件
     * @param string $algorithm 算法
     * @param string $cacheKey 缓存键
     * @param int $count 数量
     */
    private function logRecommendation(string $event, string $algorithm, string $cacheKey, int $count = 0): void
    {
        if (!$this->config['logging']['enabled']) {
            return;
        }

        $level = $this->config['logging']['level'];

        $message = "推荐事件: {$event}, 算法: {$algorithm}, 缓存键: {$cacheKey}, 数量: {$count}";

        match($level) {
            'debug' => Log::debug($message),
            'info' => Log::info($message),
            'warning' => Log::warning($message),
            'error' => Log::error($message),
            default => Log::info($message),
        };
    }

    /**
     * 清除缓存
     *
     * @param string|null $cacheKey 缓存键（为空则清除所有过期缓存）
     * @return int
     */
    public function clearCache(?string $cacheKey = null): int
    {
        if ($cacheKey) {
            return RecommendationCache::deleteCache($cacheKey) ? 1 : 0;
        }

        return RecommendationCache::clearExpiredCache();
    }

    /**
     * 批量获取推荐
     *
     * @param array $batchParams 批量参数
     * @return array
     */
    public function batchGetRecommendations(array $batchParams): array
    {
        $results = [];

        foreach ($batchParams as $params) {
            try {
                $results[] = $this->getRecommendations($params);
            } catch (\Exception $e) {
                Log::error('批量推荐失败: ' . $e->getMessage(), $params);
                $results[] = [
                    'error' => $e->getMessage(),
                    'params' => $params,
                ];
            }
        }

        return $results;
    }
}