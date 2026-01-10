<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Cache;
use think\facade\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;

/**
 * 抖音开放平台服务类
 * 用于处理抖音视频发布、账号管理等功能
 */
class DouyinService
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
     * 上传专用HTTP客户端（更长超时时间）
     */
    private Client $uploadClient;

    /**
     * 视频发布状态常量
     */
    const STATUS_UPLOADING = 'UPLOADING';
    const STATUS_UPLOADED = 'UPLOADED';
    const STATUS_PUBLISHING = 'PUBLISHING';
    const STATUS_PUBLISHED = 'PUBLISHED';
    const STATUS_FAILED = 'FAILED';

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = config('douyin', []);

        // 验证配置
        if (empty($this->config['app_id']) || empty($this->config['app_secret'])) {
            throw new \RuntimeException('抖音开放平台配置不完整：缺少app_id或app_secret');
        }

        // 初始化HTTP客户端
        $this->httpClient = new Client([
            'base_uri' => $this->config['api_base_url'],
            'timeout' => $this->config['timeout'] ?? 60,
            'verify' => false,
            'http_errors' => false,
        ]);

        // 初始化上传专用客户端
        $this->uploadClient = new Client([
            'timeout' => $this->config['upload_timeout'] ?? 300,
            'verify' => false,
            'http_errors' => false,
            'connect_timeout' => 30,
        ]);
    }

    /**
     * 获取授权URL
     *
     * @param string $redirectUri 回调地址
     * @param string $state 状态参数
     * @param string $scope 授权范围
     * @return string 授权URL
     */
    public function getAuthorizeUrl(string $redirectUri, string $state = '', string $scope = ''): string
    {
        $scope = $scope ?: $this->config['oauth']['scope'];

        $params = [
            'client_key' => $this->config['app_id'],
            'response_type' => 'code',
            'scope' => $scope,
            'redirect_uri' => $redirectUri,
            'state' => $state ?: md5(uniqid((string)mt_rand(), true)),
        ];

        $url = $this->config['oauth']['authorize_url'] . '?' . http_build_query($params);

        Log::info('生成抖音授权URL', [
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
        ]);

        return $url;
    }

    /**
     * 通过授权码获取访问令牌
     *
     * @param string $code 授权码
     * @return array ['access_token' => '', 'refresh_token' => '', 'expires_in' => 86400, 'open_id' => '']
     * @throws \Exception
     */
    public function getAccessToken(string $code): array
    {
        $url = $this->config['oauth']['access_token_url'];

        $params = [
            'client_key' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        try {
            $response = $this->httpClient->get($url, [
                'query' => $params,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode !== 200 || !isset($body['data'])) {
                $errorMsg = $body['data']['error_code'] ?? $body['message'] ?? '获取访问令牌失败';
                throw new \Exception($errorMsg);
            }

            $data = $body['data'];

            // 缓存token
            $this->cacheAccessToken($data['open_id'], $data['access_token'], $data['expires_in']);
            $this->cacheRefreshToken($data['open_id'], $data['refresh_token'], $data['refresh_expires_in'] ?? 0);

            Log::info('获取抖音访问令牌成功', [
                'open_id' => $data['open_id'],
                'expires_in' => $data['expires_in'],
            ]);

            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => $data['expires_in'],
                'refresh_expires_in' => $data['refresh_expires_in'] ?? 0,
                'open_id' => $data['open_id'],
                'scope' => $data['scope'] ?? '',
            ];

        } catch (GuzzleException $e) {
            Log::error('获取抖音访问令牌失败', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('获取访问令牌请求失败: ' . $e->getMessage());
        }
    }

    /**
     * 刷新访问令牌
     *
     * @param string $openId 用户openId
     * @param string $refreshToken 刷新令牌（可选，不传则从缓存获取）
     * @return array
     * @throws \Exception
     */
    public function refreshAccessToken(string $openId, string $refreshToken = ''): array
    {
        // 如果没有传refresh_token，尝试从缓存获取
        if (empty($refreshToken)) {
            $refreshToken = $this->getRefreshTokenFromCache($openId);
            if (empty($refreshToken)) {
                throw new \Exception('刷新令牌不存在或已过期');
            }
        }

        $url = $this->config['oauth']['refresh_token_url'];

        $params = [
            'client_key' => $this->config['app_id'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        try {
            $response = $this->httpClient->get($url, [
                'query' => $params,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode !== 200 || !isset($body['data'])) {
                $errorMsg = $body['data']['error_code'] ?? $body['message'] ?? '刷新访问令牌失败';
                throw new \Exception($errorMsg);
            }

            $data = $body['data'];

            // 更新缓存
            $this->cacheAccessToken($openId, $data['access_token'], $data['expires_in']);
            $this->cacheRefreshToken($openId, $data['refresh_token'], $data['refresh_expires_in'] ?? 0);

            Log::info('刷新抖音访问令牌成功', [
                'open_id' => $openId,
                'expires_in' => $data['expires_in'],
            ]);

            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => $data['expires_in'],
                'refresh_expires_in' => $data['refresh_expires_in'] ?? 0,
                'open_id' => $openId,
            ];

        } catch (GuzzleException $e) {
            Log::error('刷新抖音访问令牌失败', [
                'open_id' => $openId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('刷新访问令牌请求失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取客户端令牌（用于不需要用户授权的接口）
     *
     * @return string
     * @throws \Exception
     */
    public function getClientToken(): string
    {
        $cacheKey = $this->config['token_cache']['client_token_key'];

        // 尝试从缓存获取
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            return $cachedToken;
        }

        $url = $this->config['oauth']['client_token_url'];

        $params = [
            'client_key' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'grant_type' => 'client_credential',
        ];

        try {
            $response = $this->httpClient->get($url, [
                'query' => $params,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode !== 200 || !isset($body['data'])) {
                $errorMsg = $body['message'] ?? '获取客户端令牌失败';
                throw new \Exception($errorMsg);
            }

            $accessToken = $body['data']['access_token'];
            $expiresIn = $body['data']['expires_in'] ?? 7200;

            // 缓存令牌（提前5分钟过期）
            $expireMargin = $this->config['token_cache']['expire_margin'] ?? 300;
            Cache::set($cacheKey, $accessToken, $expiresIn - $expireMargin);

            Log::info('获取抖音客户端令牌成功', [
                'expires_in' => $expiresIn,
            ]);

            return $accessToken;

        } catch (GuzzleException $e) {
            Log::error('获取抖音客户端令牌失败', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('获取客户端令牌请求失败: ' . $e->getMessage());
        }
    }

    /**
     * 上传视频文件
     *
     * @param string $openId 用户openId
     * @param string $videoPath 视频文件路径
     * @return array ['video_id' => '']
     * @throws \Exception
     */
    public function uploadVideo(string $openId, string $videoPath): array
    {
        if (!file_exists($videoPath)) {
            throw new \Exception('视频文件不存在: ' . $videoPath);
        }

        $fileSize = filesize($videoPath);
        $videoConfig = $this->config['video'];

        // 验证文件大小
        if ($fileSize > $videoConfig['max_size']) {
            throw new \Exception('视频文件过大，最大支持' . ($videoConfig['max_size'] / 1024 / 1024 / 1024) . 'GB');
        }

        if ($fileSize < $videoConfig['min_size']) {
            throw new \Exception('视频文件过小，最小需要' . ($videoConfig['min_size'] / 1024 / 1024) . 'MB');
        }

        // 验证文件格式
        $extension = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
        if (!in_array($extension, $videoConfig['allowed_formats'])) {
            throw new \Exception('不支持的视频格式: ' . $extension);
        }

        $accessToken = $this->getAccessTokenFromCache($openId);
        if (empty($accessToken)) {
            throw new \Exception('访问令牌不存在或已过期，请重新授权');
        }

        $startTime = microtime(true);

        // 判断是否需要分片上传（大于分片大小）
        if ($fileSize > $videoConfig['chunk_size']) {
            $result = $this->uploadVideoByChunks($openId, $videoPath, $accessToken);
        } else {
            $result = $this->uploadVideoDirectly($openId, $videoPath, $accessToken);
        }

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('抖音视频上传成功', [
            'open_id' => $openId,
            'video_id' => $result['video_id'],
            'file_size' => $fileSize,
            'duration' => $duration,
        ]);

        return $result;
    }

    /**
     * 直接上传视频（小文件）
     *
     * @param string $openId 用户openId
     * @param string $videoPath 视频文件路径
     * @param string $accessToken 访问令牌
     * @return array
     * @throws \Exception
     */
    private function uploadVideoDirectly(string $openId, string $videoPath, string $accessToken): array
    {
        $url = $this->config['video']['upload_url'];

        try {
            $response = $this->uploadClient->post($url, [
                'multipart' => [
                    [
                        'name' => 'video',
                        'contents' => Utils::tryFopen($videoPath, 'r'),
                        'filename' => basename($videoPath),
                    ],
                    [
                        'name' => 'open_id',
                        'contents' => $openId,
                    ],
                    [
                        'name' => 'access_token',
                        'contents' => $accessToken,
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode !== 200 || !isset($body['data'])) {
                $errorMsg = $this->getErrorMessage($body);
                throw new \Exception($errorMsg);
            }

            return [
                'video_id' => $body['data']['video']['video_id'],
                'width' => $body['data']['video']['width'] ?? 0,
                'height' => $body['data']['video']['height'] ?? 0,
            ];

        } catch (GuzzleException $e) {
            Log::error('抖音视频上传失败', [
                'open_id' => $openId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('视频上传失败: ' . $e->getMessage());
        }
    }

    /**
     * 分片上传视频（大文件）
     *
     * @param string $openId 用户openId
     * @param string $videoPath 视频文件路径
     * @param string $accessToken 访问令牌
     * @return array
     * @throws \Exception
     */
    private function uploadVideoByChunks(string $openId, string $videoPath, string $accessToken): array
    {
        $fileSize = filesize($videoPath);
        $chunkSize = $this->config['video']['chunk_size'];

        // 1. 初始化分片上传
        $initResult = $this->initPartUpload($openId, $accessToken);
        $uploadId = $initResult['upload_id'];

        Log::info('抖音分片上传初始化成功', [
            'open_id' => $openId,
            'upload_id' => $uploadId,
            'file_size' => $fileSize,
        ]);

        // 2. 分片上传
        $handle = fopen($videoPath, 'rb');
        if (!$handle) {
            throw new \Exception('无法打开视频文件');
        }

        $partNumber = 1;
        $uploadedParts = [];

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new \Exception('读取视频文件失败');
                }

                $partResult = $this->uploadPart($openId, $uploadId, $partNumber, $chunk, $accessToken);
                $uploadedParts[] = [
                    'part_number' => $partNumber,
                    'etag' => $partResult['etag'] ?? '',
                ];

                Log::info('抖音分片上传进度', [
                    'upload_id' => $uploadId,
                    'part_number' => $partNumber,
                    'total_parts' => ceil($fileSize / $chunkSize),
                ]);

                $partNumber++;
            }
        } finally {
            fclose($handle);
        }

        // 3. 完成分片上传
        $completeResult = $this->completePartUpload($openId, $uploadId, $uploadedParts, $accessToken);

        return [
            'video_id' => $completeResult['video_id'],
            'width' => $completeResult['width'] ?? 0,
            'height' => $completeResult['height'] ?? 0,
        ];
    }

    /**
     * 初始化分片上传
     *
     * @param string $openId 用户openId
     * @param string $accessToken 访问令牌
     * @return array
     * @throws \Exception
     */
    private function initPartUpload(string $openId, string $accessToken): array
    {
        $url = $this->config['video']['part_init_url'];

        try {
            $response = $this->httpClient->post($url, [
                'json' => [
                    'open_id' => $openId,
                    'access_token' => $accessToken,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['data']['upload_id'])) {
                throw new \Exception($this->getErrorMessage($body));
            }

            return [
                'upload_id' => $body['data']['upload_id'],
            ];

        } catch (GuzzleException $e) {
            throw new \Exception('初始化分片上传失败: ' . $e->getMessage());
        }
    }

    /**
     * 上传分片
     *
     * @param string $openId 用户openId
     * @param string $uploadId 上传ID
     * @param int $partNumber 分片编号
     * @param string $chunk 分片数据
     * @param string $accessToken 访问令牌
     * @return array
     * @throws \Exception
     */
    private function uploadPart(string $openId, string $uploadId, int $partNumber, string $chunk, string $accessToken): array
    {
        $url = $this->config['video']['part_upload_url'];

        try {
            $response = $this->uploadClient->post($url, [
                'multipart' => [
                    [
                        'name' => 'upload_id',
                        'contents' => $uploadId,
                    ],
                    [
                        'name' => 'part_number',
                        'contents' => (string)$partNumber,
                    ],
                    [
                        'name' => 'chunk',
                        'contents' => $chunk,
                    ],
                    [
                        'name' => 'open_id',
                        'contents' => $openId,
                    ],
                    [
                        'name' => 'access_token',
                        'contents' => $accessToken,
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['data'])) {
                throw new \Exception($this->getErrorMessage($body));
            }

            return [
                'etag' => $body['data']['etag'] ?? '',
            ];

        } catch (GuzzleException $e) {
            throw new \Exception('上传分片失败: ' . $e->getMessage());
        }
    }

    /**
     * 完成分片上传
     *
     * @param string $openId 用户openId
     * @param string $uploadId 上传ID
     * @param array $parts 已上传的分片信息
     * @param string $accessToken 访问令牌
     * @return array
     * @throws \Exception
     */
    private function completePartUpload(string $openId, string $uploadId, array $parts, string $accessToken): array
    {
        $url = $this->config['video']['part_complete_url'];

        try {
            $response = $this->httpClient->post($url, [
                'json' => [
                    'open_id' => $openId,
                    'upload_id' => $uploadId,
                    'parts' => $parts,
                    'access_token' => $accessToken,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['data']['video_id'])) {
                throw new \Exception($this->getErrorMessage($body));
            }

            return [
                'video_id' => $body['data']['video_id'],
                'width' => $body['data']['width'] ?? 0,
                'height' => $body['data']['height'] ?? 0,
            ];

        } catch (GuzzleException $e) {
            throw new \Exception('完成分片上传失败: ' . $e->getMessage());
        }
    }

    /**
     * 发布视频
     *
     * @param string $openId 用户openId
     * @param array $params 发布参数 [
     *   'video_id' => '',      // 视频ID（必填）
     *   'title' => '',         // 标题（必填）
     *   'cover_tsp' => 0,      // 封面时间戳（秒）
     *   'tags' => [],          // 标签数组
     *   'privacy_level' => 0,  // 隐私级别：0-公开，1-好友，2-私密
     *   'location' => '',      // 位置信息
     *   'at_users' => [],      // @用户列表
     *   'schedule_time' => 0,  // 定时发布时间戳
     * ]
     * @return array ['item_id' => '']
     * @throws \Exception
     */
    public function publishVideo(string $openId, array $params): array
    {
        // 验证必填参数
        if (empty($params['video_id'])) {
            throw new \Exception('视频ID不能为空');
        }

        if (empty($params['title'])) {
            throw new \Exception('视频标题不能为空');
        }

        // 验证标题长度
        $titleLength = mb_strlen($params['title']);
        $publishConfig = $this->config['publish'];

        if ($titleLength > $publishConfig['title_max_length']) {
            throw new \Exception('标题长度不能超过' . $publishConfig['title_max_length'] . '字');
        }

        if ($titleLength < $publishConfig['title_min_length']) {
            throw new \Exception('标题长度不能少于' . $publishConfig['title_min_length'] . '字');
        }

        // 验证标签
        if (isset($params['tags'])) {
            if (!is_array($params['tags'])) {
                throw new \Exception('标签必须是数组');
            }

            if (count($params['tags']) > $publishConfig['max_tags']) {
                throw new \Exception('标签数量不能超过' . $publishConfig['max_tags'] . '个');
            }

            foreach ($params['tags'] as $tag) {
                if (mb_strlen($tag) > $publishConfig['tag_max_length']) {
                    throw new \Exception('单个标签长度不能超过' . $publishConfig['tag_max_length'] . '字');
                }
            }
        }

        // 验证定时发布时间
        if (isset($params['schedule_time']) && $params['schedule_time'] > 0) {
            $now = time();
            $delay = $params['schedule_time'] - $now;

            if ($delay < $publishConfig['schedule_min_delay']) {
                throw new \Exception('定时发布时间至少需要' . ($publishConfig['schedule_min_delay'] / 60) . '分钟后');
            }

            if ($delay > $publishConfig['schedule_max_delay']) {
                throw new \Exception('定时发布时间不能超过' . ($publishConfig['schedule_max_delay'] / 86400) . '天');
            }
        }

        $accessToken = $this->getAccessTokenFromCache($openId);
        if (empty($accessToken)) {
            throw new \Exception('访问令牌不存在或已过期，请重新授权');
        }

        $url = $this->config['video']['create_url'];

        // 构建请求数据
        $postData = [
            'open_id' => $openId,
            'access_token' => $accessToken,
            'video_id' => $params['video_id'],
            'text' => $params['title'],
        ];

        // 可选参数
        if (isset($params['cover_tsp'])) {
            $postData['cover_tsp'] = $params['cover_tsp'];
        }

        if (isset($params['privacy_level'])) {
            $postData['privacy_level'] = $params['privacy_level'];
        }

        if (isset($params['location'])) {
            $postData['poi_id'] = $params['location'];
        }

        if (isset($params['at_users']) && !empty($params['at_users'])) {
            $postData['at_users'] = $params['at_users'];
        }

        if (isset($params['schedule_time']) && $params['schedule_time'] > 0) {
            $postData['schedule_time'] = $params['schedule_time'];
        }

        try {
            $response = $this->httpClient->post($url, [
                'json' => $postData,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode !== 200 || !isset($body['data'])) {
                $errorMsg = $this->getErrorMessage($body);
                throw new \Exception($errorMsg);
            }

            Log::info('抖音视频发布成功', [
                'open_id' => $openId,
                'video_id' => $params['video_id'],
                'item_id' => $body['data']['item_id'],
                'title' => $params['title'],
            ]);

            return [
                'item_id' => $body['data']['item_id'],
                'share_url' => $body['data']['share_url'] ?? '',
            ];

        } catch (GuzzleException $e) {
            Log::error('抖音视频发布失败', [
                'open_id' => $openId,
                'video_id' => $params['video_id'],
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('视频发布失败: ' . $e->getMessage());
        }
    }

    /**
     * 发布内容到抖音（业务层封装方法）
     *
     * @param array $content 内容数据 [
     *   'video_path' => '',    // 视频文件路径
     *   'title' => '',         // 标题
     *   'cover_tsp' => 0,      // 封面时间戳
     *   'tags' => [],          // 标签
     * ]
     * @param array $account 账号信息 [
     *   'open_id' => '',       // 用户openId
     * ]
     * @return array
     * @throws \Exception
     */
    public function publishToDouyin(array $content, array $account): array
    {
        $startTime = microtime(true);

        try {
            // 验证账号信息
            if (empty($account['open_id'])) {
                throw new \Exception('账号openId不能为空');
            }

            $openId = $account['open_id'];

            // 1. 上传视频
            Log::info('开始上传抖音视频', [
                'open_id' => $openId,
                'video_path' => $content['video_path'],
            ]);

            $uploadResult = $this->uploadVideo($openId, $content['video_path']);
            $videoId = $uploadResult['video_id'];

            // 2. 发布视频
            Log::info('开始发布抖音视频', [
                'open_id' => $openId,
                'video_id' => $videoId,
            ]);

            $publishParams = [
                'video_id' => $videoId,
                'title' => $content['title'],
            ];

            // 可选参数
            if (isset($content['cover_tsp'])) {
                $publishParams['cover_tsp'] = $content['cover_tsp'];
            }

            if (isset($content['tags'])) {
                $publishParams['tags'] = $content['tags'];
            }

            if (isset($content['privacy_level'])) {
                $publishParams['privacy_level'] = $content['privacy_level'];
            }

            if (isset($content['schedule_time'])) {
                $publishParams['schedule_time'] = $content['schedule_time'];
            }

            $publishResult = $this->publishVideo($openId, $publishParams);

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('抖音内容发布完成', [
                'open_id' => $openId,
                'video_id' => $videoId,
                'item_id' => $publishResult['item_id'],
                'duration' => $duration,
            ]);

            return [
                'status' => self::STATUS_PUBLISHED,
                'video_id' => $videoId,
                'item_id' => $publishResult['item_id'],
                'share_url' => $publishResult['share_url'] ?? '',
                'duration' => $duration,
            ];

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            Log::error('抖音内容发布失败', [
                'open_id' => $account['open_id'] ?? '',
                'error' => $e->getMessage(),
                'duration' => $duration,
            ]);

            return [
                'status' => self::STATUS_FAILED,
                'error' => $e->getMessage(),
                'duration' => $duration,
            ];
        }
    }

    /**
     * 获取用户信息
     *
     * @param string $openId 用户openId
     * @param string $accessToken 访问令牌（可选，不传则从缓存获取）
     * @return array
     * @throws \Exception
     */
    public function getUserInfo(string $openId, string $accessToken = ''): array
    {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessTokenFromCache($openId);
            if (empty($accessToken)) {
                throw new \Exception('访问令牌不存在或已过期，请重新授权');
            }
        }

        $url = $this->config['user']['info_url'];

        try {
            $response = $this->httpClient->get($url, [
                'query' => [
                    'open_id' => $openId,
                    'access_token' => $accessToken,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode !== 200 || !isset($body['data'])) {
                $errorMsg = $this->getErrorMessage($body);
                throw new \Exception($errorMsg);
            }

            $data = $body['data'];

            return [
                'open_id' => $data['open_id'],
                'union_id' => $data['union_id'] ?? '',
                'nickname' => $data['nickname'] ?? '',
                'avatar' => $data['avatar'] ?? '',
                'gender' => $data['gender'] ?? 0,
                'city' => $data['city'] ?? '',
                'province' => $data['province'] ?? '',
                'country' => $data['country'] ?? '',
            ];

        } catch (GuzzleException $e) {
            Log::error('获取抖音用户信息失败', [
                'open_id' => $openId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('获取用户信息失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取用户粉丝数据
     *
     * @param string $openId 用户openId
     * @param string $accessToken 访问令牌（可选）
     * @return array
     * @throws \Exception
     */
    public function getFansData(string $openId, string $accessToken = ''): array
    {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessTokenFromCache($openId);
            if (empty($accessToken)) {
                throw new \Exception('访问令牌不存在或已过期，请重新授权');
            }
        }

        $url = $this->config['user']['fans_data_url'];

        try {
            $response = $this->httpClient->get($url, [
                'query' => [
                    'open_id' => $openId,
                    'access_token' => $accessToken,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode !== 200 || !isset($body['data'])) {
                $errorMsg = $this->getErrorMessage($body);
                throw new \Exception($errorMsg);
            }

            $data = $body['data'];

            return [
                'total_fans' => $data['total_fans'] ?? 0,
                'fans_increase' => $data['fans_increase'] ?? 0,
                'total_videos' => $data['total_videos'] ?? 0,
                'total_likes' => $data['total_likes'] ?? 0,
            ];

        } catch (GuzzleException $e) {
            Log::error('获取抖音粉丝数据失败', [
                'open_id' => $openId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('获取粉丝数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 缓存访问令牌
     *
     * @param string $openId 用户openId
     * @param string $accessToken 访问令牌
     * @param int $expiresIn 过期时间（秒）
     * @return void
     */
    private function cacheAccessToken(string $openId, string $accessToken, int $expiresIn): void
    {
        $cacheKey = $this->config['token_cache']['access_token_prefix'] . $openId;
        $expireMargin = $this->config['token_cache']['expire_margin'] ?? 300;

        Cache::set($cacheKey, $accessToken, $expiresIn - $expireMargin);
    }

    /**
     * 从缓存获取访问令牌
     *
     * @param string $openId 用户openId
     * @return string
     */
    private function getAccessTokenFromCache(string $openId): string
    {
        $cacheKey = $this->config['token_cache']['access_token_prefix'] . $openId;
        return Cache::get($cacheKey) ?: '';
    }

    /**
     * 缓存刷新令牌
     *
     * @param string $openId 用户openId
     * @param string $refreshToken 刷新令牌
     * @param int $expiresIn 过期时间（秒）
     * @return void
     */
    private function cacheRefreshToken(string $openId, string $refreshToken, int $expiresIn): void
    {
        if ($expiresIn <= 0) {
            $expiresIn = 30 * 86400; // 默认30天
        }

        $cacheKey = $this->config['token_cache']['refresh_token_prefix'] . $openId;
        $expireMargin = $this->config['token_cache']['expire_margin'] ?? 300;

        Cache::set($cacheKey, $refreshToken, $expiresIn - $expireMargin);
    }

    /**
     * 从缓存获取刷新令牌
     *
     * @param string $openId 用户openId
     * @return string
     */
    private function getRefreshTokenFromCache(string $openId): string
    {
        $cacheKey = $this->config['token_cache']['refresh_token_prefix'] . $openId;
        return Cache::get($cacheKey) ?: '';
    }

    /**
     * 清除用户令牌缓存
     *
     * @param string $openId 用户openId
     * @return void
     */
    public function clearTokenCache(string $openId): void
    {
        $accessTokenKey = $this->config['token_cache']['access_token_prefix'] . $openId;
        $refreshTokenKey = $this->config['token_cache']['refresh_token_prefix'] . $openId;

        Cache::delete($accessTokenKey);
        Cache::delete($refreshTokenKey);

        Log::info('清除抖音令牌缓存', [
            'open_id' => $openId,
        ]);
    }

    /**
     * 清除客户端令牌缓存
     *
     * @return void
     */
    public function clearClientTokenCache(): void
    {
        $cacheKey = $this->config['token_cache']['client_token_key'];
        Cache::delete($cacheKey);

        Log::info('清除抖音客户端令牌缓存');
    }

    /**
     * 从响应中获取错误信息
     *
     * @param array $body 响应体
     * @return string
     */
    private function getErrorMessage(array $body): string
    {
        if (isset($body['data']['error_code'])) {
            $errorCode = $body['data']['error_code'];
            $errorMsg = $body['data']['description'] ?? $body['message'] ?? '未知错误';

            // 尝试从配置获取友好的错误信息
            $errorCodes = $this->config['error_codes'] ?? [];
            if (isset($errorCodes[$errorCode])) {
                return $errorCodes[$errorCode] . ': ' . $errorMsg;
            }

            return "错误码{$errorCode}: {$errorMsg}";
        }

        return $body['message'] ?? '请求失败';
    }

    /**
     * 测试连接
     *
     * @return array
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);

        try {
            // 测试获取客户端令牌
            $clientToken = $this->getClientToken();

            if (empty($clientToken)) {
                return [
                    'success' => false,
                    'message' => '获取客户端令牌失败',
                    'time' => round(microtime(true) - $startTime, 2),
                ];
            }

            return [
                'success' => true,
                'message' => '连接测试成功',
                'client_token' => substr($clientToken, 0, 10) . '...',
                'app_id' => $this->config['app_id'],
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
     * @return array
     */
    public function getStatus(): array
    {
        $cacheKey = $this->config['token_cache']['client_token_key'];
        $hasClientToken = Cache::has($cacheKey);

        return [
            'service' => 'douyin',
            'app_id' => $this->config['app_id'],
            'client_token_cached' => $hasClientToken,
            'timeout' => $this->config['timeout'],
            'upload_timeout' => $this->config['upload_timeout'],
            'max_retries' => $this->config['max_retries'],
            'config_valid' => !empty($this->config['app_id']) && !empty($this->config['app_secret']),
        ];
    }

    /**
     * 获取配置信息（脱敏）
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'app_id' => $this->config['app_id'],
            'app_secret' => !empty($this->config['app_secret']) ? substr($this->config['app_secret'], 0, 8) . '...' : '',
            'timeout' => $this->config['timeout'],
            'upload_timeout' => $this->config['upload_timeout'],
            'max_retries' => $this->config['max_retries'],
            'max_file_size' => $this->config['video']['max_size'],
            'chunk_size' => $this->config['video']['chunk_size'],
        ];
    }
}