<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Order;
use app\model\Merchant;
use app\service\PaymentService;
use think\facade\Db;
use think\facade\Log;

class Payment extends BaseController
{
    protected PaymentService $paymentService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->paymentService = new PaymentService();
    }

    /**
     * 创建订单
     * POST /api/payment/create-order
     */
    public function createOrder()
    {
        $data = $this->request->post();
        $userId = $this->request->user_id ?? null;

        if ($userId === null) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        if ((int)$userId === 0) {
            return $this->error('管理员账号不支持支付', 400, 'admin_not_supported');
        }

        try {
            $this->validate($data, [
                'package_type' => 'require|in:basic,standard,chain',
                'duration'     => 'require|in:1,3,6,12',
                'pay_method'   => 'in:wechat,alipay',
            ]);

            $merchantId = $this->resolveMerchantId((int)$userId);
            if (!$merchantId) {
                return $this->error('未找到关联商家，请先创建商家', 404, 'merchant_not_found');
            }

            $payMethod = $data['pay_method'] ?? 'wechat';
            $order = $this->paymentService->createOrder(
                (int)$userId,
                $merchantId,
                $data['package_type'],
                (int)$data['duration'],
                $payMethod
            );

            return $this->success([
                'order_no'     => $order->order_no,
                'amount'       => $order->amount,
                'package_type' => $order->package_type,
                'duration'     => $order->duration,
                'pay_method'   => $order->pay_method,
                'status'       => $order->status,
                'create_time'  => $order->create_time,
            ], '订单创建成功');
        } catch (\Exception $e) {
            Log::error('创建订单失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_order_failed');
        }
    }

    /**
     * 发起微信支付
     * POST /api/payment/wechat-pay
     */
    public function wechatPay()
    {
        $data = $this->request->post();
        $userId = $this->request->user_id ?? null;

        if ($userId === null) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $this->validate($data, [
                'order_no' => 'require',
            ]);

            $order = Order::where('order_no', $data['order_no'])
                ->where('user_id', (int)$userId)
                ->find();

            if (!$order) {
                return $this->error('订单不存在', 404, 'order_not_found');
            }

            if ($order->pay_method !== Order::PAY_METHOD_WECHAT) {
                return $this->error('该订单不是微信支付', 400, 'invalid_pay_method');
            }

            $openid = $data['openid'] ?? '';
            $tradeType = $data['trade_type'] ?? 'JSAPI';

            if ($tradeType === 'JSAPI' && empty($openid)) {
                $userBind = Db::name('user_oauth')
                    ->where('user_id', (int)$userId)
                    ->where('platform', 'wechat')
                    ->find();
                if ($userBind) {
                    $openid = $userBind['openid'];
                }
            }

            $payData = $this->paymentService->wechatPay($order, $openid, $tradeType);

            return $this->success($payData, '支付参数获取成功');
        } catch (\Exception $e) {
            Log::error('微信支付发起失败', ['order_no' => $data['order_no'] ?? '', 'error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'wechat_pay_failed');
        }
    }

    /**
     * 微信支付回调（无需认证）
     * POST /api/payment/wechat-notify
     */
    public function wechatNotify()
    {
        $xmlData = file_get_contents('php://input');

        try {
            $result = $this->paymentService->handleWechatNotify($xmlData);

            if ($result['success']) {
                $xml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            } else {
                $xml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[' . $result['message'] . ']]></return_msg></xml>';
            }
        } catch (\Exception $e) {
            Log::error('微信回调处理异常', ['error' => $e->getMessage()]);
            $xml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[' . $e->getMessage() . ']]></return_msg></xml>';
        }

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * 查询订单详情
     * GET /api/payment/order/:id
     */
    public function orderDetail()
    {
        $id = $this->request->param('id');
        $userId = $this->request->user_id ?? null;

        if ($userId === null) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        $order = Order::where('id', $id)
            ->where('user_id', (int)$userId)
            ->find();

        if (!$order) {
            return $this->error('订单不存在', 404, 'order_not_found');
        }

        return $this->success($order->toArray(), '获取成功');
    }

    /**
     * 订单列表
     * GET /api/payment/orders
     */
    public function orders()
    {
        $userId = $this->request->user_id ?? null;

        if ($userId === null) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        $page = (int)$this->request->param('page', 1);
        $pageSize = min(max((int)$this->request->param('page_size', 20), 1), 100);

        $result = $this->paymentService->getOrderList((int)$userId, $page, $pageSize);

        return $this->paginate($result['list'], $result['total'], $result['page'], $result['page_size']);
    }

    /**
     * 获取套餐列表
     * GET /api/payment/packages
     */
    public function packages()
    {
        $packages = $this->paymentService->getPackages();
        return $this->success($packages, '获取成功');
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
