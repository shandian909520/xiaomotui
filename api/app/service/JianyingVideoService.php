<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Log;
use think\facade\Cache;
use think\Exception;

/**
 * 剪映视频生成服务
 * 封装剪映开放平台API，提供视频生成功能
 */
class JianyingVideoService
{
    /**
     * 剪映API配置
     */
    protected $config = [];

    /**
     * 视频状态常量
     */
    const STATUS_PENDING = 'PENDING';        // 等待处理
    const STATUS_PROCESSING = 'PROCESSING';  // 处理中
    const STATUS_COMPLETED = 'COMPLETED';    // 已完成
    const STATUS_FAILED = 'FAILED';          // 失败

    /**
     * 视频分辨率常量
     */
    const RESOLUTION_720P = '720p';
    const RESOLUTION_1080P = '1080p';
    const RESOLUTION_4K = '4k';

    /**
     * 视频比例常量
     */
    const RATIO_16_9 = '16:9';    // 横屏
    const RATIO_9_16 = '9:16';    // 竖屏
    const RATIO_1_1 = '1:1';      // 方形

    /**
     * 视频风格常量
     */
    const STYLE_FOOD = 'food';           // 美食
    const STYLE_FASHION = 'fashion';     // 时尚
    const STYLE_TRAVEL = 'travel';       // 旅游
    const STYLE_VLOG = 'vlog';           // Vlog
    const STYLE_BUSINESS = 'business';   // 商务

    /**
     * API超时时间
     */
    const TIMEOUT_CREATE = 30;           // 创建视频超时30秒
    const TIMEOUT_QUERY = 10;            // 查询状态超时10秒

    /**
     * 重试配置
     */
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 2;               // 重试延迟（秒）

    public function __construct()
    {
        // 加载剪映配置
        $this->config = [
            'access_key' => config('ai.jianying.access_key', ''),
            'secret_key' => config('ai.jianying.secret_key', ''),
            'api_url' => config('ai.jianying.api_url', 'https://open.douyin.com/api'),
            'app_id' => config('ai.jianying.app_id', ''),
            'app_secret' => config('ai.jianying.app_secret', '')
        ];
    }

    /**
     * 创建视频生成任务
     *
     * @param array $params 视频参数
     * @return array
     */
    public function createVideoTask(array $params): array
    {
        Log::info('剪映视频生成任务开始', $params);

        try {
            // 验证参数
            $this->validateParams($params);

            // 构建请求数据
            $requestData = $this->buildRequestData($params);

            // 获取access_token
            $accessToken = $this->getAccessToken();

            // 调用剪映API创建视频任务
            $response = $this->callCreateVideoApi($requestData, $accessToken);

            Log::info('剪映视频任务创建成功', [
                'task_id' => $response['task_id'] ?? '',
                'status' => $response['status'] ?? ''
            ]);

            return [
                'success' => true,
                'task_id' => $response['task_id'] ?? '',
                'status' => self::STATUS_PENDING,
                'estimated_time' => $params['duration'] ?? 15,
                'message' => '视频生成任务已创建'
            ];

        } catch (Exception $e) {
            Log::error('剪映视频任务创建失败', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => '视频生成任务创建失败'
            ];
        }
    }

