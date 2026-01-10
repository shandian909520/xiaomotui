<?php
declare(strict_types=1);

namespace app\controller;

use app\model\ContentTemplate;
use app\model\Merchant;
use app\model\NfcDevice;
use think\db\exception\DbException;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\Response;

/**
 * 模板管理控制器
 * 提供内容模板的完整管理功能
 */
class TemplateManage extends BaseController
{
    /**
     * 中间件配置
     * @var array
     */
    protected $middleware = [
        'app\middleware\JwtAuth'
    ];

    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'template:';
    const CACHE_HOT_KEY = 'template:hot:';
    const CACHE_CATEGORY_KEY = 'template:categories';
    const CACHE_STATS_KEY = 'template:stats:';

    /**
     * 缓存时间（秒）
     */
    const CACHE_TTL = 300; // 5分钟

    /**
     * 初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 模板列表（支持分页、筛选、搜索）
     * GET /api/template/list
     * @return Response
     */
    public function list(): Response
    {
        try {
            // 获取查询参数
            $type = $this->request->get('type', '');
            $category = $this->request->get('category', '');
            $isPublic = $this->request->get('is_public', '');
            $status = $this->request->get('status', '');
            $keyword = $this->request->get('keyword', '');
            $page = (int)$this->request->get('page', 1);
            $limit = (int)$this->request->get('limit', 20);

            // 限制每页数量
            $limit = min($limit, 100);

            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 构建查询
            $query = ContentTemplate::alias('t')
                ->field('t.*, m.name as merchant_name')
                ->leftJoin('merchants m', 't.merchant_id = m.id');

            // 权限过滤：非管理员只能看到自己的模板和公开模板
            if ($role !== 'admin') {
                $query->where(function ($query) use ($merchantId) {
                    $query->whereNull('t.merchant_id')  // 系统模板
                          ->whereOr('t.is_public', ContentTemplate::PUBLIC_YES)  // 公开模板
                          ->whereOr('t.merchant_id', $merchantId);  // 自己的模板
                });
            }

            // 类型筛选
            if ($type && in_array($type, ['VIDEO', 'TEXT', 'IMAGE'])) {
                $query->where('t.type', $type);
            }

            // 分类筛选
            if ($category) {
                $query->where('t.category', $category);
            }

            // 公开状态筛选
            if ($isPublic !== '') {
                $query->where('t.is_public', (int)$isPublic);
            }

            // 状态筛选
            if ($status !== '') {
                $query->where('t.status', (int)$status);
            }

            // 关键词搜索
            if ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->whereLike('t.name', "%{$keyword}%")
                          ->whereOr('t.category', 'like', "%{$keyword}%")
                          ->whereOr('t.style', 'like', "%{$keyword}%");
                });
            }

            // 分页查询
            $list = $query->order('t.usage_count', 'desc')
                         ->order('t.create_time', 'desc')
                         ->page($page, $limit)
                         ->select();

            // 获取总数
            $total = $query->count();

            // 处理返回数据
            $result = [];
            foreach ($list as $item) {
                $result[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'type_text' => $item->type_text,
                    'category' => $item->category,
                    'style' => $item->style,
                    'preview_url' => $item->preview_url,
                    'usage_count' => $item->usage_count,
                    'is_public' => $item->is_public,
                    'is_public_text' => $item->is_public_text,
                    'status' => $item->status,
                    'status_text' => $item->status_text,
                    'merchant_id' => $item->merchant_id,
                    'merchant_name' => $item->merchant_name ?: '系统',
                    'template_source' => $item->template_source,
                    'create_time' => $item->create_time,
                ];
            }

            // 返回分页数据
            return $this->paginate(
                $result,
                $total,
                $page,
                $limit,
                '获取模板列表成功'
            );

        } catch (\Exception $e) {
            Log::error('获取模板列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取模板列表失败：' . $e->getMessage());
        }
    }

    /**
     * 模板详情
     * GET /api/template/detail/{id}
     * @param int $id
     * @return Response
     */
    public function detail(int $id): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 查询模板
            $template = ContentTemplate::with(['merchant'])->find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            // 权限检查：非管理员只能查看自己的模板、公开模板和系统模板
            if ($role !== 'admin') {
                if ($template->merchant_id &&
                    $template->merchant_id != $merchantId &&
                    $template->is_public != ContentTemplate::PUBLIC_YES) {
                    return $this->error('无权访问该模板', 403);
                }
            }

            // 返回完整模板信息
            $result = [
                'id' => $template->id,
                'merchant_id' => $template->merchant_id,
                'merchant_name' => $template->merchant ? $template->merchant->name : '系统',
                'name' => $template->name,
                'type' => $template->type,
                'type_text' => $template->type_text,
                'category' => $template->category,
                'style' => $template->style,
                'content' => $template->content,
                'preview_url' => $template->preview_url,
                'usage_count' => $template->usage_count,
                'is_public' => $template->is_public,
                'is_public_text' => $template->is_public_text,
                'status' => $template->status,
                'status_text' => $template->status_text,
                'template_source' => $template->template_source,
                'create_time' => $template->create_time,
                'update_time' => $template->update_time,
            ];

            return $this->success($result, '获取模板详情成功');

        } catch (\Exception $e) {
            Log::error('获取模板详情失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取模板详情失败：' . $e->getMessage());
        }
    }

    /**
     * 创建模板
     * POST /api/template/create
     * @return Response
     */
    public function create(): Response
    {
        try {
            // 获取请求数据
            $data = $this->request->post();

            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 数据验证
            $this->validateTemplateData($data, 'create');

            // 验证content是否为有效的JSON
            if (isset($data['content'])) {
                if (is_string($data['content'])) {
                    $content = json_decode($data['content'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $this->error('模板内容必须是有效的JSON格式');
                    }
                    $data['content'] = $content;
                } elseif (!is_array($data['content'])) {
                    return $this->error('模板内容格式不正确');
                }
            }

            // 设置商家ID
            // 只有管理员可以创建系统模板（不设置merchant_id）
            if ($role === 'admin' && !isset($data['merchant_id'])) {
                // 系统模板
                $data['merchant_id'] = null;
            } else {
                // 商家模板
                $data['merchant_id'] = $merchantId;
            }

            // 设置默认值
            $data['usage_count'] = 0;
            $data['is_public'] = $data['is_public'] ?? ContentTemplate::PUBLIC_NO;
            $data['status'] = $data['status'] ?? ContentTemplate::STATUS_ENABLED;

            // 创建模板
            $template = ContentTemplate::create($data);

            // 清除相关缓存
            $this->clearTemplateCache();

            return $this->success([
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type,
                'category' => $template->category,
            ], '创建模板成功');

        } catch (ValidateException $e) {
            return $this->validationError([$e->getMessage()], $e->getMessage());
        } catch (\Exception $e) {
            Log::error('创建模板失败', [
                'data' => $this->request->post(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('创建模板失败：' . $e->getMessage());
        }
    }

    /**
     * 更新模板
     * POST /api/template/update/{id}
     * @param int $id
     * @return Response
     */
    public function update(int $id): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 查询模板
            $template = ContentTemplate::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            // 权限检查：只有管理员或模板所有者可以编辑
            if ($role !== 'admin') {
                if ($template->isSystemTemplate()) {
                    return $this->error('无权编辑系统模板', 403);
                }
                if ($template->merchant_id != $merchantId) {
                    return $this->error('无权编辑该模板', 403);
                }
            }

            // 获取请求数据
            $data = $this->request->post();

            // 数据验证
            $this->validateTemplateData($data, 'update');

            // 验证content是否为有效的JSON
            if (isset($data['content'])) {
                if (is_string($data['content'])) {
                    $content = json_decode($data['content'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $this->error('模板内容必须是有效的JSON格式');
                    }
                    $data['content'] = $content;
                } elseif (!is_array($data['content'])) {
                    return $this->error('模板内容格式不正确');
                }
            }

            // 不允许修改merchant_id
            unset($data['merchant_id']);

            // 更新模板
            $template->save($data);

            // 清除相关缓存
            $this->clearTemplateCache($id);

            return $this->success([
                'id' => $template->id,
                'name' => $template->name,
            ], '更新模板成功');

        } catch (ValidateException $e) {
            return $this->validationError([$e->getMessage()], $e->getMessage());
        } catch (\Exception $e) {
            Log::error('更新模板失败', [
                'id' => $id,
                'data' => $this->request->post(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('更新模板失败：' . $e->getMessage());
        }
    }

    /**
     * 删除模板
     * POST /api/template/delete/{id}
     * @param int $id
     * @return Response
     */
    public function delete(int $id): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 查询模板
            $template = ContentTemplate::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            // 权限检查：只有管理员或模板所有者可以删除
            if ($role !== 'admin') {
                if ($template->isSystemTemplate()) {
                    return $this->error('无权删除系统模板', 403);
                }
                if ($template->merchant_id != $merchantId) {
                    return $this->error('无权删除该模板', 403);
                }
            }

            // 检查是否被设备使用
            $deviceCount = NfcDevice::where('template_id', $id)->count();
            if ($deviceCount > 0) {
                return $this->error("该模板正在被 {$deviceCount} 个设备使用，无法删除", 400);
            }

            // 删除模板
            $template->delete();

            // 清除相关缓存
            $this->clearTemplateCache($id);

            return $this->success(null, '删除模板成功');

        } catch (\Exception $e) {
            Log::error('删除模板失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('删除模板失败：' . $e->getMessage());
        }
    }

    /**
     * 启用/禁用模板
     * POST /api/template/toggle-status/{id}
     * @param int $id
     * @return Response
     */
    public function toggleStatus(int $id): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 查询模板
            $template = ContentTemplate::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            // 权限检查
            if ($role !== 'admin') {
                if ($template->isSystemTemplate()) {
                    return $this->error('无权修改系统模板状态', 403);
                }
                if ($template->merchant_id != $merchantId) {
                    return $this->error('无权修改该模板状态', 403);
                }
            }

            // 获取状态参数
            $status = $this->request->post('status');

            if ($status === null) {
                return $this->error('请提供状态参数');
            }

            $status = (int)$status;

            if (!in_array($status, [ContentTemplate::STATUS_DISABLED, ContentTemplate::STATUS_ENABLED])) {
                return $this->error('状态值无效');
            }

            // 更新状态
            $template->setTemplateStatus($status);

            // 清除相关缓存
            $this->clearTemplateCache($id);

            $statusText = $status === ContentTemplate::STATUS_ENABLED ? '启用' : '禁用';

            return $this->success([
                'id' => $template->id,
                'status' => $template->status,
                'status_text' => $template->status_text,
            ], "模板已{$statusText}");

        } catch (\Exception $e) {
            Log::error('切换模板状态失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('切换模板状态失败：' . $e->getMessage());
        }
    }

    /**
     * 复制模板
     * POST /api/template/copy/{id}
     * @param int $id
     * @return Response
     */
    public function copy(int $id): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 查询模板
            $template = ContentTemplate::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            // 权限检查：可以复制公开模板、系统模板和自己的模板
            if ($role !== 'admin') {
                if ($template->merchant_id &&
                    $template->merchant_id != $merchantId &&
                    $template->is_public != ContentTemplate::PUBLIC_YES) {
                    return $this->error('无权复制该模板', 403);
                }
            }

            // 获取新名称
            $newName = $this->request->post('name', '');

            // 复制模板
            $newTemplate = $template->copyTemplate($merchantId, $newName);

            if (!$newTemplate) {
                return $this->error('复制模板失败');
            }

            // 清除相关缓存
            $this->clearTemplateCache();

            return $this->success([
                'id' => $newTemplate->id,
                'name' => $newTemplate->name,
                'type' => $newTemplate->type,
            ], '复制模板成功');

        } catch (\Exception $e) {
            Log::error('复制模板失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('复制模板失败：' . $e->getMessage());
        }
    }

    /**
     * 模板预览
     * GET /api/template/preview/{id}
     * @param int $id
     * @return Response
     */
    public function preview(int $id): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 查询模板
            $template = ContentTemplate::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            // 权限检查
            if ($role !== 'admin') {
                if ($template->merchant_id &&
                    $template->merchant_id != $merchantId &&
                    $template->is_public != ContentTemplate::PUBLIC_YES) {
                    return $this->error('无权预览该模板', 403);
                }
            }

            // 返回预览数据
            $result = [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type,
                'preview_url' => $template->preview_url,
                'preview_data' => [
                    'content' => $template->content,
                    'category' => $template->category,
                    'style' => $template->style,
                ],
            ];

            return $this->success($result, '获取模板预览成功');

        } catch (\Exception $e) {
            Log::error('获取模板预览失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取模板预览失败：' . $e->getMessage());
        }
    }

    /**
     * 热门模板推荐
     * GET /api/template/hot
     * @return Response
     */
    public function hot(): Response
    {
        try {
            // 获取参数
            $type = $this->request->get('type', '');
            $limit = (int)$this->request->get('limit', 10);
            $limit = min($limit, 50);

            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;

            // 生成缓存键
            $cacheKey = self::CACHE_HOT_KEY . ($type ?: 'all') . ':' . $limit;

            // 尝试从缓存获取
            $result = Cache::get($cacheKey);

            if (!$result) {
                // 查询热门模板
                $query = ContentTemplate::where('status', ContentTemplate::STATUS_ENABLED)
                                       ->where('usage_count', '>', 0);

                // 类型筛选
                if ($type && in_array($type, ['VIDEO', 'TEXT', 'IMAGE'])) {
                    $query->where('type', $type);
                }

                // 获取公开模板和系统模板
                $query->where(function ($query) use ($merchantId) {
                    $query->whereNull('merchant_id')
                          ->whereOr('is_public', ContentTemplate::PUBLIC_YES);
                    if ($merchantId) {
                        $query->whereOr('merchant_id', $merchantId);
                    }
                });

                $templates = $query->field('id, name, type, category, style, preview_url, usage_count, is_public, create_time')
                                  ->order('usage_count', 'desc')
                                  ->order('create_time', 'desc')
                                  ->limit($limit)
                                  ->select();

                $result = $templates->toArray();

                // 缓存结果
                Cache::set($cacheKey, $result, self::CACHE_TTL);
            }

            return $this->success($result, '获取热门模板成功');

        } catch (\Exception $e) {
            Log::error('获取热门模板失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取热门模板失败：' . $e->getMessage());
        }
    }

    /**
     * 模板分类列表
     * GET /api/template/categories
     * @return Response
     */
    public function categories(): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 尝试从缓存获取
            $cacheKey = self::CACHE_CATEGORY_KEY . ':' . ($merchantId ?: 'system');
            $result = Cache::get($cacheKey);

            if (!$result) {
                // 构建查询
                $query = ContentTemplate::where('status', ContentTemplate::STATUS_ENABLED)
                                       ->field('category, COUNT(*) as count')
                                       ->group('category');

                // 权限过滤
                if ($role !== 'admin') {
                    $query->where(function ($query) use ($merchantId) {
                        $query->whereNull('merchant_id')
                              ->whereOr('is_public', ContentTemplate::PUBLIC_YES);
                        if ($merchantId) {
                            $query->whereOr('merchant_id', $merchantId);
                        }
                    });
                }

                $categories = $query->select()->toArray();

                // 为每个分类获取示例模板
                $result = [];
                foreach ($categories as $cat) {
                    $templates = ContentTemplate::where('category', $cat['category'])
                                               ->where('status', ContentTemplate::STATUS_ENABLED)
                                               ->field('id, name, type, preview_url')
                                               ->limit(3)
                                               ->select()
                                               ->toArray();

                    $result[] = [
                        'category' => $cat['category'],
                        'count' => $cat['count'],
                        'templates' => $templates,
                    ];
                }

                // 缓存结果
                Cache::set($cacheKey, $result, self::CACHE_TTL);
            }

            return $this->success($result, '获取模板分类成功');

        } catch (\Exception $e) {
            Log::error('获取模板分类失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取模板分类失败：' . $e->getMessage());
        }
    }

    /**
     * 模板统计数据
     * GET /api/template/statistics
     * @return Response
     */
    public function statistics(): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 生成缓存键
            $cacheKey = self::CACHE_STATS_KEY . ($merchantId ?: 'system');

            // 尝试从缓存获取
            $result = Cache::get($cacheKey);

            if (!$result) {
                // 构建基础查询
                $query = ContentTemplate::query();

                // 权限过滤：非管理员只统计自己的模板
                if ($role !== 'admin') {
                    if ($merchantId) {
                        $query->where('merchant_id', $merchantId);
                    } else {
                        // 普通用户没有商家ID，返回空统计
                        return $this->success([
                            'total_count' => 0,
                            'enabled_count' => 0,
                            'disabled_count' => 0,
                            'public_count' => 0,
                            'private_count' => 0,
                            'total_usage' => 0,
                            'type_distribution' => [],
                            'usage_statistics' => [],
                            'popular_categories' => [],
                        ], '获取模板统计成功');
                    }
                }

                // 基础统计
                $totalCount = $query->count();
                $enabledCount = (clone $query)->where('status', ContentTemplate::STATUS_ENABLED)->count();
                $disabledCount = (clone $query)->where('status', ContentTemplate::STATUS_DISABLED)->count();
                $publicCount = (clone $query)->where('is_public', ContentTemplate::PUBLIC_YES)->count();
                $privateCount = $totalCount - $publicCount;

                // 按类型统计
                $typeStats = (clone $query)->field('type, COUNT(*) as count')
                                          ->group('type')
                                          ->select()
                                          ->toArray();

                $typeDistribution = [];
                foreach ($typeStats as $stat) {
                    $typeDistribution[$stat['type']] = $stat['count'];
                }

                // 使用次数统计
                $totalUsage = (clone $query)->sum('usage_count') ?: 0;
                $avgUsage = $totalCount > 0 ? round($totalUsage / $totalCount, 2) : 0;

                // 热门分类
                $popularCategories = (clone $query)->field('category, COUNT(*) as count, SUM(usage_count) as total_usage')
                                                  ->where('status', ContentTemplate::STATUS_ENABLED)
                                                  ->group('category')
                                                  ->order('total_usage', 'desc')
                                                  ->limit(5)
                                                  ->select()
                                                  ->toArray();

                $result = [
                    'total_count' => $totalCount,
                    'enabled_count' => $enabledCount,
                    'disabled_count' => $disabledCount,
                    'public_count' => $publicCount,
                    'private_count' => $privateCount,
                    'total_usage' => (int)$totalUsage,
                    'average_usage' => $avgUsage,
                    'type_distribution' => $typeDistribution,
                    'usage_statistics' => [
                        'total' => (int)$totalUsage,
                        'average' => $avgUsage,
                    ],
                    'popular_categories' => $popularCategories,
                ];

                // 缓存结果
                Cache::set($cacheKey, $result, self::CACHE_TTL);
            }

            return $this->success($result, '获取模板统计成功');

        } catch (\Exception $e) {
            Log::error('获取模板统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取模板统计失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除模板
     * POST /api/template/batch-delete
     * @return Response
     */
    public function batchDelete(): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->getUserInfo();
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 获取要删除的ID列表
            $ids = $this->request->post('ids', []);

            if (empty($ids) || !is_array($ids)) {
                return $this->error('请提供要删除的模板ID列表');
            }

            // 查询模板
            $templates = ContentTemplate::whereIn('id', $ids)->select();

            if ($templates->isEmpty()) {
                return $this->error('未找到要删除的模板');
            }

            $successCount = 0;
            $failedCount = 0;
            $errors = [];

            // 开启事务
            Db::startTrans();

            try {
                foreach ($templates as $template) {
                    // 权限检查
                    if ($role !== 'admin') {
                        if ($template->isSystemTemplate()) {
                            $errors[] = "模板 {$template->name}（ID:{$template->id}）：无权删除系统模板";
                            $failedCount++;
                            continue;
                        }
                        if ($template->merchant_id != $merchantId) {
                            $errors[] = "模板 {$template->name}（ID:{$template->id}）：无权删除该模板";
                            $failedCount++;
                            continue;
                        }
                    }

                    // 检查是否被设备使用
                    $deviceCount = NfcDevice::where('template_id', $template->id)->count();
                    if ($deviceCount > 0) {
                        $errors[] = "模板 {$template->name}（ID:{$template->id}）：正在被 {$deviceCount} 个设备使用";
                        $failedCount++;
                        continue;
                    }

                    // 删除模板
                    $template->delete();
                    $successCount++;
                }

                // 提交事务
                Db::commit();

                // 清除相关缓存
                $this->clearTemplateCache();

                // 返回结果
                $result = [
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'total' => count($ids),
                    'errors' => $errors,
                ];

                return $this->batchResponse($result, "批量删除完成，成功 {$successCount} 个，失败 {$failedCount} 个");

            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('批量删除模板失败', [
                'ids' => $this->request->post('ids', []),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('批量删除模板失败：' . $e->getMessage());
        }
    }

    /**
     * 验证模板数据
     * @param array $data
     * @param string $scene
     * @throws ValidateException
     */
    private function validateTemplateData(array $data, string $scene = 'create'): void
    {
        $rules = [
            'name' => 'max:100',
            'type' => 'in:VIDEO,TEXT,IMAGE',
            'category' => 'max:50',
            'style' => 'max:50',
            'preview_url' => 'max:255',
            'is_public' => 'in:0,1',
            'status' => 'in:0,1',
        ];

        $messages = [
            'name.require' => '模板名称不能为空',
            'name.max' => '模板名称长度不能超过100个字符',
            'type.require' => '模板类型不能为空',
            'type.in' => '模板类型值无效，必须是VIDEO、TEXT或IMAGE',
            'category.require' => '模板分类不能为空',
            'category.max' => '模板分类长度不能超过50个字符',
            'style.max' => '风格标签长度不能超过50个字符',
            'content.require' => '模板内容不能为空',
            'preview_url.max' => '预览图链接长度不能超过255个字符',
            'is_public.in' => '公开状态值无效',
            'status.in' => '状态值无效',
        ];

        // 创建时的必填字段
        if ($scene === 'create') {
            $rules['name'] = 'require|max:100';
            $rules['type'] = 'require|in:VIDEO,TEXT,IMAGE';
            $rules['category'] = 'require|max:50';
            $rules['content'] = 'require';
        }

        $this->validate($data, $rules, $messages);
    }

    /**
     * 清除模板相关缓存
     * @param int|null $templateId
     */
    private function clearTemplateCache(?int $templateId = null): void
    {
        try {
            // 清除热门模板缓存
            Cache::delete(self::CACHE_HOT_KEY . 'all:10');
            Cache::delete(self::CACHE_HOT_KEY . 'VIDEO:10');
            Cache::delete(self::CACHE_HOT_KEY . 'TEXT:10');
            Cache::delete(self::CACHE_HOT_KEY . 'IMAGE:10');

            // 清除分类缓存
            $pattern = self::CACHE_CATEGORY_KEY . ':*';
            Cache::clear();

            // 清除统计缓存
            $pattern = self::CACHE_STATS_KEY . '*';
            Cache::clear();

            // 清除单个模板缓存
            if ($templateId) {
                Cache::delete(self::CACHE_PREFIX . $templateId);
            }

        } catch (\Exception $e) {
            Log::warning('清除模板缓存失败', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
