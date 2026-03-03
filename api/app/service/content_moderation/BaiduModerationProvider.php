<?php
declare(strict_types=1);

namespace app\service\content_moderation;

use think\facade\Config;
use think\facade\Log;
use think\facade\Cache;

/**
 * 百度云内容审核服务商
 * 文档: https://ai.baidu.com/tech/textcensoring
 */
class BaiduModerationProvider implements ModerationProviderInterface
{
    /**
     * @var array 配置信息
     */
    private array $config;

    /**
     * @var string 访问令牌
     */
    private ?string $accessToken = null;

    /**
     * @var int 优先级
     */
    private int $priority = 1;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = Config::get('content_moderation.baidu', []);
        $this->priority = Config::get('content_moderation.image.providers.baidu.priority', 1);
    }

    /**
     * @inheritDoc
     */
    public function checkText(string $text, array $options = []): array
    {
        try {
            if (empty($text)) {
                return $this->formatResult(true, 100, 1.0, [], 'pass');
            }

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('无法获取访问令牌');
            }

            $endpoint = $this->config['endpoints']['text'] ?? '';
            $url = $endpoint . '?access_token=' . $accessToken;

            $postData = [
                'content' => $text,
            ];

            // 添加可选参数
            if (!empty($options['user_id'])) {
                $postData['userId'] = $options['user_id'];
            }

            if (!empty($options['device_id'])) {
                $postData['deviceId'] = $options['device_id'];
            }

            $response = $this->sendPostRequest($url, $postData);
            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('解析响应失败: ' . json_last_error_msg());
            }

            return $this->parseTextResult($result);

        } catch (\Exception $e) {
            Log::error('百度文本审核失败', [
                'text' => substr($text, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->formatErrorResult($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function checkImage(string $imageUrl, array $options = []): array
    {
        try {
            if (empty($imageUrl)) {
                return $this->formatResult(true, 100, 1.0, [], 'pass');
            }

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('无法获取访问令牌');
            }

            $endpoint = $this->config['endpoints']['image'] ?? '';
            $url = $endpoint . '?access_token=' . $accessToken;

            // 判断是URL还是Base64
            $isBase64 = $this->isBase64Image($imageUrl);
            $imageKey = $isBase64 ? 'image' : 'imgUrl';

            $postData = [
                $imageKey => $imageUrl,
            ];

            // 添加可选参数
            if (!empty($options['image_type'])) {
                $postData['imageType'] = $options['image_type'];
            }

            $response = $this->sendPostRequest($url, $postData);
            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('解析响应失败: ' . json_last_error_msg());
            }

            return $this->parseImageResult($result);

        } catch (\Exception $e) {
            Log::error('百度图片审核失败', [
                'image_url' => substr($imageUrl, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->formatErrorResult($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function checkVideo(string $videoUrl, array $options = []): array
    {
        try {
            if (empty($videoUrl)) {
                return $this->formatResult(true, 100, 1.0, [], 'pass');
            }

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('无法获取访问令牌');
            }

            $endpoint = $this->config['endpoints']['video'] ?? '';
            $url = $endpoint . '?access_token=' . $accessToken;

            $postData = [
                'videoUrl' => $videoUrl,
            ];

            // 添加可选参数
            if (!empty($options['frames'])) {
                $postData['frames'] = $options['frames'];
            }

            if (!empty($options['interval'])) {
                $postData['interval'] = $options['interval'];
            }

            $response = $this->sendPostRequest($url, $postData);
            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('解析响应失败: ' . json_last_error_msg());
            }

            return $this->parseVideoResult($result);

        } catch (\Exception $e) {
            Log::error('百度视频审核失败', [
                'video_url' => substr($videoUrl, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->formatErrorResult($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function checkAudio(string $audioUrl, array $options = []): array
    {
        try {
            if (empty($audioUrl)) {
                return $this->formatResult(true, 100, 1.0, [], 'pass');
            }

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('无法获取访问令牌');
            }

            $endpoint = $this->config['endpoints']['audio'] ?? '';
            $url = $endpoint . '?access_token=' . $accessToken;

            $postData = [
                'audioUrl' => $audioUrl,
            ];

            // 添加可选参数
            if (!empty($options['format'])) {
                $postData['format'] = $options['format'];
            }

            $response = $this->sendPostRequest($url, $postData);
            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('解析响应失败: ' . json_last_error_msg());
            }

            return $this->parseAudioResult($result);

        } catch (\Exception $e) {
            Log::error('百度音频审核失败', [
                'audio_url' => substr($audioUrl, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->formatErrorResult($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function batchCheckText(array $texts, array $options = []): array
    {
        $results = [];
        foreach ($texts as $index => $text) {
            $results[$index] = $this->checkText($text, $options);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function batchCheckImage(array $imageUrls, array $options = []): array
    {
        $results = [];
        foreach ($imageUrls as $index => $url) {
            $results[$index] = $this->checkImage($url, $options);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function getProviderName(): string
    {
        return 'baidu';
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return !empty($this->config['app_id'])
            && !empty($this->config['api_key'])
            && !empty($this->config['secret_key']);
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * 获取访问令牌
     *
     * @return string|null
     */
    private function getAccessToken(): ?string
    {
        // 先从缓存获取
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $cacheKey = 'baidu_access_token_' . md5($this->config['api_key'] ?? '');
        $token = Cache::get($cacheKey);
        if ($token) {
            $this->accessToken = $token;
            return $token;
        }

        // 请求新token
        try {
            $endpoint = $this->config['endpoints']['oauth'] ?? '';
            $postData = [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config['api_key'] ?? '',
                'client_secret' => $this->config['secret_key'] ?? '',
            ];

            $response = $this->sendPostRequest($endpoint, $postData);
            $result = json_decode($response, true);

            if (isset($result['access_token'])) {
                $this->accessToken = $result['access_token'];
                // 缓存token,有效期减去300秒作为缓冲
                $expiresIn = ($result['expires_in'] ?? 2592000) - 300;
                Cache::set($cacheKey, $this->accessToken, $expiresIn);

                return $this->accessToken;
            }

            Log::error('获取百度访问令牌失败', ['response' => $response]);
            return null;

        } catch (\Exception $e) {
            Log::error('获取百度访问令牌异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * 发送POST请求
     *
     * @param string $url 请求URL
     * @param array $data POST数据
     * @return string
     * @throws \Exception
     */
    private function sendPostRequest(string $url, array $data): string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('CURL错误: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception('HTTP错误: ' . $httpCode);
        }

        return $response;
    }

    /**
     * 解析文本审核结果
     *
     * @param array $result API返回结果
     * @return array
     */
    private function parseTextResult(array $result): array
    {
        if (isset($result['error_code'])) {
            throw new \Exception($result['error_msg'] ?? '未知错误');
        }

        $pass = true;
        $score = 100;
        $confidence = 1.0;
        $violations = [];
        $suggestion = 'pass';

        if (isset($result['result']) && is_array($result['result'])) {
            foreach ($result['result'] as $item) {
                // 百度返回: 0-正常, 1-违规
                $isViolation = ($item['conclusionType'] ?? 0) != 0;

                if ($isViolation) {
                    $pass = false;
                    $suggestion = 'reject';

                    // 获取违规类型
                    $violationType = $this->mapViolationType($item['type'] ?? 0);
                    $severity = $this->getViolationSeverity($violationType);

                    $violations[] = [
                        'type' => $violationType,
                        'type_name' => $this->getViolationTypeName($violationType),
                        'severity' => $severity,
                        'confidence' => $item['score'] ?? 0.5,
                        'description' => $item['msg'] ?? '检测到违规内容',
                        'details' => $item['data'] ?? [],
                    ];

                    // 更新评分和置信度
                    $score = min($score, 100 - ($item['score'] ?? 50));
                    $confidence = max($confidence, ($item['score'] ?? 50) / 100);
                }
            }
        }

        // 根据违规情况给出建议
        if (!$pass && $confidence >= 0.8) {
            $suggestion = 'reject';
        } elseif (!$pass && $confidence >= 0.5) {
            $suggestion = 'review';
        }

        return $this->formatResult($pass, $score, $confidence, $violations, $suggestion);
    }

    /**
     * 解析图片审核结果
     *
     * @param array $result API返回结果
     * @return array
     */
    private function parseImageResult(array $result): array
    {
        if (isset($result['error_code'])) {
            throw new \Exception($result['error_msg'] ?? '未知错误');
        }

        $pass = true;
        $score = 100;
        $confidence = 1.0;
        $violations = [];
        $suggestion = 'pass';

        // 检查是否违规
        if (isset($result['conclusionType'])) {
            // 0-正常, 1-违规, 2-疑似
            $conclusionType = $result['conclusionType'];

            if ($conclusionType == 1) {
                $pass = false;
                $suggestion = 'reject';
                $confidence = 0.9;
            } elseif ($conclusionType == 2) {
                $pass = false;
                $suggestion = 'review';
                $confidence = 0.6;
            }
        }

        // 解析违规数据
        if (!empty($result['data']) && is_array($result['data'])) {
            foreach ($result['data'] as $item) {
                $violationType = $this->mapViolationType($item['type'] ?? 0);
                $severity = $this->getViolationSeverity($violationType);

                $violations[] = [
                    'type' => $violationType,
                    'type_name' => $this->getViolationTypeName($violationType),
                    'severity' => $severity,
                    'confidence' => $item['probability'] ?? 0.5,
                    'description' => $item['msg'] ?? '检测到违规内容',
                    'details' => $item,
                ];

                $score = min($score, 100 - (($item['probability'] ?? 0) * 100));
            }
        }

        return $this->formatResult($pass, $score, $confidence, $violations, $suggestion);
    }

    /**
     * 解析视频审核结果
     *
     * @param array $result API返回结果
     * @return array
     */
    private function parseVideoResult(array $result): array
    {
        if (isset($result['error_code'])) {
            throw new \Exception($result['error_msg'] ?? '未知错误');
        }

        // 视频审核结果与图片类似
        return $this->parseImageResult($result);
    }

    /**
     * 解析音频审核结果
     *
     * @param array $result API返回结果
     * @return array
     */
    private function parseAudioResult(array $result): array
    {
        if (isset($result['error_code'])) {
            throw new \Exception($result['error_msg'] ?? '未知错误');
        }

        // 音频审核通常包含文本审核结果
        return $this->parseTextResult($result);
    }

    /**
     * 映射违规类型
     *
     * @param int $baiduType 百度违规类型代码
     * @return string
     */
    private function mapViolationType(int $baiduType): string
    {
        $map = $this->config['violation_map'] ?? [];
        return $map[$baiduType] ?? 'OTHER';
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
     * 获取违规严重程度
     *
     * @param string $type 违规类型
     * @return string
     */
    private function getViolationSeverity(string $type): string
    {
        $violationTypes = Config::get('content_moderation.violation_types', []);
        return $violationTypes[$type]['severity'] ?? 'MEDIUM';
    }

    /**
     * 格式化结果
     *
     * @param bool $pass 是否通过
     * @param int $score 评分
     * @param float $confidence 置信度
     * @param array $violations 违规列表
     * @param string $suggestion 建议
     * @return array
     */
    private function formatResult(
        bool $pass,
        int $score,
        float $confidence,
        array $violations,
        string $suggestion
    ): array {
        return [
            'pass' => $pass,
            'score' => $score,
            'confidence' => $confidence,
            'violations' => $violations,
            'suggestion' => $suggestion,
            'provider' => $this->getProviderName(),
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
            'pass' => false,
            'score' => 0,
            'confidence' => 0,
            'violations' => [],
            'suggestion' => 'review',
            'provider' => $this->getProviderName(),
            'error' => $error,
            'check_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 判断是否为Base64图片
     *
     * @param string $str 字符串
     * @return bool
     */
    private function isBase64Image(string $str): bool
    {
        return strpos($str, 'data:image') === 0 || preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $str);
    }
}
