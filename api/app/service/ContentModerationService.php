<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Db;

/**
 * 内容审核服务
 * 负责检测文本、图片、视频等内容是否违规
 */
class ContentModerationService
{
    // 违规类型常量
    const VIOLATION_SENSITIVE = 'SENSITIVE';   // 敏感内容
    const VIOLATION_ILLEGAL = 'ILLEGAL';       // 违法内容
    const VIOLATION_PORN = 'PORN';             // 色情内容
    const VIOLATION_VIOLENCE = 'VIOLENCE';     // 暴力内容
    const VIOLATION_AD = 'AD';                 // 广告内容
    const VIOLATION_FRAUD = 'FRAUD';           // 欺诈内容
    const VIOLATION_SPAM = 'SPAM';             // 垃圾内容
    const VIOLATION_COPYRIGHT = 'COPYRIGHT';   // 版权问题
    const VIOLATION_OTHER = 'OTHER';           // 其他违规

    // 严重程度常量
    const SEVERITY_HIGH = 'HIGH';
    const SEVERITY_MEDIUM = 'MEDIUM';
    const SEVERITY_LOW = 'LOW';

    /**
     * 检测素材内容是否违规
     *
     * @param array $material 素材数据
     * @return array [
     *   'has_violation' => bool,
     *   'violations' => array,
     *   'severity' => string,
     *   'confidence' => float
     * ]
     */
    public function checkMaterial(array $material): array
    {
        $violations = [];
        $maxSeverity = null;
        $maxConfidence = 0;

        try {
            // 根据素材类型进行不同的检测
            switch ($material['type']) {
                case 'TEXT':
                    $textResult = $this->checkText($material['content'] ?? '', $material);
                    if ($textResult['has_violation']) {
                        $violations = array_merge($violations, $textResult['violations']);
                        $maxSeverity = $this->getHigherSeverity($maxSeverity, $textResult['severity']);
                        $maxConfidence = max($maxConfidence, $textResult['confidence']);
                    }
                    break;

                case 'IMAGE':
                    $imageResult = $this->checkImage($material['file_url'] ?? '', $material);
                    if ($imageResult['has_violation']) {
                        $violations = array_merge($violations, $imageResult['violations']);
                        $maxSeverity = $this->getHigherSeverity($maxSeverity, $imageResult['severity']);
                        $maxConfidence = max($maxConfidence, $imageResult['confidence']);
                    }
                    break;

                case 'VIDEO':
                    $videoResult = $this->checkVideo($material['file_url'] ?? '', $material);
                    if ($videoResult['has_violation']) {
                        $violations = array_merge($violations, $videoResult['violations']);
                        $maxSeverity = $this->getHigherSeverity($maxSeverity, $videoResult['severity']);
                        $maxConfidence = max($maxConfidence, $videoResult['confidence']);
                    }
                    break;

                case 'AUDIO':
                    $audioResult = $this->checkAudio($material['file_url'] ?? '', $material);
                    if ($audioResult['has_violation']) {
                        $violations = array_merge($violations, $audioResult['violations']);
                        $maxSeverity = $this->getHigherSeverity($maxSeverity, $audioResult['severity']);
                        $maxConfidence = max($maxConfidence, $audioResult['confidence']);
                    }
                    break;
            }

            // 检查素材名称和元数据
            if (!empty($material['name'])) {
                $nameResult = $this->checkText($material['name'], $material);
                if ($nameResult['has_violation']) {
                    $violations = array_merge($violations, $nameResult['violations']);
                    $maxSeverity = $this->getHigherSeverity($maxSeverity, $nameResult['severity']);
                    $maxConfidence = max($maxConfidence, $nameResult['confidence']);
                }
            }

        } catch (\Exception $e) {
            Log::error('内容审核失败', [
                'material_id' => $material['id'] ?? null,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'has_violation' => !empty($violations),
            'violations' => $violations,
            'severity' => $maxSeverity ?? self::SEVERITY_LOW,
            'confidence' => $maxConfidence,
            'check_time' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 检测文本内容
     *
     * @param string $text 文本内容
     * @param array $context 上下文信息
     * @return array
     */
    public function checkText(string $text, array $context = []): array
    {
        if (empty($text)) {
            return [
                'has_violation' => false,
                'violations' => [],
                'severity' => self::SEVERITY_LOW,
                'confidence' => 1.0
            ];
        }

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

        // 3. 调用第三方API检测（可选）
        if (Config::get('moderation.text.third_party_enabled', false)) {
            $apiResult = $this->checkTextByAPI($text, $context);
            if (!empty($apiResult['violations'])) {
                $violations = array_merge($violations, $apiResult['violations']);
                $maxSeverity = $this->getHigherSeverity($maxSeverity, $apiResult['severity']);
                $maxConfidence = max($maxConfidence, $apiResult['confidence']);
            }
        }

        return [
            'has_violation' => !empty($violations),
            'violations' => $violations,
            'severity' => $maxSeverity,
            'confidence' => $maxConfidence
        ];
    }

    /**
     * 检测图片内容
     *
     * @param string $imageUrl 图片URL
     * @param array $context 上下文信息
     * @return array
     */
    public function checkImage(string $imageUrl, array $context = []): array
    {
        if (empty($imageUrl)) {
            return [
                'has_violation' => false,
                'violations' => [],
                'severity' => self::SEVERITY_LOW,
                'confidence' => 0
            ];
        }

        // 检查缓存
        $cacheKey = 'image_moderation:' . md5($imageUrl);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $violations = [];
        $maxSeverity = self::SEVERITY_LOW;
        $maxConfidence = 0;

        try {
            // 调用第三方图片审核API
            if (Config::get('moderation.image.enabled', false)) {
                $provider = Config::get('moderation.image.provider', 'baidu');

                if ($provider === 'baidu') {
                    $result = $this->checkImageByBaidu($imageUrl, $context);
                } elseif ($provider === 'aliyun') {
                    $result = $this->checkImageByAliyun($imageUrl, $context);
                } else {
                    $result = ['has_violation' => false, 'violations' => []];
                }

                if (!empty($result['violations'])) {
                    $violations = $result['violations'];
                    $maxSeverity = $result['severity'] ?? self::SEVERITY_MEDIUM;
                    $maxConfidence = $result['confidence'] ?? 0.8;
                }
            }
        } catch (\Exception $e) {
            Log::error('图片审核失败', [
                'image_url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
        }

        $resultData = [
            'has_violation' => !empty($violations),
            'violations' => $violations,
            'severity' => $maxSeverity,
            'confidence' => $maxConfidence
        ];

        // 缓存结果
        Cache::set($cacheKey, $resultData, 3600 * 24); // 缓存24小时

        return $resultData;
    }

    /**
     * 检测视频内容
     *
     * @param string $videoUrl 视频URL
     * @param array $context 上下文信息
     * @return array
     */
    public function checkVideo(string $videoUrl, array $context = []): array
    {
        if (empty($videoUrl)) {
            return [
                'has_violation' => false,
                'violations' => [],
                'severity' => self::SEVERITY_LOW,
                'confidence' => 0
            ];
        }

        // 检查缓存
        $cacheKey = 'video_moderation:' . md5($videoUrl);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $violations = [];
        $maxSeverity = self::SEVERITY_LOW;
        $maxConfidence = 0;

        try {
            // 调用第三方视频审核API
            if (Config::get('moderation.video.enabled', false)) {
                $provider = Config::get('moderation.video.provider', 'baidu');

                if ($provider === 'baidu') {
                    $result = $this->checkVideoByBaidu($videoUrl, $context);
                } elseif ($provider === 'aliyun') {
                    $result = $this->checkVideoByAliyun($videoUrl, $context);
                } else {
                    $result = ['has_violation' => false, 'violations' => []];
                }

                if (!empty($result['violations'])) {
                    $violations = $result['violations'];
                    $maxSeverity = $result['severity'] ?? self::SEVERITY_MEDIUM;
                    $maxConfidence = $result['confidence'] ?? 0.8;
                }
            }
        } catch (\Exception $e) {
            Log::error('视频审核失败', [
                'video_url' => $videoUrl,
                'error' => $e->getMessage()
            ]);
        }

        $resultData = [
            'has_violation' => !empty($violations),
            'violations' => $violations,
            'severity' => $maxSeverity,
            'confidence' => $maxConfidence
        ];

        // 缓存结果
        Cache::set($cacheKey, $resultData, 3600 * 24 * 7); // 缓存7天

        return $resultData;
    }

    /**
     * 检测音频内容
     *
     * @param string $audioUrl 音频URL
     * @param array $context 上下文信息
     * @return array
     */
    public function checkAudio(string $audioUrl, array $context = []): array
    {
        if (empty($audioUrl)) {
            return [
                'has_violation' => false,
                'violations' => [],
                'severity' => self::SEVERITY_LOW,
                'confidence' => 0
            ];
        }

        // 音频审核通常需要先转文字，再进行文本审核
        // 这里可以集成语音识别服务

        return [
            'has_violation' => false,
            'violations' => [],
            'severity' => self::SEVERITY_LOW,
            'confidence' => 0
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
                    // 模糊匹配（去掉空格、特殊字符）
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
                        'match_type' => $matchType
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
            'confidence' => empty($violations) ? 0 : 0.9
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
        $patterns = Config::get('moderation.patterns', []);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern['regex'], $text)) {
                $violations[] = [
                    'type' => $pattern['type'],
                    'severity' => $pattern['severity'],
                    'description' => $pattern['description'] ?? '检测到违规内容',
                    'pattern' => $pattern['name'] ?? 'unknown'
                ];
            }
        }

        $maxSeverity = empty($violations) ? self::SEVERITY_LOW : self::SEVERITY_MEDIUM;

        return [
            'violations' => $violations,
            'severity' => $maxSeverity,
            'confidence' => empty($violations) ? 0 : 0.85
        ];
    }

    /**
     * 通过第三方API检测文本
     *
     * @param string $text 文本内容
     * @param array $context 上下文
     * @return array
     */
    protected function checkTextByAPI(string $text, array $context = []): array
    {
        // 这里可以集成百度内容审核、阿里云内容安全等API
        // 示例返回格式
        return [
            'violations' => [],
            'severity' => self::SEVERITY_LOW,
            'confidence' => 0
        ];
    }

    /**
     * 通过百度API检测图片
     *
     * @param string $imageUrl 图片URL
     * @param array $context 上下文
     * @return array
     */
    protected function checkImageByBaidu(string $imageUrl, array $context = []): array
    {
        // 集成百度内容审核API
        // 参考: https://ai.baidu.com/tech/imagecensoring

        try {
            $config = Config::get('moderation.image.baidu', []);
            if (empty($config['app_id']) || empty($config['api_key'])) {
                Log::warning('百度图片审核配置不完整');
                return ['has_violation' => false, 'violations' => []];
            }

            // TODO: 调用百度API
            // 这里需要实现具体的API调用逻辑

            return ['has_violation' => false, 'violations' => []];
        } catch (\Exception $e) {
            Log::error('百度图片审核API调用失败', ['error' => $e->getMessage()]);
            return ['has_violation' => false, 'violations' => []];
        }
    }

    /**
     * 通过阿里云API检测图片
     *
     * @param string $imageUrl 图片URL
     * @param array $context 上下文
     * @return array
     */
    protected function checkImageByAliyun(string $imageUrl, array $context = []): array
    {
        // 集成阿里云内容安全API
        // 参考: https://help.aliyun.com/product/28417.html

        try {
            $config = Config::get('moderation.image.aliyun', []);
            if (empty($config['access_key']) || empty($config['secret_key'])) {
                Log::warning('阿里云图片审核配置不完整');
                return ['has_violation' => false, 'violations' => []];
            }

            // TODO: 调用阿里云API
            // 这里需要实现具体的API调用逻辑

            return ['has_violation' => false, 'violations' => []];
        } catch (\Exception $e) {
            Log::error('阿里云图片审核API调用失败', ['error' => $e->getMessage()]);
            return ['has_violation' => false, 'violations' => []];
        }
    }

    /**
     * 通过百度API检测视频
     *
     * @param string $videoUrl 视频URL
     * @param array $context 上下文
     * @return array
     */
    protected function checkVideoByBaidu(string $videoUrl, array $context = []): array
    {
        // 集成百度视频审核API
        return ['has_violation' => false, 'violations' => []];
    }

    /**
     * 通过阿里云API检测视频
     *
     * @param string $videoUrl 视频URL
     * @param array $context 上下文
     * @return array
     */
    protected function checkVideoByAliyun(string $videoUrl, array $context = []): array
    {
        // 集成阿里云视频审核API
        return ['has_violation' => false, 'violations' => []];
    }

    /**
     * 获取更高的严重程度
     *
     * @param string|null $current 当前严重程度
     * @param string $new 新的严重程度
     * @return string
     */
    protected function getHigherSeverity(?string $current, string $new): string
    {
        if ($current === null) {
            return $new;
        }

        $severityOrder = [
            self::SEVERITY_LOW => 1,
            self::SEVERITY_MEDIUM => 2,
            self::SEVERITY_HIGH => 3
        ];

        $currentLevel = $severityOrder[$current] ?? 1;
        $newLevel = $severityOrder[$new] ?? 1;

        return $newLevel > $currentLevel ? $new : $current;
    }

    /**
     * 批量检测素材
     *
     * @param array $materials 素材列表
     * @return array
     */
    public function batchCheckMaterials(array $materials): array
    {
        $results = [];

        foreach ($materials as $material) {
            $results[$material['id']] = $this->checkMaterial($material);
        }

        return $results;
    }
}
