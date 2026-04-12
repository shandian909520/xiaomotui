<?php
/**
 * AI服务配置文件
 * 包含百度文心一言、讯飞星火等AI服务的配置
 */
return [
    // 默认使用的AI服务提供商
    'default' => env('AI_DEFAULT_PROVIDER', 'wenxin'),

    // 百度文心一言配置
    'wenxin' => [
        // 协议类型: 'native' (原版百度协议) 或 'openai' (OpenAI兼容协议)
        'protocol' => env('AI.BAIDU_WENXIN_PROTOCOL', 'openai'),

        // API认证配置
        // 如果使用 openai 协议，api_key 填写 Access Key，secret_key 可留空
        'api_key' => env('AI.BAIDU_WENXIN_API_KEY', ''),
        'secret_key' => env('AI.BAIDU_WENXIN_SECRET_KEY', ''),

        // API端点配置
        'auth_url' => env('AI.BAIDU_WENXIN_AUTH_URL', 'https://aip.baidubce.com/oauth/2.0/token'),
        // 原版百度协议 Chat URL
        'chat_url' => env('AI.BAIDU_WENXIN_CHAT_URL', 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat'),
        // OpenAI 兼容协议 Base URL
        'openai_base_url' => env('AI.BAIDU_WENXIN_OPENAI_BASE_URL', 'https://qianfan.baidubce.com/v2'),

        // 模型配置
        'model' => env('AI.BAIDU_WENXIN_MODEL', 'ernie-bot-turbo'),

        // 可用的模型列表及其endpoint
        'models' => [
            'ernie-bot' => 'completions',           // ERNIE-Bot
            'ernie-bot-turbo' => 'eb-instant',      // ERNIE-Bot-turbo（推荐）
            'ernie-bot-4' => 'completions_pro',     // ERNIE-Bot 4.0
            'ernie-speed' => 'ernie_speed',         // ERNIE Speed
        ],

        // 请求参数配置
        'timeout' => env('AI.BAIDU_WENXIN_TIMEOUT', 30),           // 请求超时时间（秒）
        'max_retries' => env('AI.BAIDU_WENXIN_MAX_RETRIES', 3),    // 最大重试次数
        'retry_delay' => env('AI.BAIDU_WENXIN_RETRY_DELAY', 1),    // 重试延迟（秒）

        // Token缓存配置
        'token_cache_key' => 'wenxin:access_token',
        'token_expire_margin' => 300,  // Token过期前5分钟刷新

        // 生成参数默认值
        'generation' => [
            'temperature' => 0.8,        // 温度参数 (0-1)，越高越随机
            'top_p' => 0.9,              // 核采样参数 (0-1)
            'penalty_score' => 1.0,      // 惩罚分数，降低重复度
            'stream' => false,           // 是否流式输出
            'user_id' => 'xiaomotui',    // 用户标识
        ],

        // 内容生成配置
        'content' => [
            // 文案生成最大长度
            'max_length' => 1000,
            // 系统提示词
            'system_prompt' => '你是一个专业的营销文案创作助手，擅长根据不同场景和风格生成吸引人的营销内容。',
        ],
    ],

    // 讯飞星火配置（预留）
    'xfyun' => [
        'app_id' => env('AI.IFLYTEK_APP_ID', ''),
        'api_key' => env('AI.IFLYTEK_API_KEY', ''),
        'api_secret' => env('AI.IFLYTEK_API_SECRET', ''),
        'enabled' => false,
    ],

    // MiniMax 文字生成配置（使用 Anthropic API 兼容接口）
    'minimax' => [
        // API 认证配置
        'auth_token' => env('ANTHROPIC_AUTH_TOKEN', ''),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.minimaxi.com/anthropic'),

        // 模型配置
        'model' => env('AI.MINIMAX_MODEL', 'MiniMax-M2.7'),

        // 可用的模型列表
        'models' => [
            'MiniMax-M2.7' => 'MiniMax-M2.7',  // MiniMax M2.7 模型
        ],

        // 请求参数配置
        'timeout' => env('AI.MINIMAX_TIMEOUT', 30),
        'max_retries' => env('AI.MINIMAX_MAX_RETRIES', 3),
        'retry_delay' => env('AI.MINIMAX_RETRY_DELAY', 1),

        // 生成参数默认值
        'generation' => [
            'temperature' => 0.8,
            'top_p' => 0.9,
            'stream' => false,
        ],

        // 内容生成配置
        'content' => [
            'max_length' => 2000,
            'system_prompt' => '你是一个专业的营销文案创作助手，擅长根据不同场景和风格生成吸引人的营销内容。',
        ],
    ],

    // 内容生成提示词模板
    'prompts' => [
        // 营销文案生成
        'marketing_text' => '请为{scene}场景生成{style}风格的营销文案。
平台：{platform}
商家类别：{category}
特殊要求：{requirements}

要求：直接生成文案，不要任何解释、说明或思考过程。文案要简洁有力，20-50个中文字符，配合话题标签。
{platform_specific}

输出格式：只需输出文案本身，例如：暖灯轻洒，咖啡香浓，偷得浮生半日闲。#咖啡时光 #温暖小店',

        // 平台特定要求
        'platform_requirements' => [
            'DOUYIN' => '抖音平台要求：1. 文案简短有力，20-50字 2. 节奏感强，适合短视频 3. 配合流行元素和话题标签',
            'XIAOHONGSHU' => '小红书平台要求：1. 真实生活化的表达 2. 分享式语气 3. 适当使用emoji 4. 100-200字为宜',
            'WECHAT' => '微信平台要求：1. 亲切自然的语气 2. 内容详实 3. 适合朋友圈分享 4. 可包含引导语',
        ],

        // 风格特征
        'style_features' => [
            '温馨' => '温暖、亲切、有人情味，营造舒适放松的氛围',
            '时尚' => '前卫、新潮、个性化，突出流行趋势',
            '文艺' => '有情怀、有格调、富有诗意和文化气息',
            '潮流' => '年轻活力、时尚动感、紧跟潮流',
            '高端' => '精致优雅、品质感强、体现尊贵体验',
            '亲民' => '接地气、实惠、贴近生活',
        ],
    ],

    // 内容审核配置
    'content_filter' => [
        'enabled' => true,
        'sensitive_words' => [],  // 敏感词列表
        'max_retry_on_filter' => 2,  // 内容被过滤时的最大重试次数
    ],

    // 性能监控配置
    'monitoring' => [
        'enabled' => true,
        'log_requests' => true,      // 记录请求日志
        'log_responses' => false,    // 记录响应日志（可能包含大量内容）
        'track_performance' => true, // 跟踪性能指标
        'alert_on_failure' => true,  // 失败时发送告警
    ],

    // 配额和限流配置
    'quota' => [
        'daily_limit' => 1000,        // 每日调用限制
        'per_user_limit' => 50,       // 单用户每日限制
        'rate_limit' => 10,           // 每分钟调用限制
        'rate_window' => 60,          // 限流时间窗口（秒）
    ],
];