# 抖音发布服务使用说明

## 概述

`DouyinService` 是抖音开放平台集成服务，提供了完整的抖音视频发布、账号管理、OAuth授权等功能。

## 配置

### 1. 环境变量配置

在 `.env` 文件中添加以下配置：

```ini
[DOUYIN]
# 抖音开放平台配置
DOUYIN_APP_ID = your_app_id
DOUYIN_APP_SECRET = your_app_secret
DOUYIN_API_BASE_URL = https://open.douyin.com
DOUYIN_TIMEOUT = 60
DOUYIN_UPLOAD_TIMEOUT = 300
DOUYIN_MAX_RETRIES = 3
DOUYIN_RETRY_DELAY = 1
```

### 2. 配置文件

配置文件位于 `config/douyin.php`，包含以下主要配置项：

- **OAuth配置**: 授权URL、Token URL等
- **视频上传配置**: 文件大小限制、格式限制、分片上传配置
- **用户信息API**: 用户信息、粉丝数据等接口配置
- **发布配置**: 标题、标签、隐私级别等限制
- **监控配置**: 日志、性能跟踪、告警配置

## 功能特性

### 1. OAuth授权

```php
use app\service\DouyinService;

$douyinService = new DouyinService();

// 生成授权URL
$redirectUri = 'https://your-domain.com/callback';
$authorizeUrl = $douyinService->getAuthorizeUrl($redirectUri, 'state', 'user_info,video.create');

// 用户授权后，使用授权码获取访问令牌
$code = $_GET['code'];
$tokenData = $douyinService->getAccessToken($code);

// 返回数据包含:
// - access_token: 访问令牌
// - refresh_token: 刷新令牌
// - expires_in: 过期时间（秒）
// - open_id: 用户唯一标识
// - scope: 授权范围
```

### 2. Token管理

```php
// 刷新访问令牌
$openId = 'user_open_id';
$newTokenData = $douyinService->refreshAccessToken($openId);

// 获取客户端令牌（不需要用户授权）
$clientToken = $douyinService->getClientToken();

// 清除用户Token缓存
$douyinService->clearTokenCache($openId);

// 清除客户端Token缓存
$douyinService->clearClientTokenCache();
```

### 3. 视频上传

支持小文件直接上传和大文件分片上传，自动根据文件大小选择上传方式。

```php
$openId = 'user_open_id';
$videoPath = '/path/to/video.mp4';

// 上传视频
$uploadResult = $douyinService->uploadVideo($openId, $videoPath);

// 返回数据包含:
// - video_id: 视频ID
// - width: 视频宽度
// - height: 视频高度
```

**视频文件要求:**
- 格式支持: mp4, mov, avi, flv, mkv
- 大小限制: 1MB - 4GB
- 时长限制: 3秒 - 15分钟
- 分片大小: 5MB（自动处理）

### 4. 视频发布

```php
$openId = 'user_open_id';

// 发布参数
$publishParams = [
    'video_id' => 'video_id_from_upload',
    'title' => '视频标题',
    'cover_tsp' => 3,  // 封面时间戳（秒）
    'tags' => ['标签1', '标签2'],
    'privacy_level' => 0,  // 0-公开，1-好友，2-私密
    'location' => 'poi_id',  // 位置ID（可选）
    'schedule_time' => time() + 3600,  // 定时发布时间戳（可选）
];

// 发布视频
$publishResult = $douyinService->publishVideo($openId, $publishParams);

// 返回数据包含:
// - item_id: 作品ID
// - share_url: 分享链接
```

**发布限制:**
- 标题长度: 1-55字
- 标签数量: 最多10个
- 单个标签长度: 最多20字
- 定时发布: 至少15分钟后，最多7天内

### 5. 业务层封装方法

提供了业务层级别的便捷方法：

```php
// 一键发布到抖音（上传+发布）
$content = [
    'video_path' => '/path/to/video.mp4',
    'title' => '视频标题',
    'tags' => ['标签1', '标签2'],
    'cover_tsp' => 3,
    'privacy_level' => 0,
];

$account = [
    'open_id' => 'user_open_id',
];

$result = $douyinService->publishToDouyin($content, $account);

// 返回数据包含:
// - status: 发布状态（PUBLISHED/FAILED）
// - video_id: 视频ID
// - item_id: 作品ID
// - share_url: 分享链接
// - duration: 处理耗时
// - error: 错误信息（失败时）
```

### 6. 用户信息

```php
$openId = 'user_open_id';

// 获取用户信息
$userInfo = $douyinService->getUserInfo($openId);

// 返回数据包含:
// - open_id: 用户openId
// - union_id: 用户unionId
// - nickname: 昵称
// - avatar: 头像URL
// - gender: 性别（0-未知，1-男，2-女）
// - city: 城市
// - province: 省份
// - country: 国家
```

### 7. 粉丝数据

```php
$openId = 'user_open_id';

// 获取粉丝数据
$fansData = $douyinService->getFansData($openId);

// 返回数据包含:
// - total_fans: 总粉丝数
// - fans_increase: 粉丝增量
// - total_videos: 总视频数
// - total_likes: 总点赞数
```

