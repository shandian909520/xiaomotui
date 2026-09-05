<?php
declare(strict_types=1);

namespace app\service;

use app\model\AiStaffRole;
use think\facade\Log;
use think\Exception;

class AiStaffService
{
    /**
     * 获取分组列表及每组的员工
     */
    public function getStaffGroups(): array
    {
        $groups = AiStaffRole::getGroups();
        $result = [];

        foreach ($groups as $groupName) {
            $staffList = AiStaffRole::where('group_name', $groupName)
                ->where('status', 1)
                ->order('sort_order', 'asc')
                ->select()
                ->toArray();

            $result[] = [
                'group_name' => $groupName,
                'staff_list' => $staffList,
                'count'      => count($staffList),
            ];
        }

        return $result;
    }

    /**
     * 获取某组下的员工
     */
    public function getStaffByGroup(string $groupName): array
    {
        return AiStaffRole::where('group_name', $groupName)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 全部员工列表（支持分组筛选）
     */
    public function getStaffList(array $filters = []): array
    {
        $query = AiStaffRole::where('status', 1);

        if (!empty($filters['group_name'])) {
            $query->where('group_name', $filters['group_name']);
        }

        if (isset($filters['is_hot']) && $filters['is_hot'] !== '') {
            $query->where('is_hot', (int)$filters['is_hot']);
        }

        if (!empty($filters['keyword'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereLike('role_name', '%' . addcslashes($filters['keyword'], '%_') . '%')
                  ->whereOr('nickname', 'like', '%' . addcslashes($filters['keyword'], '%_') . '%');
            });
        }

        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        $total = $query->count();
        $list  = $query->order('sort_order', 'asc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 员工详情
     */
    public function getStaffDetail(int $id): array
    {
        $staff = AiStaffRole::find($id);
        if (!$staff) {
            throw new Exception('员工不存在');
        }
        return $staff->toArray();
    }

    /**
     * 创建员工角色
     */
    public function createStaffRole(array $data): array
    {
        $staff = AiStaffRole::create($data);
        return $staff->toArray();
    }

    /**
     * 更新员工角色
     */
    public function updateStaffRole(int $id, array $data): array
    {
        $staff = AiStaffRole::find($id);
        if (!$staff) {
            throw new Exception('员工不存在');
        }

        $staff->save($data);
        return $staff->toArray();
    }

    /**
     * 删除员工角色
     */
    public function deleteStaffRole(int $id): bool
    {
        $staff = AiStaffRole::find($id);
        if (!$staff) {
            throw new Exception('员工不存在');
        }
        return $staff->delete();
    }

    /**
     * 安排工作 - 根据task_type调用不同生成逻辑
     */
    public function assignWork(int $staffId, array $data): array
    {
        $staff = AiStaffRole::find($staffId);
        if (!$staff) {
            throw new Exception('员工不存在');
        }
        if (!$staff->status) {
            throw new Exception('该员工已禁用');
        }

        $taskTypes  = $staff->task_types ?? [];
        $taskType   = $data['task_type'] ?? '';

        if (!empty($taskTypes) && !in_array($taskType, $taskTypes)) {
            throw new Exception("该员工不支持任务类型: {$taskType}");
        }

        // 检查免费次数
        if ($staff->used_count >= $staff->free_count) {
            throw new Exception('该员工免费次数已用完');
        }

        $startTime = microtime(true);

        try {
            $result = $this->dispatchTask($taskType, $data, $staff);

            // 更新使用次数
            $staff->used_count++;
            $staff->save();

            $duration = round(microtime(true) - $startTime, 2);

            return [
                'staff_id'        => $staffId,
                'nickname'        => $staff->nickname,
                'task_type'       => $taskType,
                'result'          => $result,
                'generation_time' => $duration,
            ];
        } catch (Exception $e) {
            Log::error('智能员工任务执行失败', [
                'staff_id'  => $staffId,
                'task_type' => $taskType,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 根据task_type分派任务
     */
    protected function dispatchTask(string $taskType, array $data, AiStaffRole $staff): array
    {
        $prompt = $data['prompt'] ?? '';
        if (!empty($staff->prompt_template)) {
            $prompt = str_replace('{input}', $prompt, $staff->prompt_template);
        }

        return match ($taskType) {
            'video_script', 'notes', 'rewrite', 'review_create'
                => $this->generateTextContent($taskType, $prompt, $data),

            'naming', 'sku_plan', 'menu_plan', 'ranking', 'review_reply'
                => $this->generateTextContent($taskType, $prompt, $data),

            'poster_design', 'image_design'
                => $this->generateImage($prompt, $data),

            'image_to_video'
                => $this->generateVideo($data, $prompt),

            default => throw new Exception("不支持的任务类型: {$taskType}"),
        };
    }

    /**
     * 文本类任务 - 调用WenxinService
     */
    protected function generateTextContent(string $taskType, string $prompt, array $data): array
    {
        $provider = $data['provider'] ?? 'minimax';

        $taskPrompts = [
            'video_script'   => "请根据以下要求撰写一段短视频口播文案：\n{prompt}\n\n要求：语言精炼有感染力，适合短视频口播，控制在50-150字。",
            'notes'          => "请根据以下要求撰写一篇种草笔记：\n{prompt}\n\n要求：真实体验感，图文并茂风格，100-300字，可使用表情符号。",
            'rewrite'        => "请对以下文案进行改写优化，提升传播效果：\n{prompt}\n\n要求：保留核心信息，使表达更加生动吸引人。",
            'review_create'  => "请根据以下信息创作一条优质好评：\n{prompt}\n\n要求：真实自然，突出体验亮点，50-150字。",
            'naming'         => "请为以下内容起一个有创意、响亮的名字：\n{prompt}\n\n要求：朗朗上口，容易记住，有传播力。请给出3-5个候选名称。",
            'sku_plan'       => "请根据以下信息策划团购套餐SKU组合方案：\n{prompt}\n\n要求：包含套餐名称、内容、定价策略，适合本地生活平台。",
            'menu_plan'      => "请根据以下信息规划菜单拍摄和SKU展示方案：\n{prompt}\n\n要求：包含拍摄角度建议、菜品排序、卖点提炼。",
            'ranking'        => "请根据以下信息制定平台榜单冲榜运营策略：\n{prompt}\n\n要求：包含短期冲刺方案、长期维护策略、关键指标目标。",
            'review_reply'   => "请针对以下差评撰写一条专业回复：\n{prompt}\n\n要求：态度诚恳，化解矛盾，展示商家的服务态度和改进决心。",
        ];

        $finalPrompt = str_replace('{prompt}', $prompt, $taskPrompts[$taskType] ?? $prompt);

        $wenxinService = new WenxinService($provider);
        $result = $wenxinService->generateText([
            'scene'       => $finalPrompt,
            'style'       => '专业',
            'platform'    => 'ALL',
            'requirements' => '',
        ]);

        return [
            'status'   => 'completed',
            'text'     => $result['text'] ?? '',
            'provider' => $provider,
            'tokens'   => $result['tokens'] ?? 0,
        ];
    }

    /**
     * 图生视频 - 调用JianyingVideoService
     */
    protected function generateImage(string $prompt, array $data): array
    {
        try {
            $zhipuService = new ZhiPuService();
            $result = $zhipuService->generateImage([
                'prompt' => $prompt,
                'size' => $data['size'] ?? '1024x1024',
            ]);

            return [
                'status' => 'SUCCESS',
                'image_url' => $result['url'] ?? '',
                'model' => $result['model'] ?? 'CogView-3-Flash',
            ];
        } catch (\Exception $e) {
            Log::error('智谱AI图片生成失败', ['error' => $e->getMessage()]);
            return [
                'status' => 'FAILED',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function generateVideo(array $data, string $prompt = ''): array
    {
        try {
            $zhipuService = new ZhiPuService();
            $result = $zhipuService->generateVideo([
                'prompt' => $prompt ?: ($data['prompt'] ?? ''),
                'image_url' => $data['image_url'] ?? '',
                'duration' => $data['duration'] ?? 6,
                'resolution' => $data['resolution'] ?? '720p',
            ]);

            return [
                'status'    => $result['status'] ?? 'PROCESSING',
                'task_id'   => $result['task_id'] ?? '',
                'video_url' => $result['url'] ?? '',
                'cover_url' => $result['cover_url'] ?? '',
                'model'     => $result['model'] ?? 'CogVideoX-Flash',
            ];
        } catch (\Exception $e) {
            Log::error('智谱AI视频生成失败', ['error' => $e->getMessage()]);

            // 降级到剪映服务
            $jianyingService = new JianyingVideoService();
            $params = [
                'image_url' => $data['image_url'] ?? '',
                'duration'  => $data['duration'] ?? 15,
                'style'     => $data['style'] ?? '自然',
            ];
            $result = $jianyingService->createVideoTask($params);
            return [
                'status'    => $result['status'] ?? 'PENDING',
                'task_id'   => $result['task_id'] ?? '',
                'video_url' => $result['video_url'] ?? '',
                'duration'  => $result['duration'] ?? 0,
            ];
        }
    }

    /**
     * 使用统计
     */
    public function getStaffUsage(int $staffId): array
    {
        $staff = AiStaffRole::find($staffId);
        if (!$staff) {
            throw new Exception('员工不存在');
        }

        return [
            'staff_id'     => $staffId,
            'nickname'     => $staff->nickname,
            'free_count'   => $staff->free_count,
            'used_count'   => $staff->used_count,
            'remain_count' => max(0, $staff->free_count - $staff->used_count),
        ];
    }
}
