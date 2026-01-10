# 素材导入服务使用文档

## 概述

MaterialImportService 是一个全面的素材导入服务，支持视频片段、音效、转场效果、文案模板、图片素材和背景音乐的导入和管理。

## 功能特性

- 支持6种素材类型（视频、音效、转场、文案模板、图片、音乐）
- 单个文件导入和批量导入
- ZIP压缩包批量导入
- 文件格式和大小验证
- 自动提取元数据
- 阿里云OSS存储支持
- 自动审核和人工审核
- 素材分类管理
- 标签管理和搜索
- 缩略图自动生成
- 内容安全检查

## 安装配置

### 1. 数据库迁移

运行迁移文件创建数据表：

```bash
# Windows
D:\xiaomotui\api\database\run_migration.bat

# Linux/Mac
bash D:\xiaomotui\api\database\run_migration.sh
```

或者直接执行SQL文件：
- `20250930000006_create_materials_table.sql`
- `20250930000007_create_material_categories_table.sql`

### 2. 配置环境变量

在 `.env` 文件中添加以下配置：

```env
# OSS配置
material.oss.enabled=true
material.oss.access_key=your_access_key
material.oss.secret_key=your_secret_key
material.oss.bucket=materials
material.oss.endpoint=oss-cn-hangzhou.aliyuncs.com
material.oss.domain=https://materials.example.com

# 审核配置
material.auto_audit=true

# 内容安全配置
material.content_security_check=false
material.security_provider=aliyun

# 批量导入配置
material.batch_import.use_queue=true
```

## 使用示例

### 1. 导入单个素材

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

// 准备文件数据
$fileData = [
    'name' => 'video.mp4',
    'tmp_name' => '/tmp/upload/video.mp4',
    'size' => 10485760,  // 10MB
    'type' => 'video/mp4'
];

// 准备元数据
$metadata = [
    'name' => '产品展示视频',
    'category_id' => 1,
    'tags' => ['产品', '展示', '营销'],
    'weight' => 150,
    'creator_id' => 1
];

// 导入素材
$result = $service->importSingleMaterial('VIDEO', $fileData, $metadata);

if ($result['success']) {
    echo "素材导入成功，ID: " . $result['material_id'];
    echo "文件URL: " . $result['file_url'];
} else {
    echo "导入失败: " . $result['message'];
}
```

### 2. 批量导入素材

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

// 准备多个文件
$filesData = [
    [
        'name' => 'audio1.mp3',
        'tmp_name' => '/tmp/upload/audio1.mp3',
        'size' => 2097152,
        'type' => 'audio/mp3'
    ],
    [
        'name' => 'audio2.mp3',
        'tmp_name' => '/tmp/upload/audio2.mp3',
        'size' => 3145728,
        'type' => 'audio/mp3'
    ]
];

// 导入选项
$options = [
    'category_id' => 2,
    'tags' => ['背景音效', '轻松'],
    'creator_id' => 1
];

// 批量导入
$result = $service->batchImportMaterials('AUDIO', $filesData, $options);

echo "总计: {$result['total']}, 成功: {$result['success']}, 失败: {$result['failed']}";

// 查看详细结果
foreach ($result['details'] as $detail) {
    echo "{$detail['file_name']}: " . ($detail['success'] ? '成功' : $detail['message']) . "\n";
}
```

### 3. 从ZIP包导入

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

// ZIP文件路径
$zipFilePath = '/tmp/upload/materials.zip';

// 导入选项
$options = [
    'creator_id' => 1,
    'tags' => ['批量导入']
];

// 从ZIP导入
$result = $service->importFromZip($zipFilePath, $options);

if ($result['success']) {
    echo "ZIP导入完成\n";
    foreach ($result['results'] as $type => $typeResult) {
        echo "{$type}: 成功{$typeResult['success']}, 失败{$typeResult['failed']}\n";
    }
} else {
    echo "导入失败: " . $result['message'];
}
```

### 4. 验证素材文件

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

$filePath = '/tmp/upload/video.mp4';
$result = $service->validateMaterial('VIDEO', $filePath);

if ($result['valid']) {
    echo "验证通过";
} else {
    echo "验证失败: " . $result['message'];
}
```

### 5. 创建素材分类

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

$categoryData = [
    'parent_id' => 0,
    'name' => '产品展示',
    'type' => 'VIDEO',
    'description' => '产品相关的视频素材',
    'sort' => 10
];

$categoryId = $service->createMaterialCategory($categoryData);
echo "分类ID: {$categoryId}";
```

### 6. 获取素材分类列表

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

// 获取所有视频分类
$categories = $service->getMaterialCategories([
    'type' => 'VIDEO'
]);

foreach ($categories as $category) {
    echo "{$category['name']}\n";
}
```

### 7. 添加素材标签

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

$materialId = 1;
$tags = ['热门', '推荐', '新品'];

$result = $service->addMaterialTags($materialId, $tags);
if ($result) {
    echo "标签添加成功";
}
```

### 8. 根据标签搜索素材

```php
use app\service\MaterialImportService;

$service = new MaterialImportService();

$tags = ['热门', '推荐'];
$filters = [
    'type' => 'VIDEO',
    'category_id' => 1
];

$materials = $service->searchMaterialsByTags($tags, $filters);

foreach ($materials as $material) {
    echo "{$material['name']} (匹配度: {$material['tag_match_count']})\n";
}
```

## 控制器示例

### MaterialController.php

```php
<?php
namespace app\controller;

use app\service\MaterialImportService;
use think\Request;

class Material extends BaseController
{
    protected $materialService;

    public function __construct()
    {
        $this->materialService = new MaterialImportService();
    }

