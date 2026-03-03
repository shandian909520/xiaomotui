<?php
declare(strict_types=1);

namespace app\service\content_moderation;

use think\facade\Config;
use think\facade\Log;
use think\facade\Cache;

/**
 * 阿里云内容安全审核服务商
 * 文档: https://help.aliyun.com/product/28417.html
 */
class AliyunModerationProvider implements ModerationProviderInterface
{
    /**
     * @var array 配置信息
     */
    private array $config;

    /**
     * @var int 优先级
     */
    private int $priority = 2;

    /**
     * @var array 请求ID
     */
    private array $requestIds = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = Config::get('content_moderation.aliyun', []);
        $this->priority = Config::get('content_moderation.image.providers.aliyun.priority', 2);
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

            if (!$this->isAvailable()) {
                throw new \Exception('阿里云配置不完整');
            }

            $endpoint = $this->config['endpoints']['green_text'] ?? '';
            $action = 'TextModeration';
            $version = '2022-03-02';

            $requestData = [
                'Service' => 'comment_detection',
                'ServiceParameters' => json_encode([
                    'content' => $text,
                ]),
            ];

            // 添加可选参数
            if (!empty($options['data_id'])) {
                $requestData['DataId'] = $options['data_id'];
            }

            $result = $this->sendRequest($endpoint, $action, $version, $requestData);
            return $this->parseTextResult($result);

        } catch (\Exception $e) {
            Log::error('阿里云文本审核失败', [
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

            if (!$this->isAvailable()) {
                throw new \Exception('阿里云配置不完整');
            }

            $endpoint = $this->config['endpoints']['green_image'] ?? '';
            $action = 'ImageModeration';
            $version = '2023-05-30';

            // 判断是URL还是Base64
            $isBase64 = $this->isBase64Image($imageUrl);
            $imageKey = $isBase64 ? 'imageUrl' : 'imageUrl';

            $requestData = [
                'Service' => 'baselineCheck',
                'ServiceParameters' => json_encode([
                    $imageKey => $imageUrl,
                    'dataId' => $options['data_id'] ?? uniqid('img_'),
                ]),
            ];

            $result = $this->sendRequest($endpoint, $action, $version, $requestData);
            return $this->parseImageResult($result);

        } catch (\Exception $e) {
            Log::error('阿里云图片审核失败', [
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

            if (!$this->isAvailable()) {
                throw new \Exception('阿里云配置不完整');
            }

            $endpoint = $this->config['endpoints']['green_video'] ?? '';
            $action = 'VideoModeration';
            $version = '2022-03-02';

            $requestData = [
                'Service' => 'video_detection',
                'ServiceParameters' => json_encode([
                    'videoUrl' => $videoUrl,
                    'dataId' => $options['data_id'] ?? uniqid('video_'),
                    'frames' => $options['frames'] ?? 10,
                ]),
            ];

            $result = $this->sendRequest($endpoint, $action, $version, $requestData);
            return $this->parseVideoResult($result);

        } catch (\Exception $e) {
            Log::error('阿里云视频审核失败', [
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

            if (!$this->isAvailable()) {
                throw new \Exception('阿里云配置不完整');
            }

            $endpoint = $this->config['endpoints']['green_audio'] ?? '';
            $action = 'AudioModeration';
            $version = '2022-03-02';

            $requestData = [
                'Service' => 'audio_detection',
                'ServiceParameters' => json_encode([
                    'audioUrl' => $audioUrl,
                    'dataId' => $options['data_id'] ?? uniqid('audio_'),
                ]),
            ];

            $result = $this->sendRequest($endpoint, $action, $version, $requestData);
            return $this->parseAudioResult($result);

        } catch (\Exception $e) {
            Log::error('阿里云音频审核失败', [
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
        return 'aliyun';
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return !empty($this->config['access_key_id'])
            && !empty($this->config['access_key_secret']);
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * 发送请求到阿里云API
     *
     * @param string $endpoint API端点
     * @param string $action API动作
     * @param string $version API版本
     * @param array $requestData 请求数据
     * @return array
     * @throws \Exception
     */
    private function sendRequest(string $endpoint, string $action, string $version, array $requestData): array
    {
        // 构造公共参数
        $commonParams = [
            'Action' => $action,
            'Version' => $version,
            'Format' => 'JSON',
            'AccessKeyId' => $this->config['access_key_id'],
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => uniqid((string) mt_rand(10000, 99999)),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $params = array_merge($commonParams, $requestData);

        // 计算签名
        $signature = $this->computeSignature($params);
        $params['Signature'] = $signature;

        // 构造URL
        $url = 'https://' . $endpoint . '/?' . http_build_query($params);

        // 发送请求
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
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

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('解析响应失败: ' . json_last_error_msg());
        }

        if (isset($result['Code']) && $result['Code'] !== '200') {
            throw new \Exception($result['Message'] ?? '未知错误');
        }

        // 保存请求ID用于后续查询
        if (isset($result['DataId'])) {
            $this->requestIds[$result['DataId']] = $result['RequestId'] ?? '';
        }

        return $result;
    }

    /**
     * 计算签名
     *
     * @param array $params 参数
     * @return string
     */
    private function computeSignature(array $params): string
    {
        ksort($params);

        $stringToSign = 'GET&%2F&' . urlencode(http_build_query($params, '', '&', PHP_QUERY_RFC3986));

        $accessKeySecret = $this->config['access_key_secret'] . '&';
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret, true));

        return $signature;
    }

    /**
     * 解析文本审核结果
     *
     * @param array $result API返回结果
     * @return array
     */
    private function parseTextResult(array $result): array
    {
        $pass = true;
        $score = 100;
        $confidence = 1.0;
        $violations = [];
        $suggestion = 'pass';

        // 阿里云返回格式
        if (isset($result['Data'])) {
            $data = json_decode($result['Data'], true);

            if (isset($data['results']) && is_array($data['results'])) {
                foreach ($data['results'] as $item) {
                    $label = $item['label'] ?? '';
                    $riskLevel = $item['riskLevel'] ?? 'pass';

                    // 阿里云风险级别: pass-通过, review-需审核, reject-拒绝
                    if ($riskLevel === 'reject') {
                        $pass = false;
                        $suggestion = 'reject';
                        $confidence = $item['rate'] ?? 0.9;
                    } elseif ($riskLevel === 'review') {
                        $pass = false;
                        $suggestion = 'review';
                        $confidence = $item['rate'] ?? 0.6;
                    }

                    if (!empty($label) && $riskLevel !== 'pass') {
                        $violationType = $this->mapViolationType($label);
                        $severity = $this->getViolationSeverity($violationType);

                        $violations[] = [
                            'type' => $violationType,
                            'type_name' => $this->getViolationTypeName($violationType),
                            'severity' => $severity,
                            'confidence' => $item['rate'] ?? 0.5,
                            'description' => $item['suggestion'] ?? '检测到违规内容',
                            'details' => $item,
                        ];

                        $score = min($score, 100 - (($item['rate'] ?? 0) * 100));
                    }
                }
            }
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
        $pass = true;
        $score = 100;
        $confidence = 1.0;
        $violations = [];
        $suggestion = 'pass';

        if (isset($result['Data'])) {
            $data = json_decode($result['Data'], true);

            if (isset($data['results']) && is_array($data['results'])) {
                foreach ($data['results'] as $item) {
                    $label = $item['label'] ?? '';
                    $riskLevel = $item['riskLevel'] ?? 'pass';

                    if ($riskLevel === 'reject') {
                        $pass = false;
                        $suggestion = 'reject';
                        $confidence = $item['rate'] ?? 0.9;
                    } elseif ($riskLevel === 'review') {
                        $pass = false;
                        $suggestion = 'review';
                        $confidence = $item['rate'] ?? 0.6;
                    }

                    if (!empty($label) && $riskLevel !== 'pass') {
                        $violationType = $this->mapViolationType($label);
                        $severity = $this->getViolationSeverity($violationType);

                        $violations[] = [
                            'type' => $violationType,
                            'type_name' => $this->getViolationTypeName($violationType),
                            'severity' => $severity,
                            'confidence' => $item['rate'] ?? 0.5,
                            'description' => $item['suggestion'] ?? '检测到违规内容',
                            'details' => $item,
                        ];

                        $score = min($score, 100 - (($item['rate'] ?? 0) * 100));
                    }
                }
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
        // 音频审核通常包含文本审核结果
        return $this->parseTextResult($result);
    }

    /**
     * 映射违规类型
     *
     * @param string $aliyunType 阿里云违规类型
     * @return string
     */
    private function mapViolationType(string $aliyunType): string
    {
        $map = $this->config['violation_map'] ?? [];
        return $map[$aliyunType] ?? 'OTHER';
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
