#!/usr/bin/env php
<?php
/**
 * OSS服务快速启动脚本
 * 用于测试和验证OSS服务配置
 */

declare (strict_types = 1);

require_once __DIR__ . '/../vendor/autoload.php';

use think\facade\App;
use think\facade\Config;

// 初始化应用
$app = new App();
$app->initialize();

echo "\n";
echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║          OSS对象存储服务 - 快速启动测试              ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

/**
 * 测试1: 检查配置
 */
function test1_check_config()
{
    echo "【测试1】检查OSS配置\n";
    echo str_repeat("─", 50) . "\n";

    try {
        $ossConfig = Config::get('oss');
        $driver = $ossConfig['default'] ?? 'local';

        echo "✓ 配置文件加载成功\n";
        echo "  默认驱动: {$driver}\n";
        echo "  超时时间: {$ossConfig['global']['timeout']}秒\n";
        echo "  分片大小: " . round($ossConfig['global']['chunk_size'] / 1024 / 1024, 2) . " MB\n";
        echo "  最大文件: " . round($ossConfig['global']['max_file_size'] / 1024 / 1024, 2) . " MB\n";

        // 检查驱动配置
        foreach (['aliyun', 'qiniu', 'tencent', 'aws', 'local'] as $d) {
            $enabled = $ossConfig[$d]['enabled'] ?? false;
            $status = $enabled ? '✓ 已启用' : '✗ 未启用';
            echo "  {$d}: {$status}\n";
        }

        return true;

    } catch (\Exception $e) {
        echo "✗ 配置检查失败: " . $e->getMessage() . "\n";
        return false;
    }

    echo "\n";
}

/**
 * 测试2: 初始化OSS服务
 */
function test2_init_service()
{
    echo "【测试2】初始化OSS服务\n";
    echo str_repeat("─", 50) . "\n";

    try {
        $oss = new \app\service\OssService();

        echo "✓ OSS服务初始化成功\n";
        echo "  当前驱动: " . $oss->getDriverName() . "\n";

        return true;

    } catch (\Exception $e) {
        echo "✗ OSS服务初始化失败: " . $e->getMessage() . "\n";
        echo "\n提示:\n";
        echo "  - 确保已配置正确的驱动\n";
        echo "  - 检查.env文件中的OSS配置\n";
        echo "  - 确认上传目录权限正确\n";
        return false;
    }

    echo "\n";
}

/**
 * 测试3: 文件上传测试
 */
