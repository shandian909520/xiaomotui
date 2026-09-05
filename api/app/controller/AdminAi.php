<?php
declare(strict_types=1);

namespace app\controller;

use app\model\SystemSetting;
use app\service\WenxinService;
use app\service\AiStaffService;
use app\service\ZhiPuService;
use think\facade\Config;
use think\facade\Log;
use think\Response;

class AdminAi extends BaseController
{
    /**
     * 获取AI配置
     * GET /api/admin/ai/config
     */
    public function getConfig(): Response
    {
        try {
            $aiConfig = Config::get('ai', []);
            $defaultProvider = $aiConfig['default'] ?? 'wenxin';

            // 从数据库覆盖（优先级更高）
            $dbProvider = SystemSetting::getSetting('provider', 'ai', '');
            if ($dbProvider) {
                $defaultProvider = $dbProvider;
            }

            $providers = [];

            // 文心一言配置
            $wenxin = $aiConfig['wenxin'] ?? [];
            $providers['wenxin'] = [
                'name' => '百度文心一言',
                'protocol' => SystemSetting::getSetting('wenxin_protocol', 'ai', $wenxin['protocol'] ?? 'openai'),
                'api_key' => SystemSetting::getSetting('wenxin_api_key', 'ai', $wenxin['api_key'] ?? ''),
                'secret_key' => SystemSetting::getSetting('wenxin_secret_key', 'ai', $wenxin['secret_key'] ?? ''),
                'model' => SystemSetting::getSetting('wenxin_model', 'ai', $wenxin['model'] ?? 'ernie-bot-turbo'),
                'models' => $wenxin['models'] ?? [],
                'timeout' => (int)SystemSetting::getSetting('wenxin_timeout', 'ai', (string)($wenxin['timeout'] ?? 30)),
                'is_configured' => !empty(SystemSetting::getSetting('wenxin_api_key', 'ai', $wenxin['api_key'] ?? '')),
            ];

            // MiniMax 配置
            $minimax = $aiConfig['minimax'] ?? [];
            $providers['minimax'] = [
                'name' => 'MiniMax 大模型',
                'auth_token' => SystemSetting::getSetting('minimax_auth_token', 'ai', $minimax['auth_token'] ?? ''),
                'base_url' => SystemSetting::getSetting('minimax_base_url', 'ai', $minimax['base_url'] ?? ''),
                'model' => SystemSetting::getSetting('minimax_model', 'ai', $minimax['model'] ?? 'MiniMax-M2.7-highspeed'),
                'models' => $minimax['models'] ?? [],
                'timeout' => (int)SystemSetting::getSetting('minimax_timeout', 'ai', (string)($minimax['timeout'] ?? 30)),
                'is_configured' => !empty(SystemSetting::getSetting('minimax_auth_token', 'ai', $minimax['auth_token'] ?? '')),
            ];

            // 智谱AI配置（图像/视频）
            $zhipu = $aiConfig['zhipu'] ?? [];
            $zhipuApiKey = SystemSetting::getSetting('zhipu_api_key', 'ai', $zhipu['api_key'] ?? '');
            $providers['zhipu'] = [
                'name' => '智谱AI (图像/视频)',
                'api_key' => $zhipuApiKey,
                'image_model' => SystemSetting::getSetting('zhipu_image_model', 'ai', $zhipu['image_model'] ?? 'CogView-3-Flash'),
                'video_model' => SystemSetting::getSetting('zhipu_video_model', 'ai', $zhipu['video_model'] ?? 'CogVideoX-Flash'),
                'models' => $zhipu['models'] ?? [],
                'timeout' => (int)SystemSetting::getSetting('zhipu_timeout', 'ai', (string)($zhipu['timeout'] ?? 120)),
                'is_configured' => !empty($zhipuApiKey),
            ];

            return $this->success([
                'default_provider' => $defaultProvider,
                'providers' => $providers,
            ]);
        } catch (\Exception $e) {
            Log::error('获取AI配置失败', ['error' => $e->getMessage()]);
            return $this->error('获取AI配置失败：' . $e->getMessage());
        }
    }

