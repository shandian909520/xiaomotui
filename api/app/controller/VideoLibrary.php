<?php
declare(strict_types=1);

namespace app\controller;

use app\model\ContentTemplate;
use think\facade\Log;
use think\facade\Db;
use think\Response;

/**
 * 视频库控制器
 * 提供视频模板的专门管理功能
 */
class VideoLibrary extends BaseController
{
    /**
     * 中间件配置
     * @var array
     */
    protected array $middleware = [];

    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'video_library:';
    const CACHE_TTL = 300;

    /**
     * 初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 视频模板列表
     * GET /api/video-library/list
     * @return Response
     */
    public function list(): Response
    {
        try {
            // 获取查询参数
            $category = $this->request->get('category', '');
            $industry = $this->request->get('industry', '');
            $difficulty = $this->request->get('difficulty', '');
            $aspectRatio = $this->request->get('aspect_ratio', '');
            $keyword = $this->request->get('keyword', '');
            $page = (int)$this->request->get('page', 1);
            $limit = (int)$this->request->get('limit', 20);
            $sortBy = $this->request->get('sort_by', 'create_time');
            $sortOrder = $this->request->get('sort_order', 'desc');

            // 限制每页数量
            $limit = min($limit, 100);

            // 获取当前用户信息
            $userInfo = $this->request->userInfo ?? [];
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 构建查询 - 只查询视频模板
            $query = ContentTemplate::alias('t')
                ->field('t.*, m.name as merchant_name')
                ->leftJoin('merchants m', 't.merchant_id = m.id')
                ->where('t.type', 'VIDEO')
                ->where('t.is_template', 1)
                ->where('t.status', 1);

            // 权限过滤：非管理员只能看到自己的模板和公开模板
            if ($role !== 'admin') {
                $query->where(function ($query) use ($merchantId) {
                    $query->whereNull('t.merchant_id')
                          ->whereOr('t.is_public', ContentTemplate::PUBLIC_YES)
                          ->whereOr('t.merchant_id', $merchantId);
                });
            }

            // 分类筛选
            if ($category) {
                $query->where('t.category', $category);
            }

            // 行业筛选
            if ($industry) {
                $query->where('t.industry', $industry);
            }

            // 难度筛选
            if ($difficulty && in_array($difficulty, ['easy', 'medium', 'hard'])) {
                $query->where('t.difficulty', $difficulty);
            }

            // 宽高比筛选
            if ($aspectRatio) {
                $query->where('t.aspect_ratio', $aspectRatio);
            }

            // 关键词搜索
            if ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->whereLike('t.name', "%{$keyword}%")
                          ->whereOr('t.category', 'like', "%{$keyword}%")
                          ->whereOr('t.style', 'like', "%{$keyword}%");
                });
            }

            // 排序
            $allowedSortFields = ['create_time', 'usage_count', 'video_duration', 'name'];
            if (in_array($sortBy, $allowedSortFields)) {
                $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
                $query->order("t.{$sortBy}", $sortOrder);
            } else {
                $query->order('t.usage_count', 'desc')
                      ->order('t.create_time', 'desc');
            }

            // 分页查询
            $list = $query->page($page, $limit)->select();
            $total = $query->count();

            // 处理返回数据
            $result = [];
            foreach ($list as $item) {
                $result[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category,
                    'style' => $item->style,
                    'preview_url' => $item->preview_url,
                    'video_url' => $item->video_url,
                    'video_duration' => $item->video_duration,
                    'video_resolution' => $item->video_resolution,
                    'video_format' => $item->video_format,
                    'aspect_ratio' => $item->aspect_ratio,
                    'thumbnail_time' => $item->thumbnail_time,
                    'usage_count' => $item->usage_count,
                    'is_public' => $item->is_public,
                    'template_tags' => $item->template_tags,
                    'difficulty' => $item->difficulty,
                    'industry' => $item->industry,
                    'merchant_id' => $item->merchant_id,
                    'merchant_name' => $item->merchant_name ?: '系统',
                    'create_time' => $item->create_time,
                ];
            }

            // 返回分页数据
            return $this->paginate(
                $result,
                $total,
                $page,
                $limit,
                '获取视频模板列表成功'
            );

        } catch (\Exception $e) {
            Log::error('获取视频模板列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取视频模板列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取视频模板详情
     * GET /api/video-library/detail/{id}
     * @param int $id
     * @return Response
     */
    public function detail(int $id): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->userInfo ?? [];
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 查询模板
            $template = ContentTemplate::find($id);

            if (!$template) {
                return $this->error('视频模板不存在', 404);
            }

            // 验证是否为视频模板
            if ($template->type !== 'VIDEO') {
                return $this->error('该模板不是视频模板', 400);
            }

            // 权限检查
            if ($role !== 'admin') {
                if ($template->merchant_id &&
                    $template->merchant_id != $merchantId &&
                    $template->is_public != ContentTemplate::PUBLIC_YES) {
                    return $this->error('无权访问该视频模板', 403);
                }
            }

            // 返回完整模板信息
            $result = [
                'id' => $template->id,
                'merchant_id' => $template->merchant_id,
                'merchant_name' => $template->merchant ? $template->merchant->name : '系统',
                'name' => $template->name,
                'category' => $template->category,
                'style' => $template->style,
                'content' => $template->content,
                'preview_url' => $template->preview_url,
                'video_url' => $template->video_url,
                'video_duration' => $template->video_duration,
                'video_resolution' => $template->video_resolution,
                'video_size' => $template->video_size,
                'video_format' => $template->video_format,
                'thumbnail_time' => $template->thumbnail_time,
                'aspect_ratio' => $template->aspect_ratio,
                'is_template' => $template->is_template,
                'template_tags' => $template->template_tags,
                'difficulty' => $template->difficulty,
                'industry' => $template->industry,
                'usage_count' => $template->usage_count,
                'is_public' => $template->is_public,
                'status' => $template->status,
                'create_time' => $template->create_time,
                'update_time' => $template->update_time,
            ];

            return $this->success($result, '获取视频模板详情成功');

        } catch (\Exception $e) {
            Log::error('获取视频模板详情失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取视频模板详情失败：' . $e->getMessage());
        }
    }

    /**
     * 创建视频模板
     * POST /api/video-library/create
     * @return Response
     */
    public function create(): Response
    {
        try {
            // 获取请求数据
            $data = $this->request->post();

            // 获取当前用户信息
            $userInfo = $this->request->userInfo ?? [];
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 强制设置为视频模板
            $data['type'] = 'VIDEO';
            $data['is_template'] = 1;

            // 数据验证
            $this->validateVideoTemplateData($data);

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
            if ($role === 'admin' && !isset($data['merchant_id'])) {
                $data['merchant_id'] = null;
            } else {
                $data['merchant_id'] = $merchantId;
            }

            // 设置默认值
            $data['usage_count'] = 0;
            $data['is_public'] = $data['is_public'] ?? ContentTemplate::PUBLIC_NO;
            $data['status'] = $data['status'] ?? ContentTemplate::STATUS_ENABLED;
            $data['aspect_ratio'] = $data['aspect_ratio'] ?? '16:9';

            // 创建模板
            $template = ContentTemplate::create($data);

            return $this->success([
                'id' => $template->id,
                'name' => $template->name,
                'video_url' => $template->video_url,
            ], '创建视频模板成功');

        } catch (\Exception $e) {
            Log::error('创建视频模板失败', [
                'data' => $this->request->post(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('创建视频模板失败：' . $e->getMessage());
        }
    }

    /**
     * 使用视频模板
     * POST /api/video-library/use/{id}
     * @param int $id
     * @return Response
     */
    public function useTemplate(int $id): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->userInfo ?? [];
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 查询模板
            $template = ContentTemplate::find($id);

            if (!$template) {
                return $this->error('视频模板不存在', 404);
            }

            // 验证是否为视频模板
            if ($template->type !== 'VIDEO') {
                return $this->error('该模板不是视频模板', 400);
            }

            // 权限检查
            if ($role !== 'admin') {
                if ($template->merchant_id &&
                    $template->merchant_id != $merchantId &&
                    $template->is_public != ContentTemplate::PUBLIC_YES) {
                    return $this->error('无权使用该视频模板', 403);
                }
            }

            // 获取新名称
            $newName = $this->request->post('name', $template->name . '_副本');

            // 复制模板
            $newTemplate = $template->copyTemplate($merchantId, $newName);

            if (!$newTemplate) {
                return $this->error('使用视频模板失败');
            }

            // 增加原模板的使用次数
            $template->incrementUsageCount();

            return $this->success([
                'id' => $newTemplate->id,
                'name' => $newTemplate->name,
                'video_url' => $newTemplate->video_url,
                'content' => $newTemplate->content,
            ], '使用视频模板成功');

        } catch (\Exception $e) {
            Log::error('使用视频模板失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('使用视频模板失败：' . $e->getMessage());
        }
    }

    /**
     * 获取视频库分类
     * GET /api/video-library/categories
     * @return Response
     */
    public function categories(): Response
    {
        try {
            // 获取所有视频模板的分类
            $categories = ContentTemplate::where('type', 'VIDEO')
                                         ->where('is_template', 1)
                                         ->where('status', 1)
                                         ->field('category, COUNT(*) as count')
                                         ->group('category')
                                         ->select()
                                         ->toArray();

            return $this->success($categories, '获取分类成功');

        } catch (\Exception $e) {
            Log::error('获取视频库分类失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取视频库分类失败：' . $e->getMessage());
        }
    }

    /**
     * 获取视频库筛选选项
     * GET /api/video-library/filters
     * @return Response
     */
    public function filters(): Response
    {
        try {
            $filters = [
                'industries' => [
                    '餐饮', '零售', '教育', '医疗', '金融',
                    '房地产', '汽车', '美妆', '服装', '娱乐',
                    '旅游', '体育', '科技', '其他'
                ],
                'difficulties' => [
                    'easy' => '简单',
                    'medium' => '中等',
                    'hard' => '困难'
                ],
                'aspect_ratios' => [
                    '16:9' => '横屏 (16:9)',
                    '9:16' => '竖屏 (9:16)',
                    '1:1' => '方形 (1:1)',
                    '4:3' => '横屏 (4:3)',
                    '3:4' => '竖屏 (3:4)'
                ],
                'sort_options' => [
                    'create_time' => '创建时间',
                    'usage_count' => '使用次数',
                    'video_duration' => '视频时长',
                    'name' => '名称'
                ]
            ];

            return $this->success($filters, '获取筛选选项成功');

        } catch (\Exception $e) {
            return $this->error('获取筛选选项失败：' . $e->getMessage());
        }
    }

    /**
     * 热门视频模板
     * GET /api/video-library/hot
     * @return Response
     */
    public function hot(): Response
    {
        try {
            $limit = (int)$this->request->get('limit', 10);
            $limit = min($limit, 50);

            // 获取当前用户信息
            $userInfo = $this->request->userInfo ?? [];
            $merchantId = $userInfo['merchant_id'] ?? null;

            // 查询热门视频模板
            $query = ContentTemplate::where('type', 'VIDEO')
                                    ->where('is_template', 1)
                                    ->where('status', 1)
                                    ->where('usage_count', '>', 0);

            // 权限过滤
            $query->where(function ($query) use ($merchantId) {
                $query->whereNull('merchant_id')
                      ->whereOr('is_public', ContentTemplate::PUBLIC_YES);
                if ($merchantId) {
                    $query->whereOr('merchant_id', $merchantId);
                }
            });

            $templates = $query->field('id, name, category, preview_url, video_url, video_duration, usage_count, aspect_ratio, difficulty')
                              ->order('usage_count', 'desc')
                              ->order('create_time', 'desc')
                              ->limit($limit)
                              ->select()
                              ->toArray();

            return $this->success($templates, '获取热门视频模板成功');

        } catch (\Exception $e) {
            Log::error('获取热门视频模板失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取热门视频模板失败：' . $e->getMessage());
        }
    }

    /**
     * 视频库统计
     * GET /api/video-library/statistics
     * @return Response
     */
    public function statistics(): Response
    {
        try {
            // 获取当前用户信息
            $userInfo = $this->request->userInfo ?? [];
            $merchantId = $userInfo['merchant_id'] ?? null;
            $role = $userInfo['role'] ?? 'user';

            // 构建查询
            $query = ContentTemplate::where('type', 'VIDEO')
                                    ->where('is_template', 1);

            // 权限过滤
            if ($role !== 'admin') {
                if ($merchantId) {
                    $query->where(function($query) use ($merchantId) {
                        $query->whereNull('merchant_id')
                              ->whereOr('is_public', ContentTemplate::PUBLIC_YES)
                              ->whereOr('merchant_id', $merchantId);
                    });
                } else {
                    $query->whereNull('merchant_id')
                          ->whereOr('is_public', ContentTemplate::PUBLIC_YES);
                }
            }

            // 基础统计
            $totalCount = $query->count();
            $publicCount = (clone $query)->where('is_public', ContentTemplate::PUBLIC_YES)->count();

            // 按分类统计
            $categoryStats = (clone $query)->field('category, COUNT(*) as count')
                                           ->group('category')
                                           ->select()
                                           ->toArray();

            // 按行业统计
            $industryStats = (clone $query)->field('industry, COUNT(*) as count')
                                           ->group('industry')
                                           ->select()
                                           ->toArray();

            // 按难度统计
            $difficultyStats = (clone $query)->field('difficulty, COUNT(*) as count')
                                             ->group('difficulty')
                                             ->select()
                                             ->toArray();

            // 使用次数统计
            $totalUsage = (clone $query)->sum('usage_count') ?: 0;

            $result = [
                'total_count' => $totalCount,
                'public_count' => $publicCount,
                'private_count' => $totalCount - $publicCount,
                'total_usage' => (int)$totalUsage,
                'category_distribution' => $categoryStats,
                'industry_distribution' => $industryStats,
                'difficulty_distribution' => $difficultyStats,
            ];

            return $this->success($result, '获取视频库统计成功');

        } catch (\Exception $e) {
            Log::error('获取视频库统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取视频库统计失败：' . $e->getMessage());
        }
    }

    /**
     * 验证视频模板数据
     * @param array $data
     * @throws \Exception
     */
    private function validateVideoTemplateData(array $data): void
    {
        if (empty($data['name'])) {
            throw new \Exception('模板名称不能为空');
        }

        if (empty($data['video_url'])) {
            throw new \Exception('视频URL不能为空');
        }

        if (isset($data['video_duration']) && (!is_numeric($data['video_duration']) || $data['video_duration'] < 0)) {
            throw new \Exception('视频时长必须为正数');
        }

        if (isset($data['difficulty']) && !in_array($data['difficulty'], ['easy', 'medium', 'hard'])) {
            throw new \Exception('制作难度值无效');
        }

        if (isset($data['aspect_ratio'])) {
            $allowedRatios = ['16:9', '9:16', '1:1', '4:3', '3:4'];
            if (!in_array($data['aspect_ratio'], $allowedRatios)) {
                throw new \Exception('宽高比值无效');
            }
        }
    }
}
