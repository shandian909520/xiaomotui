<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Log;
use think\facade\Cache;
use think\Exception;
use app\model\Material;
use app\model\MaterialCategory;

/**
 * 素材导入服务
 * 支持视频片段、音效、转场效果、文案模板的批量导入功能
 */
class MaterialImportService
{
    /**
     * 素材类型常量
     */
    const TYPE_VIDEO = 'VIDEO';                    // 视频片段
    const TYPE_AUDIO = 'AUDIO';                    // 音效
    const TYPE_TRANSITION = 'TRANSITION';          // 转场效果
    const TYPE_TEXT_TEMPLATE = 'TEXT_TEMPLATE';    // 文案模板
    const TYPE_IMAGE = 'IMAGE';                    // 图片素材
    const TYPE_MUSIC = 'MUSIC';                    // 背景音乐

    /**
     * 审核状态常量
     */
    const AUDIT_PENDING = 0;    // 待审核
    const AUDIT_APPROVED = 1;   // 审核通过
    const AUDIT_REJECTED = 2;   // 审核拒绝

    /**
     * 素材状态常量
     */
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用
    const STATUS_AUDITING = 2;  // 审核中

    /**
     * 文件验证规则
     */
    protected $validationRules = [
        'VIDEO' => [
            'formats' => ['mp4', 'mov', 'avi', 'flv'],
            'max_size' => 500 * 1024 * 1024,  // 500MB
            'min_duration' => 1,
            'max_duration' => 60,
            'min_resolution' => '720x576'
        ],
        'AUDIO' => [
            'formats' => ['mp3', 'wav', 'aac', 'm4a'],
            'max_size' => 50 * 1024 * 1024,   // 50MB
            'min_duration' => 1,
            'max_duration' => 30,
            'min_bitrate' => 128
        ],
        'IMAGE' => [
            'formats' => ['jpg', 'jpeg', 'png', 'gif'],
            'max_size' => 10 * 1024 * 1024,   // 10MB
            'min_width' => 800,
            'min_height' => 600
        ],
        'TEXT_TEMPLATE' => [
            'formats' => ['txt', 'json'],
            'max_size' => 1 * 1024 * 1024,    // 1MB
            'min_length' => 10,
            'max_length' => 5000
        ],
        'TRANSITION' => [
            'formats' => ['mp4', 'mov', 'webm'],
            'max_size' => 20 * 1024 * 1024,   // 20MB
            'max_duration' => 3
        ],
        'MUSIC' => [
            'formats' => ['mp3', 'wav', 'aac', 'm4a', 'flac'],
            'max_size' => 100 * 1024 * 1024,  // 100MB
            'min_duration' => 10,
            'max_duration' => 600
        ]
    ];

