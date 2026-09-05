<?php
declare(strict_types=1);

namespace app\service;

use app\model\ClipProject;
use app\model\ClipShot;
use app\model\VoiceActor;
use think\facade\Db;
use think\facade\Log;

class ClipProjectService
{
    // ---- 工程管理 ----

    public function getProjectList(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        $query = ClipProject::where('merchant_id', $merchantId)
            ->where('is_template', 0);

        if (!empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['keyword'])) {
            $query->whereLike('name', '%' . addcslashes($filters['keyword'], '%_') . '%');
        }

        $total = $query->count();
        $list  = $query->page($page, $limit)
            ->order('update_time', 'desc')
            ->select()
            ->toArray();

        return compact('list', 'total', 'page', 'limit');
    }

    public function createProject(int $merchantId, array $data): array
    {
        $project = new ClipProject();
        $project->save([
            'merchant_id' => $merchantId,
            'user_id'     => $data['user_id'] ?? null,
            'name'        => $data['name'] ?? '未命名工程',
            'mode'        => $data['mode'] ?? 'auto',
            'config'      => $data['config'] ?? null,
            'status'      => 'draft',
            'is_template' => 0,
            'duration'    => 0,
        ]);

        // 如果携带了分镜数据，一并创建
        if (!empty($data['shots']) && is_array($data['shots'])) {
            $this->syncShots($project->id, $data['shots']);
        }

        return $project->toArray();
    }

    public function getProjectDetail(int $id): ?array
    {
        $project = ClipProject::with(['shots'])->find($id);
        if (!$project) {
            return null;
        }
        return $project->toArray();
    }

    public function updateProject(int $id, array $data): ?array
    {
        $project = ClipProject::find($id);
        if (!$project) {
            return null;
        }

        $allowFields = ['name', 'mode', 'config', 'status', 'video_url', 'duration'];
        foreach ($allowFields as $field) {
            if (array_key_exists($field, $data)) {
                $project->$field = $data[$field];
            }
        }
        $project->save();

        return $project->toArray();
    }

    public function deleteProject(int $id): bool
    {
        $project = ClipProject::find($id);
        if (!$project) {
            return false;
        }

        Db::startTrans();
        try {
            ClipShot::where('project_id', $id)->delete();
            $project->delete();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return true;
    }

    public function saveAsTemplate(int $projectId): ?array
    {
        $project = ClipProject::with(['shots'])->find($projectId);
        if (!$project) {
            return null;
        }

        $projectData = $project->toArray();

        Db::startTrans();
        try {
            $tpl = new ClipProject();
            $tpl->save([
                'merchant_id' => $projectData['merchant_id'],
                'user_id'     => $projectData['user_id'],
                'name'        => $projectData['name'] . '_模板',
                'mode'        => $projectData['mode'],
                'config'      => $projectData['config'],
                'status'      => 'draft',
                'is_template' => 1,
                'duration'    => 0,
            ]);

            foreach ($project->shots as $shot) {
                $shotData = $shot->toArray();
                unset($shotData['id'], $shotData['create_time']);
                $newShot = new ClipShot();
                $newShot->save(array_merge($shotData, ['project_id' => $tpl->id]));
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return $tpl->toArray();
    }

    public function getMyTemplates(int $merchantId): array
    {
        return ClipProject::where('merchant_id', $merchantId)
            ->where('is_template', 1)
            ->order('update_time', 'desc')
            ->select()
            ->toArray();
    }

    // ---- 分镜管理 ----

    public function getShots(int $projectId): array
    {
        return ClipShot::where('project_id', $projectId)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();
    }

    public function addShot(int $projectId, array $data): array
    {
        $maxSort = ClipShot::where('project_id', $projectId)
            ->max('sort_order');

        $materialId = $data['material_id'] ?? null;
        $material   = $materialId ? \app\model\Material::find($materialId) : null;

        $shot = new ClipShot();
        $shot->project_id      = $projectId;
        $shot->sort_order      = ($maxSort ?? 0) + 1;
        $shot->material_id     = $materialId;
        $shot->material_type   = $data['material_type'] ?? ($material ? ($material->material_type ?? 'image') : 'image');
        $shot->material_url    = $data['material_url'] ?? ($material ? ($material->file_url ?? $material->url ?? null) : null);
        $shot->thumbnail_url   = $data['thumbnail_url'] ?? ($material ? ($material->thumbnail_url ?? $material->cover ?? null) : null);
        $shot->duration        = $data['duration'] ?? 3.0;
        $shot->subtitle        = $data['subtitle'] ?? null;
        $shot->voice_text      = $data['voice_text'] ?? null;
        $shot->voice_actor_id  = $data['voice_actor_id'] ?? null;
        $shot->transition_type = $data['transition_type'] ?? 'none';
        $shot->filter_name     = $data['filter_name'] ?? null;
        $shot->mute_original   = $data['mute_original'] ?? 0;
        $shot->save();

        return $shot->toArray();
    }

    public function updateShot(int $shotId, array $data): ?array
    {
        $shot = ClipShot::find($shotId);
        if (!$shot) {
            return null;
        }

        $allowFields = [
            'sort_order', 'material_id', 'material_type', 'material_url',
            'thumbnail_url', 'duration', 'subtitle', 'voice_text',
            'voice_actor_id', 'transition_type', 'filter_name', 'mute_original',
        ];
        foreach ($allowFields as $field) {
            if (array_key_exists($field, $data)) {
                $shot->$field = $data[$field];
            }
        }
        $shot->save();

        return $shot->toArray();
    }

    public function deleteShot(int $shotId): bool
    {
        $shot = ClipShot::find($shotId);
        if (!$shot) {
            return false;
        }
        $shot->delete();
        return true;
    }

    public function sortShots(int $projectId, array $shotIds): bool
    {
        Db::startTrans();
        try {
            foreach ($shotIds as $index => $shotId) {
                ClipShot::where('id', $shotId)
                    ->where('project_id', $projectId)
                    ->update(['sort_order' => $index + 1]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
        return true;
    }

    // ---- 配置查询 ----

    public function getVoiceActors(): array
    {
        return VoiceActor::where('status', 1)
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    public function getTransitions(): array
    {
        return [
            ['key' => 'none',   'name' => '无转场'],
            ['key' => 'fade',   'name' => '淡入淡出'],
            ['key' => 'slide',  'name' => '滑动'],
            ['key' => 'zoom',   'name' => '缩放'],
            ['key' => 'wipe',   'name' => '擦除'],
            ['key' => 'random', 'name' => '随机'],
        ];
    }

    public function getFilters(): array
    {
        return [
            ['key' => 'none',      'name' => '无滤镜'],
            ['key' => 'warm',      'name' => '暖色'],
            ['key' => 'cool',      'name' => '冷色'],
            ['key' => 'vintage',   'name' => '复古'],
            ['key' => 'bw',        'name' => '黑白'],
            ['key' => 'vivid',     'name' => '鲜艳'],
            ['key' => 'soft',      'name' => '柔光'],
        ];
    }

    public function getAspectRatios(): array
    {
        return [
            ['key' => '9:16',  'name' => '竖屏 9:16'],
            ['key' => '16:9',  'name' => '横屏 16:9'],
            ['key' => '1:1',   'name' => '方形 1:1'],
        ];
    }

    public function getFrameRates(): array
    {
        return [25, 30, 60];
    }

    // ---- 一键成片：AI生成分镜 ----

    public function generateAutoShots(string $copyText, string $industry = '', array $materialIds = [], array $options = []): array
    {
        $sentences = preg_split('/[。！？\n；;]+/u', $copyText, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($sentences)) {
            $sentences = [$copyText];
        }

        $sentences = array_values(array_filter(array_map('trim', $sentences)));

        $materials = [];
        if (!empty($materialIds)) {
            $materials = \app\model\Material::whereIn('id', $materialIds)
                ->select()
                ->toArray();
        }

        $shots = [];
        $totalDuration = 0;
        $defaultDuration = floatval($options['shot_duration'] ?? 3);
        $transitionType = $options['transition_type'] ?? 'fade';

        foreach ($sentences as $index => $text) {
            $material = !empty($materials) ? $materials[$index % count($materials)] : null;

            $shot = [
                'sort_order'      => $index + 1,
                'material_id'     => $material ? $material['id'] : null,
                'material_type'   => $material ? ($material['type'] ?? 'image') : 'image',
                'material_url'    => $material ? ($material['url'] ?? $material['path'] ?? null) : null,
                'thumbnail_url'   => $material ? ($material['thumbnail'] ?? $material['cover'] ?? null) : null,
                'duration'        => $defaultDuration,
                'subtitle'        => $text,
                'voice_text'      => $text,
                'transition_type' => $index < count($sentences) - 1 ? $transitionType : 'none',
                'filter_name'     => null,
                'mute_original'   => 0,
            ];
            $shots[] = $shot;
            $totalDuration += $defaultDuration;
        }

        return [
            'shots'         => $shots,
            'total_duration' => $totalDuration,
            'shot_count'    => count($shots),
        ];
    }

    // ---- 批量混剪 ----

    public function batchRemix(int $merchantId, array $data): array
    {
        $materialIds   = $data['material_ids'] ?? [];
        $videoCount    = intval($data['video_count'] ?? 1);
        $clipDuration  = floatval($data['clip_duration'] ?? 3);
        $shotCount     = intval($data['shot_count'] ?? 4);
        $transitionType = $data['transition_type'] ?? 'random';
        $bgMusic       = $data['bg_music'] ?? '';
        $voiceActorId  = $data['voice_actor_id'] ?? null;
        $subtitleText  = $data['subtitle_text'] ?? '';
        $aspectRatio   = $data['aspect_ratio'] ?? '9:16';

        if (count($materialIds) < 2) {
            throw new \Exception('批量混剪至少需要2个素材');
        }
        if ($videoCount < 1 || $videoCount > 50) {
            throw new \Exception('生成视频数量需在1-50之间');
        }

        $materials = \app\model\Material::whereIn('id', $materialIds)
            ->select()
            ->toArray();

        if (count($materials) < 2) {
            throw new \Exception('有效素材不足2个');
        }

        $transitions = ['fade', 'slide', 'zoom', 'wipe'];
        $projects = [];

        for ($v = 0; $v < $videoCount; $v++) {
            $shuffledMaterials = $materials;
            shuffle($shuffledMaterials);
            $selectedMaterials = array_slice($shuffledMaterials, 0, min($shotCount, count($shuffledMaterials)));

            $project = new ClipProject();
            $project->merchant_id = $merchantId;
            $project->name        = ($data['name'] ?? '批量混剪') . '_' . ($v + 1);
            $project->mode        = 'batch';
            $project->status      = 'draft';
            $project->is_template = 0;
            $project->duration    = 0;
            $project->config      = json_encode([
                'batch' => [
                    'clip_duration'   => $clipDuration,
                    'shot_count'      => $shotCount,
                    'transition_type' => $transitionType,
                    'bg_music'        => $bgMusic,
                    'voice_actor_id'  => $voiceActorId,
                    'subtitle_text'   => $subtitleText,
                    'aspect_ratio'    => $aspectRatio,
                    'batch_index'     => $v + 1,
                    'batch_total'     => $videoCount,
                ],
            ]);
            $project->save();

            foreach ($selectedMaterials as $idx => $mat) {
                $shot = new ClipShot();
                $shot->project_id      = $project->id;
                $shot->sort_order      = $idx + 1;
                $shot->material_id     = $mat['id'];
                $shot->material_type   = $mat['type'] ?? 'image';
                $shot->material_url    = $mat['url'] ?? $mat['path'] ?? null;
                $shot->thumbnail_url   = $mat['thumbnail'] ?? $mat['cover'] ?? null;
                $shot->duration        = $clipDuration;
                $shot->subtitle        = $subtitleText ?: null;
                $shot->voice_text      = null;
                $shot->voice_actor_id  = $voiceActorId;
                $shot->transition_type = $transitionType === 'random'
                    ? $transitions[array_rand($transitions)]
                    : $transitionType;
                $shot->filter_name     = null;
                $shot->mute_original   = 0;
                $shot->save();
            }

            $projects[] = [
                'project_id' => $project->id,
                'name'       => $project->name,
                'shot_count' => count($selectedMaterials),
                'status'     => 'draft',
            ];
        }

        return [
            'total'    => count($projects),
            'projects' => $projects,
        ];
    }

    // ---- 导出 ----

    public function exportProject(int $projectId): ?array
    {
        $project = ClipProject::find($projectId);
        if (!$project) {
            return null;
        }

        // 防止重复导出（已处于导出中状态时拒绝）
        if ($project->status === 'exporting' || $project->status === 'processing') {
            return ['project_id' => $projectId, 'status' => $project->status];
        }

        $project->status = 'exporting';
        $project->save();

        Log::info('剪辑工程开始导出', [
            'project_id' => $projectId,
            'mode'       => $project->mode,
        ]);

        // 同步执行渲染流程
        try {
            // 获取工程的所有分镜
            $shots = ClipShot::where('project_id', $projectId)
                ->order('sort_order', 'asc')
                ->select()
                ->toArray();

            if (empty($shots)) {
                throw new \Exception('工程没有分镜数据，无法导出');
            }

            // 更新状态为处理中
            $project->status = 'processing';
            $project->save();

            // 调用完整渲染流程
            $composeService = new VideoComposeService();
            $videoPath = $composeService->fullCompose($project->toArray(), $shots);

            // 更新工程状态为已完成
            $project->status = 'completed';
            $project->video_url = $videoPath;
            $project->duration = intval($composeService->getVideoDuration(
                public_path() . $videoPath
            ));
            $project->save();

            Log::info('剪辑工程导出完成', [
                'project_id' => $projectId,
                'video_url'  => $videoPath,
                'duration'   => $project->duration,
            ]);

            return [
                'project_id' => $projectId,
                'status'     => 'completed',
                'video_url'  => $videoPath,
                'duration'   => $project->duration,
            ];

        } catch (\Exception $e) {
            // 导出失败，更新状态
            $project->status = 'failed';
            $project->save();

            Log::error('剪辑工程导出失败', [
                'project_id' => $projectId,
                'error'      => $e->getMessage(),
            ]);

            return [
                'project_id' => $projectId,
                'status'     => 'failed',
                'error'      => $e->getMessage(),
            ];
        }
    }

    // ---- 内部辅助 ----

    private function syncShots(int $projectId, array $shots): void
    {
        // 批量预查询素材，减少DB查询
        $materialIds = array_filter(array_column($shots, 'material_id'));
        $materialMap = [];
        if (!empty($materialIds)) {
            $materials = \app\model\Material::whereIn('id', array_unique($materialIds))->select();
            foreach ($materials as $m) {
                $materialMap[$m->id] = $m;
            }
        }

        foreach ($shots as $index => $shotData) {
            $materialId = $shotData['material_id'] ?? null;
            $material   = $materialId ? ($materialMap[$materialId] ?? null) : null;

            $shot = new ClipShot();
            $shot->project_id      = $projectId;
            $shot->sort_order      = $index + 1;
            $shot->material_id     = $materialId;
            $shot->material_type   = $shotData['material_type'] ?? ($material ? ($material->material_type ?? 'image') : 'image');
            $shot->material_url    = $shotData['material_url'] ?? ($material ? ($material->file_url ?? $material->url ?? null) : null);
            $shot->thumbnail_url   = $shotData['thumbnail_url'] ?? ($material ? ($material->thumbnail_url ?? $material->cover ?? null) : null);
            $shot->duration        = $shotData['duration'] ?? 3.0;
            $shot->subtitle        = $shotData['subtitle'] ?? null;
            $shot->voice_text      = $shotData['voice_text'] ?? null;
            $shot->voice_actor_id  = $shotData['voice_actor_id'] ?? null;
            $shot->transition_type = $shotData['transition_type'] ?? 'none';
            $shot->filter_name     = $shotData['filter_name'] ?? null;
            $shot->mute_original   = $shotData['mute_original'] ?? 0;
            $shot->save();
        }
    }
}
