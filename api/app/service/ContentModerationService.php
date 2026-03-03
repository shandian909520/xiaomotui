<?php
declare(strict_types=1);

namespace app\service;

use app\service\content_moderation\ModerationProviderFactory;
use app\service\content_moderation\ContentModerationJob;
use app\service\content_moderation\ModerationProviderInterface;
use think\facade\Config;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Db;

/**
 * 内容审核服务 - 重构版
 * 支持多服务商、降级策略、异步审核、结果缓存
 *
 * @author AI Assistant
 * @since 2026-01-11
 */
class ContentModerationService
{
    // ========== 违规类型常量 ==========
    const VIOLATION_PORN = 'PORN';           // 色情
    const VIOLATION_POLITICS = 'POLITICS';   // 政治
    const VIOLATION_VIOLENCE = 'VIOLENCE';   // 暴力
    const VIOLATION_AD = 'AD';               // 广告
    const VIOLATION_ILLEGAL = 'ILLEGAL';     // 违法
    const VIOLATION_ABUSE = 'ABUSE';         // 辱骂
    const VIOLATION_TERRORISM = 'TERRORISM'; // 恐怖主义
    const VIOLATION_SPAM = 'SPAM';           // 垃圾信息
    const VIOLATION_OTHER = 'OTHER';         // 其他

    // ========== 严重程度常量 ==========
    const SEVERITY_HIGH = 'HIGH';
    const SEVERITY_MEDIUM = 'MEDIUM';
    const SEVERITY_LOW = 'LOW';

    // ========== 审核建议常量 ==========
    const SUGGESTION_PASS = 'pass';
    const SUGGESTION_REVIEW = 'review';
    const SUGGESTION_REJECT = 'reject';

    /**
     * 检查素材内容(主入口)
     *
     * @param array $material 素材数据
     * @param bool $async 是否异步处理
     * @return array
     */
    public function checkMaterial(array $material, bool $async = false): array
    {
        $materialType = strtoupper($material['type'] ?? 'TEXT');
        $content = $material['content'] ?? $material['file_url'] ?? '';
        $materialId = $material['id'] ?? null;

        if (empty($content)) {
            return $this->formatEmptyResult();
        }

        // 如果启用异步且素材较大,使用异步处理
        if ($async && $this->shouldUseAsync($material)) {
            return $this->checkAsync($materialType, $content, $materialId, $material);
        }

        // 同步处理
        return $this->checkSync($materialType, $content, $material);
    }