    /**
     * OSS配置
     */
    protected $ossConfig = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->ossConfig = [
            'access_key' => config('material.oss.access_key'),
            'secret_key' => config('material.oss.secret_key'),
            'bucket' => config('material.oss.bucket'),
            'endpoint' => config('material.oss.endpoint'),
            'domain' => config('material.oss.domain')
        ];
    }

    /**
     * 导入单个素材
     * @param string $materialType 素材类型
     * @param array $fileData 文件数据（包含临时路径、原始文件名等）
     * @param array $metadata 素材元数据
     * @return array 导入结果
     */
    public function importSingleMaterial(string $materialType, array $fileData, array $metadata = []): array
    {
        Log::info('开始导入单个素材', [
            'type' => $materialType,
            'file_name' => $fileData['name'] ?? 'unknown',
            'file_size' => $fileData['size'] ?? 0
        ]);

        try {
            // 验证素材类型
            if (!$this->isValidMaterialType($materialType)) {
                throw new Exception("不支持的素材类型: {$materialType}");
            }

            // 验证文件
            $validationResult = $this->validateMaterial($materialType, $fileData['tmp_name'] ?? '');
            if (!$validationResult['valid']) {
                throw new Exception($validationResult['message']);
            }

            // 提取元数据
            $extractedMetadata = $this->extractMetadata($materialType, $fileData['tmp_name']);
            $mergedMetadata = array_merge($extractedMetadata, $metadata);

            // 上传到OSS
            $ossUrl = $this->uploadToOss($fileData['tmp_name'], $materialType, [
                'original_name' => $fileData['name'] ?? 'unknown',
                'mime_type' => $fileData['type'] ?? 'application/octet-stream'
            ]);

            // 生成缩略图（如果是视频或图片）
            $thumbnailUrl = null;
            if (in_array($materialType, ['VIDEO', 'IMAGE'])) {
                $thumbnailUrl = $this->generateThumbnail($fileData['tmp_name'], $materialType);
            }

            // 保存到数据库
            $materialData = [
                'type' => $materialType,
                'name' => $metadata['name'] ?? $fileData['name'] ?? '未命名素材',
                'category_id' => $metadata['category_id'] ?? null,
                'file_url' => $ossUrl,
                'thumbnail_url' => $thumbnailUrl,
                'file_size' => $fileData['size'] ?? filesize($fileData['tmp_name']),
                'duration' => $mergedMetadata['duration'] ?? null,
                'metadata' => json_encode($mergedMetadata),
                'tags' => json_encode($metadata['tags'] ?? []),
                'usage_count' => 0,
                'weight' => $metadata['weight'] ?? 100,
                'status' => self::STATUS_AUDITING,
                'audit_status' => self::AUDIT_PENDING,
                'creator_id' => $metadata['creator_id'] ?? null,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ];

            $material = Material::create($materialData);

            // 自动审核
            if (config('material.auto_audit', false)) {
                $this->autoAudit($material);
            }

            Log::info('素材导入成功', [
                'material_id' => $material->id,
                'type' => $materialType,
                'file_url' => $ossUrl
            ]);

            return [
                'success' => true,
                'material_id' => $material->id,
                'file_url' => $ossUrl,
                'thumbnail_url' => $thumbnailUrl,
                'message' => '素材导入成功'
            ];

        } catch (\Exception $e) {
            Log::error('素材导入失败', [
                'type' => $materialType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 批量导入素材
     * @param string $materialType 素材类型
     * @param array $filesData 多个文件数据
     * @param array $options 导入选项
     * @return array 导入结果统计
     */
    public function batchImportMaterials(string $materialType, array $filesData, array $options = []): array
    {
        Log::info('开始批量导入素材', [
            'type' => $materialType,
            'count' => count($filesData)
        ]);

        $results = [
            'total' => count($filesData),
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($filesData as $index => $fileData) {
            $metadata = array_merge($options, [
                'batch_import' => true,
                'batch_index' => $index
            ]);

            $result = $this->importSingleMaterial($materialType, $fileData, $metadata);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'file_name' => $fileData['name'] ?? 'unknown',
                'success' => $result['success'],
                'message' => $result['message'] ?? '',
                'material_id' => $result['material_id'] ?? null
            ];
        }

        Log::info('批量导入完成', [
            'type' => $materialType,
            'total' => $results['total'],
            'success' => $results['success'],
            'failed' => $results['failed']
        ]);

        return $results;
    }

    /**
     * 从ZIP压缩包导入素材
     * @param string $zipFilePath ZIP文件路径
     * @param array $options 导入选项
     * @return array 导入结果
     */
    public function importFromZip(string $zipFilePath, array $options = []): array
    {
        Log::info('开始从ZIP导入素材', [
            'zip_file' => $zipFilePath,
            'file_size' => filesize($zipFilePath)
        ]);

        try {
            // 检查ZIP文件是否存在
            if (!file_exists($zipFilePath)) {
                throw new Exception('ZIP文件不存在');
            }

            // 检查ZIP扩展
            if (!class_exists('ZipArchive')) {
                throw new Exception('系统不支持ZIP解压功能');
            }

            // 创建临时解压目录
            $extractPath = runtime_path() . 'material_import/' . uniqid() . '/';
            if (!is_dir($extractPath)) {
                mkdir($extractPath, 0755, true);
            }

            // 解压ZIP文件
            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath) !== true) {
                throw new Exception('无法打开ZIP文件');
            }

            $zip->extractTo($extractPath);
            $zip->close();

            // 扫描解压后的文件
            $files = $this->scanDirectory($extractPath);

            Log::info('ZIP文件解压完成', [
                'extract_path' => $extractPath,
                'file_count' => count($files)
            ]);

            // 按文件类型分组
            $groupedFiles = $this->groupFilesByType($files);

            // 批量导入各类素材
            $importResults = [];
            foreach ($groupedFiles as $materialType => $fileList) {
                $filesData = [];
                foreach ($fileList as $filePath) {
                    $filesData[] = [
                        'name' => basename($filePath),
                        'tmp_name' => $filePath,
                        'size' => filesize($filePath),
                        'type' => mime_content_type($filePath)
                    ];
                }

                $result = $this->batchImportMaterials($materialType, $filesData, $options);
                $importResults[$materialType] = $result;
            }

            // 清理临时目录
            $this->cleanDirectory($extractPath);

            return [
                'success' => true,
                'results' => $importResults,
                'message' => 'ZIP导入完成'
            ];

        } catch (\Exception $e) {
            Log::error('从ZIP导入失败', [
                'zip_file' => $zipFilePath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 验证素材文件
     * @param string $materialType 素材类型
     * @param string $filePath 文件路径
     * @return array 验证结果
     */
    public function validateMaterial(string $materialType, string $filePath): array
    {
        try {
            // 检查文件是否存在
            if (!file_exists($filePath)) {
                return [
                    'valid' => false,
                    'message' => '文件不存在'
                ];
            }

            // 获取验证规则
            $rules = $this->validationRules[$materialType] ?? [];
            if (empty($rules)) {
                return [
                    'valid' => false,
                    'message' => '未定义的素材类型验证规则'
                ];
            }

            // 验证文件扩展名
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (!in_array($extension, $rules['formats'])) {
                return [
                    'valid' => false,
                    'message' => "不支持的文件格式: {$extension}，支持的格式: " . implode(', ', $rules['formats'])
                ];
            }

            // 验证文件大小
            $fileSize = filesize($filePath);
            if ($fileSize > $rules['max_size']) {
                return [
                    'valid' => false,
                    'message' => '文件大小超过限制: ' . $this->formatBytes($rules['max_size'])
                ];
            }

            // 根据类型进行特定验证
            $typeValidation = match($materialType) {
                'VIDEO', 'AUDIO', 'MUSIC', 'TRANSITION' => $this->validateMediaFile($filePath, $rules),
                'IMAGE' => $this->validateImageFile($filePath, $rules),
                'TEXT_TEMPLATE' => $this->validateTextFile($filePath, $rules),
                default => ['valid' => true]
            };

            if (!$typeValidation['valid']) {
                return $typeValidation;
            }

            // 内容安全检查
            if (config('material.content_security_check', false)) {
                $securityCheck = $this->checkContentSecurity($filePath, $materialType);
                if (!$securityCheck['valid']) {
                    return $securityCheck;
                }
            }

            return [
                'valid' => true,
                'message' => '验证通过'
            ];

        } catch (\Exception $e) {
            Log::error('素材验证失败', [
                'type' => $materialType,
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'message' => '验证过程发生错误: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 上传素材到阿里云OSS
     * @param string $localFilePath 本地文件路径
     * @param string $materialType 素材类型
     * @param array $options 上传选项
     * @return string OSS文件URL
     */
    public function uploadToOss(string $localFilePath, string $materialType, array $options = []): string
    {
        try {
            // 生成OSS存储路径
            $extension = pathinfo($localFilePath, PATHINFO_EXTENSION);
            $fileName = $options['original_name'] ?? basename($localFilePath);
            $ossPath = $this->generateOssPath($materialType, $fileName, $extension);

            Log::info('开始上传到OSS', [
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
                'type' => $materialType
            ]);

            // 实际项目中这里应该使用阿里云OSS SDK
            // 这里使用模拟实现
            if (config('material.oss.enabled', false)) {
                // TODO: 实现真实的OSS上传
                // $ossClient = new \OSS\OssClient(...);
                // $ossClient->uploadFile($this->ossConfig['bucket'], $ossPath, $localFilePath);
            }

            // 生成访问URL
            $ossUrl = $this->ossConfig['domain'] . '/' . $ossPath;

            Log::info('OSS上传成功', [
                'oss_path' => $ossPath,
                'oss_url' => $ossUrl
            ]);

            return $ossUrl;

        } catch (\Exception $e) {
            Log::error('OSS上传失败', [
                'local_path' => $localFilePath,
                'error' => $e->getMessage()
            ]);

            throw new Exception('上传到OSS失败: ' . $e->getMessage());
        }
    }

    /**
     * 提取素材元数据
     * @param string $materialType 素材类型
     * @param string $filePath 文件路径
     * @return array 元数据
     */
    public function extractMetadata(string $materialType, string $filePath): array
    {
        $metadata = [
            'file_name' => basename($filePath),
            'file_size' => filesize($filePath),
            'mime_type' => mime_content_type($filePath),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
            'extract_time' => date('Y-m-d H:i:s')
        ];

        try {
            switch ($materialType) {
                case 'VIDEO':
                case 'AUDIO':
                case 'MUSIC':
                    $metadata = array_merge($metadata, $this->extractMediaMetadata($filePath));
                    break;

                case 'IMAGE':
                    $metadata = array_merge($metadata, $this->extractImageMetadata($filePath));
                    break;

                case 'TEXT_TEMPLATE':
                    $metadata = array_merge($metadata, $this->extractTextMetadata($filePath));
                    break;

                case 'TRANSITION':
                    $metadata = array_merge($metadata, $this->extractTransitionMetadata($filePath));
                    break;
            }

        } catch (\Exception $e) {
            Log::warning('元数据提取部分失败', [
                'type' => $materialType,
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
        }

        return $metadata;
    }

    /**
     * 创建素材分类
     * @param array $categoryData 分类数据
     * @return int 分类ID
     */
    public function createMaterialCategory(array $categoryData): int
    {
        try {
            $data = [
                'parent_id' => $categoryData['parent_id'] ?? 0,
                'name' => $categoryData['name'],
                'type' => $categoryData['type'],
                'description' => $categoryData['description'] ?? '',
                'sort' => $categoryData['sort'] ?? 0,
                'status' => $categoryData['status'] ?? 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ];

            $category = MaterialCategory::create($data);

            Log::info('创建素材分类成功', [
                'category_id' => $category->id,
                'name' => $category->name
            ]);

            return $category->id;

        } catch (\Exception $e) {
            Log::error('创建素材分类失败', [
                'data' => $categoryData,
                'error' => $e->getMessage()
            ]);

            throw new Exception('创建素材分类失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取素材分类列表
     * @param array $filters 过滤条件
     * @return array 分类列表
     */
    public function getMaterialCategories(array $filters = []): array
    {
        try {
            $query = MaterialCategory::where('status', 1);

            // 类型过滤
            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            // 父分类过滤
            if (isset($filters['parent_id'])) {
                $query->where('parent_id', $filters['parent_id']);
            }

            $categories = $query->order('sort', 'asc')
                               ->order('create_time', 'desc')
                               ->select()
                               ->toArray();

            return $categories;

        } catch (\Exception $e) {
            Log::error('获取素材分类列表失败', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * 为素材添加标签
     * @param int $materialId 素材ID
     * @param array $tags 标签列表
     * @return bool 操作结果
     */
    public function addMaterialTags(int $materialId, array $tags): bool
    {
        try {
            $material = Material::find($materialId);
            if (!$material) {
                throw new Exception('素材不存在');
            }

            // 合并现有标签
            $existingTags = json_decode($material->tags, true) ?? [];
            $mergedTags = array_unique(array_merge($existingTags, $tags));

            $material->tags = json_encode($mergedTags);
            $material->save();

            Log::info('添加素材标签成功', [
                'material_id' => $materialId,
                'tags' => $tags
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('添加素材标签失败', [
                'material_id' => $materialId,
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 根据标签搜索素材
     * @param array $tags 标签列表
     * @param array $filters 额外过滤条件
     * @return array 素材列表
     */
    public function searchMaterialsByTags(array $tags, array $filters = []): array
    {
        try {
            $cacheKey = 'materials:tags:' . md5(json_encode($tags) . json_encode($filters));

            // 尝试从缓存获取
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }

            $query = Material::where('status', self::STATUS_ENABLED)
                            ->where('audit_status', self::AUDIT_APPROVED);

            // 类型过滤
            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            // 分类过滤
            if (!empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }

            $materials = $query->select();

            // 根据标签过滤
            $filteredMaterials = [];
            foreach ($materials as $material) {
                $materialTags = json_decode($material->tags, true) ?? [];
                $matchCount = count(array_intersect($tags, $materialTags));

                if ($matchCount > 0) {
                    $materialArray = $material->toArray();
                    $materialArray['tag_match_count'] = $matchCount;
                    $filteredMaterials[] = $materialArray;
                }
            }

            // 按匹配度和权重排序
            usort($filteredMaterials, function($a, $b) {
                if ($a['tag_match_count'] !== $b['tag_match_count']) {
                    return $b['tag_match_count'] - $a['tag_match_count'];
                }
                return $b['weight'] - $a['weight'];
            });

            // 缓存结果（5分钟）
            Cache::set($cacheKey, $filteredMaterials, 300);

            return $filteredMaterials;

        } catch (\Exception $e) {
            Log::error('根据标签搜索素材失败', [
                'tags' => $tags,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * 自动审核素材
     * @param Material $material 素材对象
     * @return void
     */
    protected function autoAudit($material): void
    {
        try {
            // 自动审核逻辑（简单实现）
            $autoPass = true;

            // 检查文件大小是否异常
            if ($material->file_size > 1024 * 1024 * 1024) { // 超过1GB
                $autoPass = false;
            }

            // 检查是否有敏感词（需要配置敏感词库）
            $sensitiveWords = config('material.sensitive_words', []);
            foreach ($sensitiveWords as $word) {
                if (strpos($material->name, $word) !== false) {
                    $autoPass = false;
                    break;
                }
            }

            if ($autoPass) {
                $material->audit_status = self::AUDIT_APPROVED;
                $material->status = self::STATUS_ENABLED;
                $material->audit_message = '自动审核通过';
            } else {
                $material->audit_status = self::AUDIT_PENDING;
                $material->status = self::STATUS_AUDITING;
                $material->audit_message = '需要人工审核';
            }

            $material->save();

            Log::info('素材自动审核完成', [
                'material_id' => $material->id,
                'audit_status' => $material->audit_status,
                'status' => $material->status
            ]);

        } catch (\Exception $e) {
            Log::error('素材自动审核失败', [
                'material_id' => $material->id ?? 0,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 验证媒体文件
     * @param string $filePath 文件路径
     * @param array $rules 验证规则
     * @return array 验证结果
     */
    protected function validateMediaFile(string $filePath, array $rules): array
    {
        // 简化实现，实际应该使用FFmpeg等工具
        return ['valid' => true];
    }

    /**
     * 验证图片文件
     * @param string $filePath 文件路径
     * @param array $rules 验证规则
     * @return array 验证结果
     */
    protected function validateImageFile(string $filePath, array $rules): array
    {
        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return [
                'valid' => false,
                'message' => '无法读取图片信息'
            ];
        }

        if ($imageInfo[0] < $rules['min_width'] || $imageInfo[1] < $rules['min_height']) {
            return [
                'valid' => false,
                'message' => "图片分辨率过低，最小要求: {$rules['min_width']}x{$rules['min_height']}"
            ];
        }

        return ['valid' => true];
    }

    /**
     * 验证文本文件
     * @param string $filePath 文件路径
     * @param array $rules 验证规则
     * @return array 验证结果
     */
    protected function validateTextFile(string $filePath, array $rules): array
    {
        $content = file_get_contents($filePath);
        $length = mb_strlen($content);

        if ($length < $rules['min_length']) {
            return [
                'valid' => false,
                'message' => "文本内容过短，最少{$rules['min_length']}字符"
            ];
        }

        if ($length > $rules['max_length']) {
            return [
                'valid' => false,
                'message' => "文本内容过长，最多{$rules['max_length']}字符"
            ];
        }

        return ['valid' => true];
    }

    /**
     * 内容安全检查
     * @param string $filePath 文件路径
     * @param string $materialType 素材类型
     * @return array 检查结果
     */
    protected function checkContentSecurity(string $filePath, string $materialType): array
    {
        // TODO: 实现内容安全检查（接入阿里云内容安全、腾讯云天御等）
        return ['valid' => true];
    }

    /**
     * 生成缩略图
     * @param string $filePath 文件路径
     * @param string $materialType 素材类型
     * @return string|null 缩略图URL
     */
    protected function generateThumbnail(string $filePath, string $materialType): ?string
    {
        try {
            // TODO: 实现真实的缩略图生成
            // 对于视频，需要使用FFmpeg
            // 对于图片，使用GD或Imagick
            return null;

        } catch (\Exception $e) {
            Log::warning('生成缩略图失败', [
                'file_path' => $filePath,
                'type' => $materialType,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 提取媒体文件元数据
     * @param string $filePath 文件路径
     * @return array 元数据
     */
    protected function extractMediaMetadata(string $filePath): array
    {
        // TODO: 使用FFmpeg或getID3库提取媒体元数据
        return [
            'duration' => 0,
            'bitrate' => 0,
            'codec' => '',
            'resolution' => ''
        ];
    }

    /**
     * 提取图片元数据
     * @param string $filePath 文件路径
     * @return array 元数据
     */
    protected function extractImageMetadata(string $filePath): array
    {
        $imageInfo = @getimagesize($filePath);
        return [
            'width' => $imageInfo[0] ?? 0,
            'height' => $imageInfo[1] ?? 0,
            'type' => $imageInfo[2] ?? 0,
            'mime' => $imageInfo['mime'] ?? ''
        ];
    }

    /**
     * 提取文本元数据
     * @param string $filePath 文件路径
     * @return array 元数据
     */
    protected function extractTextMetadata(string $filePath): array
    {
        $content = file_get_contents($filePath);
        return [
            'length' => mb_strlen($content),
            'lines' => substr_count($content, "\n") + 1,
            'encoding' => mb_detect_encoding($content)
        ];
    }

    /**
     * 提取转场效果元数据
     * @param string $filePath 文件路径
     * @return array 元数据
     */
    protected function extractTransitionMetadata(string $filePath): array
    {
        return $this->extractMediaMetadata($filePath);
    }

    /**
     * 生成OSS存储路径
     * @param string $materialType 素材类型
     * @param string $fileName 文件名
     * @param string $extension 扩展名
     * @return string OSS路径
     */
    protected function generateOssPath(string $materialType, string $fileName, string $extension): string
    {
        $date = date('Y/m/d');
        $hash = md5($fileName . time() . uniqid());
        return "materials/{$materialType}/{$date}/{$hash}.{$extension}";
    }

    /**
     * 扫描目录获取所有文件
     * @param string $directory 目录路径
     * @return array 文件列表
     */
    protected function scanDirectory(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * 按类型分组文件
     * @param array $files 文件列表
     * @return array 分组后的文件
     */
    protected function groupFilesByType(array $files): array
    {
        $grouped = [];

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $materialType = $this->detectMaterialType($extension);

            if ($materialType) {
                if (!isset($grouped[$materialType])) {
                    $grouped[$materialType] = [];
                }
                $grouped[$materialType][] = $file;
            }
        }

        return $grouped;
    }

    /**
     * 检测素材类型
     * @param string $extension 文件扩展名
     * @return string|null 素材类型
     */
    protected function detectMaterialType(string $extension): ?string
    {
        foreach ($this->validationRules as $type => $rules) {
            if (in_array($extension, $rules['formats'])) {
                return $type;
            }
        }
        return null;
    }

    /**
     * 清理目录
     * @param string $directory 目录路径
     * @return void
     */
    protected function cleanDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->cleanDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }

    /**
     * 验证素材类型是否有效
     * @param string $materialType 素材类型
     * @return bool
     */
    protected function isValidMaterialType(string $materialType): bool
    {
        return in_array($materialType, [
            self::TYPE_VIDEO,
            self::TYPE_AUDIO,
            self::TYPE_TRANSITION,
            self::TYPE_TEXT_TEMPLATE,
            self::TYPE_IMAGE,
            self::TYPE_MUSIC
        ]);
    }

    /**
     * 格式化字节大小
     * @param int $bytes 字节数
     * @return string 格式化后的大小
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }
}