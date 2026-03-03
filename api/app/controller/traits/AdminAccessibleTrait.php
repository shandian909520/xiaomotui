<?php
declare(strict_types=1);

namespace app\controller\traits;

/**
 * Admin访问控制Trait
 * 为控制器提供admin访问所有数据的能力
 */
trait AdminAccessibleTrait
{
    /**
     * 检查是否为管理员
     */
    protected function isAdmin(): bool
    {
        return $this->request->role === 'admin';
    }

    /**
     * 获取有效的商家ID（支持admin查看所有或指定商家）
     * @param mixed $request
     * @return int|null
     */
    protected function getEffectiveMerchantId($request): ?int
    {
        // 如果请求中指定了merchant_id参数，使用该值（仅admin可用）
        $requestedMerchantId = $request->get('merchant_id') ?: $request->post('merchant_id') ?: $request->put('merchant_id');
        if ($requestedMerchantId && $this->isAdmin()) {
            return (int)$requestedMerchantId;
        }
        // 否则返回当前用户的merchant_id
        return $this->merchantId ?? null;
    }

    /**
     * 检查商家权限
     * @param int|null $requiredMerchantId
     * @return bool
     */
    protected function checkMerchantAccess(?int $requiredMerchantId = null): bool
    {
        if ($this->isAdmin()) {
            return true; // admin可以访问所有
        }
        if (!$this->merchantId) {
            return false;
        }
        if ($requiredMerchantId && $this->merchantId !== $requiredMerchantId) {
            return false;
        }
        return true;
    }

    /**
     * 检查是否有有效的商家ID
     * @return bool
     */
    protected function hasEffectiveMerchantId(): bool
    {
        return $this->merchantId !== null || $this->isAdmin();
    }
}
