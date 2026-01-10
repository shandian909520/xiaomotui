<?php

namespace app\service;

use app\model\PlatformAccount;
use app\model\Merchant;
use app\common\utils\OAuthHelper;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;

/**
 * 平台OAuth服务
 *
 * 统一管理各平台的OAuth授权流程
 */
class PlatformOAuthService
{
    /**
     * 获取授权URL
     *
     * @param int $merchantId 商户ID
     * @param string $platform 平台标识
     * @param array $params 额外参数
     * @return array
     * @throws \Exception
     */
    public function getAuthUrl(int $merchantId, string $platform, array $params = []): array
    {
        // 验证商户
        $merchant = Merchant::find($merchantId);
        if (!$merchant) {
            throw new \Exception('商户不存在');
        }

        // 验证平台配置
        $config = config("platform_oauth.{$platform}");
        if (!$config) {
            throw new \Exception('不支持的平台');
        }

        if (!$config['enabled']) {
            throw new \Exception("平台 {$platform} 暂未开放");
        }

        // 在state中包含merchant_id,回调时使用
        $params['merchant_id'] = $merchantId;

        // 生成授权URL
        $authUrl = OAuthHelper::generateAuthUrl($platform, $params);

        return [
            'platform' => $platform,
            'platform_name' => $config['name'],
            'auth_url' => $authUrl,
            'tips' => $this->getAuthTips($platform)
        ];
    }

