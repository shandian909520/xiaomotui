<?php
/**
 * OSS服务快速开始示例
 *
 * 本文件展示了如何使用OSS服务的各种功能
 */

declare (strict_types = 1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use app\service\OssService;
use app\service\MaterialImportService;
use app\service\oss\OssThumbnailService;
use app\service\oss\MediaMetadataExtractor;

/**
 * 示例1: 基础文件上传
 */
function example1_basic_upload()
{
    echo "=== 示例1: 基础文件上传 ===\n";

    try {
        // 使用默认驱动(local)
        $oss = new OssService();

        // 创建测试文件
        $testFile = runtime_path() . 'test_image.jpg';
        $imageData = file_get_contents('https://via.placeholder.com/800x600.jpg');
        file_put_contents($testFile, $imageData);

        // 上传文件
        $result = $oss->upload(
            $testFile,
            'examples/image.jpg',
            [
                'content_type' => 'image/jpeg'
            ]
        );

        echo "上传成功!\n";
        echo "文件路径: {$result['path']}\n";
        echo "访问URL: {$result['url']}\n";
        echo "文件大小: " . round($result['size'] / 1024, 2) . " KB\n";

        // 清理
        @unlink($testFile);

    } catch (\Exception $e) {
        echo "上传失败: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

/**
 * 示例2: 大文件分片上传
 */
function example2_multipart_upload()
{
    echo "=== 示例2: 大文件分片上传 ===\n";

    try {
        $oss = new OssService();

        // 创建大文件(模拟)
        $testFile = runtime_path() . 'large_video.mp4';
        $handle = fopen($testFile, 'wb');
        // 写入10MB数据
        for ($i = 0; $i < 10 * 1024 * 1024; $i += 8192) {
            fwrite($handle, str_repeat('A', 8192));
        }
        fclose($handle);

        echo "文件大小: " . round(filesize($testFile) / 1024 / 1024, 2) . " MB\n";

        // 分片上传
        $result = $oss->multipartUpload(
            $testFile,
            'videos/large_video.mp4',
            [
                'chunk_size' => 1048576, // 1MB分片
                'progress_callback' => function($uploaded, $total, $percentage) {
                    echo "上传进度: {$percentage}% (" . round($uploaded / 1024 / 1024, 2) . " MB / " . round($total / 1024 / 1024, 2) . " MB)\r";
                }
            ]
        );

        echo "\n上传完成!\n";
        echo "文件URL: {$result['url']}\n";

        // 清理
        @unlink($testFile);

    } catch (\Exception $e) {
        echo "上传失败: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

/**
 * 示例3: 生成缩略图
 */
function example3_thumbnail_generation()
{
    echo "=== 示例3: 生成缩略图 ===\n";

    try {
        // 下载测试图片
        $testImage = runtime_path() . 'original_image.jpg';
        $imageData = file_get_contents('https://via.placeholder.com/1920x1080.jpg');
        file_put_contents($testImage, $imageData);

        // 初始化缩略图服务
        $thumbnailConfig = [
            'driver' => 'gd', // 或 'imagick'
            'quality' => 85,
            'format' => 'jpg',
            'sizes' => [
                'small' => [150, 150],
                'medium' => [300, 300],
                'large' => [800, 600],
            ],
        ];

        $thumbnailService = new OssThumbnailService($thumbnailConfig);

        // 生成不同尺寸的缩略图
        $sizes = ['small', 'medium', 'large'];
        foreach ($sizes as $size) {
            $result = $thumbnailService->generate($testImage, $size);

            if ($result['success']) {
                echo "✓ {$size} 缩略图生成成功\n";
                echo "  尺寸: {$result['width']}x{$result['height']}\n";
                echo "  大小: " . round($result['size'] / 1024, 2) . " KB\n";
                echo "  路径: {$result['thumbnail_path']}\n";
            }
        }

        // 清理
        @unlink($testImage);

    } catch (\Exception $e) {
        echo "生成缩略图失败: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

/**
 * 示例4: 提取媒体元数据
 */
function example4_metadata_extraction()
{
    echo "=== 示例4: 提取媒体元数据 ===\n";

    try {
        $extractor = new MediaMetadataExtractor();

        // 测试图片元数据
        echo "--- 图片元数据 ---\n";
        $testImage = runtime_path() . 'test_image.png';
        $imageData = file_get_contents('https://via.placeholder.com/1920x1080.png');
        file_put_contents($testImage, $imageData);

        $imageMeta = $extractor->extract($testImage, 'image');
        echo "宽度: {$imageMeta['width']} px\n";
        echo "高度: {$imageMeta['height']} px\n";
        echo "格式: {$imageMeta['type']}\n";
        echo "MIME类型: {$imageMeta['mime']}\n";

        // 清理
        @unlink($testImage);

    } catch (\Exception $e) {
        echo "提取元数据失败: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

/**
 * 示例5: 文件操作
 */
function example5_file_operations()
{
    echo "=== 示例5: 文件操作 ===\n";

    try {
        $oss = new OssService('local');

        // 创建测试文件
        $testFile = runtime_path() . 'operations_test.txt';
        file_put_contents($testFile, '测试文件内容');

        // 1. 上传文件
        $uploadResult = $oss->upload($testFile, 'operations/original.txt');
        echo "✓ 文件上传成功\n";

        // 2. 检查文件是否存在
        $exists = $oss->exists('operations/original.txt');
        echo "✓ 文件存在检查: " . ($exists ? '存在' : '不存在') . "\n";

        // 3. 获取文件信息
        $info = $oss->getFileInfo('operations/original.txt');
        echo "✓ 文件信息:\n";
        echo "  大小: " . round($info['size'] / 1024, 2) . " KB\n";
        echo "  类型: {$info['type']}\n";

        // 4. 复制文件
        $copied = $oss->copy('operations/original.txt', 'operations/copied.txt');
        echo "✓ 文件复制: " . ($copied ? '成功' : '失败') . "\n";

        // 5. 获取URL
        $url = $oss->getUrl('operations/original.txt');
        echo "✓ 文件URL: {$url}\n";

        // 6. 列出文件
        $files = $oss->listFiles('operations/', 10);
        echo "✓ 文件列表 (共 " . count($files['files']) . " 个文件):\n";
        foreach ($files['files'] as $file) {
            echo "  - {$file['key']}\n";
        }

        // 7. 批量删除
        $deleteResult = $oss->batchDelete([
            'operations/original.txt',
            'operations/copied.txt',
        ]);
        echo "✓ 批量删除: " . count(array_filter($deleteResult)) . "/" . count($deleteResult) . " 成功\n";

        // 清理
        @unlink($testFile);

    } catch (\Exception $e) {
        echo "操作失败: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

/**
 * 示例6: 素材导入集成
 */
function example6_material_import()
{
    echo "=== 示例6: 素材导入集成 ===\n";

    try {
        $materialService = new MaterialImportService();

        // 模拟上传文件数据
        $testImage = runtime_path() . 'material_test.jpg';
        $imageData = file_get_contents('https://via.placeholder.com/800x600.jpg');
        file_put_contents($testImage, $imageData);

        $fileData = [
            'name' => 'test_image.jpg',
            'tmp_name' => $testImage,
            'size' => filesize($testImage),
            'type' => 'image/jpeg'
        ];

        // 导入素材
        $result = $materialService->importSingleMaterial(
            'IMAGE',
            $fileData,
            [
                'name' => '测试图片',
                'category_id' => 1,
                'tags' => ['测试', '示例'],
            ]
        );

        if ($result['success']) {
            echo "✓ 素材导入成功\n";
            echo "  素材ID: {$result['material_id']}\n";
            echo "  文件URL: {$result['file_url']}\n";
            echo "  缩略图URL: {$result['thumbnail_url']}\n";
        } else {
            echo "✗ 素材导入失败: {$result['message']}\n";
        }

        // 清理
        @unlink($testImage);

    } catch (\Exception $e) {
        echo "素材导入失败: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

/**
 * 示例7: 不同驱动切换
 */
function example7_driver_switching()
{
    echo "=== 示例7: 不同驱动切换 ===\n";

    $drivers = ['local', 'aliyun', 'qiniu', 'tencent', 'aws'];

    foreach ($drivers as $driver) {
        try {
            echo "尝试初始化 {$driver} 驱动...\n";
            $oss = OssService::driver($driver);
            echo "✓ {$driver} 驱动初始化成功\n";
            echo "  当前驱动: " . $oss->getDriverName() . "\n";
        } catch (\Exception $e) {
            echo "✗ {$driver} 驱动初始化失败: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
}

/**
 * 运行所有示例
 */
function run_all_examples()
{
    echo "\n";
    echo "╔════════════════════════════════════════╗\n";
    echo "║   OSS服务快速开始示例                  ║\n";
    echo "╚════════════════════════════════════════╝\n\n";

    example1_basic_upload();
    example2_multipart_upload();
    example3_thumbnail_generation();
    example4_metadata_extraction();
    example5_file_operations();
    example6_material_import();
    example7_driver_switching();

    echo "所有示例运行完成!\n";
}

// 如果直接运行此文件
if (php_sapi_name() === 'cli' && realpath($argv[0]) === realpath(__FILE__)) {
    run_all_examples();
}