    /**
     * 检查文本内容
     *
     * @param string $text 文本内容
     * @param array $options 选项
     * @return array
     */
    public function checkText(string $text, array $options = []): array
    {
        if (empty($text)) {
            return $this->formatEmptyResult();
        }

        // 检查缓存
        $cacheKey = $this->getCacheKey('text', $text);
        if ($this->isCacheEnabled()) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('文本审核命中缓存', ['text_length' => strlen($text)]);
                return $cached;
            }
        }

        // 1. 本地关键词检测
        $localResult = $this->checkLocalText($text);
        if ($localResult['has_violation']) {
            $result = $this->mergeResults([], $localResult);
        } else {
            // 2. 第三方API检测
            $result = $this->checkWithProviders('text', $text, $options);
        }

        // 缓存结果
        if ($this->isCacheEnabled()) {
            Cache::set($cacheKey, $result, $this->getCacheTTL());
        }

        return $result;
    }

    /**
     * 检查图片内容
     *
     * @param string $imageUrl 图片URL或Base64
     * @param array $options 选项
     * @return array
     */
    public function checkImage(string $imageUrl, array $options = []): array
    {
        if (empty($imageUrl)) {
            return $this->formatEmptyResult();
        }

        // 检查缓存
        $cacheKey = $this->getCacheKey('image', $imageUrl);
        if ($this->isCacheEnabled()) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('图片审核命中缓存', ['url' => substr($imageUrl, 0, 100)]);
                return $cached;
            }
        }

        // 调用服务商API
        $result = $this->checkWithProviders('image', $imageUrl, $options);

        // 缓存结果
        if ($this->isCacheEnabled()) {
            Cache::set($cacheKey, $result, $this->getCacheTTL());
        }

        return $result;
    }

    /**
     * 检查视频内容
     *
     * @param string $videoUrl 视频URL
     * @param array $options 选项
     * @return array
     */
    public function checkVideo(string $videoUrl, array $options = []): array
    {
        if (empty($videoUrl)) {
            return $this->formatEmptyResult();
        }

        // 检查缓存
        $cacheKey = $this->getCacheKey('video', $videoUrl);
        if ($this->isCacheEnabled()) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('视频审核命中缓存', ['url' => substr($videoUrl, 0, 100)]);
                return $cached;
            }
        }

        // 调用服务商API
        $result = $this->checkWithProviders('video', $videoUrl, $options);

        // 缓存结果(视频缓存时间更长)
        if ($this->isCacheEnabled()) {
            Cache::set($cacheKey, $result, $this->getCacheTTL() * 7);
        }

        return $result;
    }

    /**
     * 检查音频内容
     *
     * @param string $audioUrl 音频URL
     * @param array $options 选项
     * @return array
     */
    public function checkAudio(string $audioUrl, array $options = []): array
    {
        if (empty($audioUrl)) {
            return $this->formatEmptyResult();
        }

        // 检查缓存
        $cacheKey = $this->getCacheKey('audio', $audioUrl);
        if ($this->isCacheEnabled()) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('音频审核命中缓存', ['url' => substr($audioUrl, 0, 100)]);
                return $cached;
            }
        }

        // 调用服务商API
        $result = $this->checkWithProviders('audio', $audioUrl, $options);

        // 缓存结果
        if ($this->isCacheEnabled()) {
            Cache::set($cacheKey, $result, $this->getCacheTTL());
        }

        return $result;
    }

    /**
     * 使用服务商进行检查(含降级策略)
     *
     * @param string $contentType 内容类型
     * @param string $content 内容
     * @param array $options 选项
     * @return array
     */
    private function checkWithProviders(string $contentType, string $content, array $options = []): array
    {
        $providers = ModerationProviderFactory::getAvailableProviders($contentType);

        if (empty($providers)) {
            Log::warning('没有可用的内容审核服务商', ['type' => $contentType]);
            return $this->formatErrorResult('没有可用的审核服务商');
        }

        $fallbackEnabled = Config::get('content_moderation.fallback.enabled', true);
        $maxAttempts = Config::get('content_moderation.fallback.max_attempts', 3);

        $lastError = null;
        $attempts = 0;

        foreach ($providers as $provider) {
            if ($attempts >= $maxAttempts) {
                break;
            }
            $attempts++;

            try {
                Log::info('使用服务商进行内容审核', [
                    'provider' => $provider->getProviderName(),
                    'type' => $contentType,
                ]);

                // 根据类型调用不同的方法
                $result = match ($contentType) {
                    'text' => $provider->checkText($content, $options),
                    'image' => $provider->checkImage($content, $options),
                    'video' => $provider->checkVideo($content, $options),
                    'audio' => $provider->checkAudio($content, $options),
                    default => throw new \Exception('不支持的内容类型: ' . $contentType),
                };

                // 检查是否出错
                if (isset($result['error'])) {
                    throw new \Exception($result['error']);
                }

                // 成功,记录日志并返回
                Log::info('内容审核成功', [
                    'provider' => $provider->getProviderName(),
                    'pass' => $result['pass'] ?? false,
                    'score' => $result['score'] ?? 0,
                    'suggestion' => $result['suggestion'] ?? 'review',
                ]);

                return $this->normalizeResult($result);

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $providerName = $provider->getProviderName();

                Log::error('服务商内容审核失败', [
                    'provider' => $providerName,
                    'type' => $contentType,
                    'error' => $lastError,
                ]);

                // 如果启用降级,将失败的服务商加入黑名单
                if ($fallbackEnabled) {
                    ModerationProviderFactory::addToBlacklist($providerName);
                }

                // 继续尝试下一个服务商
                continue;
            }
        }

        // 所有服务商都失败了
        Log::error('所有内容审核服务商都失败', [
            'type' => $contentType,
            'last_error' => $lastError,
        ]);

        return $this->formatErrorResult($lastError ?? '未知错误');
    }

    /**
     * 本地文本检查(关键词、正则等)
     *
     * @param string $text 文本内容
     * @return array
     */
    private function checkLocalText(string $text): array
    {
        $violations = [];
        $maxSeverity = self::SEVERITY_LOW;
        $maxConfidence = 0;

        // 1. 关键词检测
        $keywordResult = $this->checkKeywords($text);
        if (!empty($keywordResult['violations'])) {
            $violations = array_merge($violations, $keywordResult['violations']);
            $maxSeverity = $this->getHigherSeverity($maxSeverity, $keywordResult['severity']);
            $maxConfidence = max($maxConfidence, $keywordResult['confidence']);
        }

        // 2. 正则模式检测
        $patternResult = $this->checkPatterns($text);
        if (!empty($patternResult['violations'])) {
            $violations = array_merge($violations, $patternResult['violations']);
            $maxSeverity = $this->getHigherSeverity($maxSeverity, $patternResult['severity']);
            $maxConfidence = max($maxConfidence, $patternResult['confidence']);
        }

        return [
            'has_violation' => !empty($violations),
            'violations' => $violations,
            'severity' => $maxSeverity,
            'confidence' => $maxConfidence,
        ];
    }

    /**
     * 关键词检测
     *
     * @param string $text 文本内容
     * @return array
     */
    protected function checkKeywords(string $text): array
    {
        $violations = [];
        $maxSeverity = self::SEVERITY_LOW;

        try {
            // 从数据库获取关键词
            $keywords = Db::name('violation_keywords')
                ->where('enabled', 1)
                ->select()
                ->toArray();

            foreach ($keywords as $keywordData) {
                $keyword = $keywordData['keyword'];
                $matchType = $keywordData['match_type'];
                $matched = false;

                if ($matchType === 'EXACT') {
                    // 精确匹配
                    $matched = stripos($text, $keyword) !== false;
                } elseif ($matchType === 'FUZZY') {
                    // 模糊匹配(去掉空格、特殊字符)
                    $cleanText = preg_replace('/[\s\p{P}]/u', '', $text);
                    $cleanKeyword = preg_replace('/[\s\p{P}]/u', '', $keyword);
                    $matched = stripos($cleanText, $cleanKeyword) !== false;
                } elseif ($matchType === 'REGEX' && !empty($keywordData['pattern'])) {
                    // 正则匹配
                    $matched = preg_match($keywordData['pattern'], $text) > 0;
                }

                if ($matched) {
                    $violations[] = [
                        'type' => $keywordData['category'],
                        'keyword' => $keyword,
                        'severity' => $keywordData['severity'],
                        'description' => "检测到{$keywordData['category']}关键词: {$keyword}",
                        'match_type' => $matchType,
                        'source' => 'local',
                    ];

                    // 更新命中次数
                    Db::name('violation_keywords')
                        ->where('id', $keywordData['id'])
                        ->inc('hit_count')
                        ->update(['last_hit_time' => date('Y-m-d H:i:s')]);

                    $maxSeverity = $this->getHigherSeverity($maxSeverity, $keywordData['severity']);
                }
            }
        } catch (\Exception $e) {
            Log::error('关键词检测失败', ['error' => $e->getMessage()]);
        }

        return [
            'violations' => $violations,
            'severity' => $maxSeverity,
            'confidence' => empty($violations) ? 0 : 0.9,
        ];
    }

    /**
     * 正则模式检测
     *
     * @param string $text 文本内容
     * @return array
     */
    protected function checkPatterns(string $text): array
    {
        $violations = [];
        $patterns = Config::get('content_moderation.patterns', []);

        foreach ($patterns as $pattern) {
            if (!($pattern['enabled'] ?? true)) {
                continue;
            }

            if (preg_match($pattern['regex'], $text)) {
                $violations[] = [
                    'type' => $pattern['type'],
                    'severity' => $pattern['severity'],
                    'description' => $pattern['description'] ?? '检测到违规内容',
                    'pattern' => $pattern['name'] ?? 'unknown',
                    'source' => 'local',
                ];
            }
        }

        $maxSeverity = empty($violations) ? self::SEVERITY_LOW : self::SEVERITY_MEDIUM;

        return [
            'violations' => $violations,
            'severity' => $maxSeverity,
            'confidence' => empty($violations) ? 0 : 0.85,
        ];
    }

    /**
     * 异步检查
     *
     * @param string $contentType 内容类型
     * @param string $content 内容
     * @param string|null $materialId 素材ID
     * @param array $material 素材数据
     * @return array
     */
    private function checkAsync(
        string $contentType,
        string $content,
        ?string $materialId,
        array $material
    ): array {
        try {
            $provider = ModerationProviderFactory::getDefaultProvider(strtolower($contentType));
            if (!$provider) {
                throw new \Exception('没有可用的审核服务商');
            }

            $taskId = ContentModerationJob::dispatch(
                strtolower($contentType),
                $content,
                $provider->getProviderName(),
                $material['options'] ?? [],
                $materialId
            );

            if (!$taskId) {
                throw new \Exception('创建异步任务失败');
            }

            return [
                'has_violation' => false,
                'violations' => [],
                'severity' => self::SEVERITY_LOW,
                'confidence' => 0,
                'async' => true,
                'task_id' => $taskId,
                'message' => '审核任务已创建,请稍后查看结果',
                'check_time' => date('Y-m-d H:i:s'),
            ];

        } catch (\Exception $e) {
            Log::error('创建异步审核任务失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->formatErrorResult($e->getMessage());
        }
    }

    /**
     * 同步检查
     *
     * @param string $contentType 内容类型
     * @param string $content 内容
     * @param array $material 素材数据
     * @return array
     */
    private function checkSync(string $contentType, string $content, array $material): array
    {
        $options = $material['options'] ?? [];

        $result = match ($contentType) {
            'TEXT' => $this->checkText($content, $options),
            'IMAGE' => $this->checkImage($content, $options),
            'VIDEO' => $this->checkVideo($content, $options),
            'AUDIO' => $this->checkAudio($content, $options),
            default => $this->formatErrorResult('不支持的素材类型: ' . $contentType),
        };

        return $result;
    }

    /**
     * 判断是否应该使用异步处理
     *
     * @param array $material 素材数据
     * @return bool
     */
    private function shouldUseAsync(array $material): bool
    {
        // 检查是否启用异步队列
        if (!Config::get('content_moderation.queue.enabled', false)) {
            return false;
        }

        $type = strtoupper($material['type'] ?? 'TEXT');

        // 视频和音频默认使用异步
        if (in_array($type, ['VIDEO', 'AUDIO'])) {
            return true;
        }

        // 大文件使用异步
        if (($material['file_size'] ?? 0) > 10 * 1024 * 1024) { // 大于10MB
            return true;
        }

        return false;
    }

    /**
     * 标准化服务商返回结果
     *
     * @param array $result 服务商返回结果
     * @return array
     */
    private function normalizeResult(array $result): array
    {
        $pass = $result['pass'] ?? false;
        $score = $result['score'] ?? 0;
        $confidence = $result['confidence'] ?? 0;
        $suggestion = $result['suggestion'] ?? 'review';
        $violations = $result['violations'] ?? [];

        // 转换违规详情
        $formattedViolations = [];
        foreach ($violations as $violation) {
            $formattedViolations[] = [
                'type' => $violation['type'] ?? 'OTHER',
                'type_name' => $violation['type_name'] ?? $this->getViolationTypeName($violation['type'] ?? 'OTHER'),
                'severity' => $violation['severity'] ?? 'MEDIUM',
                'confidence' => $violation['confidence'] ?? 0,
                'description' => $violation['description'] ?? '',
                'source' => $result['provider'] ?? 'unknown',
            ];
        }

        // 计算是否有违规
        $hasViolation = !$pass || !empty($formattedViolations);

        // 确定最高严重程度
        $maxSeverity = self::SEVERITY_LOW;
        foreach ($formattedViolations as $violation) {
            $maxSeverity = $this->getHigherSeverity($maxSeverity, $violation['severity']);
        }

        return [
            'has_violation' => $hasViolation,
            'violations' => $formattedViolations,
            'severity' => $maxSeverity,
            'confidence' => $confidence,
            'score' => $score,
            'suggestion' => $suggestion,
            'provider' => $result['provider'] ?? 'unknown',
            'check_time' => $result['check_time'] ?? date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 合并结果
     *
     * @param array $result1 结果1
     * @param array $result2 结果2
     * @return array
     */
    private function mergeResults(array $result1, array $result2): array
    {
        $violations1 = $result1['violations'] ?? [];
        $violations2 = $result2['violations'] ?? [];
        $mergedViolations = array_merge($violations1, $violations2);

        $severity1 = $result1['severity'] ?? self::SEVERITY_LOW;
        $severity2 = $result2['severity'] ?? self::SEVERITY_LOW;
        $maxSeverity = $this->getHigherSeverity($severity1, $severity2);

        $confidence1 = $result1['confidence'] ?? 0;
        $confidence2 = $result2['confidence'] ?? 0;
        $maxConfidence = max($confidence1, $confidence2);

        return [
            'has_violation' => !empty($mergedViolations),
            'violations' => $mergedViolations,
            'severity' => $maxSeverity,
            'confidence' => $maxConfidence,
        ];
    }

    /**
     * 格式化空结果
     *
     * @return array
     */
    private function formatEmptyResult(): array
    {
        return [
            'has_violation' => false,
            'violations' => [],
            'severity' => self::SEVERITY_LOW,
            'confidence' => 0,
            'score' => 100,
            'suggestion' => 'pass',
            'provider' => 'local',
            'check_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 格式化错误结果
     *
     * @param string $error 错误信息
     * @return array
     */
    private function formatErrorResult(string $error): array
    {
        return [
            'has_violation' => false,
            'violations' => [],
            'severity' => self::SEVERITY_LOW,
            'confidence' => 0,
            'score' => 0,
            'suggestion' => 'review',
            'provider' => 'error',
            'error' => $error,
            'check_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 获取更高的严重程度
     *
     * @param string $current 当前严重程度
     * @param string $new 新的严重程度
     * @return string
     */
    protected function getHigherSeverity(string $current, string $new): string
    {
        $severityOrder = [
            self::SEVERITY_LOW => 1,
            self::SEVERITY_MEDIUM => 2,
            self::SEVERITY_HIGH => 3,
        ];

        $currentLevel = $severityOrder[$current] ?? 1;
        $newLevel = $severityOrder[$new] ?? 1;

        return $newLevel > $currentLevel ? $new : $current;
    }

    /**
     * 获取违规类型名称
     *
     * @param string $type 违规类型
     * @return string
     */
    private function getViolationTypeName(string $type): string
    {
        $violationTypes = Config::get('content_moderation.violation_types', []);
        return $violationTypes[$type]['name'] ?? '未知';
    }

    /**
     * 生成缓存键
     *
     * @param string $type 内容类型
     * @param string $content 内容
     * @return string
     */
    private function getCacheKey(string $type, string $content): string
    {
        $hash = md5($content);
        return 'moderation:' . $type . ':' . $hash;
    }

    /**
     * 检查是否启用缓存
     *
     * @return bool
     */
    private function isCacheEnabled(): bool
    {
        return Config::get('content_moderation.cache_enabled', true);
    }

    /**
     * 获取缓存TTL
     *
     * @return int
     */
    private function getCacheTTL(): int
    {
        return Config::get('content_moderation.cache_ttl', 86400);
    }

    /**
     * 批量检查素材
     *
     * @param array $materials 素材列表
     * @param bool $async 是否异步处理
     * @return array
     */
    public function batchCheckMaterials(array $materials, bool $async = false): array
    {
        $results = [];

        foreach ($materials as $material) {
            $materialId = $material['id'] ?? null;
            $results[$materialId] = $this->checkMaterial($material, $async);
        }

        return $results;
    }

    /**
     * 综合评分
     *
     * @param array $results 多个审核结果
     * @return array
     */
    public function calculateOverallScore(array $results): array
    {
        if (empty($results)) {
            return [
                'overall_score' => 100,
                'overall_suggestion' => 'pass',
                'has_violation' => false,
                'violations' => [],
            ];
        }

        $scoringConfig = Config::get('content_moderation.scoring', []);
        $weights = $scoringConfig['weights'] ?? [];
        $severityWeights = $scoringConfig['severity_weights'] ?? [];

        $totalWeight = 0;
        $weightedScore = 0;
        $allViolations = [];

        foreach ($results as $type => $result) {
            $weight = $weights[$type] ?? 1.0;
            $score = $result['score'] ?? 100;
            $violations = $result['violations'] ?? [];

            // 根据严重程度调整权重
            foreach ($violations as $violation) {
                $severity = $violation['severity'] ?? 'LOW';
                $severityWeight = $severityWeights[$severity] ?? 1.0;
                $adjustedScore = $score * (1 / $severityWeight);
                $weightedScore += $adjustedScore * $weight;
                $totalWeight += $weight;
            }

            if (empty($violations)) {
                $weightedScore += $score * $weight;
                $totalWeight += $weight;
            }

            $allViolations = array_merge($allViolations, $violations);
        }

        $overallScore = $totalWeight > 0 ? intval($weightedScore / $totalWeight) : 100;

        // 根据评分确定建议
        $thresholds = Config::get('content_moderation.thresholds', []);
        $passThreshold = $thresholds['pass'] ?? 60;
        $reviewThreshold = $thresholds['review'] ?? 90;

        if ($overallScore >= $passThreshold) {
            $overallSuggestion = 'pass';
        } elseif ($overallScore >= $reviewThreshold) {
            $overallSuggestion = 'review';
        } else {
            $overallSuggestion = 'reject';
        }

        return [
            'overall_score' => $overallScore,
            'overall_suggestion' => $overallSuggestion,
            'has_violation' => !empty($allViolations),
            'violations' => $allViolations,
        ];
    }
}
