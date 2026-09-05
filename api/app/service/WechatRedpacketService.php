<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

/**
 * 微信现金红包服务
 * 集成微信支付V2红包接口
 */
class WechatRedpacketService
{
    protected array $config;

    public function __construct()
    {
        $this->config = Config::get('redpacket.wechat', []);
    }

    /**
     * 发送普通红包
     *
     * @param string $openid 用户openid
     * @param int $amount 金额（分）
     * @param int $merchantId 商家ID
     * @param int $activityId 活动ID
     * @param string $wishing 祝福语
     * @param string $clientIp 客户端IP
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function sendRedpacket(
        string $openid,
        int $amount,
        int $merchantId,
        int $activityId = 0,
        string $wishing = '恭喜发财，大吉大利',
        string $clientIp = ''
    ): array {
        $limits = Config::get('redpacket.limits', []);
        $minAmount = (int)round(($limits['min_amount'] ?? 0.01) * 100);
        $maxAmount = (int)round(($limits['max_amount'] ?? 200) * 100);

        if ($amount < $minAmount || $amount > $maxAmount) {
            return ['success' => false, 'message' => '红包金额超出限制'];
        }

        if (empty($openid)) {
            return ['success' => false, 'message' => '用户openid不能为空'];
        }

        $mchBillNo = $this->generateMchBillNo();

        $params = [
            'nonce_str'      => $this->getNonceStr(),
            'mch_billno'     => $mchBillNo,
            'mch_id'         => $this->config['mch_id'],
            'wxappid'        => $this->config['app_id'],
            'send_name'      => '小魔推',
            're_openid'      => $openid,
            'total_amount'   => $amount,
            'total_num'      => 1,
            'wishing'        => $wishing,
            'client_ip'      => $clientIp ?: ($this->getClientIp()),
            'act_name'       => '营销活动红包',
            'remark'         => 'NFC扫码领红包',
        ];

        if (!empty($this->config['scene_id'])) {
            $params['scene_id'] = $this->config['scene_id'];
        }
        if (!empty($this->config['notify_url'])) {
            $params['notify_url'] = $this->config['notify_url'];
        }

        $params['sign'] = $this->makeSign($params);

        $this->logRedpacket('info', '准备发送红包', [
            'mch_billno' => $mchBillNo,
            'openid' => $openid,
            'amount' => $amount,
            'merchant_id' => $merchantId,
            'activity_id' => $activityId,
        ]);

        $result = $this->postXml(
            'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack',
            $this->toXml($params)
        );

        if (!$result['success']) {
            $this->recordSendLog($mchBillNo, $merchantId, $activityId, $openid, $amount, 'FAILED', $result['message']);
            return $result;
        }

        $data = $result['data'];
        if (($data['return_code'] ?? '') === 'SUCCESS' && ($data['result_code'] ?? '') === 'SUCCESS') {
            $this->recordSendLog($mchBillNo, $merchantId, $activityId, $openid, $amount, 'SUCCESS', '', $data);
            $this->updateActivityConsumed($activityId, $amount);
            return [
                'success' => true,
                'message' => '红包发送成功',
                'data' => [
                    'mch_billno'   => $mchBillNo,
                    'send_listid'  => $data['send_listid'] ?? '',
                    'total_amount' => $amount,
                ],
            ];
        }

        $errMsg = $data['err_code_des'] ?? ($data['return_msg'] ?? '发送失败');
        $this->recordSendLog($mchBillNo, $merchantId, $activityId, $openid, $amount, 'FAILED', $errMsg, $data);
        return ['success' => false, 'message' => $errMsg, 'data' => $data];
    }

    /**
     * 发送裂变红包
     *
     * @param string $openid 用户openid
     * @param int $totalAmount 总金额（分）
     * @param int $totalNum 红包个数
     * @param int $merchantId 商家ID
     * @param int $activityId 活动ID
     * @param string $wishing 祝福语
     * @return array
     */
    public function sendGroupRedpacket(
        string $openid,
        int $totalAmount,
        int $totalNum,
        int $merchantId,
        int $activityId = 0,
        string $wishing = '恭喜发财，大吉大利'
    ): array {
        if ($totalNum < 3 || $totalNum > 20) {
            return ['success' => false, 'message' => '裂变红包个数必须在3-20之间'];
        }

        $mchBillNo = $this->generateMchBillNo();

        $params = [
            'nonce_str'      => $this->getNonceStr(),
            'mch_billno'     => $mchBillNo,
            'mch_id'         => $this->config['mch_id'],
            'wxappid'        => $this->config['app_id'],
            'send_name'      => '小魔推',
            're_openid'      => $openid,
            'total_amount'   => $totalAmount,
            'total_num'      => $totalNum,
            'amt_type'       => 'ALL_RAND',
            'wishing'        => $wishing,
            'act_name'       => '营销活动裂变红包',
            'remark'         => 'NFC扫码领红包',
        ];

        if (!empty($this->config['scene_id'])) {
            $params['scene_id'] = $this->config['scene_id'];
        }
        if (!empty($this->config['notify_url'])) {
            $params['notify_url'] = $this->config['notify_url'];
        }

        $params['sign'] = $this->makeSign($params);

        $this->logRedpacket('info', '准备发送裂变红包', [
            'mch_billno' => $mchBillNo,
            'openid' => $openid,
            'total_amount' => $totalAmount,
            'total_num' => $totalNum,
        ]);

        $result = $this->postXml(
            'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendgroupredpack',
            $this->toXml($params)
        );

        if (!$result['success']) {
            $this->recordSendLog($mchBillNo, $merchantId, $activityId, $openid, $totalAmount, 'FAILED', $result['message']);
            return $result;
        }

        $data = $result['data'];
        if (($data['return_code'] ?? '') === 'SUCCESS' && ($data['result_code'] ?? '') === 'SUCCESS') {
            $this->recordSendLog($mchBillNo, $merchantId, $activityId, $openid, $totalAmount, 'SUCCESS', '', $data);
            $this->updateActivityConsumed($activityId, $totalAmount);
            return [
                'success' => true,
                'message' => '裂变红包发送成功',
                'data' => [
                    'mch_billno'   => $mchBillNo,
                    'send_listid'  => $data['send_listid'] ?? '',
                    'total_amount' => $totalAmount,
                ],
            ];
        }

        $errMsg = $data['err_code_des'] ?? ($data['return_msg'] ?? '发送失败');
        $this->recordSendLog($mchBillNo, $merchantId, $activityId, $openid, $totalAmount, 'FAILED', $errMsg, $data);
        return ['success' => false, 'message' => $errMsg, 'data' => $data];
    }

