<?php
declare(strict_types=1);

namespace app\service;

use app\model\Merchant;
use app\model\MerchantBenefit;
use app\model\NfcDevice;
use app\model\Order;
use app\model\User;
use think\facade\Db;
use think\facade\Log;

class AccountService
{
    public function changePassword(int $userId, string $oldPassword, string $newPassword): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('用户不存在');
        }

        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,16}$/', $newPassword)) {
            throw new \Exception('新密码格式不正确，需6-16位字母+数字');
        }

        if ($oldPassword === $newPassword) {
            throw new \Exception('新密码不能与原密码相同');
        }

        // 用户表暂无password字段时先检查是否存在
        if (property_exists($user, 'password') && $user->password) {
            if (!password_verify($oldPassword, $user->password)) {
                throw new \Exception('原密码错误');
            }
        }

        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->save();

        Log::info('用户修改密码成功', ['user_id' => $userId]);
        return true;
    }

    public function switchVersion(int $merchantId, string $targetVersion): array
    {
        $validVersions = [
            MerchantBenefit::VERSION_BASIC,
            MerchantBenefit::VERSION_STANDARD,
            MerchantBenefit::VERSION_CHAIN,
        ];

        if (!in_array($targetVersion, $validVersions)) {
            throw new \Exception('无效的版本类型');
        }

        $benefit = MerchantBenefit::where('merchant_id', $merchantId)->find();
        if (!$benefit) {
            throw new \Exception('商家权益记录不存在');
        }

        // 降级或同级切换不检查付费
        $versionOrder = [MerchantBenefit::VERSION_BASIC => 1, MerchantBenefit::VERSION_STANDARD => 2, MerchantBenefit::VERSION_CHAIN => 3];
        $currentLevel = $versionOrder[$benefit->version_type] ?? 0;
        $targetLevel = $versionOrder[$targetVersion] ?? 0;

        if ($targetLevel > $currentLevel) {
            // 升级需检查是否有有效付费订单或有效卡密激活记录
            $hasPaid = Order::hasValidPaidOrder($merchantId, $targetVersion);
            if (!$hasPaid) {
                throw new \Exception('请先购买' . MerchantBenefit::getVersionTextMap()[$targetVersion] . '套餐或使用卡密激活');
            }
        }

        $targetQuota = MerchantBenefit::getVersionStoreQuota($targetVersion);
        $storeCount = NfcDevice::where('merchant_id', $merchantId)->count();

        if ($storeCount > $targetQuota) {
            throw new \Exception("当前门店数量({$storeCount})超过目标版本额度({$targetQuota})，请先减少门店");
        }

        Db::startTrans();
        try {
            $oldVersion = $benefit->version_type;
            $benefit->version_type = $targetVersion;
            $benefit->store_quota = $targetQuota;
            $benefit->save();

            Db::commit();

            Log::info('版本切换成功', [
                'merchant_id' => $merchantId,
                'from' => $oldVersion,
                'to' => $targetVersion,
            ]);

            return [
                'old_version' => $oldVersion,
                'new_version' => $targetVersion,
                'store_quota' => $targetQuota,
                'store_used' => $storeCount,
            ];
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function getBenefits(int $merchantId): array
    {
        $benefit = MerchantBenefit::where('merchant_id', $merchantId)->find();

        if (!$benefit) {
            $benefit = MerchantBenefit::createForMerchant($merchantId);
        }

        return [
            'version_type' => $benefit->version_type,
            'version_text' => MerchantBenefit::getVersionTextMap()[$benefit->version_type] ?? '未知',
            'store_quota' => $benefit->store_quota,
            'store_used' => $benefit->store_used,
            'clip_power' => $benefit->clip_power,
            'storage' => $benefit->storage,
            'storage_text' => $this->formatBytes($benefit->storage),
            'redpacket_balance' => $benefit->redpacket_balance,
            'expire_time' => $benefit->expire_time,
        ];
    }

    public static function createBenefitForMerchant(int $merchantId, string $version = MerchantBenefit::VERSION_BASIC): MerchantBenefit
    {
        return MerchantBenefit::createForMerchant($merchantId, $version);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
