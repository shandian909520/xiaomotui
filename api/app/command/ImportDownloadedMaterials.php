<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class ImportDownloadedMaterials extends Command
{
    private string $materialsPath;

    protected function configure()
    {
        $this->setName('import:materials')
            ->setDescription('导入下载的素材数据到内容库')
            ->addOption('merchant-id', 'm', Option::VALUE_OPTIONAL, '目标商家ID', 1)
            ->addOption('type', 't', Option::VALUE_OPTIONAL, '导入类型: video|graphic|topic|all', 'all')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅预览不实际导入')
            ->addOption('path', 'p', Option::VALUE_OPTIONAL, '素材目录路径', '');
    }

    protected function execute(Input $input, Output $output)
    {
        $merchantId = (int)$input->getOption('merchant-id');
        $type = $input->getOption('type');
        $dryRun = $input->getOption('dry-run');
        $customPath = $input->getOption('path');

        $this->materialsPath = $customPath ?: root_path() . '../downloaded_materials';

        $output->writeln('==========================================');
        $output->writeln('素材数据导入工具');
        $output->writeln('==========================================');
        $output->writeln("商家ID: {$merchantId}");
        $output->writeln("导入类型: {$type}");
        $output->writeln("素材路径: {$this->materialsPath}");
        $output->writeln("预览模式: " . ($dryRun ? '是' : '否'));
        $output->writeln('');

        if ($dryRun) {
            $output->writeln('<info>[预览模式] 不会实际写入数据库</info>');
            $output->writeln('');
        }

        $stats = ['libraries' => 0, 'items' => 0];

        Db::startTrans();
        try {
            if ($type === 'all' || $type === 'topic') {
                $count = $this->importTopicLibraries($merchantId, $dryRun, $output);
                $stats['libraries'] += $count['libraries'];
                $stats['items'] += $count['items'];
            }

            if ($type === 'all' || $type === 'graphic') {
                $count = $this->importGraphicLibraries($merchantId, $dryRun, $output);
                $stats['libraries'] += $count['libraries'];
                $stats['items'] += $count['items'];
            }

            if ($type === 'all' || $type === 'video') {
                $count = $this->importVideoLibraries($merchantId, $dryRun, $output);
                $stats['libraries'] += $count['libraries'];
                $stats['items'] += $count['items'];
            }

            if (!$dryRun) {
                Db::commit();
                $output->writeln('');
                $output->writeln('<info>导入完成!</info>');
            } else {
                Db::rollback();
                $output->writeln('');
                $output->writeln('<info>预览完成 (已回滚)</info>');
            }

            $output->writeln("统计: {$stats['libraries']} 个库, {$stats['items']} 个条目");
        } catch (\Exception $e) {
            Db::rollback();
            $output->writeln('<error>导入失败: ' . $e->getMessage() . '</error>');
            Log::error('素材导入失败', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    private function importTopicLibraries(int $merchantId, bool $dryRun, Output $output): array
    {
        $output->writeln('--- 导入话题库 ---');

        $jsonPath = $this->materialsPath . '/topic_library.json';
        if (!file_exists($jsonPath)) {
            $output->writeln("<comment>未找到 {$jsonPath}，跳过</comment>");
            return ['libraries' => 0, 'items' => 0];
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) {
            $output->writeln('<error>topic_library.json 解析失败</error>');
            return ['libraries' => 0, 'items' => 0];
        }

        $libraries = 0;
        $items = 0;

        foreach ($data as $lib) {
            $libraryId = Db::table('xmt_content_libraries')->insertGetId([
                'merchant_id' => $merchantId,
                'library_type' => 'topic',
                'name' => $lib['name'],
                'max_use_count' => $lib['topic_count'] ?? 1,
                'total_count' => $lib['topic_count'] ?? 0,
                'used_count' => 0,
                'remaining_count' => $lib['topic_count'] ?? 0,
                'status' => 1,
                'create_time' => $lib['created_at'] ?? date('Y-m-d H:i:s'),
                'update_time' => $lib['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);

            if ($dryRun) {
                $output->writeln("  [预览] 话题库: {$lib['name']} ({$lib['topic_count']} 话题)");
            } else {
                $output->writeln("  ✓ 话题库: {$lib['name']} (ID:{$libraryId})");
            }
            $libraries++;

            if (!empty($lib['topics'])) {
                foreach ($lib['topics'] as $topic) {
                    Db::table('xmt_content_library_items')->insert([
                        'library_id' => $libraryId,
                        'item_type' => 'topic',
                        'title' => null,
                        'content' => $topic['content'],
                        'file_url' => null,
                        'thumbnail_url' => null,
                        'paired_item_id' => null,
                        'metadata' => json_encode([
                            'original_id' => $topic['id'],
                            'use_count_original' => $topic['use_count'],
                        ]),
                        'use_count' => $topic['use_count'] ?? 0,
                        'source' => 'import',
                        'status' => 1,
                        'create_time' => $topic['created_at'] ?? date('Y-m-d H:i:s'),
                        'update_time' => $topic['updated_at'] ?? date('Y-m-d H:i:s'),
                    ]);
                    $items++;
                }
            }
        }

        $output->writeln("  话题库: {$libraries} 个库, {$items} 个话题");
        return ['libraries' => $libraries, 'items' => $items];
    }

    private function importGraphicLibraries(int $merchantId, bool $dryRun, Output $output): array
    {
        $output->writeln('--- 导入图文库 ---');

        $jsonPath = $this->materialsPath . '/graphic_library.json';
        if (!file_exists($jsonPath)) {
            $output->writeln("<comment>未找到 {$jsonPath}，跳过</comment>");
            return ['libraries' => 0, 'items' => 0];
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) {
            $output->writeln('<error>graphic_library.json 解析失败</error>');
            return ['libraries' => 0, 'items' => 0];
        }

        $libraries = 0;
        $items = 0;

        foreach ($data as $lib) {
            $graphicCount = $lib['graphic_text_count'] ?? count($lib['graphics'] ?? []);

            $libraryId = Db::table('xmt_content_libraries')->insertGetId([
                'merchant_id' => $merchantId,
                'library_type' => 'graphic',
                'name' => $lib['name'],
                'max_use_count' => $lib['max_use_count'] ?? 1,
                'total_count' => $graphicCount,
                'used_count' => 0,
                'remaining_count' => $graphicCount,
                'status' => 1,
                'create_time' => $lib['created_at'] ?? date('Y-m-d H:i:s'),
                'update_time' => $lib['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);

            if ($dryRun) {
                $output->writeln("  [预览] 图文库: {$lib['name']} ({$graphicCount} 图文)");
            } else {
                $output->writeln("  ✓ 图文库: {$lib['name']} (ID:{$libraryId})");
            }
            $libraries++;

            if (!empty($lib['graphics'])) {
                foreach ($lib['graphics'] as $graphic) {
                    $imageUrls = $graphic['image_urls'] ?? [];
                    $firstImageUrl = $imageUrls[0] ?? null;

                    $textItemId = Db::table('xmt_content_library_items')->insertGetId([
                        'library_id' => $libraryId,
                        'item_type' => 'text',
                        'title' => $graphic['title'],
                        'content' => $graphic['content'],
                        'file_url' => null,
                        'thumbnail_url' => null,
                        'paired_item_id' => null,
                        'metadata' => json_encode([
                            'original_id' => $graphic['id'],
                            'material_ids' => $graphic['material_ids'],
                            'oss_ids' => $graphic['oss_ids'],
                            'source' => $graphic['source'],
                        ]),
                        'use_count' => $graphic['use_count'] ?? 0,
                        'source' => 'import',
                        'status' => 1,
                        'create_time' => $graphic['created_at'] ?? date('Y-m-d H:i:s'),
                        'update_time' => $graphic['updated_at'] ?? date('Y-m-d H:i:s'),
                    ]);

                    if ($firstImageUrl) {
                        Db::table('xmt_content_library_items')->insert([
                            'library_id' => $libraryId,
                            'item_type' => 'image',
                            'title' => $graphic['title'],
                            'content' => null,
                            'file_url' => $firstImageUrl,
                            'thumbnail_url' => $firstImageUrl,
                            'paired_item_id' => $textItemId,
                            'metadata' => json_encode([
                                'original_id' => $graphic['id'],
                                'all_image_urls' => $imageUrls,
                            ]),
                            'use_count' => 0,
                            'source' => 'import',
                            'status' => 1,
                            'create_time' => $graphic['created_at'] ?? date('Y-m-d H:i:s'),
                            'update_time' => $graphic['updated_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                        $items++;
                    }

                    $items++;
                }
            }
        }

        $output->writeln("  图文库: {$libraries} 个库, {$items} 个图文条目");
        return ['libraries' => $libraries, 'items' => $items];
    }

    private function importVideoLibraries(int $merchantId, bool $dryRun, Output $output): array
    {
        $output->writeln('--- 导入视频库 ---');

        $videosPath = $this->materialsPath . '/videos';
        if (!is_dir($videosPath)) {
            $output->writeln("<comment>未找到视频目录 {$videosPath}，跳过</comment>");
            return ['libraries' => 0, 'items' => 0];
        }

        $libraries = 0;
        $items = 0;

        $folders = glob($videosPath . '/*', GLOB_ONLYDIR);
        if (!$folders) {
            $output->writeln('<comment>视频目录下没有子文件夹</comment>');
            return ['libraries' => 0, 'items' => 0];
        }

        foreach ($folders as $folder) {
            $folderName = basename($folder);

            $videoFiles = glob($folder . '/*.mp4');
            if (empty($videoFiles)) {
                $videoFiles = array_merge($videoFiles, glob($folder . '/*.MP4'));
            }
            if (empty($videoFiles)) {
                $videoFiles = array_merge($videoFiles, glob($folder . '/*.mov'));
            }

            if (empty($videoFiles)) {
                $output->writeln("  <comment>跳过空文件夹: {$folderName}</comment>");
                continue;
            }

            $libraryId = Db::table('xmt_content_libraries')->insertGetId([
                'merchant_id' => $merchantId,
                'library_type' => 'video',
                'name' => $folderName,
                'max_use_count' => 0,
                'total_count' => count($videoFiles),
                'used_count' => 0,
                'remaining_count' => count($videoFiles),
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            if ($dryRun) {
                $output->writeln("  [预览] 视频库: {$folderName} (" . count($videoFiles) . " 个视频)");
            } else {
                $output->writeln("  ✓ 视频库: {$folderName} (ID:{$libraryId}, " . count($videoFiles) . " 个视频)");
            }
            $libraries++;

            foreach ($videoFiles as $videoFile) {
                $fileName = basename($videoFile);
                $fileSize = filesize($videoFile);
                $originalId = str_replace(['-output.mp4', '-output.MP4', '.mp4', '.MP4'], '', $fileName);

                Db::table('xmt_content_library_items')->insert([
                    'library_id' => $libraryId,
                    'item_type' => 'video',
                    'title' => $folderName . ' - ' . $originalId,
                    'content' => null,
                    'file_url' => str_replace('\\', '/', $videoFile),
                    'thumbnail_url' => null,
                    'paired_item_id' => null,
                    'metadata' => json_encode([
                        'original_id' => $originalId,
                        'file_size' => $fileSize,
                        'file_name' => $fileName,
                        'folder' => $folderName,
                        'import_source' => 'downloaded_materials',
                    ]),
                    'use_count' => 0,
                    'source' => 'import',
                    'status' => 1,
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                $items++;
            }
        }

        $output->writeln("  视频库: {$libraries} 个库, {$items} 个视频");
        return ['libraries' => $libraries, 'items' => $items];
    }
}
