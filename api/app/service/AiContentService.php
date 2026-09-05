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
    const PROVIDER_MINIMAX = 'minimax';      // MiniMax

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
            ],
            'minimax' => [
                'auth_token' => config('ai.minimax.auth_token'),
                'base_url' => config('ai.minimax.base_url', 'https://api.minimaxi.com/anthropic'),
                'model' => config('ai.minimax.model', 'MiniMax-Text-01'),
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

            // 使用重试机制调用API
            $result = $this->retry(function () use ($provider, $prompt) {
                return match ($provider) {
                    self::PROVIDER_WENXIN => $this->callWenxinTextApi($prompt),
                    self::PROVIDER_XINGHUO => $this->callXinghuoTextApi($prompt),
                    self::PROVIDER_MINIMAX => $this->callMiniMaxTextApi($prompt),
                    default => throw new Exception("不支持的AI提供商: {$provider}")
                };
            });

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

        $wenxinService = new WenxinService(self::PROVIDER_WENXIN);
        $result = $wenxinService->generateText([
            'scene' => '',
            'style' => '',
            'platform' => 'ALL',
            '_direct_prompt' => $prompt,
        ]);

        $generationTime = microtime(true) - $startTime;

        return [
            'text' => $result['text'],
            'title' => '',
            'tags' => [],
            'generation_time' => $generationTime,
        ];
    }

    /**
     * 调用讯飞星火文案生成API
     * 当前未配置讯飞星火，回退到文心一言
     *
     * @param string $prompt 提示词
     * @return array
     */
    protected function callXinghuoTextApi(string $prompt): array
    {
        Log::info('讯飞星火未配置，回退到文心一言');
        return $this->callWenxinTextApi($prompt);
    }

    /**
     * 调用MiniMax文字生成API
     *
     * @param string $prompt 提示词
     * @return array
     */
    protected function callMiniMaxTextApi(string $prompt): array
    {
        $startTime = microtime(true);

        $wenxinService = new WenxinService(self::PROVIDER_MINIMAX);
        $result = $wenxinService->generateText([
            'scene' => '',
            'style' => '',
            'platform' => 'ALL',
            '_direct_prompt' => $prompt,
        ]);

        $generationTime = microtime(true) - $startTime;

        return [
            'text' => $result['text'],
            'title' => '',
            'tags' => [],
            'generation_time' => $generationTime,
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
        Log::info('调用剪映API生成视频', $params);
        throw new Exception('视频生成功能暂未开放，请使用文本生成');
    }

    /**
     * 调用腾讯智影视频生成API
     *
     * @param array $params 参数
     * @return array
     */
    protected function callZhiyingVideoApi(array $params): array
    {
        Log::info('调用腾讯智影API生成视频', $params);
        throw new Exception('视频生成功能暂未开放，请使用文本生成');
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
     * 委托给 WenxinService 处理
     *
     * @return string
     */
    protected function getWenxinAccessToken(): string
    {
        throw new Exception('请使用 WenxinService 获取 AccessToken');
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
                    usleep(pow(2, $attempt) * 1000000); // 1s, 2s, 4s...
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