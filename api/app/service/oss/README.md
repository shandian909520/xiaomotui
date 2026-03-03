# OSS对象存储服务使用文档

## 概述

本项目实现了统一的OSS对象存储服务,支持多种云存储后端和本地存储。

## 支持的存储驱动

1. **阿里云OSS** - 阿里云对象存储服务
2. **七牛云** - 七牛云存储
3. **腾讯云COS** - 腾讯云对象存储
4. **AWS S3** - 亚马逊S3及兼容服务
5. **本地存储** - 开发环境本地文件系统

## 目录结构

```
api/app/service/oss/
├── OssDriverInterface.php          # 驱动接口
├── AbstractOssDriver.php           # 抽象基类
├── AliyunOssDriver.php             # 阿里云OSS驱动
├── QiniuOssDriver.php              # 七牛云驱动
├── TencentCosDriver.php            # 腾讯云COS驱动
├── AwsS3Driver.php                 # AWS S3驱动
├── LocalStorageDriver.php          # 本地存储驱动
├── OssThumbnailService.php         # 缩略图服务
└── MediaMetadataExtractor.php      # 媒体元数据提取器
```

## 配置说明

### 1. 环境变量配置 (.env)

```bash
# 默认存储驱动 (aliyun, qiniu, tencent, aws, local)
OSS_DRIVER=local

# 全局配置
OSS_TIMEOUT=60
OSS_RETRY=3
OSS_CHUNK_SIZE=5242880
OSS_MAX_FILE_SIZE=5368709120
OSS_USE_HTTPS=true
OSS_ENABLE_LOG=true

# 阿里云OSS配置
OSS_ALIYUN_ENABLED=false
OSS_ALIYUN_ACCESS_KEY=your_access_key
OSS_ALIYUN_SECRET_KEY=your_secret_key
OSS_ALIYUN_BUCKET=your_bucket
OSS_ALIYUN_ENDPOINT=oss-cn-hangzhou.aliyuncs.com
OSS_ALIYUN_CDN_DOMAIN=cdn.example.com
OSS_ALIYUN_PREFIX=uploads/

# 七牛云配置
OSS_QINIU_ENABLED=false
OSS_QINIU_ACCESS_KEY=your_access_key
OSS_QINIU_SECRET_KEY=your_secret_key
OSS_QINIU_BUCKET=your_bucket
OSS_QINIU_DOMAIN=https://cdn.example.com
OSS_QINIU_PREFIX=uploads/
OSS_QINIU_ZONE=z0

# 腾讯云COS配置
OSS_TENCENT_ENABLED=false
OSS_TENCENT_SECRET_ID=your_secret_id
OSS_TENCENT_SECRET_KEY=your_secret_key
OSS_TENCENT_BUCKET=your_bucket
OSS_TENCENT_REGION=ap-guangzhou
OSS_TENCENT_CDN_DOMAIN=cdn.example.com
OSS_TENCENT_PREFIX=uploads/

# AWS S3配置
OSS_AWS_ENABLED=false
OSS_AWS_ACCESS_KEY=your_access_key
OSS_AWS_SECRET_KEY=your_secret_key
OSS_AWS_BUCKET=your_bucket
OSS_AWS_REGION=us-east-1
OSS_AWS_ENDPOINT=https://s3.amazonaws.com
OSS_AWS_CDN_DOMAIN=cdn.example.com
OSS_AWS_PREFIX=uploads/

# 本地存储配置
OSS_LOCAL_ENABLED=true
OSS_LOCAL_ROOT_PATH=public/uploads
OSS_LOCAL_URL_PREFIX=/uploads
OSS_LOCAL_PREFIX=

# 缩略图配置
OSS_THUMBNAIL_ENABLED=true
OSS_THUMBNAIL_DRIVER=gd
OSS_THUMBNAIL_QUALITY=85
OSS_THUMBNAIL_FORMAT=jpg

# CDN配置
OSS_CDN_ENABLED=false
OSS_CDN_PROVIDER=aliyun
OSS_CDN_DOMAIN=cdn.example.com
OSS_CDN_HTTPS=true
```

### 2. 配置文件 (config/oss.php)

配置文件位于 `D:\xiaomotui\api\config\oss.php`,已自动创建。

## 使用示例

### 基本使用

```php
use app\service\OssService;

// 1. 使用默认驱动
$oss = new OssService();

// 2. 指定驱动
$oss = OssService::driver('aliyun');

// 3. 上传文件
$result = $oss->upload(
    '/local/path/to/file.jpg',
    'materials/2024/01/15/photo.jpg'
);

// 返回结果
// [
//     'success' => true,
//     'path' => 'materials/2024/01/15/photo.jpg',
//     'url' => 'https://cdn.example.com/materials/2024/01/15/photo.jpg',
//     'bucket' => 'your_bucket',
//     'size' => 102400
// ]
```

### 分片上传大文件

```php
// 大文件自动使用分片上传
$result = $oss->multipartUpload(
    '/local/path/to/large_video.mp4',
    'videos/2024/01/15/large_video.mp4',
    [
        'chunk_size' => 10485760, // 10MB分片
        'progress_callback' => function($uploaded, $total, $percentage) {
            echo "上传进度: {$percentage}%\n";
        }
    ]
);
```

### 获取文件URL

```php
// 公开URL
$url = $oss->getUrl('materials/2024/01/15/photo.jpg');

// 签名URL(1小时有效)
$signedUrl = $oss->getSignedUrl('materials/2024/01/15/photo.jpg', 3600);

// CDN URL
$cdnUrl = $oss->getCdnUrl('materials/2024/01/15/photo.jpg');
```

