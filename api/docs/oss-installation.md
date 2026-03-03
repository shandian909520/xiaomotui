# OSS功能依赖安装指南

## 概述

本OSS服务需要安装相关的云存储SDK和扩展。本文档详细说明如何安装各个组件。

## 已安装的SDK

根据项目`composer.json`,以下SDK已安装:

```json
{
    "aliyuncs/oss-sdk-php": "^2.0",  // 阿里云OSS
    "qiniu/php-sdk": "^7.0"           // 七牛云
}
```

## 需要安装的SDK

### 1. 腾讯云COS SDK

```bash
composer require qcloud/cos-sdk-v5
```

### 2. AWS S3 SDK

```bash
composer require aws/aws-sdk-php
```

### 3. getID3库 (可选,用于媒体元数据提取)

```bash
composer require james-heinrich/getid3
```

## PHP扩展要求

### 必需扩展

1. **GD扩展** - 图片处理(缩略图生成)
   ```bash
   # Windows (php.ini)
   extension=gd

   # Linux (Ubuntu/Debian)
   sudo apt-get install php-gd

   # Linux (CentOS/RHEL)
   sudo yum install php-gd
   ```

2. **FileInfo扩展** - MIME类型检测
   ```bash
   # Windows (php.ini)
   extension=fileinfo

   # Linux
   sudo apt-get install php-fileinfo  # Ubuntu/Debian
   sudo yum install php-fileinfo      # CentOS/RHEL
   ```

### 可选扩展

1. **Imagick扩展** - 高级图片处理
   ```bash
   # Windows
   # 下载并安装ImageMagick,然后启用php_imagick.dll

   # Linux (Ubuntu/Debian)
   sudo apt-get install php-imagick

   # Linux (CentOS/RHEL)
   sudo yum install php-pecl-imagick
   ```

2. **Zip扩展** - ZIP文件导入
   ```bash
   # Windows (php.ini)
   extension=zip

   # Linux
   sudo apt-get install php-zip  # Ubuntu/Debian
   sudo yum install php-zip      # CentOS/RHEL
   ```

## FFmpeg安装 (可选,用于视频处理)

### Windows

1. 下载FFmpeg: https://ffmpeg.org/download.html
2. 解压到目录(如`C:\ffmpeg`)
3. 添加到系统PATH环境变量
4. 验证安装:
   ```bash
   ffmpeg -version
   ffprobe -version
   ```

### Linux (Ubuntu/Debian)

```bash
sudo apt-get update
sudo apt-get install ffmpeg ffprobe
```

### Linux (CentOS/RHEL)

```bash
# 添加EPEL仓库
sudo yum install epel-release

# 安装Nux Dextop仓库
sudo rpm -Uvh http://li.nux.ro/download/nux/dextop/el7/x86_64/nux-dextop-release-0-5.el7.nux.noarch.rpm

# 安装FFmpeg
sudo yum install ffmpeg ffmpeg-devel
```

### macOS

```bash
brew install ffmpeg
```

## 验证安装

运行以下PHP脚本验证所有组件:

```php
<?php
// 检查PHP扩展
$required_extensions = ['gd', 'fileinfo', 'zip'];
$optional_extensions = ['imagick'];

echo "=== 必需扩展检查 ===\n";
foreach ($required_extensions as $ext) {
    $status = extension_loaded($ext) ? '✓ 已安装' : '✗ 未安装';
    echo "{$ext}: {$status}\n";
}

echo "\n=== 可选扩展检查 ===\n";
foreach ($optional_extensions as $ext) {
    $status = extension_loaded($ext) ? '✓ 已安装' : '✗ 未安装';
    echo "{$ext}: {$status}\n";
}

echo "\n=== Composer包检查 ===\n";
$packages = [
    'aliyuncs/oss-sdk-php' => '阿里云OSS',
    'qiniu/php-sdk' => '七牛云',
    'qcloud/cos-sdk-v5' => '腾讯云COS',
    'aws/aws-sdk-php' => 'AWS S3',
    'james-heinrich/getid3' => 'getID3',
];

foreach ($packages as $package => $name) {
    $installed = class_exists(str_replace('/', '\\', $package));
    $status = $installed ? '✓ 已安装' : '✗ 未安装';
    echo "{$name} ({$package}): {$status}\n";
}

echo "\n=== FFmpeg检查 ===\n";
$ffmpeg_exists = shell_exec('which ffmpeg');
$ffprobe_exists = shell_exec('which ffprobe');
echo "FFmpeg: " . ($ffmpeg_exists ? '✓ 已安装' : '✗ 未安装') . "\n";
echo "FFprobe: " . ($ffprobe_exists ? '✓ 已安装' : '✗ 未安装') . "\n";

echo "\n=== 完整性检查 ===\n";
try {
    // 尝试实例化OSS服务
    require_once 'vendor/autoload.php';
    $oss = new \app\service\OssService();
    echo "✓ OSS服务可以正常初始化\n";
    echo "当前驱动: " . $oss->getDriverName() . "\n";
} catch (\Exception $e) {
    echo "✗ OSS服务初始化失败: " . $e->getMessage() . "\n";
}
```

## 配置说明

### 1. 复制环境变量配置

```bash
cp .env.oss.example .env
```

### 2. 编辑.env文件

根据使用的云服务商配置相应的密钥和bucket信息。

### 3. 设置目录权限

```bash
# 确保上传目录可写
chmod -R 755 public/uploads

# 确保runtime目录可写
chmod -R 755 runtime
```

## 测试上传功能

创建测试脚本`test_oss.php`:

```php
<?php
require_once 'vendor/autoload.php';

use app\service\OssService;

try {
    // 使用本地存储进行测试
    $oss = new OssService('local');

    // 创建测试文件
    $testFile = runtime_path() . 'test.txt';
    file_put_contents($testFile, 'OSS测试文件 ' . date('Y-m-d H:i:s'));

    // 上传测试
    $result = $oss->upload($testFile, 'test/test_upload.txt');

    echo "上传成功!\n";
    print_r($result);

    // 检查文件是否存在
    $exists = $oss->exists('test/test_upload.txt');
    echo "文件存在: " . ($exists ? '是' : '否') . "\n";

    // 获取URL
    $url = $oss->getUrl('test/test_upload.txt');
    echo "文件URL: {$url}\n";

    // 删除测试文件
    $oss->delete('test/test_upload.txt');
    @unlink($testFile);

    echo "\n测试完成!\n";

} catch (\Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
}
```

运行测试:
```bash
php test_oss.php
```

## 故障排查

### 1. Composer包安装失败

```bash
# 清除缓存
composer clear-cache

# 重新安装
composer install
```

### 2. PHP扩展未加载

检查`php.ini`文件:
```bash
php --ini
```

确认扩展路径正确:
```bash
php -m | grep gd
php -m | grep fileinfo
```

### 3. FFmpeg命令不可用

检查系统PATH:
```bash
echo $PATH  # Linux/macOS
echo %PATH% # Windows
```

确保FFmpeg安装路径在PATH中。

### 4. 权限问题

```bash
# 检查目录权限
ls -la public/uploads
ls -la runtime

# 修改所有者(如www-data)
sudo chown -R www-data:www-data public/uploads runtime
```

## 生产环境建议

1. **使用云存储**: 生产环境不要使用本地存储
2. **配置CDN**: 启用CDN加速文件访问
3. **HTTPS**: 启用HTTPS保证传输安全
4. **私有Bucket**: 敏感文件使用私有bucket和签名URL
5. **定期备份**: 配置自动备份策略
6. **监控告警**: 配置OSS服务的监控和告警

## 性能优化

1. **使用分片上传**: 大文件使用分片上传
2. **批量操作**: 使用批量删除等API
3. **缓存URL**: 文件URL可以缓存
4. **异步处理**: 使用队列处理大文件上传

## 更新日志

- 2024-01-15: 初始版本,支持阿里云OSS、七牛云、腾讯云COS、AWS S3、本地存储
- 完整的缩略图生成功能
- 媒体元数据提取功能
- 分片上传支持
- CDN集成