    /**
     * 查询红包状态
     *
     * @param string $mchBillNo 商户订单号
     * @return array
     */
    public function queryRedpacket(string $mchBillNo): array
    {
        $params = [
            'nonce_str'  => $this->getNonceStr(),
            'mch_billno' => $mchBillNo,
            'mch_id'     => $this->config['mch_id'],
            'appid'      => $this->config['app_id'],
            'bill_type'  => 'MCHT',
        ];

        $params['sign'] = $this->makeSign($params);

        $result = $this->postXml(
            'https://api.mch.weixin.qq.com/mmpaymkttransfers/gethbinfo',
            $this->toXml($params)
        );

        if (!$result['success']) {
            return $result;
        }

        $data = $result['data'];
        if (($data['return_code'] ?? '') === 'SUCCESS' && ($data['result_code'] ?? '') === 'SUCCESS') {
            return [
                'success' => true,
                'data' => [
                    'mch_billno'    => $data['mch_billno'] ?? $mchBillNo,
                    'status'        => $data['status'] ?? '',
                    'total_amount'  => $data['total_amount'] ?? 0,
                    'send_time'     => $data['send_time'] ?? '',
                    'rcv_time'      => $data['hbinfo']['rcv_time'] ?? '',
                    'send_listid'   => $data['send_listid'] ?? '',
                ],
            ];
        }

        return ['success' => false, 'message' => $data['err_code_des'] ?? '查询失败', 'data' => $data];
    }