    /**
     * 更新AI配置
     * PUT /api/admin/ai/config
     */
    public function updateConfig(): Response
    {
        try {
            $data = $this->request->post();

            if (empty($data)) {
                return $this->error('没有提交配置数据', 400);
            }

            $settings = [];

            // 默认提供商
            if (isset($data['default_provider'])) {
                $settings['provider'] = $data['default_provider'];
            }

            // 文心一言配置
            $wenxinFields = ['wenxin_protocol', 'wenxin_api_key', 'wenxin_secret_key', 'wenxin_model', 'wenxin_timeout'];
            foreach ($wenxinFields as $field) {
                if (array_key_exists($field, $data)) {
                    $settings[$field] = $data[$field];
                }
            }

            // MiniMax 配置
            $minimaxFields = ['minimax_auth_token', 'minimax_base_url', 'minimax_model', 'minimax_timeout'];
            foreach ($minimaxFields as $field) {
                if (array_key_exists($field, $data)) {
                    $settings[$field] = $data[$field];
                }
            }

            // 智谱AI配置
            $zhipuFields = ['zhipu_api_key', 'zhipu_image_model', 'zhipu_video_model', 'zhipu_timeout'];
            foreach ($zhipuFields as $field) {
                if (array_key_exists($field, $data)) {
                    $settings[$field] = $data[$field];
                }
            }

            if (!empty($settings)) {
                SystemSetting::batchSave($settings, 'ai');
            }

            return $this->success(null, 'AI配置已更新');
        } catch (\Exception $e) {
            Log::error('更新AI配置失败', ['error' => $e->getMessage()]);
            return $this->error('更新AI配置失败：' . $e->getMessage());
        }
    }