    /**
     * 处理授权回调
     *
     * @param string $platform 平台标识
     * @param string $code 授权码
     * @param string $state state参数
     * @return array
     * @throws \Exception
     */
    public function handleCallback(string $platform, string $code, string $state): array
    {
        Db::startTrans();

        try {
            // 从state中解析merchant_id
            // 注: 实际项目中应该在OAuthHelper的state缓存中存储merchant_id
            // 这里简化处理,假设可以从请求中获取
            $merchantId = request()->param('merchant_id', 0);

            if (!$merchantId) {
                throw new \Exception('缺少商户ID');
            }

            // 获取access_token
            $tokenData = OAuthHelper::handleCallback($platform, $code, $state);

            if (empty($tokenData['access_token'])) {
                throw new \Exception('获取access_token失败');
            }

            // 获取用户信息
            $userInfo = OAuthHelper::getUserInfo(
                $platform,
                $tokenData['access_token'],
                $tokenData['open_id']
            );

            // 计算过期时间
            $expiresAt = time() + $tokenData['expires_in'];

            // 检查是否已存在该平台账号
            $account = PlatformAccount::where('merchant_id', $merchantId)
                ->where('platform', $platform)
                ->where('open_id', $userInfo['open_id'])
                ->find();

            if ($account) {
                // 更新已有账号
                $account->access_token = $tokenData['access_token'];
                $account->refresh_token = $tokenData['refresh_token'] ?? '';
                $account->expires_at = $expiresAt;
                $account->nickname = $userInfo['nickname'];
                $account->avatar = $userInfo['avatar'];
                $account->status = 'ACTIVE';
                $account->last_auth_time = date('Y-m-d H:i:s');
                $account->save();

                Log::info("更新平台账号授权", [
                    'account_id' => $account->id,
                    'platform' => $platform,
                    'merchant_id' => $merchantId
                ]);
            } else {
                // 创建新账号
                $account = new PlatformAccount();
                $account->merchant_id = $merchantId;
                $account->platform = $platform;
                $account->open_id = $userInfo['open_id'];
                $account->access_token = $tokenData['access_token'];
                $account->refresh_token = $tokenData['refresh_token'] ?? '';
                $account->expires_at = $expiresAt;
                $account->account_name = $userInfo['nickname'];
                $account->nickname = $userInfo['nickname'];
                $account->avatar = $userInfo['avatar'];
                $account->status = 'ACTIVE';
                $account->last_auth_time = date('Y-m-d H:i:s');
                $account->save();

                Log::info("创建平台账号授权", [
                    'account_id' => $account->id,
                    'platform' => $platform,
                    'merchant_id' => $merchantId
                ]);
            }

            Db::commit();

            return [
                'account_id' => $account->id,
                'platform' => $platform,
                'platform_name' => config("platform_oauth.{$platform}.name"),
                'nickname' => $userInfo['nickname'],
                'avatar' => $userInfo['avatar'],
                'expires_at' => $expiresAt
            ];
        } catch (\Exception $e) {
            Db::rollback();

            Log::error("OAuth授权回调处理失败", [
                'platform' => $platform,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 刷新access_token
     *
     * @param int $accountId 平台账号ID
     * @return array
     * @throws \Exception
     */
    public function refreshToken(int $accountId): array
    {
        $account = PlatformAccount::find($accountId);
        if (!$account) {
            throw new \Exception('平台账号不存在');
        }

        // 检查是否支持token刷新
        if (empty($account->refresh_token)) {
            throw new \Exception('该平台不支持token刷新,请重新授权');
        }

        try {
            // 调用OAuth刷新
            $tokenData = OAuthHelper::refreshToken(
                $account->platform,
                $account->refresh_token
            );

            // 更新账号信息
            $expiresAt = time() + $tokenData['expires_in'];
            $account->access_token = $tokenData['access_token'];

            // 某些平台刷新后会返回新的refresh_token
            if (!empty($tokenData['refresh_token'])) {
                $account->refresh_token = $tokenData['refresh_token'];
            }

            $account->expires_at = $expiresAt;
            $account->status = 'ACTIVE';
            $account->save();

            Log::info("刷新平台账号token成功", [
                'account_id' => $account->id,
                'platform' => $account->platform
            ]);

            // 清除缓存
            $this->clearAccountCache($accountId);

            return [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'expires_at' => $expiresAt,
                'message' => 'Token刷新成功'
            ];
        } catch (\Exception $e) {
            // 刷新失败,标记账号为过期
            $account->status = 'EXPIRED';
            $account->save();

            Log::error("刷新平台账号token失败", [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Token刷新失败: ' . $e->getMessage());
        }
    }

    /**
     * 撤销授权(删除平台账号)
     *
     * @param int $accountId 平台账号ID
     * @param int $merchantId 商户ID (验证权限)
     * @return bool
     * @throws \Exception
     */
    public function revokeAuth(int $accountId, int $merchantId): bool
    {
        $account = PlatformAccount::where('id', $accountId)
            ->where('merchant_id', $merchantId)
            ->find();

        if (!$account) {
            throw new \Exception('平台账号不存在或无权操作');
        }

        try {
            // 标记为已撤销
            $account->status = 'REVOKED';
            $account->save();

            // 清除缓存
            $this->clearAccountCache($accountId);

            Log::info("撤销平台账号授权", [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'merchant_id' => $merchantId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("撤销平台账号授权失败", [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 获取商户的平台账号列表
     *
     * @param int $merchantId 商户ID
     * @param string $platform 平台筛选 (可选)
     * @return array
     */
    public function getAccounts(int $merchantId, string $platform = ''): array
    {
        $query = PlatformAccount::where('merchant_id', $merchantId)
            ->whereIn('status', ['ACTIVE', 'EXPIRED']);

        if ($platform) {
            $query->where('platform', $platform);
        }

        $accounts = $query->order('created_at', 'desc')
            ->select()
            ->toArray();

        // 添加平台名称和状态描述
        foreach ($accounts as &$account) {
            $config = config("platform_oauth.{$account['platform']}");
            $account['platform_name'] = $config['name'] ?? $account['platform'];

            // 检查是否即将过期
            if ($account['status'] === 'ACTIVE') {
                $isExpiring = OAuthHelper::isTokenExpiringSoon($account['expires_at']);
                $account['is_expiring_soon'] = $isExpiring;

                if ($isExpiring) {
                    $account['expiring_message'] = 'Token即将过期,建议刷新';
                }
            }

            // 格式化过期时间
            $account['expires_at_formatted'] = date('Y-m-d H:i:s', $account['expires_at']);
        }

        return $accounts;
    }

    /**
     * 自动刷新即将过期的token
     *
     * @param int $merchantId 商户ID (可选,不传则刷新所有)
     * @return array 刷新结果统计
     */
    public function autoRefreshTokens(int $merchantId = 0): array
    {
        $globalConfig = config('platform_oauth.global');

        if (!($globalConfig['auto_refresh_token'] ?? true)) {
            return ['message' => '自动刷新功能未启用'];
        }

        $query = PlatformAccount::where('status', 'ACTIVE')
            ->whereNotNull('refresh_token')
            ->where('refresh_token', '<>', '');

        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }

        $accounts = $query->select();

        $stats = [
            'total' => count($accounts),
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        foreach ($accounts as $account) {
            // 检查是否即将过期
            if (!OAuthHelper::isTokenExpiringSoon($account->expires_at)) {
                $stats['skipped']++;
                continue;
            }

            try {
                $this->refreshToken($account->id);
                $stats['success']++;

                Log::info("自动刷新token成功", [
                    'account_id' => $account->id,
                    'platform' => $account->platform
                ]);
            } catch (\Exception $e) {
                $stats['failed']++;

                Log::warning("自动刷新token失败", [
                    'account_id' => $account->id,
                    'platform' => $account->platform,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    /**
     * 获取授权提示信息
     *
     * @param string $platform 平台标识
     * @return string
     */
    private function getAuthTips(string $platform): string
    {
        $tips = [
            'douyin' => '授权后可发布视频到抖音,获取视频数据等。授权有效期30天。',
            'xiaohongshu' => '授权后可发布笔记到小红书,获取笔记数据等。授权有效期30天。',
            'kuaishou' => '授权后可发布视频到快手,获取视频数据等。授权有效期30天。',
            'weibo' => '授权后可发布微博,获取微博数据等。授权有效期30天。注意:微博不支持refresh_token。',
            'bilibili' => '授权后可发布视频到B站,获取视频数据等。授权有效期60天。'
        ];

        return $tips[$platform] ?? '授权后可使用该平台功能';
    }

    /**
     * 清除账号缓存
     *
     * @param int $accountId 账号ID
     * @return void
     */
    private function clearAccountCache(int $accountId): void
    {
        $cacheKeys = [
            "platform_account:{$accountId}",
            "platform_account_token:{$accountId}"
        ];

        foreach ($cacheKeys as $key) {
            Cache::delete($key);
        }
    }

    /**
     * 获取平台账号 (带缓存)
     *
     * @param int $accountId 账号ID
     * @return PlatformAccount|null
     */
    public function getAccount(int $accountId): ?PlatformAccount
    {
        $cacheKey = "platform_account:{$accountId}";

        return Cache::remember($cacheKey, function () use ($accountId) {
            return PlatformAccount::find($accountId);
        }, 300); // 缓存5分钟
    }

    /**
     * 验证token有效性
     *
     * @param int $accountId 账号ID
     * @return bool
     */
    public function validateToken(int $accountId): bool
    {
        $account = $this->getAccount($accountId);

        if (!$account) {
            return false;
        }

        if ($account->status !== 'ACTIVE') {
            return false;
        }

        // 检查是否过期
        if ($account->expires_at <= time()) {
            // 尝试自动刷新
            try {
                $this->refreshToken($accountId);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }
}
