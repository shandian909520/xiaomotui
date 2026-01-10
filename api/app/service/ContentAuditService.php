<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Log;
use think\facade\Cache;
use think\facade\Config;
use think\Exception;
use app\model\ContentAudit;
use app\model\SensitiveWord;
use app\model\Material;
use app\model\ContentTask;

/**
 * 内容审核服务
 * 提供自动审核和人工审核相结合的内容安全机制
 */
class ContentAuditService
{
    /**
     * 审核类型常量
     */
    const TYPE_TEXT = 'TEXT';
    const TYPE_IMAGE = 'IMAGE';
    const TYPE_VIDEO = 'VIDEO';
    const TYPE_AUDIO = 'AUDIO';

    /**
     * 审核方式常量
     */
    const METHOD_AUTO = 'AUTO';
    const METHOD_MANUAL = 'MANUAL';
    const METHOD_MIXED = 'MIXED';

    /**
     * 审核状态常量
     */
    const STATUS_PENDING = 0;    // 待审核
    const STATUS_APPROVED = 1;   // 通过
    const STATUS_REJECTED = 2;   // 拒绝
    const STATUS_AUDITING = 3;   // 审核中

    /**
     * 风险等级常量
     */
    const RISK_LOW = 'LOW';
    const RISK_MEDIUM = 'MEDIUM';
    const RISK_HIGH = 'HIGH';
    const RISK_CRITICAL = 'CRITICAL';

    /**
     * 违规类型常量
     */
    const VIOLATION_POLITICAL = 'POLITICAL';
    const VIOLATION_PORNOGRAPHIC = 'PORNOGRAPHIC';
    const VIOLATION_VIOLENCE = 'VIOLENCE';
    const VIOLATION_GAMBLING = 'GAMBLING';
    const VIOLATION_DRUGS = 'DRUGS';
    const VIOLATION_ILLEGAL = 'ILLEGAL';
    const VIOLATION_SPAM = 'SPAM';
    const VIOLATION_COPYRIGHT = 'COPYRIGHT';
    const VIOLATION_FALSE_INFO = 'FALSE_INFO';
    const VIOLATION_OTHER = 'OTHER';

    /**
     * 内容类型常量
     */
    const CONTENT_TYPE_MATERIAL = 'MATERIAL';
    const CONTENT_TYPE_TASK = 'CONTENT_TASK';
    const CONTENT_TYPE_COMMENT = 'COMMENT';
    const CONTENT_TYPE_USER = 'USER_CONTENT';

    /**
     * 配置信息
     */
    protected $config = [];

    /**
     * 敏感词树
     */
    protected $sensitiveWordTree = null;

    public function __construct()
    {
        $this->config = Config::get('audit');
    }

