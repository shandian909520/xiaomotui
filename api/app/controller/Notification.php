<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\service\FeatureNotificationService;
use think\exception\ValidateException;
use think\facade\Log;

class Notification extends BaseController
{
    protected FeatureNotificationService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new FeatureNotificationService();
    }

    public function list()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'type'    => $this->request->param('type', ''),
                'is_read' => $this->request->param('is_read', ''),
                'page'    => (int)$this->request->param('page', 1),
                'limit'   => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getNotificationList($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取通知列表成功'
            );
        } catch (\Exception $e) {
            Log::error('获取通知列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_notification_list_failed');
        }
    }

    public function detail()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('通知ID不能为空', 400, 'notification_id_required');
            }

            $result = $this->service->getNotificationDetail($id);
            if (!$result) {
                return $this->error('通知不存在', 404, 'notification_not_found');
            }

            return $this->success($result, '获取通知详情成功');
        } catch (\Exception $e) {
            Log::error('获取通知详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_notification_detail_failed');
        }
    }

    public function markRead()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $id = (int)$this->request->post('id', 0);
            if ($id <= 0) {
                return $this->error('通知ID不能为空', 400, 'notification_id_required');
            }

            $result = $this->service->markAsRead($id, $merchantId);
            if (!$result) {
                return $this->error('通知不存在', 404, 'notification_not_found');
            }

            return $this->success([], '标记已读成功');
        } catch (\Exception $e) {
            Log::error('标记通知已读失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'mark_notification_read_failed');
        }
    }

    public function markAllRead()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $count = $this->service->markAllAsRead($merchantId);
            return $this->success(['count' => $count], '全部标记已读成功');
        } catch (\Exception $e) {
            Log::error('全部标记已读失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'mark_all_read_failed');
        }
    }

    public function unreadCount()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $count = $this->service->getUnreadCount($merchantId);
            return $this->success(['count' => $count], '获取未读数量成功');
        } catch (\Exception $e) {
            Log::error('获取未读数量失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_unread_count_failed');
        }
    }

    public function create()
    {
        try {
            $data = $this->request->post();

            $this->validate($data, [
                'title' => 'require|max:200',
            ], [
                'title.require' => '通知标题不能为空',
                'title.max'     => '通知标题不能超过200个字符',
            ]);

            $result = $this->service->createNotification($data);
            return $this->success($result, '创建通知成功');
        } catch (ValidateException $e) {
            return $this->validationError(['notification' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建通知失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_notification_failed');
        }
    }

    private function resolveMerchantId(): ?int
    {
        $merchantId = $this->request->merchant_id ?? null;
        $userId     = $this->request->user_id ?? null;
        $userRole   = $this->request->user_role ?? '';

        if (!$merchantId && $userId) {
            $merchant = MerchantModel::where('user_id', $userId)->find();
            if ($merchant) {
                $merchantId = $merchant->id;
            }
        }

        if (!$merchantId && ($userRole === 'admin' || ($userId === 0))) {
            $merchantId = 1;
        }

        return $merchantId ? (int)$merchantId : null;
    }
}
