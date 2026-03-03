<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Cache;
use think\facade\Log;
use think\exception\HttpException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * 百度文心一言服务类
 * 用于集成百度文心一言API，实现AI内容生成功能
 */
class WenxinService
{
    /**
     * 配置信息
     */
    private array $config;

    /**
     * HTTP客户端
     */
    private Client $httpClient;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = config('ai.wenxin', []);

        // 验证配置
        $protocol = $this->config['protocol'] ?? 'native';

        if ($protocol === 'openai') {
             if (empty($this->config['api_key'])) {
                 throw new \RuntimeException(
                     '文心一言API密钥未配置,请在.env文件中设置BAIDU_WENXIN_API_KEY'
                 );
             }
        } else {
             if (empty($this->config['api_key']) || empty($this->config['secret_key'])) {
                 throw new \RuntimeException(
                     '文心一言API密钥未配置,请在.env文件中设置BAIDU_WENXIN_API_KEY和BAIDU_WENXIN_SECRET_KEY'
                 );
             }
        }

        // 初始化HTTP客户端
        // 注意: 仅在本地开发环境禁用SSL验证,生产环境必须启用
        $this->httpClient = new Client([
            'timeout' => $this->config['timeout'] ?? 30,
            'verify' => env('APP_ENV', 'production') === 'local' ? false : true,
            'http_errors' => false,
        ]);
    }

    /**
     * 生成营销文案
     *
     * @param array $params 生成参数 [
     *   'scene' => '咖啡店',              // 场景描述
     *   'style' => '温馨',                // 风格
     *   'platform' => 'DOUYIN',          // 平台
     *   'category' => '餐饮',             // 商家类别
     *   'requirements' => '突出环境氛围',  // 特殊要求
     * ]
     * @return array ['text' => '生成的文案', 'tokens' => 100, 'time' => 2.5]
     * @throws \Exception
     */
    public function generateText(array $params): array
    {
        $startTime = microtime(true);

        try {
            // 如果是测试模式，返回模拟内容
            if ($this->config['api_key'] === 'test_api_key_demo') {
                return $this->generateMockText($params, $startTime);
            }

            // 构建提示词
            $prompt = $this->buildPrompt($params);

            // 调用API生成内容
            $response = $this->chat($prompt, $params);

            // 解析响应
            $text = $this->parseResponse($response);

            // 内容过滤
            if ($this->config['content_filter']['enabled'] ?? true) {
                $text = $this->filterContent($text);
            }

            $duration = round(microtime(true) - $startTime, 2);

            // 记录日志
            if ($this->config['monitoring']['log_requests'] ?? true) {
                Log::info('文心一言内容生成成功', [
                    'scene' => $params['scene'] ?? '',
                    'style' => $params['style'] ?? '',
                    'platform' => $params['platform'] ?? '',
                    'duration' => $duration,
                    'text_length' => mb_strlen($text),
                ]);
            }

            return [
                'text' => $text,
                'tokens' => $response['usage']['total_tokens'] ?? 0,
                'time' => $duration,
                'model' => $this->config['model'],
            ];

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            Log::error('文心一言内容生成失败', [
                'params' => $params,
                'duration' => $duration,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('AI内容生成失败: ' . $e->getMessage());
        }
    }

    /**
     * 批量生成文案
     *
     * @param array $batchParams 批量参数列表
     * @param int $concurrency 并发数
     * @return array 生成结果列表
     */
    public function batchGenerateText(array $batchParams, int $concurrency = 3): array
    {
        $results = [];

        foreach ($batchParams as $index => $params) {
            try {
                $result = $this->generateText($params);
                $results[] = [
                    'success' => true,
                    'index' => $index,
                    'data' => $result,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 调用文心一言Chat API
     *
     * @param string $prompt 提示词
     * @param array $options 额外选项
     * @return array API响应
     * @throws \Exception
     */
    private function chat(string $prompt, array $options = []): array
    {
        $protocol = $this->config['protocol'] ?? 'native';
        
        if ($protocol === 'openai') {
            return $this->chatOpenAI($prompt, $options);
        }

        // 获取访问令牌
        $accessToken = $this->getAccessToken();

        // 获取模型endpoint
        $model = $options['model'] ?? $this->config['model'];
        $endpoint = $this->config['models'][$model] ?? 'eb-instant';

        // 构建请求URL
        $url = $this->config['chat_url'] . '/' . $endpoint . '?access_token=' . $accessToken;

        // 构建请求体
        $requestBody = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ]
            ],
            'temperature' => $options['temperature'] ?? $this->config['generation']['temperature'],
            'top_p' => $options['top_p'] ?? $this->config['generation']['top_p'],
            'penalty_score' => $options['penalty_score'] ?? $this->config['generation']['penalty_score'],
            'stream' => false,
            'user_id' => $options['user_id'] ?? $this->config['generation']['user_id'],
        ];

        // 如果有系统提示词，添加到消息列表
        if (!empty($this->config['content']['system_prompt'])) {
            array_unshift($requestBody['messages'], [
                'role' => 'user',
                'content' => $this->config['content']['system_prompt'],
            ]);
        }

        // 发送请求（带重试机制）
        $maxRetries = $this->config['max_retries'] ?? 3;
        $retryDelay = $this->config['retry_delay'] ?? 1;
        $lastException = null;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $response = $this->httpClient->post($url, [
                    'json' => $requestBody,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $body = json_decode($response->getBody()->getContents(), true);

                // 检查响应
                if ($statusCode === 200 && !isset($body['error_code'])) {
                    return $body;
                }

                // API返回错误
                $errorMsg = $body['error_msg'] ?? '未知错误';
                $errorCode = $body['error_code'] ?? 0;

                Log::warning('文心一言API返回错误', [
                    'error_code' => $errorCode,
                    'error_msg' => $errorMsg,
                    'retry' => $i + 1,
                ]);

                // 某些错误不需要重试
                if (in_array($errorCode, [4, 17, 18, 19])) {
                    throw new \Exception("API错误: {$errorMsg} (错误码: {$errorCode})");
                }

                $lastException = new \Exception("API错误: {$errorMsg} (错误码: {$errorCode})");

            } catch (GuzzleException $e) {
                $lastException = $e;
                Log::warning('文心一言API请求失败', [
                    'error' => $e->getMessage(),
                    'retry' => $i + 1,
                ]);
            }

            // 等待后重试
            if ($i < $maxRetries - 1) {
                sleep($retryDelay);
            }
        }

        throw new \Exception('API请求失败: ' . ($lastException ? $lastException->getMessage() : '未知错误'));
    }

    /**
     * 调用 OpenAI 兼容 API
     *
     * @param string $prompt
     * @param array $options
     * @return array
     * @throws \Exception
     */
    private function chatOpenAI(string $prompt, array $options = []): array
    {
        $baseUrl = $this->config['openai_base_url'] ?? 'https://qianfan.baidubce.com/v2';
        $url = rtrim($baseUrl, '/') . '/chat/completions';
        $apiKey = $this->config['api_key'];

        $model = $options['model'] ?? $this->config['model'];

        $requestBody = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ]
            ],
            'temperature' => $options['temperature'] ?? $this->config['generation']['temperature'],
            'top_p' => $options['top_p'] ?? $this->config['generation']['top_p'],
            'stream' => false,
        ];

        // 如果有系统提示词，添加到消息列表
        if (!empty($this->config['content']['system_prompt'])) {
            array_unshift($requestBody['messages'], [
                'role' => 'system', // OpenAI 协议支持 system role
                'content' => $this->config['content']['system_prompt'],
            ]);
        }

        // 发送请求（带重试机制）
        $maxRetries = $this->config['max_retries'] ?? 3;
        $retryDelay = $this->config['retry_delay'] ?? 1;
        $lastException = null;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $response = $this->httpClient->post($url, [
                    'json' => $requestBody,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $apiKey,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $body = json_decode($response->getBody()->getContents(), true);

                if ($statusCode === 200 && isset($body['choices'])) {
                    return $body;
                }
                
                // 处理错误响应
                if (isset($body['error'])) {
                     $errorMsg = is_array($body['error']) ? ($body['error']['message'] ?? json_encode($body['error'])) : $body['error'];
                     throw new \Exception("OpenAI API错误: " . $errorMsg);
                }

                throw new \Exception("OpenAI API响应格式未知");

            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('OpenAI API请求失败', [
                    'error' => $e->getMessage(),
                    'retry' => $i + 1,
                ]);
            }
            
             // 等待后重试
            if ($i < $maxRetries - 1) {
                sleep($retryDelay);
            }
        }
        
        throw new \Exception('API请求失败: ' . ($lastException ? $lastException->getMessage() : '未知错误'));
    }

    /**
     * 获取访问令牌（带缓存）
     *
     * @return string 访问令牌
     * @throws \Exception
     */
    private function getAccessToken(): string
    {
        $cacheKey = $this->config['token_cache_key'] ?? 'wenxin:access_token';

        // 尝试从缓存获取
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            return $cachedToken;
        }

        // 请求新的访问令牌
        $url = $this->config['auth_url'] . '?' . http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['api_key'],
            'client_secret' => $this->config['secret_key'],
        ]);

        try {
            $response = $this->httpClient->get($url);
            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['access_token'])) {
                $accessToken = $body['access_token'];
                $expiresIn = $body['expires_in'] ?? 2592000; // 默认30天

                // 缓存令牌（提前5分钟过期）
                $expireMargin = $this->config['token_expire_margin'] ?? 300;
                Cache::set($cacheKey, $accessToken, $expiresIn - $expireMargin);

                Log::info('文心一言访问令牌获取成功', [
                    'expires_in' => $expiresIn,
                ]);

                return $accessToken;
            }

            throw new \Exception('获取访问令牌失败: ' . ($body['error_description'] ?? '未知错误'));

        } catch (GuzzleException $e) {
            throw new \Exception('获取访问令牌请求失败: ' . $e->getMessage());
        }
    }

    /**
     * 构建提示词
     *
     * @param array $params 参数
     * @return string 提示词
     */
    private function buildPrompt(array $params): string
    {
        $prompts = config('ai.prompts', []);

        // 获取基础模板
        $template = $prompts['marketing_text'] ?? '';

        // 获取平台特定要求
        $platform = strtoupper($params['platform'] ?? '');
        $platformRequirements = $prompts['platform_requirements'][$platform] ?? '';

        // 获取风格特征
        $style = $params['style'] ?? '';
        $styleFeature = $prompts['style_features'][$style] ?? '';

        // 替换变量
        $prompt = str_replace(
            [
                '{scene}',
                '{style}',
                '{platform}',
                '{category}',
                '{requirements}',
                '{platform_specific}',
            ],
            [
                $params['scene'] ?? '商家店铺',
                $style . ($styleFeature ? "（{$styleFeature}）" : ''),
                $platform === 'DOUYIN' ? '抖音' : ($platform === 'XIAOHONGSHU' ? '小红书' : '微信'),
                $params['category'] ?? '商家',
                $params['requirements'] ?? '无特殊要求',
                $platformRequirements,
            ],
            $template
        );

        return $prompt;
    }

    /**
     * 解析API响应
     *
     * @param array $response API响应
     * @return string 生成的文本
     * @throws \Exception
     */
    private function parseResponse(array $response): string
    {
        // 尝试解析 OpenAI 格式
        if (isset($response['choices'][0]['message']['content'])) {
            $text = trim($response['choices'][0]['message']['content']);
        } 
        // 尝试解析百度原版格式
        elseif (isset($response['result'])) {
            $text = trim($response['result']);
        } else {
            throw new \Exception('API响应格式错误: 未找到生成内容');
        }

        if (empty($text)) {
            throw new \Exception('生成的内容为空');
        }

        // 检查内容长度
        $maxLength = $this->config['content']['max_length'] ?? 1000;
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }

        return $text;
    }

    /**
     * 内容过滤
     *
     * @param string $text 待过滤文本
     * @return string 过滤后的文本
     */
    private function filterContent(string $text): string
    {
        $sensitiveWords = $this->config['content_filter']['sensitive_words'] ?? [];

        foreach ($sensitiveWords as $word) {
            $replacement = str_repeat('*', mb_strlen($word));
            $text = str_replace($word, $replacement, $text);
        }

        return $text;
    }

    /**
     * 测试API连接
     *
     * @return array 测试结果
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);
        $protocol = $this->config['protocol'] ?? 'native';

        try {
            $accessTokenInfo = '';
            
            // 仅在原生模式下检查 Access Token
            if ($protocol !== 'openai') {
                // 测试获取访问令牌
                $accessToken = $this->getAccessToken();
    
                if (empty($accessToken)) {
                    return [
                        'success' => false,
                        'message' => '获取访问令牌失败',
                        'time' => round(microtime(true) - $startTime, 2),
                    ];
                }
                $accessTokenInfo = substr($accessToken, 0, 10) . '...';
            } else {
                $accessTokenInfo = 'OpenAI Protocol (Key: ' . substr($this->config['api_key'] ?? '', 0, 8) . '...)';
            }

            // 测试简单对话
            $testPrompt = '请用一句话介绍你自己。';
            $response = $this->chat($testPrompt);
            
            // 解析响应内容
            $responseText = '';
            if (isset($response['choices'][0]['message']['content'])) {
                $responseText = $response['choices'][0]['message']['content'];
            } elseif (isset($response['result'])) {
                $responseText = $response['result'];
            }

            return [
                'success' => true,
                'message' => '连接测试成功',
                'access_token' => $accessTokenInfo,
                'model' => $this->config['model'],
                'response' => $responseText,
                'time' => round(microtime(true) - $startTime, 2),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '连接测试失败: ' . $e->getMessage(),
                'time' => round(microtime(true) - $startTime, 2),
            ];
        }
    }

    /**
     * 获取服务状态
     *
     * @return array 服务状态信息
     */
    public function getStatus(): array
    {
        $cacheKey = $this->config['token_cache_key'] ?? 'wenxin:access_token';
        $hasToken = Cache::has($cacheKey);

        return [
            'service' => 'wenxin',
            'model' => $this->config['model'],
            'available_models' => array_keys($this->config['models']),
            'token_cached' => $hasToken,
            'timeout' => $this->config['timeout'],
            'max_retries' => $this->config['max_retries'],
            'config_valid' => !empty($this->config['api_key']) && !empty($this->config['secret_key']),
        ];
    }

    /**
     * 清除访问令牌缓存
     *
     * @return bool 是否成功
     */
    public function clearTokenCache(): bool
    {
        $cacheKey = $this->config['token_cache_key'] ?? 'wenxin:access_token';
        return Cache::delete($cacheKey);
    }

    /**
     * 获取配置信息（脱敏）
     *
     * @return array 配置信息
     */
    public function getConfig(): array
    {
        return [
            'model' => $this->config['model'],
            'timeout' => $this->config['timeout'],
            'max_retries' => $this->config['max_retries'],
            'api_key' => !empty($this->config['api_key']) ? substr($this->config['api_key'], 0, 8) . '...' : '',
            'secret_key' => !empty($this->config['secret_key']) ? substr($this->config['secret_key'], 0, 8) . '...' : '',
        ];
    }

    /**
     * 生成模拟文案内容（测试模式）
     *
     * @param array $params 参数
     * @param float $startTime 开始时间
     * @return array
     */
    private function generateMockText(array $params, float $startTime): array
    {
        $scene = $params['scene'] ?? '通用营销';
        $style = $params['style'] ?? '温馨';
        $platform = $params['platform'] ?? 'douyin';

        // 模拟文案库
        $mockTexts = [
            '咖啡店' => [
                '温馨' => [
                    'douyin' => '☕ 时光正好，咖啡香浓！来我们小店，品一杯暖心咖啡，享受惬意时光。#咖啡店日常',
                    'xiaohongshu' => '终于找到了这家宝藏咖啡店！☕️ 氛围感超棒，咖啡香醇，还有各种精美小甜点～适合和朋友小聚，也适合一个人发呆看书。'
                ],
                '时尚' => [
                    'douyin' => '🔥 城市新地标！网红咖啡店打卡圣地，高颜值咖啡+ins风环境，拍照超出片！#咖啡探店',
                    'xiaohongshu' => '发现一家超有格调的咖啡店✨ 工业风装修+专业咖啡设备，每一杯都是艺术品。咖啡师小哥颜值在线，咖啡更是绝绝！'
                ]
            ],
            '餐厅' => [
                '温馨' => [
                    'douyin' => '🍲 家的味道，就在这里！地道家乡菜，温暖你的胃，治愈你的心。#美食探店',
                    'xiaohongshu' => '这家餐厅太有家的感觉了！🏠 装修温馨，菜品丰富，价格实惠。每一道菜都有妈妈的味道，让人倍感亲切。'
                ],
                '潮流' => [
                    'douyin' => '🌟 网红餐厅来袭！创意料理+潮流环境，每一道菜都是艺术品！#新店打卡',
                    'xiaohongshu' => '打卡了这家超火的网红餐厅！✨ 菜品颜值爆表，口味也很在线。环境装修超有设计感，随手一拍就是大片！'
                ]
            ],
            '通用营销' => [
                '温馨' => [
                    'douyin' => '❤️ 温暖如家，品质如初。我们用心做好每一件产品，只为给您最好的体验。',
                    'xiaohongshu' => '一家有温度的小店，每一件商品都承载着我们的用心。希望在这里，你能找到属于自己的小确幸。'
                ],
                '时尚' => [
                    'douyin' => '🔥 潮流前线，品质生活！精选全球好物，让您的生活更有格调。',
                    'xiaohongshu' => '发现一家超有格调的买手店！✨ 每一件单品都是精挑细选，品质和颜值都在线。店主的审美真的太好了！'
                ]
            ]
        ];

        // 根据场景、风格、平台选择合适的文案
        $text = $this->selectMockText($mockTexts, $scene, $style, $platform);

        $duration = round(microtime(true) - $startTime, 2);

        return [
            'text' => $text,
            'tokens' => rand(100, 500),
            'time' => $duration,
            'model' => 'mock-ernie-bot-turbo',
            'params' => $params,
        ];
    }

    /**
     * 选择合适的模拟文案
     *
     * @param array $mockTexts 模拟文案库
     * @param string $scene 场景
     * @param string $style 风格
     * @param string $platform 平台
     * @return string
     */
    private function selectMockText(array $mockTexts, string $scene, string $style, string $platform): string
    {
        // 先尝试精确匹配
        if (isset($mockTexts[$scene][$style][$platform])) {
            return $mockTexts[$scene][$style][$platform];
        }

        // 再尝试风格匹配
        if (isset($mockTexts[$scene][$style])) {
            $platforms = $mockTexts[$scene][$style];
            return $platforms[array_rand($platforms)];
        }

        // 最后使用通用营销文案
        return $mockTexts['通用营销'][$style][$platform] ?? '欢迎光临我们的店铺，为您提供优质的产品和服务！';
    }
}