    /**
     * 测试AI连接
     * POST /api/admin/ai/test
     */
    public function testConnection(): Response
    {
        try {
            $provider = $this->request->post('provider', '');
            if (empty($provider)) {
                return $this->error('请指定AI提供商', 400);
            }

            $startTime = microtime(true);

            if ($provider === 'zhipu') {
                $zhipuService = new ZhiPuService();
                $result = $zhipuService->testConnection();
                $duration = round(microtime(true) - $startTime, 2);

                if ($result['success']) {
                    return $this->success([
                        'provider' => 'zhipu',
                        'status' => 'success',
                        'response_preview' => '图像生成测试成功',
                        'preview_url' => $result['preview_url'] ?? '',
                        'model' => $result['model'] ?? '',
                        'duration' => $duration . 's',
                    ], '智谱AI连接测试成功');
                } else {
                    return $this->error('智谱AI连接测试失败：' . ($result['message'] ?? '未知错误'));
                }
            }

            $testPrompt = '请用一句话介绍你自己。';

            $wenxinService = new WenxinService($provider);
            $result = $wenxinService->generateText([
                'scene' => $testPrompt,
                'style' => '简洁',
                'platform' => 'ALL',
                'requirements' => '',
            ]);

            $duration = round(microtime(true) - $startTime, 2);

            return $this->success([
                'provider' => $provider,
                'status' => 'success',
                'response_preview' => mb_substr($result['text'] ?? '', 0, 200),
                'tokens' => $result['tokens'] ?? 0,
                'duration' => $duration . 's',
            ], 'AI连接测试成功');
        } catch (\Exception $e) {
            Log::error('AI连接测试失败', [
                'provider' => $provider ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return $this->error('AI连接测试失败：' . $e->getMessage());
        }
    }

    /**
     * 获取可用模型列表
     * GET /api/admin/ai/models
     */
    public function getModels(): Response
    {
        try {
            $aiConfig = Config::get('ai', []);
            $models = [];

            if (!empty($aiConfig['wenxin']['models'])) {
                $models['wenxin'] = [
                    'name' => '百度文心一言',
                    'models' => $aiConfig['wenxin']['models'],
                ];
            }

            if (!empty($aiConfig['minimax']['models'])) {
                $models['minimax'] = [
                    'name' => 'MiniMax 大模型',
                    'models' => $aiConfig['minimax']['models'],
                ];
            }

            if (!empty($aiConfig['zhipu']['models'])) {
                $models['zhipu'] = [
                    'name' => '智谱AI (图像/视频)',
                    'type' => 'image_video',
                    'models' => $aiConfig['zhipu']['models'],
                    'image_model' => SystemSetting::getSetting('zhipu_image_model', 'ai', $aiConfig['zhipu']['image_model'] ?? 'CogView-3-Flash'),
                    'video_model' => SystemSetting::getSetting('zhipu_video_model', 'ai', $aiConfig['zhipu']['video_model'] ?? 'CogVideoX-Flash'),
                ];
            }

            return $this->success($models);
        } catch (\Exception $e) {
            return $this->error('获取模型列表失败：' . $e->getMessage());
        }
    }

    /**
     * AI员工列表（管理后台）
     * GET /api/admin/ai-staff/list
     */
    public function staffList(): Response
    {
        try {
            $filters = [
                'group_name' => $this->request->param('group_name', ''),
                'keyword' => $this->request->param('keyword', ''),
                'status' => $this->request->param('status', ''),
                'page' => (int)$this->request->param('page', 1),
                'limit' => (int)$this->request->param('limit', 20),
            ];

            $query = \app\model\AiStaffRole::when(
                $filters['group_name'] !== '',
                fn($q) => $q->where('group_name', $filters['group_name'])
            )->when(
                $filters['keyword'] !== '',
                fn($q) => $q->where(function ($q) use ($filters) {
                    $q->whereLike('role_name', '%' . addcslashes($filters['keyword'], '%_') . '%')
                      ->whereOr('nickname', 'like', '%' . addcslashes($filters['keyword'], '%_') . '%');
                })
            )->when(
                $filters['status'] !== '',
                fn($q) => $q->where('status', (int)$filters['status'])
            );

            $total = (clone $query)->count();
            $list = $query->order('sort_order', 'asc')
                ->page($filters['page'], $filters['limit'])
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $filters['page'], $filters['limit'], '获取AI员工列表成功');
            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取AI员工列表成功'
            );
        } catch (\Exception $e) {
            Log::error('管理后台获取AI员工列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建AI员工（管理后台）
     * POST /api/admin/ai-staff/create
     */
    private function getJsonInput(): array
    {
        // ThinkPHP 对 JSON body 请求，post() 和 put() 都会返回解析后的数据
        $data = $this->request->post();
        if (!empty($data)) {
            return $data;
        }
        return $this->request->param();
    }

    public function staffCreate(): Response
    {
        try {
            $allParams = $this->request->post();
            if (empty($allParams)) {
                $allParams = $this->request->param();
            }
            $allowedFields = ['group_name', 'role_name', 'nickname', 'avatar_url',
                'description', 'task_types', 'prompt_template',
                'is_hot', 'free_count', 'sort_order', 'status'];

            $data = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $allParams)) {
                    $data[$field] = $allParams[$field];
                }
            }

            if (empty($data['group_name']) || empty($data['role_name']) || empty($data['nickname'])) {
                return $this->error('分组、角色名称、昵称不能为空');
            }

            if (is_string($data['task_types'] ?? null)) {
                $data['task_types'] = json_decode($data['task_types'], true) ?? [];
            }

            $service = new AiStaffService();
            $result = $service->createStaffRole($data);
            return $this->success($result, '创建AI员工成功');
        } catch (\Exception $e) {
            Log::error('管理后台创建AI员工失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新AI员工（管理后台）
     * PUT /api/admin/ai-staff/update
     */
    public function staffUpdate(): Response
    {
        try {
            // ThinkPHP JSON body 数据统一用 post() 获取（包括 PUT 请求）
            $allParams = $this->request->post();
            if (empty($allParams)) {
                $allParams = $this->request->param();
            }

            $id = (int)($allParams['id'] ?? 0);
            if ($id <= 0) {
                return $this->error('员工ID不能为空');
            }

            $allowedFields = ['group_name', 'role_name', 'nickname', 'avatar_url',
                'description', 'task_types', 'prompt_template',
                'is_hot', 'free_count', 'sort_order', 'status'];

            $data = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $allParams)) {
                    $data[$field] = $allParams[$field];
                }
            }

            if (is_string($data['task_types'] ?? null)) {
                $data['task_types'] = json_decode($data['task_types'], true) ?? [];
            }

            $data = array_filter($data, fn($v) => $v !== null && $v !== '');

            $service = new AiStaffService();
            $result = $service->updateStaffRole($id, $data);
            return $this->success($result, '更新AI员工成功');
        } catch (\Exception $e) {
            Log::error('管理后台更新AI员工失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除AI员工（管理后台）
     * DELETE /api/admin/ai-staff/delete
     */
    public function staffDelete(): Response
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('员工ID不能为空');
            }
            $service = new AiStaffService();
            $service->deleteStaffRole($id);
            return $this->success([], '删除AI员工成功');
        } catch (\Exception $e) {
            Log::error('管理后台删除AI员工失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * AI员工分组列表（管理后台）
     * GET /api/admin/ai-staff/groups
     */
    public function staffGroups(): Response
    {
        try {
            $service = new AiStaffService();
            $groups = $service->getStaffGroups();
            return $this->success($groups, '获取分组列表成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * AI员工详情（管理后台）
     * GET /api/admin/ai/staff/:id
     */
    public function staffDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('员工ID不能为空');
            }
            $service = new AiStaffService();
            $result = $service->getStaffDetail($id);
            return $this->success($result, '获取员工详情成功');
        } catch (\Exception $e) {
            Log::error('管理后台获取AI员工详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }
}
