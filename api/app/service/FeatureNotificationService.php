<?php
declare(strict_types=1);

namespace app\service;

use app\model\Notification as NotificationModel;

class FeatureNotificationService
{
    public function getNotificationList(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        $query = NotificationModel::where(function ($q) use ($merchantId) {
            $q->whereNull('merchant_id')->whereOr('merchant_id', $merchantId);
        });

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $query->where('is_read', (int)$filters['is_read']);
        }

        $total = $query->count();
        $list  = $query->page($page, $limit)
            ->order('create_time', 'desc')
            ->select()
            ->toArray();

        return compact('list', 'total', 'page', 'limit');
    }

    public function getNotificationDetail(int $id): ?array
    {
        $notification = NotificationModel::find($id);
        if (!$notification) {
            return null;
        }
        return $notification->toArray();
    }

    public function markAsRead(int $id, int $merchantId): bool
    {
        $notification = NotificationModel::find($id);
        if (!$notification) {
            return false;
        }

        if ($notification->is_read) {
            return true;
        }

        $notification->is_read = 1;
        $notification->save();
        return true;
    }

    public function markAllAsRead(int $merchantId): int
    {
        return NotificationModel::where(function ($q) use ($merchantId) {
            $q->whereNull('merchant_id')->whereOr('merchant_id', $merchantId);
        })
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    public function getUnreadCount(int $merchantId): int
    {
        return NotificationModel::where(function ($q) use ($merchantId) {
            $q->whereNull('merchant_id')->whereOr('merchant_id', $merchantId);
        })
            ->where('is_read', 0)
            ->count();
    }

    public function createNotification(array $data): array
    {
        $notification = new NotificationModel();
        $notification->merchant_id  = $data['merchant_id'] ?? null;
        $notification->title        = $data['title'];
        $notification->content      = $data['content'] ?? null;
        $notification->type         = $data['type'] ?? NotificationModel::TYPE_FEATURE_UPDATE;
        $notification->extra_data   = $data['extra_data'] ?? null;
        $notification->publish_time = $data['publish_time'] ?? date('Y-m-d H:i:s');
        $notification->is_read      = 0;
        $notification->save();

        return $notification->toArray();
    }
}
