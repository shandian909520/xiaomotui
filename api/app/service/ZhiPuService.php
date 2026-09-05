<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Log;
use think\facade\Cache;
use app\model\SystemSetting;

/**
 * 智谱AI服务
 * 支持 CogView-3-Flash 图像生成、CogVideoX-Flash 视频生成
 */
class ZhiPuService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://open.bigmodel.cn/api/paas/v4';
    protected int $timeout = 120;

    public function __construct()
    {
        $this->apiKey = SystemSetting::getSetting('zhipu_api_key', 'ai', '');
        if (empty($this->apiKey)) {
            $aiConfig = Config::get('ai.zhipu', []);
            $this->apiKey = $aiConfig['api_key'] ?? '';
        }

        $dbTimeout = SystemSetting::getSetting('zhipu_timeout', 'ai', '');
        if ($dbTimeout) {
            $this->timeout = (int)$dbTimeout;
        }
    }

    /**
     * 图像生成 (CogView-3-Flash)
     */
    public function generateImage(array $params): array
    {
        $model = SystemSetting::getSetting('zhipu_image_model', 'ai', 'CogView-3-Flash');
        $prompt = $params['prompt'] ?? '';
        $size = $params['size'] ?? '1024x1024';

        if (empty($prompt)) {
            throw new \InvalidArgumentException('图像生成提示词不能为空');
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
        ];

        Log::info('智谱图像生成请求', ['model' => $model, 'prompt' => mb_substr($prompt, 0, 100)]);

        $response = $this->request('/images/generations', $payload);

        if (isset($response['data'][0]['url'])) {
            return [
                'success' => true,
                'url' => $response['data'][0]['url'],
                'model' => $model,
            ];
        }

        throw new \RuntimeException('图像生成失败：' . ($response['error']['message'] ?? '未知错误'));
    }

    /**
     * 视频生成 (CogVideoX-Flash)
     * 异步接口：提交任务 -> 轮询结果
     */
    public function generateVideo(array $params): array
    {
        $model = SystemSetting::getSetting('zhipu_video_model', 'ai', 'CogVideoX-Flash');
        $prompt = $params['prompt'] ?? '';
        $image_url = $params['image_url'] ?? '';
        $duration = $params['duration'] ?? 6;
        $resolution = $params['resolution'] ?? '720p';

        if (empty($prompt) && empty($image_url)) {
            throw new \InvalidArgumentException('视频生成需要提供提示词或参考图片');
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
        ];

        if (!empty($image_url)) {
            $payload['image_url'] = $image_url;
        }

        Log::info('智谱视频生成请求', ['model' => $model, 'prompt' => mb_substr($prompt, 0, 100)]);

        // CogVideoX 使用异步模式
        $response = $this->request('/videos/generations', $payload);

        if (isset($response['id'])) {
            return [
                'success' => true,
                'task_id' => $response['id'],
                'model' => $model,
                'status' => $response['task_status'] ?? 'PROCESSING',
            ];
        }

        if (isset($response['data'][0]['url'])) {
            return [
                'success' => true,
                'url' => $response['data'][0]['url'],
                'cover_url' => $response['data'][0]['cover_image_url'] ?? '',
                'model' => $model,
                'status' => 'SUCCESS',
            ];
        }

        throw new \RuntimeException('视频生成失败：' . ($response['error']['message'] ?? '未知错误'));
    }

    /**
     * 查询视频生成任务状态
     */
    public function queryVideoTask(string $taskId): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('智谱API Key未配置');
        }

        $url = $this->baseUrl . '/async-result/' . $taskId;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Log::error('智谱视频任务查询失败', ['task_id' => $taskId, 'http_code' => $httpCode, 'response' => $result]);
            return ['success' => false, 'status' => 'FAILED', 'message' => '查询失败'];
        }

        $response = json_decode($result, true);

        if (isset($response['task_status'])) {
            $status = $response['task_status'];
            $result_data = [
                'success' => true,
                'task_id' => $taskId,
                'status' => $status,
            ];

            if ($status === 'SUCCESS' && isset($response['video_result'][0])) {
                $video = $response['video_result'][0];
                $result_data['url'] = $video['url'] ?? '';
                $result_data['cover_url'] = $video['cover_image_url'] ?? '';
            }

            return $result_data;
        }

        return ['success' => false, 'status' => 'UNKNOWN', 'message' => '未知响应'];
    }

    /**
     * 测试连接
     */
    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API Key未配置'];
        }

        try {
            $result = $this->generateImage([
                'prompt' => '一只可爱的小猫在阳光下睡觉',
                'size' => '512x512',
            ]);

            return [
                'success' => true,
                'message' => '智谱AI连接成功',
                'model' => $result['model'] ?? '',
                'preview_url' => $result['url'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '连接失败：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 获取配置状态
     */
    public function getConfigStatus(): array
    {
        $imageModel = SystemSetting::getSetting('zhipu_image_model', 'ai', 'CogView-3-Flash');
        $videoModel = SystemSetting::getSetting('zhipu_video_model', 'ai', 'CogVideoX-Flash');

        return [
            'api_key' => !empty($this->apiKey) ? substr($this->apiKey, 0, 8) . '***' : '',
            'is_configured' => !empty($this->apiKey),
            'image_model' => $imageModel,
            'video_model' => $videoModel,
            'models' => [
                'CogView-3-Flash' => 'CogView-3-Flash (图像生成-快速)',
                'CogView-3-Plus' => 'CogView-3-Plus (图像生成-增强)',
                'CogVideoX-Flash' => 'CogVideoX-Flash (视频生成-快速)',
                'CogVideoX-2' => 'CogVideoX-2 (视频生成-标准)',
            ],
        ];
    }

    /**
     * 发送HTTP请求
     */
    protected function request(string $endpoint, array $payload): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('智谱API Key未配置');
        }

        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('智谱AI请求cURL错误', ['endpoint' => $endpoint, 'error' => $error]);
            throw new \RuntimeException('请求失败：' . $error);
        }

        $response = json_decode($result, true);

        if ($httpCode !== 200) {
            $errMsg = $response['error']['message'] ?? "HTTP {$httpCode}";
            Log::error('智谱AI请求失败', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'error' => $errMsg,
            ]);
            throw new \RuntimeException($errMsg);
        }

        return $response ?? [];
    }
}
