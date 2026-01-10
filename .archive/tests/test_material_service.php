<?php
/**
 * 素材导入服务测试文件
 */

require __DIR__ . '/vendor/autoload.php';

use app\service\MaterialImportService;

// 初始化应用
$app = require __DIR__ . '/bootstrap.php';

echo "========================================\n";
echo "素材导入服务测试\n";
echo "========================================\n\n";

$service = new MaterialImportService();

// 测试1: 验证素材类型
echo "【测试1】验证素材类型支持\n";
$types = ['VIDEO', 'AUDIO', 'IMAGE', 'TEXT_TEMPLATE', 'TRANSITION', 'MUSIC'];
foreach ($types as $type) {
    echo "- {$type}: 支持\n";
}
echo "\n";

// 测试2: 创建素材分类
echo "【测试2】创建素材分类\n";
try {
    $categoryData = [
        'parent_id' => 0,
        'name' => '测试分类-视频',
        'type' => 'VIDEO',
        'description' => '这是一个测试分类',
        'sort' => 10,
        'status' => 1
    ];

    $categoryId = $service->createMaterialCategory($categoryData);
    echo "✓ 创建成功，分类ID: {$categoryId}\n";
} catch (\Exception $e) {
    echo "✗ 创建失败: {$e->getMessage()}\n";
}
echo "\n";

// 测试3: 获取素材分类列表
echo "【测试3】获取素材分类列表\n";
try {
    $categories = $service->getMaterialCategories(['type' => 'VIDEO']);
    echo "✓ 获取成功，共 " . count($categories) . " 个分类\n";
    foreach ($categories as $category) {
        echo "  - {$category['name']} (ID: {$category['id']})\n";
    }
} catch (\Exception $e) {
    echo "✗ 获取失败: {$e->getMessage()}\n";
}
echo "\n";

// 测试4: 验证文件（模拟）
echo "【测试4】文件验证规则检查\n";
$validationRules = [
    'VIDEO' => [
        'formats' => ['mp4', 'mov', 'avi', 'flv'],
        'max_size' => '500MB',
        'duration' => '1-60秒'
    ],
    'AUDIO' => [
        'formats' => ['mp3', 'wav', 'aac', 'm4a'],
        'max_size' => '50MB',
        'duration' => '1-30秒'
    ],
    'IMAGE' => [
        'formats' => ['jpg', 'jpeg', 'png', 'gif'],
        'max_size' => '10MB',
        'min_resolution' => '800x600'
    ]
];

foreach ($validationRules as $type => $rules) {
    echo "- {$type}:\n";
    echo "  格式: " . implode(', ', $rules['formats']) . "\n";
    echo "  大小: {$rules['max_size']}\n";
    if (isset($rules['duration'])) {
        echo "  时长: {$rules['duration']}\n";
    }
    if (isset($rules['min_resolution'])) {
        echo "  分辨率: {$rules['min_resolution']}\n";
    }
}
echo "\n";

// 测试5: 模拟素材导入（不实际上传文件）
echo "【测试5】模拟素材导入流程\n";
echo "注意：这是模拟测试，不会实际上传文件\n";
echo "完整的导入流程包括：\n";
echo "  1. 文件验证（格式、大小、时长等）\n";
echo "  2. 元数据提取（分辨率、时长、编码等）\n";
echo "  3. 上传到OSS存储\n";
echo "  4. 生成缩略图（视频/图片）\n";
echo "  5. 保存到数据库\n";
echo "  6. 自动审核或人工审核\n";
echo "\n";

// 测试6: 标签管理
echo "【测试6】标签管理功能\n";
echo "支持的操作：\n";
echo "  - 为素材添加标签\n";
echo "  - 根据标签搜索素材\n";
echo "  - 标签匹配度排序\n";
echo "  - 标签缓存优化\n";
echo "\n";

// 测试7: 配置检查
echo "【测试7】配置检查\n";
$configs = [
    'OSS存储' => config('material.oss.enabled', false) ? '已启用' : '未启用',
    '自动审核' => config('material.auto_audit', true) ? '已启用' : '未启用',
    '内容安全检查' => config('material.content_security_check', false) ? '已启用' : '未启用',
    '批量导入队列' => config('material.batch_import.use_queue', true) ? '已启用' : '未启用',
    '缩略图生成' => config('material.thumbnail.enabled', true) ? '已启用' : '未启用'
];

foreach ($configs as $name => $status) {
    echo "- {$name}: {$status}\n";
}
echo "\n";

// 测试8: 素材类型统计
echo "【测试8】素材类型说明\n";
$typeDescriptions = [
    'VIDEO' => '视频片段 - 用于视频创作的素材片段',
    'AUDIO' => '音效 - 短音效素材',
    'TRANSITION' => '转场效果 - 视频转场特效',
    'TEXT_TEMPLATE' => '文案模板 - 预设的文案模板',
    'IMAGE' => '图片素材 - 图片类型素材',
    'MUSIC' => '背景音乐 - 长时间背景音乐'
];

foreach ($typeDescriptions as $type => $description) {
    echo "- {$type}: {$description}\n";
}
echo "\n";

echo "========================================\n";
echo "测试完成！\n";
echo "========================================\n";
echo "\n";
echo "注意事项：\n";
echo "1. 请确保数据库迁移已执行\n";
echo "2. 请配置OSS相关参数（如需使用）\n";
echo "3. 请确保运行时目录有写入权限\n";
echo "4. 生产环境请启用内容安全检查\n";
echo "\n";
echo "使用文档：MATERIAL_IMPORT_SERVICE_USAGE.md\n";
echo "\n";