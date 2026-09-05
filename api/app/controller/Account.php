<?php
declare(strict_types=1);

namespace app\controller;

use app\service\AccountService;
use app\service\CardKeyService;
use app\model\Merchant;
use think\facade\Log;

class Account extends BaseController
{
    protected AccountService $accountService;
    protected CardKeyService $cardKeyService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->accountService = new AccountService();
        $this->cardKeyService = new CardKeyService();
    }

    public function changePassword()
    {
        $data = $this->request->post();
        $userId = $this->request->user_id ?? null;

        if (!$userId && $userId !== 0) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        // 管理员账号不支持密码修改
        if ((int)$userId === 0) {
            return $this->error('管理员账号请通过环境配置修改密码', 400, 'admin_not_supported');
        }

        try {
            $this->validate($data, [
                'old_password' => 'require',
                'new_password' => 'require',
            ]);

            $result = $this->accountService->changePassword(
                (int)$userId,
                $data['old_password'],
                $data['new_password']
            );

            return $this->success(null, '密码修改成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'change_password_failed');
        }
    }

    public function activateCard()
    {
        $data = $this->request->post();
        $userId = $this->request->user_id ?? null;

        if (!$userId && $userId !== 0) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        // 管理员无商家关联，不能激活卡密
        if ((int)$userId === 0) {
            return $this->error('管理员账号不支持卡密激活', 400, 'admin_not_supported');
        }

        try {
            $this->validate($data, [
                'card_key' => 'require',
            ]);

            $merchantId = $this->resolveMerchantId((int)$userId);
            if (!$merchantId) {
                return $this->error('未找到关联商家', 404, 'merchant_not_found');
            }

            $result = $this->cardKeyService->activate($merchantId, $data['card_key']);

            return $this->success($result, '卡密激活成功');
        } catch (\Exception $e) {
            Log::error('卡密激活失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage(), 400, 'activate_card_failed');
        }
    }

    public function switchVersion()
    {
        $data = $this->request->post();
        $userId = $this->request->user_id ?? null;

        if (!$userId && $userId !== 0) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        // 管理员无商家关联，不能切换版本
        if ((int)$userId === 0) {
            return $this->error('管理员账号不支持版本切换', 400, 'admin_not_supported');
        }

        try {
            $this->validate($data, [
                'target_version' => 'require|in:basic,standard,chain',
            ]);

            $merchantId = $this->resolveMerchantId((int)$userId);
            if (!$merchantId) {
                return $this->error('未找到关联商家，请先创建商家', 404, 'merchant_not_found');
            }

            $result = $this->accountService->switchVersion($merchantId, $data['target_version']);

            return $this->success($result, '版本切换成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'switch_version_failed');
        }
    }

    public function benefits()
    {
        $userId = $this->request->user_id ?? null;

        if (!$userId && $userId !== 0) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        // 管理员无商家关联，返回管理员权益信息
        if ((int)$userId === 0) {
            return $this->success([
                'version_type' => 'chain',
                'version_text' => '连锁版(管理员)',
                'store_quota' => 999,
                'store_used' => 0,
                'clip_power' => 999,
                'storage' => 107374182400,
                'storage_text' => '100.00 GB',
                'redpacket_balance' => 9999.00,
                'expire_time' => null,
            ], '获取权益信息成功');
        }

        try {
            $merchantId = $this->resolveMerchantId((int)$userId);
            if (!$merchantId) {
                return $this->error('未找到关联商家', 404, 'merchant_not_found');
            }

            $result = $this->accountService->getBenefits($merchantId);

            return $this->success($result, '获取权益信息成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_benefits_failed');
        }
    }

    private function resolveMerchantId(int $userId): ?int
    {
        $merchantId = $this->request->merchant_id ?? null;
        if ($merchantId) {
            return (int)$merchantId;
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        return $merchant ? $merchant->id : null;
    }
}
