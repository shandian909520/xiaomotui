<?php
declare(strict_types=1);

namespace app\controller;

use app\model\PlatformAccount;
use app\service\PlatformOAuthService;
use think\facade\Log;

class Platform extends BaseController
{
    /**
     * OAuth服务实例
     */
    protected PlatformOAuthService $oauthService;

    /**
     * 控制器初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->oauthService = new PlatformOAuthService();
    }
    /**
     * 平台账号列表
     * GET /api/platform/account/list
     */
    public function accountList()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $accounts = PlatformAccount::getUserAccounts($userId);
            $platforms = PlatformAccount::getUserPlatforms($userId);
            $stats = PlatformAccount::getAccountStats($userId);

            return $this->success([
                'list' => $accounts,
                'platforms' => $platforms,
                'stats' => $stats,
            ], '获取成功');
        } catch (\Exception $e) {
            Log::error('获取平台账号列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_accounts_failed');
        }
    }

    /**
     * 移除平台账号
     * DELETE /api/platform/account/:id
     */
    public function removeAccount()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        $id = (int)$this->request->param('id', 0);
        if (!$id) {
            return $this->error('账号ID不能为空', 400, 'id_required');
        }

        try {
            $account = PlatformAccount::where('id', $id)
                ->where('user_id', $userId)
                ->find();

            if (!$account) {
                return $this->error('账号不存在或无权操作', 404, 'account_not_found');
            }

            $account->delete();

            return $this->success(null, '账号已移除');
        } catch (\Exception $e) {
            Log::error('移除平台账号失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'remove_account_failed');
        }
    }

    /**
     * 刷新OAuth令牌
     * POST /api/platform/account/:id/refresh
     */
    public function refreshToken()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        $id = (int)$this->request->param('id', 0);
        if (!$id) {
            return $this->error('账号ID不能为空', 400, 'id_required');
        }

        try {
            $account = PlatformAccount::where('id', $id)
                ->where('user_id', $userId)
                ->find();

            if (!$account) {
                return $this->error('账号不存在或无权操作', 404, 'account_not_found');
            }

            if (empty($account->refresh_token)) {
                return $this->error('该账号不支持令牌刷新，请重新授权', 400, 'no_refresh_token');
            }

            // 调用 PlatformOAuthService 的真实刷新实现
            // TODO: merchant_id 应从认证上下文获取而非 request 参数
            $merchantId = (int)($this->request->param('merchant_id', 0));
            $result = $this->oauthService->refreshToken($id, $merchantId);

            Log::info('刷新平台令牌成功', [
                'user_id' => $userId,
                'account_id' => $id,
                'platform' => $result['platform'] ?? ''
            ]);

            return $this->success($result, '令牌刷新成功');
        } catch (\Exception $e) {
            Log::error('刷新平台令牌失败', [
                'error' => $e->getMessage(),
                'account_id' => $id
            ]);
            return $this->error($e->getMessage(), 400, 'refresh_token_failed');
        }
    }
}
