<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Log;
use think\facade\Cache;
use think\Exception;

/**
 * AI内容生成服务
 * 整合百度文心一言、讯飞星火、剪映API等AI服务
 */
class AiContentService
{
    /**
     * AI服务提供商常量
     */
    const PROVIDER_WENXIN = 'wenxin';        // 百度文心一言
    const PROVIDER_XINGHUO = 'xinghuo';      // 讯飞星火
    const PROVIDER_JIANYING = 'jianying';    // 剪映
    const PROVIDER_ZHIYING = 'zhiying';      // 腾讯智影

    /**
     * 内容类型常量
     */
    const TYPE_TEXT = 'TEXT';                // 文案生成
    const TYPE_VIDEO = 'VIDEO';              // 视频生成
    const TYPE_IMAGE = 'IMAGE';              // 图片生成

    /**
     * 生成状态常量
     */
    const STATUS_PENDING = 'PENDING';        // 等待中
    const STATUS_PROCESSING = 'PROCESSING';  // 生成中
    const STATUS_COMPLETED = 'COMPLETED';    // 已完成
    const STATUS_FAILED = 'FAILED';          // 失败

    /**
     * 超时时间设置
     */
    const TIMEOUT_TEXT = 30;                 // 文案生成超时30秒
    const TIMEOUT_VIDEO = 120;               // 视频生成超时120秒
    const TIMEOUT_IMAGE = 60;                // 图片生成超时60秒

    /**
     * 重试次数
     */
    const MAX_RETRIES = 3;

    /**
     * 配置信息
     */
    protected $config = [];