    /**
     * 上传单个素材
     */
    public function upload(Request $request)
    {
        try {
            $file = $request->file('file');
            $type = $request->post('type', 'VIDEO');

            if (!$file) {
                return json(['code' => 400, 'message' => '请选择文件']);
            }

            $fileData = [
                'name' => $file->getOriginalName(),
                'tmp_name' => $file->getPathname(),
                'size' => $file->getSize(),
                'type' => $file->getMime()
            ];

            $metadata = [
                'name' => $request->post('name', $file->getOriginalName()),
                'category_id' => $request->post('category_id'),
                'tags' => $request->post('tags', []),
                'creator_id' => $this->userId
            ];

            $result = $this->materialService->importSingleMaterial($type, $fileData, $metadata);

            if ($result['success']) {
                return json(['code' => 200, 'message' => '上传成功', 'data' => $result]);
            } else {
                return json(['code' => 500, 'message' => $result['message']]);
            }

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 批量上传素材
     */
    public function batchUpload(Request $request)
    {
        try {
            $files = $request->file('files');
            $type = $request->post('type', 'VIDEO');

            if (!$files) {
                return json(['code' => 400, 'message' => '请选择文件']);
            }

            $filesData = [];
            foreach ($files as $file) {
                $filesData[] = [
                    'name' => $file->getOriginalName(),
                    'tmp_name' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'type' => $file->getMime()
                ];
            }

            $options = [
                'category_id' => $request->post('category_id'),
                'tags' => $request->post('tags', []),
                'creator_id' => $this->userId
            ];

            $result = $this->materialService->batchImportMaterials($type, $filesData, $options);

            return json([
                'code' => 200,
                'message' => '批量上传完成',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 从ZIP导入
     */
    public function importZip(Request $request)
    {
        try {
            $file = $request->file('zip');

            if (!$file) {
                return json(['code' => 400, 'message' => '请选择ZIP文件']);
            }

            $options = [
                'creator_id' => $this->userId
            ];

            $result = $this->materialService->importFromZip($file->getPathname(), $options);

            if ($result['success']) {
                return json(['code' => 200, 'message' => 'ZIP导入成功', 'data' => $result]);
            } else {
                return json(['code' => 500, 'message' => $result['message']]);
            }

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 创建分类
     */
    public function createCategory(Request $request)
    {
        try {
            $data = [
                'parent_id' => $request->post('parent_id', 0),
                'name' => $request->post('name'),
                'type' => $request->post('type'),
                'description' => $request->post('description', ''),
                'sort' => $request->post('sort', 0)
            ];

            $categoryId = $this->materialService->createMaterialCategory($data);

            return json([
                'code' => 200,
                'message' => '创建成功',
                'data' => ['category_id' => $categoryId]
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 搜索素材
     */
    public function search(Request $request)
    {
        try {
            $tags = $request->post('tags', []);
            $filters = [
                'type' => $request->post('type'),
                'category_id' => $request->post('category_id')
            ];

            $materials = $this->materialService->searchMaterialsByTags($tags, $filters);

            return json([
                'code' => 200,
                'message' => '查询成功',
                'data' => $materials
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => $e->getMessage()]);
        }
    }
}
```

## 素材类型说明

### VIDEO - 视频片段
- 格式：mp4, mov, avi, flv
- 最大大小：500MB
- 时长限制：1-60秒
- 最小分辨率：720x576

### AUDIO - 音效
- 格式：mp3, wav, aac, m4a
- 最大大小：50MB
- 时长限制：1-30秒
- 最低比特率：128kbps

### IMAGE - 图片素材
- 格式：jpg, jpeg, png, gif
- 最大大小：10MB
- 最小分辨率：800x600

### TEXT_TEMPLATE - 文案模板
- 格式：txt, json
- 最大大小：1MB
- 字符限制：10-5000字符

### TRANSITION - 转场效果
- 格式：mp4, mov, webm
- 最大大小：20MB
- 最大时长：3秒

### MUSIC - 背景音乐
- 格式：mp3, wav, aac, m4a, flac
- 最大大小：100MB
- 时长限制：10-600秒

## 审核流程

### 自动审核
- 文件大小检查
- 文件格式验证
- 敏感词过滤
- 基础内容安全检查

### 人工审核
- 视频内容审核
- 音频内容审核
- 版权合规检查

## 错误处理

服务方法返回统一格式：

```php
// 成功
[
    'success' => true,
    'material_id' => 123,
    'message' => '操作成功'
]

// 失败
[
    'success' => false,
    'message' => '错误信息'
]
```

## 性能优化建议

1. **批量导入**：使用队列处理大批量导入
2. **缓存**：启用分类和标签缓存
3. **异步上传**：大文件使用异步上传到OSS
4. **分块处理**：ZIP文件分块解压和导入
5. **CDN加速**：OSS配置CDN加速访问

## 安全注意事项

1. 验证文件类型和大小
2. 启用内容安全检查
3. 配置敏感词过滤
4. 限制上传频率
5. 权限控制和认证

## 日志记录

服务会自动记录以下日志：

- 导入操作日志
- 验证失败日志
- 上传失败日志
- 审核结果日志

日志位置：`runtime/log/`

## 常见问题

### 1. 上传失败

检查：
- 文件大小是否超限
- 文件格式是否支持
- OSS配置是否正确
- 网络连接是否正常

### 2. 审核不通过

原因：
- 文件包含敏感词
- 文件大小异常
- 内容违规

### 3. ZIP导入失败

检查：
- ZIP文件是否完整
- 解压目录权限
- 临时目录空间

## 更新日志

### v1.0.0 (2025-09-30)
- 初始版本发布
- 支持6种素材类型
- 实现基础导入功能
- 支持批量导入和ZIP导入
- 实现自动审核功能

## 技术支持

如有问题，请查看：
- 项目文档
- 日志文件
- 配置文件

或联系技术支持团队。