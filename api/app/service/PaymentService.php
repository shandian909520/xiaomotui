<?php
declare(strict_types=1);

namespace app\service;

use app\model\Order;
use app\model\Merchant;
use app\model\MerchantBenefit;
use think\facade\Db;
use think\facade\Log;
use think\facade\Config;

class PaymentService
{
    protected array $wechatConfig;
    protected array $packages;

    public function __construct()
    {
        $this->wechatConfig = Config::get('payment.wechat', []);
        $this->packages = Config::get('payment.packages', []);
    }

    /**
     * 创建订单
     */
    public function createOrder(int $userId, int $merchantId, string $packageType, int $duration, string $payMethod = 'wechat'): Order
    {
        if (!isset($this->packages[$packageType])) {
            throw new \Exception('无效的套餐类型');
        }

        if (!isset($this->packages[$packageType]['prices'][$duration])) {
            throw new \Exception('无效的订阅时长');
        }

        if (!in_array($payMethod, [Order::PAY_METHOD_WECHAT, Order::PAY_METHOD_ALIPAY])) {
            throw new \Exception('无效的支付方式');
        }

        $amount = $this->packages[$packageType]['prices'][$duration];

        Db::startTrans();
        try {
            // 关闭该商家同类型待支付订单
            Order::where('merchant_id', $merchantId)
                ->where('status', Order::STATUS_PENDING)
                ->update(['status' => Order::STATUS_CLOSED]);

            $order = new Order();
            $order->order_no = Order::generateOrderNo();
            $order->user_id = $userId;
            $order->merchant_id = $merchantId;
            $order->amount = $amount;
            $order->pay_method = $payMethod;
            $order->status = Order::STATUS_PENDING;
            $order->package_type = $packageType;
            $order->duration = $duration;
            $order->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        Log::info('订单创建成功', [
            'order_no' => $order->order_no,
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'package_type' => $packageType,
        ]);

        return $order;
    }

    /**
     * 发起微信支付
     */
    public function wechatPay(Order $order, string $openid = '', string $tradeType = 'JSAPI'): array
    {
        if (!$order->isPending()) {
            throw new \Exception('订单状态不允许支付');
        }

        $config = $this->wechatConfig;
        if (empty($config['app_id']) || empty($config['mch_id']) || empty($config['api_key'])) {
            throw new \Exception('微信支付配置不完整，请在后台配置支付参数');
        }

        $params = [
            'appid'            => $config['app_id'],
            'mch_id'           => $config['mch_id'],
            'nonce_str'        => $this->getNonceStr(),
            'body'             => '小魔推-' . ($this->packages[$order->package_type]['name'] ?? '服务订阅'),
            'out_trade_no'     => $order->order_no,
            'total_fee'        => (int)bcmul((string)$order->amount, '100', 0),
            'spbill_create_ip' => request()->ip(),
            'notify_url'       => $config['notify_url'],
            'trade_type'       => $tradeType,
        ];

        if ($tradeType === 'JSAPI' && $openid) {
            $params['openid'] = $openid;
        }

        if ($tradeType === 'H5') {
            $params['scene_info'] = json_encode([
                'h5_info' => [
                    'type' => 'Wap',
                    'wap_url' => request()->domain(),
                    'wap_name' => '小魔推支付',
                ],
            ]);
        }

        $params['sign'] = $this->makeSign($params, $config['api_key']);

        $xml = $this->arrayToXml($params);

        $response = $this->postXmlCurl('https://api.mch.weixin.qq.com/pay/unifiedorder', $xml);
        $result = $this->xmlToArray($response);

        Log::info('微信统一下单响应', ['order_no' => $order->order_no, 'result' => $result]);

        if (!isset($result['return_code']) || $result['return_code'] !== 'SUCCESS') {
            throw new \Exception('微信支付通信失败: ' . ($result['return_msg'] ?? '未知错误'));
        }

        if (!isset($result['result_code']) || $result['result_code'] !== 'SUCCESS') {
            throw new \Exception('微信支付业务失败: ' . ($result['err_code_des'] ?? $result['err_code'] ?? '未知错误'));
        }

        $payData = [
            'prepay_id' => $result['prepay_id'],
            'trade_type' => $result['trade_type'],
        ];

        if ($tradeType === 'JSAPI') {
            $payData['jsapi_params'] = $this->buildJsapiParams($result['prepay_id'], $config);
        }

        if ($tradeType === 'H5') {
            $payData['h5_url'] = $result['mweb_url'] ?? '';
        }

        if ($tradeType === 'MWEB') {
            $payData['mweb_url'] = $result['mweb_url'] ?? '';
        }

        return $payData;
    }

    /**
     * 处理微信支付回调
     */
    public function handleWechatNotify(string $xmlData): array
    {
        $data = $this->xmlToArray($xmlData);

        Log::info('收到微信支付回调', ['data' => $data]);

        if (!isset($data['return_code']) || $data['return_code'] !== 'SUCCESS') {
            return ['success' => false, 'message' => '通信失败'];
        }

        $config = $this->wechatConfig;

        // 验证签名
        if (!$this->verifySign($data, $config['api_key'])) {
            Log::warning('微信支付回调签名验证失败', ['data' => $data]);
            return ['success' => false, 'message' => '签名验证失败'];
        }

        if ($data['result_code'] !== 'SUCCESS') {
            return ['success' => false, 'message' => '支付失败'];
        }

        $orderNo = $data['out_trade_no'];
        $transactionId = $data['transaction_id'];

        Db::startTrans();
        try {
            $order = Order::where('order_no', $orderNo)->lock(true)->find();
            if (!$order) {
                Db::rollback();
                return ['success' => false, 'message' => '订单不存在'];
            }

            if ($order->isPaid()) {
                Db::commit();
                return ['success' => true, 'message' => '已处理'];
            }

            if (!$order->isPending()) {
                Db::rollback();
                return ['success' => false, 'message' => '订单状态异常'];
            }

            // 验证金额
            $totalFee = bcdiv((string)$data['total_fee'], '100', 2);
            if (bccomp($totalFee, (string)$order->amount, 2) !== 0) {
                Log::warning('微信支付回调金额不一致', [
                    'order_no' => $orderNo,
                    'order_amount' => $order->amount,
                    'paid_amount' => $totalFee,
                ]);
                Db::rollback();
                return ['success' => false, 'message' => '金额不一致'];
            }

            $order->markAsPaid($transactionId);

            // 升级商家权益
            $this->applyOrderBenefit($order);

            Db::commit();

            Log::info('订单支付成功', [
                'order_no' => $orderNo,
                'transaction_id' => $transactionId,
            ]);

            return ['success' => true, 'message' => '处理成功'];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('处理微信支付回调异常', [
                'order_no' => $orderNo ?? '',
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 查询订单
     */
    public function queryOrder(string $orderNo): array
    {
        $order = Order::where('order_no', $orderNo)->find();
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        // 如果待支付，主动查询微信
        if ($order->isPending()) {
            $this->syncWechatOrderStatus($order);
            $order->refresh();
        }

        return $order->toArray();
    }

    /**
     * 获取订单列表
     */
    public function getOrderList(int $userId, int $page = 1, int $pageSize = 20): array
    {
        $list = Order::where('user_id', $userId)
            ->order('create_time', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $pageSize,
            ]);

        return [
            'list' => $list->items(),
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'page_size' => $pageSize,
        ];
    }

    /**
     * 退款
     */
    public function refund(string $orderNo, ?float $refundAmount = null): array
    {
        $order = Order::where('order_no', $orderNo)->find();
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        if (!$order->isPaid()) {
            throw new \Exception('只有已支付订单可以退款');
        }

        $config = $this->wechatConfig;
        $refundAmount = $refundAmount ?? $order->amount;
        if ($refundAmount > $order->amount || $refundAmount <= 0) {
            throw new \Exception('退款金额无效');
        }
        $refundNo = 'RF' . date('YmdHis') . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        $params = [
            'appid'          => $config['app_id'],
            'mch_id'         => $config['mch_id'],
            'nonce_str'      => $this->getNonceStr(),
            'out_trade_no'   => $order->order_no,
            'out_refund_no'  => $refundNo,
            'total_fee'      => (int)bcmul((string)$order->amount, '100', 0),
            'refund_fee'     => (int)bcmul((string)$refundAmount, '100', 0),
        ];

        $params['sign'] = $this->makeSign($params, $config['api_key']);

        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl('https://api.mch.weixin.qq.com/secapi/pay/refund', $xml, true);
        $result = $this->xmlToArray($response);

        if (!isset($result['return_code']) || $result['return_code'] !== 'SUCCESS') {
            throw new \Exception('退款通信失败: ' . ($result['return_msg'] ?? '未知错误'));
        }

        if (!isset($result['result_code']) || $result['result_code'] !== 'SUCCESS') {
            throw new \Exception('退款业务失败: ' . ($result['err_code_des'] ?? '未知错误'));
        }

        $order->markAsRefunded();

        Log::info('订单退款成功', [
            'order_no' => $orderNo,
            'refund_no' => $refundNo,
            'refund_amount' => $refundAmount,
        ]);

        return [
            'refund_no' => $refundNo,
            'refund_amount' => $refundAmount,
            'refund_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 关闭超时订单
     */
    public function closeExpiredOrders(): int
    {
        $timeout = Config::get('payment.order_timeout', 1800);
        $expiredTime = date('Y-m-d H:i:s', time() - $timeout);

        $count = Order::where('status', Order::STATUS_PENDING)
            ->where('create_time', '<', $expiredTime)
            ->update(['status' => Order::STATUS_CLOSED]);

        return $count;
    }

    /**
     * 获取套餐列表
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * 应用订单权益
     */
    protected function applyOrderBenefit(Order $order): void
    {
        $benefit = MerchantBenefit::where('merchant_id', $order->merchant_id)->find();
        if (!$benefit) {
            $benefit = MerchantBenefit::createForMerchant($order->merchant_id, $order->package_type);
        }

        // 升级版本
        $benefit->version_type = $order->package_type;
        $benefit->store_quota = MerchantBenefit::getVersionStoreQuota($order->package_type);

        // 延长到期时间
        $currentExpire = $benefit->expire_time ? strtotime($benefit->expire_time) : time();
        if ($currentExpire < time()) {
            $currentExpire = time();
        }
        $benefit->expire_time = date('Y-m-d H:i:s', strtotime("+{$order->duration} months", $currentExpire));

        $benefit->save();

        Log::info('订单权益已应用', [
            'order_no' => $order->order_no,
            'merchant_id' => $order->merchant_id,
            'package_type' => $order->package_type,
            'duration' => $order->duration,
        ]);
    }

    /**
     * 同步微信订单状态
     */
    protected function syncWechatOrderStatus(Order $order): void
    {
        if ($order->pay_method !== Order::PAY_METHOD_WECHAT) {
            return;
        }

        $config = $this->wechatConfig;
        $params = [
            'appid'        => $config['app_id'],
            'mch_id'       => $config['mch_id'],
            'out_trade_no' => $order->order_no,
            'nonce_str'    => $this->getNonceStr(),
        ];
        $params['sign'] = $this->makeSign($params, $config['api_key']);

        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl('https://api.mch.weixin.qq.com/pay/orderquery', $xml);
        $result = $this->xmlToArray($response);

        if (isset($result['trade_state']) && $result['trade_state'] === 'SUCCESS') {
            Db::startTrans();
            try {
                $freshOrder = Order::where('order_no', $order->order_no)->lock(true)->find();
                if (!$freshOrder || $freshOrder->isPaid()) {
                    Db::commit();
                    return;
                }
                $freshOrder->markAsPaid($result['transaction_id']);
                $this->applyOrderBenefit($freshOrder);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                Log::error('同步微信订单状态异常', ['order_no' => $order->order_no, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 构建JSAPI支付参数
     */
    protected function buildJsapiParams(string $prepayId, array $config): array
    {
        $params = [
            'appId'     => $config['app_id'],
            'timeStamp' => (string)time(),
            'nonceStr'  => $this->getNonceStr(),
            'package'   => 'prepay_id=' . $prepayId,
            'signType'  => 'MD5',
        ];
        $params['paySign'] = $this->makeSign($params, $config['api_key']);
        return $params;
    }

    /**
     * 生成签名
     */
    protected function makeSign(array $params, string $key): string
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $v === '' || $v === null) {
                continue;
            }
            $string .= $k . '=' . $v . '&';
        }
        $string .= 'key=' . $key;
        return strtoupper(md5($string));
    }

    /**
     * 验证签名
     */
    protected function verifySign(array $data, string $key): bool
    {
        if (!isset($data['sign'])) {
            return false;
        }
        $sign = $data['sign'];
        $calculated = $this->makeSign($data, $key);
        return hash_equals($calculated, $sign);
    }

    /**
     * 生成随机字符串
     */
    protected function getNonceStr(int $length = 32): string
    {
        return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
    }

    /**
     * 数组转XML
     */
    protected function arrayToXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $k => $v) {
            if (is_numeric($v)) {
                $xml .= '<' . $k . '>' . $v . '</' . $k . '>';
            } else {
                $xml .= '<' . $k . '><![CDATA[' . $v . ']]></' . $k . '>';
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    /**
     * XML转数组
     */
    protected function xmlToArray(string $xml): array
    {
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data ?: [];
    }

    /**
     * POST XML请求
     */
    protected function postXmlCurl(string $url, string $xml, bool $useCert = false): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $caPath = $this->wechatConfig['ca_path'] ?? '';
        if ($caPath && file_exists($caPath)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caPath);
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

        if ($useCert) {
            $config = $this->wechatConfig;
            if (!empty($config['cert_path']) && !empty($config['key_path'])) {
                curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                curl_setopt($ch, CURLOPT_SSLCERT, $config['cert_path']);
                curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                curl_setopt($ch, CURLOPT_SSLKEY, $config['key_path']);
            }
        }

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            Log::error('微信支付CURL请求失败', ['url' => $url, 'errno' => $errno, 'error' => $error]);
            throw new \Exception('网络请求失败: ' . $error);
        }

        return $response;
    }
}