    public function __construct()
    {
        // 加载AI服务配置
        $this->config = [
            'wenxin' => [
                'api_key' => config('ai.wenxin.api_key'),
                'secret_key' => config('ai.wenxin.secret_key'),
                'api_url' => config('ai.wenxin.api_url', 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/completions')
            ],
            'xinghuo' => [
                'app_id' => config('ai.xinghuo.app_id'),
                'api_key' => config('ai.xinghuo.api_key'),
                'api_secret' => config('ai.xinghuo.api_secret'),
                'api_url' => config('ai.xinghuo.api_url', 'https://spark-api.xf-yun.com/v3.5/chat')
            ],
            'jianying' => [
                'access_key' => config('ai.jianying.access_key'),
                'secret_key' => config('ai.jianying.secret_key'),
                'api_url' => config('ai.jianying.api_url', 'https://open.douyin.com/api/video/create')
            ],
            'zhiying' => [
                'secret_id' => config('ai.zhiying.secret_id'),
                'secret_key' => config('ai.zhiying.secret_key'),
                'api_url' => config('ai.zhiying.api_url', 'https://zhiying.qq.com/api/v1/video/create')
            ]
        ];
    }

    /**
     * 生成文案内容
     *
     * @param array $params 生成参数
     * @return array
     */
    public function generateText(array $params): array
    {
        $provider = $params['provider'] ?? self::PROVIDER_WENXIN;
        $scene = $params['scene'] ?? '通用场景';
        $style = $params['style'] ?? '吸引人的';
        $requirements = $params['requirements'] ?? '';
        $platform = $params['platform'] ?? 'ALL';

        Log::info('开始生成AI文案', [
            'provider' => $provider,
            'scene' => $scene,
            'style' => $style,
            'platform' => $platform
        ]);

        try {
            // 构建提示词
            $prompt = $this->buildTextPrompt($scene, $style, $requirements, $platform);

            // 根据提供商调用不同的API
            $result = match ($provider) {
                self::PROVIDER_WENXIN => $this->callWenxinTextApi($prompt),
                self::PROVIDER_XINGHUO => $this->callXinghuoTextApi($prompt),
                default => throw new Exception("不支持的AI提供商: {$provider}")
            };

            Log::info('AI文案生成成功', [
                'provider' => $provider,
                'text_length' => mb_strlen($result['text'])
            ]);

            return [
                'status' => self::STATUS_COMPLETED,
                'provider' => $provider,
                'text' => $result['text'],
                'title' => $result['title'] ?? '',
                'tags' => $result['tags'] ?? [],
                'generation_time' => $result['generation_time'] ?? 0
            ];

        } catch (Exception $e) {
            Log::error('AI文案生成失败', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => self::STATUS_FAILED,
                'provider' => $provider,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成视频内容
     *
     * @param array $params 生成参数
     * @return array
     */
    public function generateVideo(array $params): array
    {
        $provider = $params['provider'] ?? self::PROVIDER_JIANYING;
        $scene = $params['scene'] ?? '通用场景';
        $style = $params['style'] ?? '自然';
        $duration = $params['duration'] ?? 15;
        $materials = $params['materials'] ?? [];

        Log::info('开始生成AI视频', [
            'provider' => $provider,
            'scene' => $scene,
            'style' => $style,
            'duration' => $duration
        ]);

        try {
            // 根据提供商调用不同的API
            $result = match ($provider) {
                self::PROVIDER_JIANYING => $this->callJianyingVideoApi($params),
                self::PROVIDER_ZHIYING => $this->callZhiyingVideoApi($params),
                default => throw new Exception("不支持的视频AI提供商: {$provider}")
            };

            Log::info('AI视频生成成功', [
                'provider' => $provider,
                'video_url' => $result['video_url']
            ]);

            return [
                'status' => self::STATUS_COMPLETED,
                'provider' => $provider,
                'video_url' => $result['video_url'],
                'duration' => $result['duration'] ?? $duration,
                'file_size' => $result['file_size'] ?? 0,
                'cover_url' => $result['cover_url'] ?? '',
                'generation_time' => $result['generation_time'] ?? 0
            ];

        } catch (Exception $e) {
            Log::error('AI视频生成失败', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => self::STATUS_FAILED,
                'provider' => $provider,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 使用模板生成内容
     *
     * @param int $templateId 模板ID
     * @param array $data 替换数据
     * @return array
     */
    public function processTemplate(int $templateId, array $data): array
    {
        Log::info('使用模板生成内容', [
            'template_id' => $templateId,
            'data_keys' => array_keys($data)
        ]);

        try {
            // 获取模板信息
            $template = \app\model\ContentTemplate::find($templateId);
            if (!$template) {
                throw new Exception('模板不存在');
            }

            // 解析模板内容
            $templateContent = json_decode($template->content, true);

            // 根据模板类型处理
            $result = match ($template->type) {
                self::TYPE_TEXT => $this->processTextTemplate($templateContent, $data),
                self::TYPE_VIDEO => $this->processVideoTemplate($templateContent, $data),
                self::TYPE_IMAGE => $this->processImageTemplate($templateContent, $data),
                default => throw new Exception("不支持的模板类型: {$template->type}")
            };

            // 更新模板使用次数
            $template->usage_count++;
            $template->save();

            Log::info('模板内容生成成功', [
                'template_id' => $templateId,
                'type' => $template->type
            ]);

            return [
                'status' => self::STATUS_COMPLETED,
                'template_id' => $templateId,
                'type' => $template->type,
                'result' => $result
            ];

        } catch (Exception $e) {
            Log::error('模板内容生成失败', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => self::STATUS_FAILED,
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 构建文案生成提示词
     *
     * @param string $scene 场景
     * @param string $style 风格
     * @param string $requirements 要求
     * @param string $platform 平台
     * @return string
     */
    protected function buildTextPrompt(string $scene, string $style, string $requirements, string $platform): string
    {
        $platformGuide = match ($platform) {
            'DOUYIN' => '要求：简短有力，50字以内，使用1-2个热门话题标签，适合短视频传播',
            'XIAOHONGSHU' => '要求：详细生动，200-500字，分段清晰，多用表情符号，突出体验感',
            'WECHAT' => '要求：真诚自然，100-300字，朋友圈风格，亲切友好',
            default => '要求：通用文案，简洁明了，吸引眼球'
        };

        $prompt = "请为{$scene}生成一段{$style}风格的营销文案。\n\n";
        $prompt .= "{$platformGuide}\n\n";

        if ($requirements) {
            $prompt .= "特殊要求：{$requirements}\n\n";
        }

        $prompt .= "请直接输出文案内容，不要添加额外的说明。";

        return $prompt;
    }

    /**
     * 调用百度文心一言文案生成API
     *
     * @param string $prompt 提示词
     * @return array
     */
    protected function callWenxinTextApi(string $prompt): array
    {
        $startTime = microtime(true);

        // 获取access_token（应该缓存）
        $accessToken = $this->getWenxinAccessToken();

        $apiUrl = $this->config['wenxin']['api_url'] . '?access_token=' . $accessToken;

        $requestData = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.8,
            'top_p' => 0.9,
            'penalty_score' => 1.0
        ];

        // 这里应该实际调用API，目前返回模拟数据
        // $response = $this->httpPost($apiUrl, $requestData, self::TIMEOUT_TEXT);

        // 模拟响应数据（实际开发中需要真实API调用）
        $generationTime = microtime(true) - $startTime;

        return [
            'text' => "这是一段AI生成的营销文案示例。\n\n✨ 特色亮点\n💫 温馨氛围\n🎯 超值体验\n\n#探店推荐 #必打卡",
            'title' => '探店推荐',
            'tags' => ['探店推荐', '必打卡'],
            'generation_time' => round($generationTime, 2)
        ];
    }

    /**
     * 调用讯飞星火文案生成API
     *
     * @param string $prompt 提示词
     * @return array
     */
    protected function callXinghuoTextApi(string $prompt): array
    {
        $startTime = microtime(true);

        // 讯飞星火API调用逻辑
        // 实际开发中需要实现WebSocket连接和鉴权

        $generationTime = microtime(true) - $startTime;

        return [
            'text' => "这是讯飞星火生成的营销文案。",
            'title' => '营销推广',
            'tags' => [],
            'generation_time' => round($generationTime, 2)
        ];
    }

    /**
     * 调用剪映视频生成API
     *
     * @param array $params 参数
     * @return array
     */
    protected function callJianyingVideoApi(array $params): array
    {
        $startTime = microtime(true);

        // 剪映API调用逻辑
        // 实际开发中需要实现真实的API调用

        Log::info('调用剪映API生成视频', $params);

        $generationTime = microtime(true) - $startTime;

        return [
            'video_url' => 'https://example.com/video/demo.mp4',
            'duration' => $params['duration'] ?? 15,
            'file_size' => 2048000,
            'cover_url' => 'https://example.com/video/cover.jpg',
            'generation_time' => round($generationTime, 2)
        ];
    }

    /**
     * 调用腾讯智影视频生成API
     *
     * @param array $params 参数
     * @return array
     */
    protected function callZhiyingVideoApi(array $params): array
    {
        $startTime = microtime(true);

        // 腾讯智影API调用逻辑
        // 实际开发中需要实现真实的API调用

        Log::info('调用腾讯智影API生成视频', $params);

        $generationTime = microtime(true) - $startTime;

        return [
            'video_url' => 'https://example.com/video/demo.mp4',
            'duration' => $params['duration'] ?? 15,
            'file_size' => 2048000,
            'cover_url' => 'https://example.com/video/cover.jpg',
            'generation_time' => round($generationTime, 2)
        ];
    }

    /**
     * 处理文案模板
     *
     * @param array $templateContent 模板内容
     * @param array $data 数据
     * @return array
     */
    protected function processTextTemplate(array $templateContent, array $data): array
    {
        $text = $templateContent['template'] ?? '';

        // 替换变量
        foreach ($data as $key => $value) {
            $text = str_replace("{{$key}}", $value, $text);
        }

        return [
            'text' => $text,
            'title' => $data['title'] ?? '',
            'tags' => $data['tags'] ?? []
        ];
    }

    /**
     * 处理视频模板
     *
     * @param array $templateContent 模板内容
     * @param array $data 数据
     * @return array
     */
    protected function processVideoTemplate(array $templateContent, array $data): array
    {
        // 视频模板处理逻辑
        return [
            'video_url' => $data['video_url'] ?? '',
            'duration' => $templateContent['duration'] ?? 15,
            'cover_url' => $data['cover_url'] ?? ''
        ];
    }

    /**
     * 处理图片模板
     *
     * @param array $templateContent 模板内容
     * @param array $data 数据
     * @return array
     */
    protected function processImageTemplate(array $templateContent, array $data): array
    {
        // 图片模板处理逻辑
        return [
            'image_url' => $data['image_url'] ?? '',
            'width' => $templateContent['width'] ?? 1080,
            'height' => $templateContent['height'] ?? 1920
        ];
    }

    /**
     * 获取百度文心一言AccessToken
     *
     * @return string
     */
    protected function getWenxinAccessToken(): string
    {
        $cacheKey = 'wenxin_access_token';

        // 尝试从缓存获取
        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }

        // 获取新token
        $apiKey = $this->config['wenxin']['api_key'];
        $secretKey = $this->config['wenxin']['secret_key'];

        $url = "https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials&client_id={$apiKey}&client_secret={$secretKey}";

        // 实际调用
        // $response = $this->httpGet($url);
        // $token = $response['access_token'];

        // 模拟token
        $token = 'mock_access_token_' . time();

        // 缓存token（有效期30天）
        Cache::set($cacheKey, $token, 30 * 86400);

        return $token;
    }

    /**
     * HTTP GET请求
     *
     * @param string $url 请求URL
     * @param int $timeout 超时时间
     * @return array
     */
    protected function httpGet(string $url, int $timeout = 10): array
    {
        // 实际HTTP请求实现
        return [];
    }

    /**
     * HTTP POST请求
     *
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param int $timeout 超时时间
     * @return array
     */
    protected function httpPost(string $url, array $data, int $timeout = 30): array
    {
        // 实际HTTP请求实现
        return [];
    }

    /**
     * 重试机制
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

                Log::warning("API调用失败，正在重试", [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage()
                ]);

                // 指数退避
                if ($attempt < $maxRetries) {
                    usleep(pow(2, $attempt) * 100000); // 0.2s, 0.4s, 0.8s...
                }
            }
        }

        throw $lastException;
    }

    /**
     * 检查AI服务是否可用
     *
     * @param string $provider 服务提供商
     * @return bool
     */
    public function checkServiceAvailability(string $provider): bool
    {
        try {
            // 检查配置是否完整
            if (!isset($this->config[$provider])) {
                Log::warning("AI服务提供商配置不存在", ['provider' => $provider]);
                return false;
            }

            // 检查必要的配置项
            $config = $this->config[$provider];
            foreach ($config as $key => $value) {
                if (empty($value) && $key !== 'api_url') {
                    Log::warning("AI服务配置项为空", [
                        'provider' => $provider,
                        'config_key' => $key
                    ]);
                    return false;
                }
            }

            return true;

        } catch (Exception $e) {
            Log::error("检查AI服务可用性失败", [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取AI服务统计信息
     *
     * @param string $provider 服务提供商
     * @return array
     */
    public function getServiceStats(string $provider): array
    {
        // 从缓存或数据库获取统计信息
        return [
            'provider' => $provider,
            'total_calls' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'avg_response_time' => 0
        ];
    }
}