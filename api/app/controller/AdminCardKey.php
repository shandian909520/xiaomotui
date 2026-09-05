<?php
declare(strict_types=1);

namespace app\controller;

use app\service\CardKeyService;
use app\model\CardKey;
use think\facade\Log;

class AdminCardKey extends BaseController
{
    protected CardKeyService $cardKeyService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->cardKeyService = new CardKeyService();
    }

    /**
     * 生成卡密
     * POST /api/admin/cardkey/generate
     */
    public function generate()
    {
        $data = $this->request->post();
        $userId = $this->request->user_id ?? null;

        if (!$userId && $userId !== 0) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $this->validate($data, [
                'type' => 'require',
            ]);

            $benefitPayload = $data['benefit_payload'] ?? [];
            $expireAt = $data['expire_at'] ?? null;

            $card = $this->cardKeyService->generateCardKey(
                $data['type'],
                $benefitPayload,
                (int)$userId,
                $expireAt
            );

            return $this->success([
                'card_key' => $card->card_key,
                'type' => $card->type,
                'expire_at' => $card->expire_at,
            ], '卡密生成成功');
        } catch (\Exception $e) {
            Log::error('卡密生成失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'generate_cardkey_failed');
        }
    }

    /**
     * 卡密列表
     * GET /api/admin/cardkey/list
     */
    public function lists()
    {
        $page = (int)$this->request->param('page', 1);
        $pageSize = (int)$this->request->param('page_size', 20);
        $status = $this->request->param('status');

        $query = CardKey::order('create_time', 'desc');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        return $this->paginate($list->items(), $list->total(), $list->currentPage(), $pageSize);
    }
}