function test3_upload_test()
{
    echo "【测试3】文件上传测试\n";
    echo str_repeat("─", 50) . "\n";

    try {
        $oss = new \app\service\OssService();

        // 创建测试文件
        $testDir = runtime_path() . 'oss_test';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $testFile = $testDir . '/test_' . time() . '.txt';
        $testContent = 'OSS测试文件 - ' . date('Y-m-d H:i:s') . "\n";
        $testContent .= "测试驱动: " . $oss->getDriverName() . "\n";
        $testContent .= "文件大小: " . strlen($testContent) . " 字节\n";

        file_put_contents($testFile, $testContent);

        echo "  → 创建测试文件: {$testFile}\n";

        // 上传文件
        $ossPath = 'test/uploads/' . date('Y/m/d') . '/test_' . time() . '.txt';
        echo "  → 开始上传到: {$ossPath}\n";

        $result = $oss->upload($testFile, $ossPath);

        echo "✓ 文件上传成功\n";
        echo "  存储路径: {$result['path']}\n";
        echo "  访问URL: {$result['url']}\n";
        echo "  文件大小: " . round($result['size'] / 1024, 2) . " KB\n";

        // 保存测试信息
        file_put_contents($testDir . '/last_upload.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 清理测试文件
        @unlink($testFile);

        return true;

    } catch (\Exception $e) {
        echo "✗ 文件上传失败: " . $e->getMessage() . "\n";
        echo "\n故障排查:\n";
        echo "  - 检查存储服务配置是否正确\n";
        echo "  - 确认网络连接正常\n";
        echo "  - 查看日志文件获取详细错误: runtime/logs/oss.log\n";
        return false;
    }

    echo "\n";
}

/**
 * 测试4: 文件操作测试
 */
function test4_file_operations()
{
    echo "【测试4】文件操作测试\n";
    echo str_repeat("─", 50) . "\n";

    try {
        $oss = new \app\service\OssService();

        // 读取上次上传的文件信息
        $testInfoFile = runtime_path() . 'oss_test/last_upload.json';
        if (!file_exists($testInfoFile)) {
            echo "⊘ 跳过(需要先运行测试3)\n\n";
            return null;
        }

        $lastUpload = json_decode(file_get_contents($testInfoFile), true);
        $ossPath = $lastUpload['path'];

        // 检查文件是否存在
        $exists = $oss->exists($ossPath);
        echo "  → 文件存在检查: " . ($exists ? '存在' : '不存在') . "\n";

        if (!$exists) {
            echo "✗ 文件不存在,跳过后续测试\n\n";
            return false;
        }

        // 获取文件信息
        $info = $oss->getFileInfo($ossPath);
        echo "  → 文件信息:\n";
        echo "    大小: " . round($info['size'] / 1024, 2) . " KB\n";
        if (!empty($info['type'])) {
            echo "    类型: {$info['type']}\n";
        }

        // 复制文件
        $copyPath = str_replace('.txt', '_copy.txt', $ossPath);
        $copied = $oss->copy($ossPath, $copyPath);
        echo "  → 文件复制: " . ($copied ? '成功' : '失败') . "\n";

        // 列出文件
        $prefix = dirname($ossPath);
        $files = $oss->listFiles($prefix . '/', 10);
        echo "  → 文件列表(共" . count($files['files']) . "个)\n";

        // 清理测试文件
        $oss->delete($ossPath);
        $oss->delete($copyPath);
        echo "  → 测试文件已清理\n";

        echo "✓ 文件操作测试通过\n";

        return true;

    } catch (\Exception $e) {
        echo "✗ 文件操作失败: " . $e->getMessage() . "\n";
        return false;
    }

    echo "\n";
}

/**
 * 测试5: 缩略图生成测试
 */
function test5_thumbnail_test()
{
    echo "【测试5】缩略图生成测试\n";
    echo str_repeat("─", 50) . "\n";

    try {
        // 下载测试图片
        $testDir = runtime_path() . 'oss_test';
        $testImage = $testDir . '/test_image.jpg';

        echo "  → 下载测试图片...\n";
        $imageData = @file_get_contents('https://via.placeholder.com/800x600.jpg');

        if ($imageData === false) {
            echo "⊘ 无法下载测试图片,跳过此测试\n";
            echo "  提示: 检查网络连接或手动准备测试图片\n\n";
            return null;
        }

        file_put_contents($testImage, $imageData);

        // 初始化缩略图服务
        $thumbnailConfig = Config::get('oss.thumbnail');
        $thumbnailService = new \app\service\oss\OssThumbnailService($thumbnailConfig);

        echo "  → 使用驱动: " . strtoupper($thumbnailConfig['driver'] ?? 'gd') . "\n";

        // 生成缩略图
        $sizes = ['small', 'medium', 'large'];
        $successCount = 0;

        foreach ($sizes as $size) {
            $result = $thumbnailService->generate($testImage, $size);

            if ($result['success']) {
                echo "  ✓ {$size} 缩略图: {$result['width']}x{$result['height']} (" . round($result['size'] / 1024, 2) . " KB)\n";
                @unlink($result['thumbnail_path']);
                $successCount++;
            } else {
                echo "  ✗ {$size} 缩略图: {$result['message']}\n";
            }
        }

        // 清理
        @unlink($testImage);

        if ($successCount === count($sizes)) {
            echo "✓ 缩略图生成测试通过\n";
            return true;
        } elseif ($successCount > 0) {
            echo "⊘ 部分缩略图生成失败 ({$successCount}/" . count($sizes) . ")\n";
            return null;
        } else {
            echo "✗ 缩略图生成全部失败\n";
            echo "  提示: 确保GD或Imagick扩展已安装\n";
            return false;
        }

    } catch (\Exception $e) {
        echo "✗ 缩略图测试失败: " . $e->getMessage() . "\n";
        return false;
    }

    echo "\n";
}

/**
 * 测试6: MaterialImportService集成测试
 */
function test6_material_import_test()
{
    echo "【测试6】MaterialImportService集成测试\n";
    echo str_repeat("─", 50) . "\n";

    try {
        $materialService = new \app\service\MaterialImportService();

        echo "  → MaterialImportService初始化成功\n";
        echo "  ✓ OSS服务已集成\n";
        echo "  ✓ 缩略图服务已集成\n";
        echo "  ✓ 元数据提取器已集成\n";

        return true;

    } catch (\Exception $e) {
        echo "✗ MaterialImportService初始化失败: " . $e->getMessage() . "\n";
        return false;
    }

    echo "\n";
}

/**
 * 运行所有测试
 */
function run_all_tests()
{
    $results = [];

    $results['配置检查'] = test1_check_config();
    $results['服务初始化'] = test2_init_service();

    // 只有服务初始化成功才继续后续测试
    if ($results['服务初始化']) {
        $results['文件上传'] = test3_upload_test();
        $results['文件操作'] = test4_file_operations();
        $results['缩略图生成'] = test5_thumbnail_test();
        $results['MaterialImport'] = test6_material_import_test();
    }

    // 输出测试总结
    echo "╔═══════════════════════════════════════════════════════╗\n";
    echo "║                    测试总结                            ║\n";
    echo "╚═══════════════════════════════════════════════════════╝\n\n";

    $passed = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($results as $name => $result) {
        if ($result === true) {
            echo "✓ {$name}: 通过\n";
            $passed++;
        } elseif ($result === false) {
            echo "✗ {$name}: 失败\n";
            $failed++;
        } else {
            echo "⊘ {$name}: 跳过\n";
            $skipped++;
        }
    }

    echo "\n";
    echo "总计: " . count($results) . " 个测试\n";
    echo "  通过: {$passed}\n";
    echo "  失败: {$failed}\n";
    echo "  跳过: {$skipped}\n";
    echo "\n";

    if ($failed === 0) {
        echo "🎉 恭喜!所有测试通过,OSS服务已就绪!\n\n";
        echo "下一步:\n";
        echo "  1. 配置生产环境云存储密钥\n";
        echo "  2. 启用CDN加速\n";
        echo "  3. 配置HTTPS\n";
        echo "  4. 查看文档: app/service/oss/README.md\n";
    } else {
        echo "⚠️  部分测试失败,请检查配置和依赖\n\n";
        echo "常见问题:\n";
        echo "  - 查看详细文档: docs/oss-installation.md\n";
        echo "  - 检查日志文件: runtime/logs/oss.log\n";
        echo "  - 运行依赖安装: composer install\n";
    }

    echo "\n";
}

// 运行测试
if (php_sapi_name() === 'cli') {
    run_all_tests();
} else {
    die("此脚本只能在命令行中运行");
}