### 删除文件

```php
// 删除单个文件
$oss->delete('materials/2024/01/15/photo.jpg');

// 批量删除
$results = $oss->batchDelete([
    'materials/2024/01/15/photo1.jpg',
    'materials/2024/01/15/photo2.jpg',
]);
```

### 文件操作

```php
// 检查文件是否存在
$exists = $oss->exists('materials/2024/01/15/photo.jpg');

// 获取文件信息
$info = $oss->getFileInfo('materials/2024/01/15/photo.jpg');
// [
//     'size' => 102400,
//     'type' => 'image/jpeg',
//     'last_modified' => '2024-01-15 10:30:00',
//     'etag' => 'abc123...',
// ]

// 复制文件
$oss->copy('materials/photo1.jpg', 'materials/photo2.jpg');

// 移动/重命名文件
$oss->move('materials/old_name.jpg', 'materials/new_name.jpg');

// 列出文件
$files = $oss->listFiles('materials/2024/01/', 100);
```

## 在素材导入服务中使用

MaterialImportService已集成OSS服务:

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

// 导入素材(自动上传到OSS)
$result = $service->importSingleMaterial('IMAGE', [
    'name' => 'example.jpg',
    'tmp_name' => '/tmp/phpXXXX',
    'size' => 102400,
    'type' => 'image/jpeg'
], [
    'name' => '示例图片',
    'category_id' => 1
]);

// 自动功能:
// - 文件验证
// - OSS上传(支持大文件分片)
// - 缩略图生成
// - 元数据提取
// - 保存到数据库
```

## 缩略图生成

```php
use app\service\oss\OssThumbnailService;

$thumbnailService = new OssThumbnailService($thumbnailConfig);

// 生成单个缩略图
$result = $thumbnailService->generate(
    '/local/path/to/image.jpg',
    'medium' // small, medium, large
);

// 批量生成
$results = $thumbnailService->generateBatch(
    '/local/path/to/image.jpg',
    ['small', 'medium', 'large']
);
```

## 媒体元数据提取

```php
use app\service\oss\MediaMetadataExtractor;

$extractor = new MediaMetadataExtractor();

// 提取视频元数据
$videoMeta = $extractor->extract('/path/to/video.mp4', 'video');
// [
//     'duration' => 120.5,
//     'width' => 1920,
//     'height' => 1080,
//     'bitrate' => 5000000,
//     'codec' => 'h264',
//     'fps' => 30.0,
//     'audio_codec' => 'aac',
// ]

// 提取音频元数据
$audioMeta = $extractor->extract('/path/to/audio.mp3', 'audio');
// [
//     'duration' => 180.0,
//     'bitrate' => 320000,
//     'sample_rate' => 44100,
//     'channels' => 2,
//     'codec' => 'mp3',
//     'artist' => '歌手名',
//     'title' => '歌曲名',
// ]

// 提取图片元数据
$imageMeta = $extractor->extract('/path/to/image.jpg', 'image');
// [
//     'width' => 1920,
//     'height' => 1080,
//     'type' => 'jpeg',
//     'mime' => 'image/jpeg',
//     'exif' => [...],
// ]

// 从视频提取缩略图
$thumbnailPath = '/path/to/thumbnail.jpg';
$success = $extractor->extractVideoThumbnail(
    '/path/to/video.mp4',
    $thumbnailPath,
    5, // 第5秒的帧
    [
        'width' => 320,
        'height' => 240,
        'quality' => 85,
    ]
);
```

## 错误处理

所有OSS操作都包含完整的异常处理:

```php
try {
    $result = $oss->upload($localPath, $ossPath);
} catch (\Exception $e) {
    // 错误已自动记录到日志
    echo "上传失败: " . $e->getMessage();
}
```

## 日志

OSS操作日志存储在: `runtime/logs/oss.log`

```
[2024-01-15 10:30:00] oss.INFO: 开始上传文件到阿里云OSS {"local_path":"/tmp/file.jpg","oss_path":"materials/2024/01/15/file.jpg"}
[2024-01-15 10:30:01] oss.INFO: 文件上传到阿里云OSS成功 {"oss_path":"materials/2024/01/15/file.jpg","file_size":102400}
```

## 性能优化

1. **大文件分片上传**: 自动对大于5MB的文件使用分片上传
2. **CDN加速**: 配置CDN域名可显著提升访问速度
3. **连接复用**: 使用连接池减少初始化开销
4. **批量操作**: 使用batchDelete等批量操作减少请求次数

## 安全建议

1. 使用环境变量存储密钥
2. 配置私有bucket时使用签名URL
3. 启用HTTPS传输
4. 定期轮换访问密钥
5. 限制bucket权限范围

## 故障排查

### 常见问题

1. **上传失败**
   - 检查密钥配置是否正确
   - 确认bucket权限设置
   - 查看日志文件获取详细错误

2. **缩略图生成失败**
   - 检查GD/Imagick扩展是否安装
   - 确认图片格式支持

3. **FFmpeg相关功能不可用**
   - 安装FFmpeg和FFprobe
   - 配置正确的可执行文件路径

### 开发环境

使用本地存储驱动进行开发测试:

```bash
OSS_DRIVER=local
OSS_LOCAL_ROOT_PATH=public/uploads
OSS_LOCAL_URL_PREFIX=/uploads
```

## 技术支持

- 项目文档: `D:\xiaomotui\api\app\service\oss\`
- 配置文件: `D:\xiaomotui\api\config\oss.php`
- 日志文件: `runtime/logs/oss.log`