    /**
     * 审核文本内容
     *
     * @param string $text 文本内容
     * @param array $options 审核选项
     * @return array 审核结果
     */
    public function auditText(string $text, array $options = []): array
    {
        Log::info('开始审核文本内容', [
            'text_length' => mb_strlen($text),
            'options' => $options
        ]);

        try {
            // 检查审核开关
            if (!$this->config['enabled'] || !$this->config['rules']['text']['enabled']) {
                return $this->buildPassResult('审核未启用');
            }

            // 验证文本长度
            $textLength = mb_strlen($text);
            if ($textLength < $this->config['rules']['text']['min_length']) {
                return $this->buildRejectResult('文本内容过短', [self::VIOLATION_OTHER]);
            }

            if ($textLength > $this->config['rules']['text']['max_length']) {
                return $this->buildRejectResult('文本内容过长', [self::VIOLATION_OTHER]);
            }

            $violations = [];
            $riskScore = 0.0;

            // 敏感词检测
            if ($this->config['rules']['text']['check_sensitive_words']) {
                $sensitiveResult = $this->detectSensitiveWords($text);
                if (!empty($sensitiveResult['words'])) {
                    $violations[] = self::VIOLATION_OTHER;
                    $riskScore = max($riskScore, $sensitiveResult['risk_score']);
                }
            }

            // 第三方API审核
            if ($this->config['auto_audit_enabled']) {
                $apiResult = $this->callThirdPartyTextAudit($text);
                if (!empty($apiResult['violations'])) {
                    $violations = array_merge($violations, $apiResult['violations']);
                    $riskScore = max($riskScore, $apiResult['risk_score']);
                }
            }

            // 计算风险等级
            $riskLevel = $this->calculateRiskLevel($riskScore);

            // 根据风险等级决定审核结果
            return $this->determineAuditResult($riskLevel, $riskScore, $violations);

        } catch (Exception $e) {
            Log::error('文本审核失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => self::STATUS_PENDING,
                'risk_level' => self::RISK_MEDIUM,
                'message' => '审核异常，待人工审核',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 审核图片内容
     *
     * @param string $imageUrl 图片URL或本地路径
     * @param array $options 审核选项
     * @return array 审核结果
     */
    public function auditImage(string $imageUrl, array $options = []): array
    {
        Log::info('开始审核图片内容', [
            'image_url' => $imageUrl,
            'options' => $options
        ]);

        try {
            // 检查审核开关
            if (!$this->config['enabled'] || !$this->config['rules']['image']['enabled']) {
                return $this->buildPassResult('审核未启用');
            }

            // 验证图片文件
            $imageInfo = $this->validateImageFile($imageUrl);
            if (!$imageInfo['valid']) {
                return $this->buildRejectResult($imageInfo['message'], [self::VIOLATION_OTHER]);
            }

            $violations = [];
            $riskScore = 0.0;

            // 第三方API审核
            if ($this->config['auto_audit_enabled']) {
                $apiResult = $this->callThirdPartyImageAudit($imageUrl);
                if (!empty($apiResult['violations'])) {
                    $violations = $apiResult['violations'];
                    $riskScore = $apiResult['risk_score'];
                }
            }

            // 计算风险等级
            $riskLevel = $this->calculateRiskLevel($riskScore);

            return $this->determineAuditResult($riskLevel, $riskScore, $violations);

        } catch (Exception $e) {
            Log::error('图片审核失败', [
                'image_url' => $imageUrl,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => self::STATUS_PENDING,
                'risk_level' => self::RISK_MEDIUM,
                'message' => '审核异常，待人工审核',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 审核视频内容
     *
     * @param string $videoUrl 视频URL或本地路径
     * @param array $options 审核选项
     * @return array 审核结果
     */
    public function auditVideo(string $videoUrl, array $options = []): array
    {
        Log::info('开始审核视频内容', [
            'video_url' => $videoUrl,
            'options' => $options
        ]);

        try {
            // 检查审核开关
            if (!$this->config['enabled'] || !$this->config['rules']['video']['enabled']) {
                return $this->buildPassResult('审核未启用');
            }

            // 验证视频文件
            $videoInfo = $this->validateVideoFile($videoUrl);
            if (!$videoInfo['valid']) {
                return $this->buildRejectResult($videoInfo['message'], [self::VIOLATION_OTHER]);
            }

            $violations = [];
            $riskScore = 0.0;

            // 第三方API审核
            if ($this->config['auto_audit_enabled']) {
                $apiResult = $this->callThirdPartyVideoAudit($videoUrl);
                if (!empty($apiResult['violations'])) {
                    $violations = $apiResult['violations'];
                    $riskScore = $apiResult['risk_score'];
                }
            }

            // 计算风险等级
            $riskLevel = $this->calculateRiskLevel($riskScore);

            return $this->determineAuditResult($riskLevel, $riskScore, $violations);

        } catch (Exception $e) {
            Log::error('视频审核失败', [
                'video_url' => $videoUrl,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => self::STATUS_PENDING,
                'risk_level' => self::RISK_MEDIUM,
                'message' => '审核异常，待人工审核',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 审核音频内容
     *
     * @param string $audioUrl 音频URL或本地路径
     * @param array $options 审核选项
     * @return array 审核结果
     */
    public function auditAudio(string $audioUrl, array $options = []): array
    {
        Log::info('开始审核音频内容', [
            'audio_url' => $audioUrl,
            'options' => $options
        ]);

        try {
            // 检查审核开关
            if (!$this->config['enabled'] || !$this->config['rules']['audio']['enabled']) {
                return $this->buildPassResult('审核未启用');
            }

            // 验证音频文件
            $audioInfo = $this->validateAudioFile($audioUrl);
            if (!$audioInfo['valid']) {
                return $this->buildRejectResult($audioInfo['message'], [self::VIOLATION_OTHER]);
            }

            $violations = [];
            $riskScore = 0.0;

            // 第三方API审核
            if ($this->config['auto_audit_enabled']) {
                $apiResult = $this->callThirdPartyAudioAudit($audioUrl);
                if (!empty($apiResult['violations'])) {
                    $violations = $apiResult['violations'];
                    $riskScore = $apiResult['risk_score'];
                }
            }

            // 计算风险等级
            $riskLevel = $this->calculateRiskLevel($riskScore);

            return $this->determineAuditResult($riskLevel, $riskScore, $violations);

        } catch (Exception $e) {
            Log::error('音频审核失败', [
                'audio_url' => $audioUrl,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => self::STATUS_PENDING,
                'risk_level' => self::RISK_MEDIUM,
                'message' => '审核异常，待人工审核',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 批量审核内容
     *
     * @param array $items 待审核项列表
     * @param string $type 审核类型
     * @return array 审核结果列表
     */
    public function batchAudit(array $items, string $type): array
    {
        Log::info('开始批量审核', [
            'count' => count($items),
            'type' => $type
        ]);

        // 检查批量数量限制
        $maxItems = $this->config['batch']['max_items'];
        if (count($items) > $maxItems) {
            throw new Exception("批量审核数量不能超过{$maxItems}条");
        }

        $results = [];
        $chunkSize = $this->config['batch']['chunk_size'];

        // 分块处理
        $chunks = array_chunk($items, $chunkSize);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $item) {
                $result = match ($type) {
                    self::TYPE_TEXT => $this->auditText($item['content'] ?? '', $item['options'] ?? []),
                    self::TYPE_IMAGE => $this->auditImage($item['url'] ?? '', $item['options'] ?? []),
                    self::TYPE_VIDEO => $this->auditVideo($item['url'] ?? '', $item['options'] ?? []),
                    self::TYPE_AUDIO => $this->auditAudio($item['url'] ?? '', $item['options'] ?? []),
                    default => ['status' => self::STATUS_REJECTED, 'message' => '不支持的审核类型']
                };

                $results[] = array_merge($item, ['audit_result' => $result]);
            }
        }

        Log::info('批量审核完成', [
            'total' => count($results),
            'passed' => count(array_filter($results, fn($r) => $r['audit_result']['status'] === self::STATUS_APPROVED))
        ]);

        return $results;
    }

    /**
     * 提交人工审核
     *
     * @param int $contentId 内容ID
     * @param string $contentType 内容类型
     * @param array $data 审核数据
     * @return int 审核任务ID
     */
    public function submitManualAudit(int $contentId, string $contentType, array $data): int
    {
        Log::info('提交人工审核', [
            'content_id' => $contentId,
            'content_type' => $contentType
        ]);

        $audit = new ContentAudit();
        $audit->content_id = $contentId;
        $audit->content_type = $contentType;
        $audit->audit_type = $data['audit_type'] ?? self::TYPE_TEXT;
        $audit->audit_method = self::METHOD_MANUAL;
        $audit->status = self::STATUS_AUDITING;
        $audit->auto_result = $data['auto_result'] ?? null;
        $audit->risk_level = $data['risk_level'] ?? self::RISK_MEDIUM;
        $audit->violation_types = $data['violation_types'] ?? null;
        $audit->audit_message = $data['message'] ?? '';
        $audit->submit_time = date('Y-m-d H:i:s');
        $audit->save();

        Log::info('人工审核任务已创建', ['audit_id' => $audit->id]);

        return $audit->id;
    }

    /**
     * 完成人工审核
     *
     * @param int $auditId 审核任务ID
     * @param int $result 审核结果 1通过 2拒绝
     * @param string $reason 审核理由
     * @param int $auditorId 审核员ID
     * @return bool
     */
    public function completeManualAudit(int $auditId, int $result, string $reason, int $auditorId): bool
    {
        Log::info('完成人工审核', [
            'audit_id' => $auditId,
            'result' => $result,
            'auditor_id' => $auditorId
        ]);

        try {
            $audit = ContentAudit::find($auditId);
            if (!$audit) {
                throw new Exception('审核记录不存在');
            }

            if ($audit->status !== self::STATUS_AUDITING) {
                throw new Exception('审核记录状态异常');
            }

            $audit->status = $result;
            $audit->manual_result = [
                'result' => $result,
                'reason' => $reason,
                'auditor_id' => $auditorId,
                'audit_time' => date('Y-m-d H:i:s')
            ];
            $audit->auditor_id = $auditorId;
            $audit->audit_time = date('Y-m-d H:i:s');
            $audit->save();

            // 处理审核结果
            if ($result === self::STATUS_REJECTED) {
                $this->handleViolation($audit->content_id, $audit->content_type, [
                    'reason' => $reason,
                    'violation_types' => $audit->violation_types
                ]);
            }

            Log::info('人工审核完成', ['audit_id' => $auditId, 'result' => $result]);

            return true;

        } catch (Exception $e) {
            Log::error('人工审核失败', [
                'audit_id' => $auditId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 检测敏感词
     *
     * @param string $text 文本内容
     * @return array 检测结果
     */
    public function detectSensitiveWords(string $text): array
    {
        if (empty($text)) {
            return ['words' => [], 'risk_score' => 0.0];
        }

        // 加载敏感词库
        $sensitiveWords = $this->loadSensitiveWords();
        if (empty($sensitiveWords)) {
            return ['words' => [], 'risk_score' => 0.0];
        }

        $foundWords = [];
        $maxLevel = 0;

        // 简单匹配算法（实际应用中建议使用Trie树或AC自动机）
        foreach ($sensitiveWords as $word) {
            if (mb_strpos($text, $word['word']) !== false) {
                $foundWords[] = [
                    'word' => $word['word'],
                    'category' => $word['category'],
                    'level' => $word['level'],
                    'action' => $word['action']
                ];
                $maxLevel = max($maxLevel, $word['level']);
            }
        }

        // 计算风险分数（1-5级映射到0-1分数）
        $riskScore = $maxLevel > 0 ? $maxLevel / 5 : 0.0;

        return [
            'words' => $foundWords,
            'risk_score' => $riskScore,
            'count' => count($foundWords)
        ];
    }

    /**
     * 替换敏感词
     *
     * @param string $text 文本内容
     * @param string $replacement 替换字符
     * @return string 处理后的文本
     */
    public function replaceSensitiveWords(string $text, string $replacement = '*'): string
    {
        if (empty($text)) {
            return $text;
        }

        $sensitiveWords = $this->loadSensitiveWords();
        if (empty($sensitiveWords)) {
            return $text;
        }

        foreach ($sensitiveWords as $word) {
            if ($word['action'] === 'REPLACE') {
                $wordLength = mb_strlen($word['word']);
                $replace = str_repeat($replacement, $wordLength);
                $text = str_replace($word['word'], $replace, $text);
            }
        }

        return $text;
    }

    /**
     * 处理违规内容
     *
     * @param int $contentId 内容ID
     * @param string $contentType 内容类型
     * @param array $auditResult 审核结果
     * @return bool
     */
    public function handleViolation(int $contentId, string $contentType, array $auditResult): bool
    {
        Log::info('处理违规内容', [
            'content_id' => $contentId,
            'content_type' => $contentType,
            'audit_result' => $auditResult
        ]);

        try {
            $config = $this->config['violation_handling'];

            // 自动下架
            if ($config['auto_takedown']) {
                $this->takedownContent($contentId, $contentType);
            }

            // 记录日志
            if ($config['record_log']) {
                Log::warning('内容违规', [
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'reason' => $auditResult['reason'] ?? '',
                    'violations' => $auditResult['violation_types'] ?? []
                ]);
            }

            // 通知商家
            if ($config['notify_merchant']) {
                $merchantId = $this->getMerchantIdByContent($contentId, $contentType);
                if ($merchantId) {
                    $this->notifyMerchantViolation($merchantId, [
                        'content_id' => $contentId,
                        'content_type' => $contentType,
                        'reason' => $auditResult['reason'] ?? '内容违规',
                        'violations' => $auditResult['violation_types'] ?? []
                    ]);
                }
            }

            return true;

        } catch (Exception $e) {
            Log::error('违规内容处理失败', [
                'content_id' => $contentId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 通知商家违规内容
     *
     * @param int $merchantId 商家ID
     * @param array $violationData 违规数据
     * @return bool
     */
    public function notifyMerchantViolation(int $merchantId, array $violationData): bool
    {
        Log::info('通知商家违规内容', [
            'merchant_id' => $merchantId,
            'violation_data' => $violationData
        ]);

        try {
            // 使用通知服务发送通知
            $notificationService = new NotificationService();

            // 构建通知消息
            $message = $this->buildViolationNotificationMessage($violationData);

            // 发送系统通知（可扩展为短信、邮件等）
            $cacheKey = "merchant_violations:{$merchantId}";
            $violations = Cache::get($cacheKey, []);

            $violations[] = [
                'content_id' => $violationData['content_id'],
                'content_type' => $violationData['content_type'],
                'reason' => $violationData['reason'],
                'violations' => $violationData['violations'],
                'time' => date('Y-m-d H:i:s')
            ];

            Cache::set($cacheKey, $violations, 7 * 24 * 3600); // 保存7天

            Log::info('商家违规通知已发送', ['merchant_id' => $merchantId]);

            return true;

        } catch (Exception $e) {
            Log::error('商家违规通知失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 获取审核统计
     *
     * @param array $filters 过滤条件
     * @return array 统计数据
     */
    public function getAuditStatistics(array $filters = []): array
    {
        Log::info('获取审核统计', ['filters' => $filters]);

        try {
            // 尝试从缓存获取
            if ($this->config['statistics']['enabled']) {
                $cacheKey = 'audit_statistics:' . md5(json_encode($filters));
                $cached = Cache::get($cacheKey);
                if ($cached !== false) {
                    return $cached;
                }
            }

            $query = ContentAudit::query();

            // 应用过滤条件
            if (!empty($filters['content_type'])) {
                $query->where('content_type', $filters['content_type']);
            }

            if (!empty($filters['audit_type'])) {
                $query->where('audit_type', $filters['audit_type']);
            }

            if (!empty($filters['start_date'])) {
                $query->where('create_time', '>=', $filters['start_date']);
            }

            if (!empty($filters['end_date'])) {
                $query->where('create_time', '<=', $filters['end_date']);
            }

            // 统计数据
            $total = (clone $query)->count();
            $approved = (clone $query)->where('status', self::STATUS_APPROVED)->count();
            $rejected = (clone $query)->where('status', self::STATUS_REJECTED)->count();
            $pending = (clone $query)->where('status', self::STATUS_PENDING)->count();
            $auditing = (clone $query)->where('status', self::STATUS_AUDITING)->count();

            // 按风险等级统计
            $riskStats = [
                self::RISK_LOW => (clone $query)->where('risk_level', self::RISK_LOW)->count(),
                self::RISK_MEDIUM => (clone $query)->where('risk_level', self::RISK_MEDIUM)->count(),
                self::RISK_HIGH => (clone $query)->where('risk_level', self::RISK_HIGH)->count(),
                self::RISK_CRITICAL => (clone $query)->where('risk_level', self::RISK_CRITICAL)->count(),
            ];

            // 按审核方式统计
            $methodStats = [
                self::METHOD_AUTO => (clone $query)->where('audit_method', self::METHOD_AUTO)->count(),
                self::METHOD_MANUAL => (clone $query)->where('audit_method', self::METHOD_MANUAL)->count(),
                self::METHOD_MIXED => (clone $query)->where('audit_method', self::METHOD_MIXED)->count(),
            ];

            $statistics = [
                'total' => $total,
                'approved' => $approved,
                'rejected' => $rejected,
                'pending' => $pending,
                'auditing' => $auditing,
                'approval_rate' => $total > 0 ? round($approved / $total * 100, 2) : 0,
                'rejection_rate' => $total > 0 ? round($rejected / $total * 100, 2) : 0,
                'risk_stats' => $riskStats,
                'method_stats' => $methodStats,
                'generated_at' => date('Y-m-d H:i:s')
            ];

            // 缓存统计结果
            if ($this->config['statistics']['enabled']) {
                Cache::set($cacheKey, $statistics, $this->config['statistics']['cache_ttl']);
            }

            return $statistics;

        } catch (Exception $e) {
            Log::error('获取审核统计失败', [
                'error' => $e->getMessage()
            ]);

            return [
                'error' => $e->getMessage(),
                'total' => 0
            ];
        }
    }

    /**
     * 加载敏感词库
     *
     * @return array
     */
    protected function loadSensitiveWords(): array
    {
        if (!$this->config['sensitive_words']['enabled']) {
            return [];
        }

        $cacheKey = $this->config['sensitive_words']['cache_key'];
        $cacheTtl = $this->config['sensitive_words']['cache_ttl'];

        // 尝试从缓存获取
        $words = Cache::get($cacheKey);
        if ($words !== false) {
            return $words;
        }

        // 从数据库加载
        $words = SensitiveWord::where('status', 1)
            ->field(['word', 'category', 'level', 'action'])
            ->select()
            ->toArray();

        // 缓存敏感词
        Cache::set($cacheKey, $words, $cacheTtl);

        return $words;
    }

    /**
     * 调用第三方文本审核API
     *
     * @param string $text
     * @return array
     */
    protected function callThirdPartyTextAudit(string $text): array
    {
        // 选择可用的审核提供商
        $provider = $this->selectAuditProvider('text');
        if (!$provider) {
            return ['violations' => [], 'risk_score' => 0.0];
        }

        Log::info('调用第三方文本审核API', ['provider' => $provider]);

        try {
            // 这里应该调用实际的第三方API
            // 目前返回模拟数据
            return [
                'violations' => [],
                'risk_score' => 0.0,
                'provider' => $provider,
                'details' => []
            ];

        } catch (Exception $e) {
            Log::error('第三方文本审核失败', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            return ['violations' => [], 'risk_score' => 0.0];
        }
    }

    /**
     * 调用第三方图片审核API
     *
     * @param string $imageUrl
     * @return array
     */
    protected function callThirdPartyImageAudit(string $imageUrl): array
    {
        $provider = $this->selectAuditProvider('image');
        if (!$provider) {
            return ['violations' => [], 'risk_score' => 0.0];
        }

        Log::info('调用第三方图片审核API', ['provider' => $provider]);

        try {
            // 实际应调用第三方API
            return [
                'violations' => [],
                'risk_score' => 0.0,
                'provider' => $provider,
                'details' => []
            ];

        } catch (Exception $e) {
            Log::error('第三方图片审核失败', ['error' => $e->getMessage()]);
            return ['violations' => [], 'risk_score' => 0.0];
        }
    }

    /**
     * 调用第三方视频审核API
     *
     * @param string $videoUrl
     * @return array
     */
    protected function callThirdPartyVideoAudit(string $videoUrl): array
    {
        $provider = $this->selectAuditProvider('video');
        if (!$provider) {
            return ['violations' => [], 'risk_score' => 0.0];
        }

        Log::info('调用第三方视频审核API', ['provider' => $provider]);

        try {
            // 实际应调用第三方API
            return [
                'violations' => [],
                'risk_score' => 0.0,
                'provider' => $provider,
                'details' => []
            ];

        } catch (Exception $e) {
            Log::error('第三方视频审核失败', ['error' => $e->getMessage()]);
            return ['violations' => [], 'risk_score' => 0.0];
        }
    }

    /**
     * 调用第三方音频审核API
     *
     * @param string $audioUrl
     * @return array
     */
    protected function callThirdPartyAudioAudit(string $audioUrl): array
    {
        $provider = $this->selectAuditProvider('audio');
        if (!$provider) {
            return ['violations' => [], 'risk_score' => 0.0];
        }

        Log::info('调用第三方音频审核API', ['provider' => $provider]);

        try {
            // 实际应调用第三方API
            return [
                'violations' => [],
                'risk_score' => 0.0,
                'provider' => $provider,
                'details' => []
            ];

        } catch (Exception $e) {
            Log::error('第三方音频审核失败', ['error' => $e->getMessage()]);
            return ['violations' => [], 'risk_score' => 0.0];
        }
    }

    /**
     * 选择可用的审核提供商
     *
     * @param string $type
     * @return string|null
     */
    protected function selectAuditProvider(string $type): ?string
    {
        $providers = $this->config['providers'];

        foreach ($providers as $name => $config) {
            if (!empty($config['enabled'])) {
                return $name;
            }
        }

        return null;
    }

    /**
     * 验证图片文件
     *
     * @param string $imageUrl
     * @return array
     */
    protected function validateImageFile(string $imageUrl): array
    {
        // 这里应该实现实际的文件验证逻辑
        return ['valid' => true, 'message' => ''];
    }

    /**
     * 验证视频文件
     *
     * @param string $videoUrl
     * @return array
     */
    protected function validateVideoFile(string $videoUrl): array
    {
        // 这里应该实现实际的文件验证逻辑
        return ['valid' => true, 'message' => ''];
    }

    /**
     * 验证音频文件
     *
     * @param string $audioUrl
     * @return array
     */
    protected function validateAudioFile(string $audioUrl): array
    {
        // 这里应该实现实际的文件验证逻辑
        return ['valid' => true, 'message' => ''];
    }

    /**
     * 计算风险等级
     *
     * @param float $riskScore
     * @return string
     */
    protected function calculateRiskLevel(float $riskScore): string
    {
        $levels = $this->config['risk_levels'];

        foreach ($levels as $level => $config) {
            [$min, $max] = $config['score_range'];
            if ($riskScore >= $min && $riskScore <= $max) {
                return $level;
            }
        }

        return self::RISK_MEDIUM;
    }

    /**
     * 根据风险等级决定审核结果
     *
     * @param string $riskLevel
     * @param float $riskScore
     * @param array $violations
     * @return array
     */
    protected function determineAuditResult(string $riskLevel, float $riskScore, array $violations): array
    {
        $levelConfig = $this->config['risk_levels'][$riskLevel];
        $action = $levelConfig['action'];

        return match ($action) {
            'auto_pass' => $this->buildPassResult('自动审核通过', $riskLevel, $riskScore),
            'auto_reject' => $this->buildRejectResult('内容存在严重违规', $violations, $riskLevel, $riskScore),
            'manual_review', 'manual_audit' => $this->buildPendingResult('需要人工审核', $riskLevel, $riskScore, $violations),
            default => $this->buildPendingResult('待审核', $riskLevel, $riskScore, $violations)
        };
    }

    /**
     * 构建通过结果
     *
     * @param string $message
     * @param string $riskLevel
     * @param float $riskScore
     * @return array
     */
    protected function buildPassResult(string $message, string $riskLevel = self::RISK_LOW, float $riskScore = 0.0): array
    {
        return [
            'status' => self::STATUS_APPROVED,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'message' => $message,
            'violations' => []
        ];
    }

    /**
     * 构建拒绝结果
     *
     * @param string $message
     * @param array $violations
     * @param string $riskLevel
     * @param float $riskScore
     * @return array
     */
    protected function buildRejectResult(string $message, array $violations, string $riskLevel = self::RISK_CRITICAL, float $riskScore = 1.0): array
    {
        return [
            'status' => self::STATUS_REJECTED,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'message' => $message,
            'violations' => $violations
        ];
    }

    /**
     * 构建待审核结果
     *
     * @param string $message
     * @param string $riskLevel
     * @param float $riskScore
     * @param array $violations
     * @return array
     */
    protected function buildPendingResult(string $message, string $riskLevel, float $riskScore, array $violations = []): array
    {
        return [
            'status' => self::STATUS_PENDING,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'message' => $message,
            'violations' => $violations
        ];
    }

    /**
     * 下架内容
     *
     * @param int $contentId
     * @param string $contentType
     * @return bool
     */
    protected function takedownContent(int $contentId, string $contentType): bool
    {
        try {
            switch ($contentType) {
                case self::CONTENT_TYPE_MATERIAL:
                    $material = Material::find($contentId);
                    if ($material) {
                        $material->status = 0;
                        $material->audit_status = 2;
                        $material->save();
                    }
                    break;

                case self::CONTENT_TYPE_TASK:
                    $task = ContentTask::find($contentId);
                    if ($task) {
                        $task->status = 'FAILED';
                        $task->error_message = '内容审核未通过';
                        $task->save();
                    }
                    break;
            }

            Log::info('内容已下架', [
                'content_id' => $contentId,
                'content_type' => $contentType
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('内容下架失败', [
                'content_id' => $contentId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 根据内容获取商家ID
     *
     * @param int $contentId
     * @param string $contentType
     * @return int|null
     */
    protected function getMerchantIdByContent(int $contentId, string $contentType): ?int
    {
        try {
            switch ($contentType) {
                case self::CONTENT_TYPE_MATERIAL:
                    // Material模型中没有直接的merchant_id，需要通过其他方式获取
                    return null;

                case self::CONTENT_TYPE_TASK:
                    $task = ContentTask::find($contentId);
                    return $task ? $task->merchant_id : null;

                default:
                    return null;
            }

        } catch (Exception $e) {
            Log::error('获取商家ID失败', [
                'content_id' => $contentId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * 构建违规通知消息
     *
     * @param array $violationData
     * @return string
     */
    protected function buildViolationNotificationMessage(array $violationData): string
    {
        $message = "您的内容审核未通过\n\n";
        $message .= "内容ID: {$violationData['content_id']}\n";
        $message .= "内容类型: {$violationData['content_type']}\n";
        $message .= "拒绝原因: {$violationData['reason']}\n";

        if (!empty($violationData['violations'])) {
            $violations = array_map(function($v) {
                return $this->config['violation_types'][$v] ?? $v;
            }, $violationData['violations']);
            $message .= "违规类型: " . implode('、', $violations) . "\n";
        }

        $message .= "\n请修改内容后重新提交审核。";

        return $message;
    }
}