### 8. 服务状态

```php
// 测试连接
$testResult = $douyinService->testConnection();

// 获取服务状态
$status = $douyinService->getStatus();

// 获取配置信息（脱敏）
$config = $douyinService->getConfig();
```

## 完整使用流程

### 步骤1: 用户授权

```php
// 在控制器中
$douyinService = new DouyinService();
$redirectUri = 'https://your-domain.com/douyin/callback';
$authorizeUrl = $douyinService->getAuthorizeUrl($redirectUri);

// 跳转到授权页面
return redirect($authorizeUrl);
```

### 步骤2: 处理授权回调

```php
// 回调处理
public function callback()
{
    $code = $this->request->get('code');

    if (empty($code)) {
        throw new \Exception('授权失败');
    }

    $douyinService = new DouyinService();
    $tokenData = $douyinService->getAccessToken($code);

    // 保存用户信息和token到数据库
    // ...

    return json(['code' => 0, 'msg' => '授权成功', 'data' => $tokenData]);
}
```

### 步骤3: 发布视频

```php
// 发布视频
public function publish()
{
    $videoPath = $this->request->post('video_path');
    $title = $this->request->post('title');
    $tags = $this->request->post('tags', []);
    $openId = $this->request->user['douyin_open_id'];  // 从已授权用户获取

    $douyinService = new DouyinService();

    $content = [
        'video_path' => $videoPath,
        'title' => $title,
        'tags' => $tags,
    ];

    $account = [
        'open_id' => $openId,
    ];

    $result = $douyinService->publishToDouyin($content, $account);

    if ($result['status'] === DouyinService::STATUS_PUBLISHED) {
        return json(['code' => 0, 'msg' => '发布成功', 'data' => $result]);
    } else {
        return json(['code' => 1, 'msg' => $result['error']]);
    }
}
```

## 错误处理

服务会抛出异常，建议使用 try-catch 处理：

```php
try {
    $result = $douyinService->publishToDouyin($content, $account);
} catch (\Exception $e) {
    Log::error('抖音发布失败', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    return json(['code' => 1, 'msg' => '发布失败: ' . $e->getMessage()]);
}
```

## 常见错误码

| 错误码 | 说明 | 处理方法 |
|--------|------|----------|
| 10000 | 服务器错误 | 稍后重试 |
| 10001 | 参数错误 | 检查请求参数 |
| 10002 | 未授权 | 引导用户重新授权 |
| 10003 | 权限不足 | 检查授权范围 |
| 10004 | 请求过于频繁 | 降低请求频率 |
| 10005 | access_token过期 | 刷新token |
| 10006 | refresh_token过期 | 重新授权 |
| 10008 | 视频格式不支持 | 转换视频格式 |
| 10009 | 视频大小超限 | 压缩视频 |
| 10010 | 视频时长超限 | 剪辑视频 |

## 性能优化

### 1. Token缓存

访问令牌和刷新令牌会自动缓存到Redis，避免频繁请求。

### 2. 分片上传

大文件自动使用分片上传，提高上传成功率和速度。

### 3. 重试机制

网络请求失败会自动重试（默认3次），提高稳定性。

### 4. 超时控制

- 普通请求超时: 60秒
- 上传请求超时: 300秒

## 日志记录

服务会自动记录关键操作日志：

- OAuth授权流程
- Token获取和刷新
- 视频上传进度
- 视频发布结果
- 错误信息

查看日志：

```bash
tail -f runtime/log/202501/01.log | grep "抖音"
```

## 测试

运行测试脚本：

```bash
php test_douyin_service.php
```

## 注意事项

1. **安全性**
   - 不要在客户端暴露 app_secret
   - Token应该安全存储，不要明文保存
   - 建议使用HTTPS传输

2. **限流**
   - 遵守抖音平台的接口调用限制
   - 建议在应用层实现限流控制

3. **异常处理**
   - 所有方法都可能抛出异常，务必使用try-catch
   - 记录详细的错误日志便于排查问题

4. **Token管理**
   - access_token有效期较短，需要及时刷新
   - refresh_token过期需要用户重新授权

5. **视频要求**
   - 确保视频文件符合格式和大小要求
   - 建议上传前进行预检查
   - 视频内容需符合抖音社区规范

## 相关文档

- [抖音开放平台官方文档](https://developer.open-douyin.com/)
- [OAuth 2.0授权流程](https://developer.open-douyin.com/docs/resource/zh-CN/dop/develop/openapi/account-management/authorization-code)
- [视频管理接口](https://developer.open-douyin.com/docs/resource/zh-CN/dop/develop/openapi/video-management/douyin/create-video)

## 技术支持

如有问题，请查看：
1. 配置文件 `config/douyin.php`
2. 服务源码 `app/service/DouyinService.php`
3. 测试脚本 `test_douyin_service.php`
4. 日志文件 `runtime/log/`