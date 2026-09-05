<?php
declare(strict_types=1);

namespace app\command;

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class UploadVideosToQiniu extends Command
{
    private string $accessKey = 'TKLS2Hd5d5Gp9rX4wOSa6h3P9oLXMxbwUx675L72';
    private string $secretKey = 'y1lLQN_ys4pGvKGa4bPQE4tex_0Iz8AgLloZTh4W';
    private string $bucket = 'chahuadianying';
    private string $publicDomain = 'http://chahua.img.moban8.top';

    protected function configure()
    {
        $this->setName('upload:videos')
            ->setDescription('上传本地视频到七牛云并更新数据库URL')
            ->addOption('library-id', 'l', Option::VALUE_OPTIONAL, '指定视频库ID，不指定则上传全部', '')
            ->addOption('limit', null, Option::VALUE_OPTIONAL, '限制上传数量', '0')
            ->addOption('force', 'f', Option::VALUE_NONE, '强制重新上传（覆盖已有公网地址的）')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅预览');
    }

    protected function execute(Input $input, Output $output)
    {
        $libraryId = $input->getOption('library-id');
        $limit = (int)$input->getOption('limit');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        $output->writeln('==========================================');
        $output->writeln('视频上传到七牛云');
        $output->writeln('==========================================');
        $output->writeln("Bucket: {$this->bucket}");
        $output->writeln("公网域名: {$this->publicDomain}");

        $auth = new Auth($this->accessKey, $this->secretKey);
        $policy = ['insertOnly' => 0];
        $token = $auth->uploadToken($this->bucket, null, 3600, $policy);
        $uploadMgr = new UploadManager();

        $query = Db::table('xmt_content_library_items')
            ->alias('i')
            ->join('xmt_content_libraries l', 'l.id = i.library_id')
            ->where('l.library_type', 'video')
            ->where('i.item_type', 'video')
            ->where('i.source', 'import');

        if ($libraryId) {
            $query->where('i.library_id', (int)$libraryId);
            $output->writeln("指定视频库ID: {$libraryId}");
        }

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('i.file_url')->whereOr('i.file_url', 'not like', 'http%');
            });
        }

        $items = $query->field('i.id,i.library_id,i.title,i.file_url,i.metadata,l.name as library_name')
            ->order('i.library_id', 'asc')
            ->order('i.id', 'asc')
            ->select()
            ->toArray();

        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        $total = count($items);
        $output->writeln("待上传: {$total} 个视频\n");

        if ($total === 0) {
            $output->writeln('<info>没有需要上传的视频</info>');
            return 0;
        }

        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($items as $index => $item) {
            $num = $index + 1;
            $localPath = $item['file_url'] ?? '';

            if (empty($localPath) || !file_exists($localPath)) {
                $output->writeln("  [{$num}/{$total}] <comment>跳过: 文件不存在 - {$localPath}</comment>");
                $skipped++;
                continue;
            }

            $metadata = json_decode($item['metadata'] ?? '{}', true);
            $folder = $metadata['folder'] ?? $item['library_name'];
            $fileName = $metadata['file_name'] ?? basename($localPath);

            $key = "xiaomotui/videos/{$folder}/{$fileName}";

            $output->write("  [{$num}/{$total}] {$key} ");

            if ($dryRun) {
                $output->writeln("<info>[预览]</info>");
                $success++;
                continue;
            }

            try {
                list($result, $error) = $uploadMgr->putFile($token, $key, $localPath, null, 'application/octet-stream', true, null, 'v2');

                if ($error !== null) {
                    $output->writeln("<error>失败: {$error->message()}</error>");
                    $failed++;
                    Log::error('视频上传失败', ['key' => $key, 'error' => $error->message()]);
                    continue;
                }

                $publicUrl = $this->publicDomain . '/' . $key;

                Db::table('xmt_content_library_items')
                    ->where('id', $item['id'])
                    ->update(['file_url' => $publicUrl]);

                $fileSizeMB = round(filesize($localPath) / 1024 / 1024, 1);
                $output->writeln("<info>OK</info> ({$fileSizeMB}MB) -> {$publicUrl}");
                $success++;

                if ($success % 10 === 0) {
                    $token = $auth->uploadToken($this->bucket, null, 3600, $policy);
                }
            } catch (\Exception $e) {
                $output->writeln("<error>异常: {$e->getMessage()}</error>");
                $failed++;
                Log::error('视频上传异常', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        $output->writeln('');
        $output->writeln("==========================================");
        $output->writeln("上传完成: 成功 {$success}, 失败 {$failed}, 跳过 {$skipped}");
        $output->writeln("==========================================");

        return $failed > 0 ? 1 : 0;
    }
}