    /**
     * 查询视频生成任务状态
     *
     * @param string $taskId 任务ID
     * @return array
     */
    public function queryTaskStatus(string $taskId): array
    {
        Log::info('查询剪映视频任务状态', ['task_id' => $taskId]);

        try {
            // 获取access_token
            $accessToken = $this->getAccessToken();

            // 调用剪映API查询任务状态
            $response = $this->callQueryStatusApi($taskId, $accessToken);

            $status = $this->mapStatus($response['status'] ?? '');

            $result = [
                'success' => true,
                'task_id' => $taskId,
                'status' => $status,
                'progress' => $response['progress'] ?? 0
            ];

            // 如果已完成，添加视频信息
            if ($status === self::STATUS_COMPLETED) {
                $result['video_url'] = $response['video_url'] ?? '';
                $result['cover_url'] = $response['cover_url'] ?? '';
                $result['duration'] = $response['duration'] ?? 0;
                $result['file_size'] = $response['file_size'] ?? 0;
            }

            // 如果失败，添加错误信息
            if ($status === self::STATUS_FAILED) {
                $result['error'] = $response['error'] ?? '未知错误';
            }

            Log::info('剪映视频任务状态查询成功', $result);

            return $result;

        } catch (Exception $e) {
            Log::error('剪映视频任务状态查询失败', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成探店视频（便捷方法）
     *
     * @param array $params 参数
     * @return array
     */
    public function generateExploreVideo(array $params): array
    {
        // 设置默认参数
        $videoParams = [
            'type' => 'explore',
            'scene' => $params['scene'] ?? '商店',
            'style' => $params['style'] ?? self::STYLE_VLOG,
            'duration' => $params['duration'] ?? 15,
            'resolution' => self::RESOLUTION_1080P,
            'ratio' => self::RATIO_9_16,
            'materials' => $params['materials'] ?? [],
            'text_overlays' => $params['text_overlays'] ?? [],
            'music' => $params['music'] ?? 'auto',
            'transitions' => $params['transitions'] ?? 'auto'
        ];

        return $this->createVideoTask($videoParams);
    }

    /**
     * 使用模板生成视频
     *
     * @param string $templateId 模板ID
     * @param array $data 替换数据
     * @return array
     */
    public function generateFromTemplate(string $templateId, array $data): array
    {
        Log::info('使用模板生成视频', [
            'template_id' => $templateId,
            'data_keys' => array_keys($data)
        ]);

        try {
            // 获取模板信息
            $template = $this->getTemplateInfo($templateId);

            // 构建视频参数
            $videoParams = array_merge($template, [
                'materials' => $data['materials'] ?? [],
                'text_overlays' => $data['text_overlays'] ?? [],
                'custom_config' => $data['custom_config'] ?? []
            ]);

            return $this->createVideoTask($videoParams);

        } catch (Exception $e) {
            Log::error('使用模板生成视频失败', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 验证参数
     *
     * @param array $params 参数
     * @throws Exception
     */
    protected function validateParams(array $params): void
    {
        if (empty($params['scene'])) {
            throw new Exception('场景参数不能为空');
        }

        $duration = $params['duration'] ?? 0;
        if ($duration < 5 || $duration > 60) {
            throw new Exception('视频时长必须在5-60秒之间');
        }

        // 验证分辨率
        $validResolutions = [self::RESOLUTION_720P, self::RESOLUTION_1080P, self::RESOLUTION_4K];
        $resolution = $params['resolution'] ?? self::RESOLUTION_1080P;
        if (!in_array($resolution, $validResolutions)) {
            throw new Exception('不支持的分辨率');
        }

        // 验证比例
        $validRatios = [self::RATIO_16_9, self::RATIO_9_16, self::RATIO_1_1];
        $ratio = $params['ratio'] ?? self::RATIO_9_16;
        if (!in_array($ratio, $validRatios)) {
            throw new Exception('不支持的视频比例');
        }
    }

    /**
     * 构建请求数据
     *
     * @param array $params 参数
     * @return array
     */
    protected function buildRequestData(array $params): array
    {
        return [
            'project' => [
                'type' => 'auto_generate',
                'scene' => $params['scene'] ?? '通用',
                'style' => $params['style'] ?? self::STYLE_VLOG,
                'duration' => $params['duration'] ?? 15,
                'resolution' => $params['resolution'] ?? self::RESOLUTION_1080P,
                'ratio' => $params['ratio'] ?? self::RATIO_9_16
            ],
            'materials' => $params['materials'] ?? [],
            'text_overlays' => $params['text_overlays'] ?? [],
            'music' => [
                'mode' => $params['music'] ?? 'auto',
                'volume' => $params['music_volume'] ?? 0.3
            ],
            'transitions' => [
                'mode' => $params['transitions'] ?? 'auto',
                'style' => $params['transition_style'] ?? 'smooth'
            ],
            'effects' => $params['effects'] ?? [],
            'filters' => $params['filters'] ?? []
        ];
    }

    /**
     * 调用剪映API创建视频
     *
     * @param array $data 请求数据
     * @param string $accessToken 访问令牌
     * @return array
     */
    protected function callCreateVideoApi(array $data, string $accessToken): array
    {
        $url = $this->config['api_url'] . '/video/create';

        // 添加认证信息
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        // 实际生产环境中应该使用真实的HTTP请求
        // $response = $this->httpPost($url, $data, $headers, self::TIMEOUT_CREATE);

        // 模拟响应（实际开发时需要替换为真实API调用）
        return [
            'task_id' => 'jy_' . uniqid() . '_' . time(),
            'status' => 'pending',
            'message' => '任务已创建'
        ];
    }

    /**
     * 调用剪映API查询任务状态
     *
     * @param string $taskId 任务ID
     * @param string $accessToken 访问令牌
     * @return array
     */
    protected function callQueryStatusApi(string $taskId, string $accessToken): array
    {
        $url = $this->config['api_url'] . '/video/query';

        $params = [
            'task_id' => $taskId
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        // 实际生产环境中应该使用真实的HTTP请求
        // $response = $this->httpPost($url, $params, $headers, self::TIMEOUT_QUERY);

        // 模拟响应（实际开发时需要替换为真实API调用）
        return [
            'task_id' => $taskId,
            'status' => 'completed',
            'progress' => 100,
            'video_url' => 'https://example.com/videos/' . $taskId . '.mp4',
            'cover_url' => 'https://example.com/covers/' . $taskId . '.jpg',
            'duration' => 15,
            'file_size' => 5242880  // 5MB
        ];
    }

    /**
     * 获取AccessToken
     *
     * @return string
     */
    protected function getAccessToken(): string
    {
        $cacheKey = 'jianying_access_token';

        // 尝试从缓存获取
        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }

        // 获取新token
        $url = $this->config['api_url'] . '/oauth/access_token';

        $params = [
            'appid' => $this->config['app_id'],
            'secret' => $this->config['app_secret'],
            'grant_type' => 'client_credentials'
        ];

        // 实际生产环境中应该使用真实的HTTP请求
        // $response = $this->httpPost($url, $params, [], 10);
        // $token = $response['access_token'];

        // 模拟token（实际开发时需要替换为真实API调用）
        $token = 'jy_mock_token_' . time();

        // 缓存token（有效期2小时）
        Cache::set($cacheKey, $token, 7200);

        return $token;
    }

    /**
     * 映射剪映状态到系统状态
     *
     * @param string $jianyingStatus 剪映状态
     * @return string
     */
    protected function mapStatus(string $jianyingStatus): string
    {
        return match (strtolower($jianyingStatus)) {
            'pending', 'queued' => self::STATUS_PENDING,
            'processing', 'rendering' => self::STATUS_PROCESSING,
            'completed', 'success' => self::STATUS_COMPLETED,
            'failed', 'error' => self::STATUS_FAILED,
            default => self::STATUS_PENDING
        };
    }

    /**
     * 获取模板信息
     *
     * @param string $templateId 模板ID
     * @return array
     */
    protected function getTemplateInfo(string $templateId): array
    {
        // 从数据库或缓存获取模板信息
        $cacheKey = 'jianying_template_' . $templateId;

        $template = Cache::get($cacheKey);
        if ($template) {
            return $template;
        }

        // 实际开发中从数据库获取
        // $template = ContentTemplate::find($templateId);

        // 模拟模板数据
        $template = [
            'id' => $templateId,
            'scene' => '通用',
            'style' => self::STYLE_VLOG,
            'duration' => 15,
            'resolution' => self::RESOLUTION_1080P,
            'ratio' => self::RATIO_9_16
        ];

        Cache::set($cacheKey, $template, 3600);

        return $template;
    }

    /**
     * HTTP POST请求
     *
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @param int $timeout 超时时间
     * @return array
     */
    protected function httpPost(string $url, array $data, array $headers = [], int $timeout = 30): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('HTTP请求失败: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('HTTP状态码错误: ' . $httpCode);
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON解析失败: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * 重试机制包装
     *
     * @param callable $callback 回调函数
     * @param int $maxRetries 最大重试次数
     * @return mixed
     */
    protected function retry(callable $callback, int $maxRetries = self::MAX_RETRIES)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $callback();
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                Log::warning('剪映API调用失败，正在重试', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $maxRetries) {
                    sleep(self::RETRY_DELAY * $attempt);
                }
            }
        }

        throw $lastException;
    }

    /**
     * 获取服务状态
     *
     * @return array
     */
    public function getServiceStatus(): array
    {
        try {
            // 检查配置
            if (empty($this->config['app_id']) || empty($this->config['app_secret'])) {
                return [
                    'available' => false,
                    'message' => '剪映配置不完整'
                ];
            }

            // 尝试获取token
            $token = $this->getAccessToken();

            return [
                'available' => true,
                'message' => '剪映服务正常',
                'has_token' => !empty($token)
            ];

        } catch (Exception $e) {
            Log::error('获取剪映服务状态失败', ['error' => $e->getMessage()]);

            return [
                'available' => false,
                'message' => '剪映服务异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取支持的视频风格列表
     *
     * @return array
     */
    public function getSupportedStyles(): array
    {
        return [
            self::STYLE_FOOD => [
                'name' => '美食',
                'description' => '适合餐饮、美食探店',
                'features' => ['暖色调', '食物特写', '动态效果']
            ],
            self::STYLE_FASHION => [
                'name' => '时尚',
                'description' => '适合服装、美妆、潮牌',
                'features' => ['高对比度', '快节奏', '特效转场']
            ],
            self::STYLE_TRAVEL => [
                'name' => '旅游',
                'description' => '适合景点、酒店、民宿',
                'features' => ['广角镜头', '慢动作', '自然光']
            ],
            self::STYLE_VLOG => [
                'name' => 'Vlog',
                'description' => '通用生活记录',
                'features' => ['自然风格', '平稳过渡', '人物特写']
            ],
            self::STYLE_BUSINESS => [
                'name' => '商务',
                'description' => '适合企业、办公、会议',
                'features' => ['专业色调', '稳重节奏', '简洁布局']
            ]
        ];
    }

    /**
     * 清除缓存
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        try {
            Cache::delete('jianying_access_token');
            Log::info('剪映服务缓存已清除');
            return true;
        } catch (Exception $e) {
            Log::error('清除剪映服务缓存失败', ['error' => $e->getMessage()]);
            return false;
        }
    }
}