    /**
     * 生成签名
     */
    protected function makeSign(array $params): string
    {
        unset($params['sign']);
        $params = array_filter($params, function ($val) {
            return $val !== '' && $val !== null;
        });
        ksort($params);

        $signStr = '';
        foreach ($params as $key => $val) {
            $signStr .= "{$key}={$val}&";
        }
        $signStr .= 'key=' . $this->config['api_key'];

        return strtoupper(md5($signStr));
    }

    /**
     * 生成商户订单号
     */
    protected function generateMchBillNo(): string
    {
        return $this->config['mch_id'] . date('YmdHis') . str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 生成随机字符串
     */
    protected function getNonceStr(int $length = 32): string
    {
        return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
    }

    /**
     * 获取客户端IP
     */
    protected function getClientIp(): string
    {
        return request()->ip();
    }

    /**
     * 数组转XML
     */
    protected function toXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<{$key}>{$val}</{$key}>";
            } else {
                $xml .= "<{$key}><![CDATA[{$val}]]></{$key}>";
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    /**
     * XML转数组
     */
    protected function fromXml(string $xml): array
    {
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return is_array($data) ? $data : [];
    }

    /**
     * 使用证书发送XML请求
     */
    protected function postXml(string $url, string $xml): array
    {
        $this->logRedpacket('info', '发送红包请求', ['url' => $url]);

        $certPath = $this->config['cert_path'] ?? '';
        $keyPath = $this->config['key_path'] ?? '';

        if (empty($certPath) || empty($keyPath)) {
            return ['success' => false, 'message' => '商户证书未配置', 'data' => []];
        }

        if (!file_exists($certPath) || !file_exists($keyPath)) {
            return ['success' => false, 'message' => '商户证书文件不存在', 'data' => []];
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $xml,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLCERT        => $certPath,
                CURLOPT_SSLKEY         => $keyPath,
                CURLOPT_HTTPHEADER     => ['Content-Type: text/xml; charset=utf-8'],
            ]);

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno) {
                $this->logRedpacket('error', '红包请求CURL错误', ['error' => $error, 'errno' => $errno]);
                return ['success' => false, 'message' => '网络请求失败: ' . $error, 'data' => []];
            }

            if ($httpCode !== 200) {
                return ['success' => false, 'message' => 'HTTP请求失败,状态码: ' . $httpCode, 'data' => []];
            }

            $data = $this->fromXml($response);
            $this->logRedpacket('info', '红包响应', $data);

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            $this->logRedpacket('error', '红包请求异常', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 更新活动已消费金额
     */
    protected function updateActivityConsumed(int $activityId, int $amountFen): void
    {
        if ($activityId <= 0) {
            return;
        }

        $amountYuan = $amountFen / 100;
        Db::name('redpacket_activities')->where('id', $activityId)->inc('consumed_amount', $amountYuan)->update();
    }

    /**
     * 记录红包发送日志
     */
    protected function recordSendLog(
        string $mchBillNo,
        int $merchantId,
        int $activityId,
        string $openid,
        int $amount,
        string $status,
        string $message = '',
        array $responseData = []
    ): void {
        Db::startTrans();
        try {
            Db::name('redpacket_send_logs')->insert([
                'mch_billno'    => $mchBillNo,
                'merchant_id'   => $merchantId,
                'activity_id'   => $activityId,
                'openid'        => $openid,
                'amount'        => $amount,
                'status'        => $status,
                'message'       => $message,
                'response_data' => !empty($responseData) ? json_encode($responseData, JSON_UNESCAPED_UNICODE) : null,
                'create_time'   => date('Y-m-d H:i:s'),
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->logRedpacket('error', '记录红包日志失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 记录日志
     */
    protected function logRedpacket(string $level, string $message, array $context = []): void
    {
        $logConfig = Config::get('redpacket.log', []);
        if (empty($logConfig['enabled'])) {
            return;
        }
        Log::channel($logConfig['channel'] ?? 'file')->$level('[红包] ' . $message, $context);
    }
}
