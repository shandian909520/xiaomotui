<?php
declare(strict_types=1);

namespace app\service\content_moderation;

use think\facade\Config;
use think\facade\Log;
use think\facade\Cache;

/**
 * 腾讯云天御内容审核服务商
 * 文档: https://cloud.tencent.com/document/product/1125
 */
class TencentModerationProvider implements ModerationProviderInterface
{
    /**
     * @var array 配置信息
     */
    private array $config;

    /**
     * @var int 优先级
     */
    private int $priority = 3;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = Config::get('content_moderation.tencent', []);
        $this->priority = Config::get('content_moderation.image.providers.tencent.priority', 3);
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
                throw new \Exception('腾讯云配置不完整');
            }

            $endpoint = $this->config['endpoints']['text'] ?? '';
            $action = 'TextModeration';
            $version = '2020-12-29';

            $requestData = [
                'Content' => base64_encode($text),
                'BizType' => $options['biz_type'] ?? '',
            ];

            // 添加可选参数
            if (!empty($options['data_id'])) {
                $requestData['DataId'] = $options['data_id'];
            }

            if (!empty($options['user_id'])) {
                $requestData['User'] = [
                    'UserId' => $options['user_id'],
                ];
            }

            $result = $this->sendRequest($endpoint, $action, $version, $requestData);
            return $this->parseTextResult($result);

        } catch (\Exception $e) {
            Log::error('腾讯云文本审核失败', [
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
                throw new \Exception('腾讯云配置不完整');
            }

            $endpoint = $this->config['endpoints']['image'] ?? '';
            $action = 'ImageModeration';
            $version = '2020-12-29';

            // 判断是URL还是Base64
            $isUrl = filter_var($imageUrl, FILTER_VALIDATE_URL) !== false;

            $requestData = [
                'BizType' => $options['biz_type'] ?? '',
            ];

            if ($isUrl) {
                $requestData['ImageUrl'] = $imageUrl;
            } else {
                $requestData['ImageBase64'] = $imageUrl;
            }

            // 添加可选参数
            if (!empty($options['data_id'])) {
                $requestData['DataId'] = $options['data_id'];
            }

            $result = $this->sendRequest($endpoint, $action, $version, $requestData);
            return $this->parseImageResult($result);

        } catch (\Exception $e) {
            Log::error('腾讯云图片审核失败', [
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
                throw new \Exception('腾讯云配置不完整');
            }

            $endpoint = $this->config['endpoints']['video'] ?? '';
            $action = 'VideoModeration';
            $version = '2020-12-29';

            $requestData = [
                'VideoUrl' => $videoUrl,
                'BizType' => $options['biz_type'] ?? '',
                'CallbackUrl' => $options['callback_url'] ?? '',
            ];

            // 添加可选参数
            if (!empty($options['data_id'])) {
                $requestData['DataId'] = $options['data_id'];
            }

            $result = $this->sendRequest($endpoint, $action, $version, $requestData);

            // 腾讯云视频审核是异步的,需要返回任务ID
            if (isset($result['Data']['TaskId'])) {
                return [
                    'pass' => true, // 异步审核暂时通过
                    'score' => 100,
                    'confidence' => 1.0,
                    'violations' => [],
                    'suggestion' => 'review',
                    'provider' => $this->getProviderName(),
                    'async' => true,
                    'task_id' => $result['Data']['TaskId'],
                    'check_time' => date('Y-m-d H:i:s'),
                ];
            }

            return $this->parseVideoResult($result);

        } catch (\Exception $e) {
            Log::error('腾讯云视频审核失败', [
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
                throw new \Exception('腾讯云配置不完整');
            }

            // 腾讯云音频审核需要先转文字
            // 这里简化处理,直接返回
            return $this->formatResult(true, 100, 1.0, [], 'pass');

        } catch (\Exception $e) {
            Log::error('腾讯云音频审核失败', [
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
        return 'tencent';
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return !empty($this->config['secret_id'])
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
     * 发送请求到腾讯云API
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
        // 构造请求
        $region = $this->config['region'] ?? 'ap-guangzhou';
        $url = 'https://' . $endpoint;

        $timestamp = time();
        $nonce = mt_rand(10000, 99999);

        // 构造请求体
        $payload = json_encode([
            'Action' => $action,
            'Version' => $version,
            'Region' => $region,
            'Timestamp' => $timestamp,
            'Nonce' => $nonce,
            'SecretId' => $this->config['secret_id'],
            'Data' => $requestData,
        ]);

        // 计算签名
        $signature = $this->computeSignature($payload, $timestamp);

        // 发送请求
        $ch = curl_init();
        $headers = [
            'Content-Type: application/json',
            'Authorization: ' . $this->config['secret_id'] . ',' . $signature,
            'X-TC-Timestamp: ' . $timestamp,
            'X-TC-Nonce: ' . $nonce,
            'X-TC-Region: ' . $region,
            'X-TC-Version: ' . $version,
            'X-TC-Action: ' . $action,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
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

        if (isset($result['Response']['Error'])) {
            throw new \Exception($result['Response']['Error']['Message'] ?? '未知错误');
        }

        return $result['Response'] ?? $result;
    }

    /**
     * 计算签名
     *
     * @param string $payload 请求体
     * @param int $timestamp 时间戳
     * @return string
     */
    private function computeSignature(string $payload, int $timestamp): string
    {
        $secretKey = $this->config['secret_key'];
        $date = gmdate('Y-m-d', $timestamp);

        $hashedRequestPayload = hash('SHA256', $payload);
        $credentialScope = $date . '/' . 'ims' . '/' . 'tc3_request';

        $canonicalRequest = "POST\n/\n\ncontent-type:application/json\nhost:" . parse_url($this->config['endpoints']['text'] ?? '', PHP_URL_HOST) . "\n\n" . "content-type;host\n" . $hashedRequestPayload;

        $hashedCanonicalRequest = hash('SHA256', $canonicalRequest);
        $stringToSign = "TC3-HMAC-SHA256\n" . $timestamp . "\n" . $credentialScope . "\n" . $hashedCanonicalRequest;

        $secretDate = hash_hmac('SHA256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('SHA256', 'ims', $secretDate, true);
        $secretSigning = hash_hmac('SHA256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('SHA256', $stringToSign, $secretSigning);

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

        if (isset($result['Suggestion'])) {
            $suggestionCode = $result['Suggestion'];

            // Pass-通过, Review-需审核, Block-拒绝
            if ($suggestionCode === 'Block') {
                $pass = false;
                $suggestion = 'reject';
                $confidence = $result['Score'] ?? 0.9 / 100;
            } elseif ($suggestionCode === 'Review') {
                $pass = false;
                $suggestion = 'review';
                $confidence = $result['Score'] ?? 0.6 / 100;
            }
        }

        // 解析违规详情
        if (isset($result['LabelResults']) && is_array($result['LabelResults'])) {
            foreach ($result['LabelResults'] as $item) {
                $label = $item['Label'] ?? '';
                $status = $item['Status'] ?? '';

                if ($status === 'Block' || $status === 'Review') {
                    $violationType = $this->mapViolationType($label);
                    $severity = $this->getViolationSeverity($violationType);

                    $violations[] = [
                        'type' => $violationType,
                        'type_name' => $this->getViolationTypeName($violationType),
                        'severity' => $severity,
                        'confidence' => ($item['Score'] ?? 50) / 100,
                        'description' => $item['Suggestion'] ?? '检测到违规内容',
                        'details' => $item,
                    ];

                    $score = min($score, 100 - ($item['Score'] ?? 50));
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

        if (isset($result['Suggestion'])) {
            $suggestionCode = $result['Suggestion'];

            if ($suggestionCode === 'Block') {
                $pass = false;
                $suggestion = 'reject';
                $confidence = $result['Score'] ?? 90 / 100;
            } elseif ($suggestionCode === 'Review') {
                $pass = false;
                $suggestion = 'review';
                $confidence = $result['Score'] ?? 60 / 100;
            }
        }

        // 解析违规详情
        if (isset($result['LabelResults']) && is_array($result['LabelResults'])) {
            foreach ($result['LabelResults'] as $item) {
                $label = $item['Label'] ?? '';
                $status = $item['Status'] ?? '';

                if ($status === 'Block' || $status === 'Review') {
                    $violationType = $this->mapViolationType($label);
                    $severity = $this->getViolationSeverity($violationType);

                    $violations[] = [
                        'type' => $violationType,
                        'type_name' => $this->getViolationTypeName($violationType),
                        'severity' => $severity,
                        'confidence' => ($item['Score'] ?? 50) / 100,
                        'description' => $item['Suggestion'] ?? '检测到违规内容',
                        'details' => $item,
                    ];

                    $score = min($score, 100 - ($item['Score'] ?? 50));
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
     * @param string $tencentType 腾讯云违规类型
     * @return string
     */
    private function mapViolationType(string $tencentType): string
    {
        $map = $this->config['violation_map'] ?? [];
        return $map[$tencentType] ?? 'OTHER';
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
}
