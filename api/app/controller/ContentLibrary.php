<?php
declare(strict_types=1);

namespace app\controller;

use app\model\ContentLibrary as ContentLibraryModel;
use app\service\ContentLibraryService;
use think\App;
use think\facade\Log;
use think\Response;

class ContentLibrary extends BaseController
{
    protected ContentLibraryService $libraryService;
    protected ?int $merchantId = null;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->libraryService = new ContentLibraryService();
        $this->merchantId = $this->request->merchantId ?? null;
    }

    protected function isAdmin(): bool
    {
        return $this->request->role === 'admin';
    }

    protected function getEffectiveMerchantId(): ?int
    {
        $requestedId = $this->request->get('merchant_id') ?: $this->request->post('merchant_id');
        if ($requestedId && $this->isAdmin()) {
            return (int)$requestedId;
        }
        return $this->merchantId;
    }

    // ==================== 视频库 ====================

    public function videoList(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $filters = [
                'keyword' => $this->request->get('keyword', ''),
                'status' => $this->request->get('status', ''),
                'page' => (int)$this->request->get('page', 1),
                'page_size' => (int)$this->request->get('page_size', 20),
            ];

            if ($merchantId) {
                $result = $this->libraryService->getVideoLibraryList($merchantId, $filters);
            } else {
                $query = ContentLibraryModel::where('library_type', 'video')->where('status', '>=', 0);
                if (!empty($filters['keyword'])) {
                    $query->whereLike('name', "%{$filters['keyword']}%");
                }
                $total = $query->count();
                $list = $query->order('create_time', 'desc')
                    ->page($filters['page'], $filters['page_size'])
                    ->select()
                    ->toArray();
                $result = ['list' => $list, 'total' => $total, 'page' => $filters['page'], 'page_size' => $filters['page_size']];
            }

            return $this->paginate($result['list'], $result['total'], $result['page'], $result['page_size']);
        } catch (\Exception $e) {
            Log::error('获取视频库列表失败', ['error' => $e->getMessage()]);
            return $this->error('获取视频库列表失败: ' . $e->getMessage());
        }
    }

    public function videoCreate(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId) {
                return $this->error('商家信息无效', 401);
            }

            $data = $this->request->post();
            if (empty($data['name'])) {
                return $this->error('库名称不能为空', 400);
            }

            $result = $this->libraryService->createVideoLibrary($merchantId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '创建视频库成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('创建视频库失败', ['error' => $e->getMessage()]);
            return $this->error('创建视频库失败: ' . $e->getMessage());
        }
    }

    public function videoDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->libraryService->getVideoLibraryDetail($id);
            if (!$data) {
                return $this->error('视频库不存在', 404);
            }
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('获取视频库详情失败', ['error' => $e->getMessage()]);
            return $this->error('获取视频库详情失败: ' . $e->getMessage());
        }
    }

    public function videoUpdate(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->request->put();
            $result = $this->libraryService->updateVideoLibrary($id, $data);
            if ($result['success']) {
                return $this->success($result['data'], '更新成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('更新视频库失败', ['error' => $e->getMessage()]);
            return $this->error('更新视频库失败: ' . $e->getMessage());
        }
    }

    public function videoDelete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $result = $this->libraryService->deleteVideoLibrary($id);
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('删除视频库失败', ['error' => $e->getMessage()]);
            return $this->error('删除视频库失败: ' . $e->getMessage());
        }
    }

    public function videoAddLocal(): Response
    {
        try {
            $libraryId = (int)$this->request->param('id');
            if (!$libraryId) {
                return $this->error('库ID不能为空', 400);
            }

            $data = $this->request->post();
            if (empty($data['file_url'])) {
                return $this->error('文件URL不能为空', 400);
            }

            $result = $this->libraryService->addLocalVideo($libraryId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '添加视频成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('添加本地视频失败', ['error' => $e->getMessage()]);
            return $this->error('添加本地视频失败: ' . $e->getMessage());
        }
    }

    public function videoImport(): Response
    {
        try {
            $libraryId = (int)$this->request->param('id');
            if (!$libraryId) {
                return $this->error('库ID不能为空', 400);
            }

            $data = $this->request->post();
            if (empty($data['items']) || !is_array($data['items'])) {
                return $this->error('导入数据不能为空', 400);
            }

            $result = $this->libraryService->importVideo($libraryId, $data);
            if ($result['success']) {
                return $this->success(['imported' => $result['imported']], '导入视频成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('导入视频失败', ['error' => $e->getMessage()]);
            return $this->error('导入视频失败: ' . $e->getMessage());
        }
    }

    // ==================== 图文库 ====================

    public function graphicList(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $filters = [
                'keyword' => $this->request->get('keyword', ''),
                'status' => $this->request->get('status', ''),
                'page' => (int)$this->request->get('page', 1),
                'page_size' => (int)$this->request->get('page_size', 20),
            ];

            if ($merchantId) {
                $result = $this->libraryService->getGraphicLibraryList($merchantId, $filters);
            } else {
                $query = ContentLibraryModel::where('library_type', 'graphic')->where('status', '>=', 0);
                if (!empty($filters['keyword'])) {
                    $query->whereLike('name', "%{$filters['keyword']}%");
                }
                $total = $query->count();
                $list = $query->order('create_time', 'desc')
                    ->page($filters['page'], $filters['page_size'])
                    ->select()
                    ->toArray();
                $result = ['list' => $list, 'total' => $total, 'page' => $filters['page'], 'page_size' => $filters['page_size']];
            }

            return $this->paginate($result['list'], $result['total'], $result['page'], $result['page_size']);
        } catch (\Exception $e) {
            Log::error('获取图文库列表失败', ['error' => $e->getMessage()]);
            return $this->error('获取图文库列表失败: ' . $e->getMessage());
        }
    }

    public function graphicCreate(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId) {
                return $this->error('商家信息无效', 401);
            }

            $data = $this->request->post();
            if (empty($data['name'])) {
                return $this->error('库名称不能为空', 400);
            }

            $result = $this->libraryService->createGraphicLibrary($merchantId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '创建图文库成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('创建图文库失败', ['error' => $e->getMessage()]);
            return $this->error('创建图文库失败: ' . $e->getMessage());
        }
    }

    public function graphicDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->libraryService->getGraphicLibraryDetail($id);
            if (!$data) {
                return $this->error('图文库不存在', 404);
            }
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('获取图文库详情失败', ['error' => $e->getMessage()]);
            return $this->error('获取图文库详情失败: ' . $e->getMessage());
        }
    }

    public function graphicUpdate(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->request->put();
            $result = $this->libraryService->updateGraphicLibrary($id, $data);
            if ($result['success']) {
                return $this->success($result['data'], '更新成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('更新图文库失败', ['error' => $e->getMessage()]);
            return $this->error('更新图文库失败: ' . $e->getMessage());
        }
    }

    public function graphicDelete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $result = $this->libraryService->deleteGraphicLibrary($id);
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('删除图文库失败', ['error' => $e->getMessage()]);
            return $this->error('删除图文库失败: ' . $e->getMessage());
        }
    }

    public function graphicAddContent(): Response
    {
        try {
            $libraryId = (int)$this->request->param('id');
            if (!$libraryId) {
                return $this->error('库ID不能为空', 400);
            }

            $data = $this->request->post();
            $imageItemId = !empty($data['image_item_id']) ? (int)$data['image_item_id'] : null;
            $textItemId = !empty($data['text_item_id']) ? (int)$data['text_item_id'] : null;

            if (!$imageItemId && !$textItemId) {
                return $this->error('至少需要一个图片或文案条目ID', 400);
            }

            $result = $this->libraryService->addGraphicContent($libraryId, $imageItemId, $textItemId);
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('添加图文内容失败', ['error' => $e->getMessage()]);
            return $this->error('添加图文内容失败: ' . $e->getMessage());
        }
    }

    // ==================== 图片库 ====================

    public function imageList(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $filters = [
                'keyword' => $this->request->get('keyword', ''),
                'status' => $this->request->get('status', ''),
                'page' => (int)$this->request->get('page', 1),
                'page_size' => (int)$this->request->get('page_size', 20),
            ];

            if ($merchantId) {
                $result = $this->libraryService->getImageLibraryList($merchantId, $filters);
            } else {
                $query = ContentLibraryModel::where('library_type', 'image')->where('status', '>=', 0);
                if (!empty($filters['keyword'])) {
                    $query->whereLike('name', "%{$filters['keyword']}%");
                }
                $total = $query->count();
                $list = $query->order('create_time', 'desc')
                    ->page($filters['page'], $filters['page_size'])
                    ->select()
                    ->toArray();
                $result = ['list' => $list, 'total' => $total, 'page' => $filters['page'], 'page_size' => $filters['page_size']];
            }

            return $this->paginate($result['list'], $result['total'], $result['page'], $result['page_size']);
        } catch (\Exception $e) {
            Log::error('获取图片库列表失败', ['error' => $e->getMessage()]);
            return $this->error('获取图片库列表失败: ' . $e->getMessage());
        }
    }

    public function imageCreate(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId) {
                return $this->error('商家信息无效', 401);
            }

            $data = $this->request->post();
            if (empty($data['name'])) {
                return $this->error('库名称不能为空', 400);
            }

            $result = $this->libraryService->createImageLibrary($merchantId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '创建图片库成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('创建图片库失败', ['error' => $e->getMessage()]);
            return $this->error('创建图片库失败: ' . $e->getMessage());
        }
    }

    public function imageAdd(): Response
    {
        try {
            $libraryId = (int)$this->request->param('id');
            if (!$libraryId) {
                return $this->error('库ID不能为空', 400);
            }

            $data = $this->request->post();
            if (empty($data['file_url'])) {
                return $this->error('图片URL不能为空', 400);
            }

            $result = $this->libraryService->addImage($libraryId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '添加图片成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('添加图片失败', ['error' => $e->getMessage()]);
            return $this->error('添加图片失败: ' . $e->getMessage());
        }
    }

    public function imageDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->libraryService->getImageLibraryDetail($id);
            if (!$data) {
                return $this->error('图片库不存在', 404);
            }
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('获取图片库详情失败', ['error' => $e->getMessage()]);
            return $this->error('获取图片库详情失败: ' . $e->getMessage());
        }
    }

    public function imageUpdate(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->request->put();
            $result = $this->libraryService->updateImageLibrary($id, $data);
            if ($result['success']) {
                return $this->success($result['data'], '更新成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('更新图片库失败', ['error' => $e->getMessage()]);
            return $this->error('更新图片库失败: ' . $e->getMessage());
        }
    }

    public function imageDelete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $result = $this->libraryService->deleteImageLibrary($id);
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('删除图片库失败', ['error' => $e->getMessage()]);
            return $this->error('删除图片库失败: ' . $e->getMessage());
        }
    }

    // ==================== 文案库 ====================

    public function textList(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $filters = [
                'keyword' => $this->request->get('keyword', ''),
                'status' => $this->request->get('status', ''),
                'page' => (int)$this->request->get('page', 1),
                'page_size' => (int)$this->request->get('page_size', 20),
            ];

            if ($merchantId) {
                $result = $this->libraryService->getTextLibraryList($merchantId, $filters);
            } else {
                $query = ContentLibraryModel::where('library_type', 'text')->where('status', '>=', 0);
                if (!empty($filters['keyword'])) {
                    $query->whereLike('name', "%{$filters['keyword']}%");
                }
                $total = $query->count();
                $list = $query->order('create_time', 'desc')
                    ->page($filters['page'], $filters['page_size'])
                    ->select()
                    ->toArray();
                $result = ['list' => $list, 'total' => $total, 'page' => $filters['page'], 'page_size' => $filters['page_size']];
            }

            return $this->paginate($result['list'], $result['total'], $result['page'], $result['page_size']);
        } catch (\Exception $e) {
            Log::error('获取文案库列表失败', ['error' => $e->getMessage()]);
            return $this->error('获取文案库列表失败: ' . $e->getMessage());
        }
    }

    public function textCreate(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId) {
                return $this->error('商家信息无效', 401);
            }

            $data = $this->request->post();
            if (empty($data['name'])) {
                return $this->error('库名称不能为空', 400);
            }

            $result = $this->libraryService->createTextLibrary($merchantId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '创建文案库成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('创建文案库失败', ['error' => $e->getMessage()]);
            return $this->error('创建文案库失败: ' . $e->getMessage());
        }
    }

    public function textAdd(): Response
    {
        try {
            $libraryId = (int)$this->request->param('id');
            if (!$libraryId) {
                return $this->error('库ID不能为空', 400);
            }

            $data = $this->request->post();
            if (empty($data['content'])) {
                return $this->error('文案内容不能为空', 400);
            }

            $result = $this->libraryService->addText($libraryId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '添加文案成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('添加文案失败', ['error' => $e->getMessage()]);
            return $this->error('添加文案失败: ' . $e->getMessage());
        }
    }

    public function textDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->libraryService->getTextLibraryDetail($id);
            if (!$data) {
                return $this->error('文案库不存在', 404);
            }
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('获取文案库详情失败', ['error' => $e->getMessage()]);
            return $this->error('获取文案库详情失败: ' . $e->getMessage());
        }
    }

    public function textUpdate(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->request->put();
            $result = $this->libraryService->updateTextLibrary($id, $data);
            if ($result['success']) {
                return $this->success($result['data'], '更新成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('更新文案库失败', ['error' => $e->getMessage()]);
            return $this->error('更新文案库失败: ' . $e->getMessage());
        }
    }

    public function textDelete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $result = $this->libraryService->deleteTextLibrary($id);
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('删除文案库失败', ['error' => $e->getMessage()]);
            return $this->error('删除文案库失败: ' . $e->getMessage());
        }
    }

    // ==================== 话题库 ====================

    public function topicList(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $filters = [
                'keyword' => $this->request->get('keyword', ''),
                'status' => $this->request->get('status', ''),
                'page' => (int)$this->request->get('page', 1),
                'page_size' => (int)$this->request->get('page_size', 20),
            ];

            if ($merchantId) {
                $result = $this->libraryService->getTopicLibraryList($merchantId, $filters);
            } else {
                $query = ContentLibraryModel::where('library_type', 'topic')->where('status', '>=', 0);
                if (!empty($filters['keyword'])) {
                    $query->whereLike('name', "%{$filters['keyword']}%");
                }
                $total = $query->count();
                $list = $query->order('create_time', 'desc')
                    ->page($filters['page'], $filters['page_size'])
                    ->select()
                    ->toArray();
                $result = ['list' => $list, 'total' => $total, 'page' => $filters['page'], 'page_size' => $filters['page_size']];
            }

            return $this->paginate($result['list'], $result['total'], $result['page'], $result['page_size']);
        } catch (\Exception $e) {
            Log::error('获取话题库列表失败', ['error' => $e->getMessage()]);
            return $this->error('获取话题库列表失败: ' . $e->getMessage());
        }
    }

    public function topicCreate(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId) {
                return $this->error('商家信息无效', 401);
            }

            $data = $this->request->post();
            if (empty($data['name'])) {
                return $this->error('库名称不能为空', 400);
            }

            $result = $this->libraryService->createTopicLibrary($merchantId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '创建话题库成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('创建话题库失败', ['error' => $e->getMessage()]);
            return $this->error('创建话题库失败: ' . $e->getMessage());
        }
    }

    public function topicDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $data = $this->libraryService->getTopicLibraryDetail($id);
            if (!$data) {
                return $this->error('话题库不存在', 404);
            }
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('获取话题库详情失败', ['error' => $e->getMessage()]);
            return $this->error('获取话题库详情失败: ' . $e->getMessage());
        }
    }

    public function topicAdd(): Response
    {
        try {
            $libraryId = (int)$this->request->param('id');
            if (!$libraryId) {
                return $this->error('库ID不能为空', 400);
            }

            $data = $this->request->post();
            if (empty($data['content'])) {
                return $this->error('话题内容不能为空', 400);
            }

            $result = $this->libraryService->addTopic($libraryId, $data);
            if ($result['success']) {
                return $this->success($result['data'], '添加话题成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('添加话题失败', ['error' => $e->getMessage()]);
            return $this->error('添加话题失败: ' . $e->getMessage());
        }
    }

    public function topicRename(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $name = $this->request->put('name', '');
            if (empty($name)) {
                return $this->error('名称不能为空', 400);
            }

            $result = $this->libraryService->renameTopicLibrary($id, $name);
            if ($result['success']) {
                return $this->success($result['data'], '重命名成功');
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('重命名话题库失败', ['error' => $e->getMessage()]);
            return $this->error('重命名话题库失败: ' . $e->getMessage());
        }
    }

    public function topicDelete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('ID不能为空', 400);
            }

            $result = $this->libraryService->deleteTopicLibrary($id);
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('删除话题库失败', ['error' => $e->getMessage()]);
            return $this->error('删除话题库失败: ' . $e->getMessage());
        }
    }

    // ==================== 通用 ====================

    public function setWarningEmail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            if (!$id) {
                return $this->error('库ID不能为空', 400);
            }

            $email = $this->request->post('email', '');
            if (empty($email)) {
                return $this->error('邮箱不能为空', 400);
            }

            $result = $this->libraryService->setWarningEmail($id, $email);
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('设置预警邮箱失败', ['error' => $e->getMessage()]);
            return $this->error('设置预警邮箱失败: ' . $e->getMessage());
        }
    }

    public function deleteItem(): Response
    {
        try {
            $itemId = (int)$this->request->param('id');
            if (!$itemId) {
                return $this->error('条目ID不能为空', 400);
            }

            $result = $this->libraryService->deleteItem($itemId);
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('删除条目失败', ['error' => $e->getMessage()]);
            return $this->error('删除条目失败: ' . $e->getMessage());
        }
    }

    public function statistics(): Response
    {
        try {
            $merchantId = $this->getEffectiveMerchantId();
            if (!$merchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            if ($merchantId) {
                $stats = $this->libraryService->getLibraryStatistics($merchantId);
            } else {
                $stats = $this->libraryService->getLibraryStatistics(0);
            }

            return $this->success($stats);
        } catch (\Exception $e) {
            Log::error('获取统计失败', ['error' => $e->getMessage()]);
            return $this->error('获取统计失败: ' . $e->getMessage());
        }
    }
}
