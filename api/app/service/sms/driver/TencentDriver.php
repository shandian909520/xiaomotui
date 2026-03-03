<?php
declare (strict_types = 1);

namespace app\service\sms\driver;

use think\facade\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * 腾讯云短信驱动
 *
 * 基于腾讯云短信服务API实现
 */
class TencentDriver implements SmsDriverInterface
{
    /**
     * @var array 配置信息
     */
    protected array $config;

    /**
     * @var Client HTTP客户端
     */
    protected Client $httpClient;

    /**
     * @var string API版本
     */
    protected string $version = '2021-01-11';

    /**
     * @var string 服务名称
     */
    protected string $service = 'sms';

    /**
     * 构造函数
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->httpClient = new Client([
            'timeout' => 5,
            'verify' => false,
        ]);
    }

    /**
     * 发送短信
     *
     * @param string $phone 手机号码
     * @param string $code 验证码
     * @param array $data 额外参数
     * @return array 返回结果
     * @throws \Exception
     */
    public function send(string $phone, string $code, array $data = []): array
    {
        try {
            // 检查配置
            if (!$this->checkConfig()) {
                throw new \Exception('腾讯云短信配置不完整');
            }

            // 构建请求参数
            $params = [
                'PhoneNumberSet' => [$this->formatPhone($phone)],
                'SmsSdkAppId' => $this->config['app_id'],
                'SignName' => $this->config['sign_name'],
                'TemplateId' => (int)$this->config['template_id'],
                'TemplateParamSet' => [$code],
            ];

            // 添加公共参数
            $params = array_merge($params, $this->getCommonParams());

            // 计算签名
            $authorization = $this->calculateAuthorization($params);

            // 发送HTTP请求
            $response = $this->httpClient->post(
                $this->buildUrl(),
                [
                    'json' => $params,
                    'headers' => [
                        'Authorization' => $authorization,
                        'Content-Type' => 'application/json',
                        'Host' => $this->config['endpoint'] ?? 'sms.tencentcloudapi.com',
                        'X-TC-Action' => 'SendSms',
                        'X-TC-Timestamp' => (string)$this->getTimestamp(),
                        'X-TC-Version' => $this->version,
                        'X-TC-Region' => $this->config['region'] ?? 'ap-guangzhou',
                    ],
                ]
            );

            // 解析响应
            $result = json_decode($response->getBody()->getContents(), true);

            // 检查响应结果
            if (isset($result['Response']['Error'])) {
                $error = $result['Response']['Error'];
                $errorMessage = $error['Message'] ?? '发送失败';
                throw new \Exception("腾讯云短信发送失败: {$errorMessage}");
            }

            $sendStatusSet = $result['Response']['SendStatusSet'][0] ?? [];

            return [
                'driver' => 'tencent',
                'success' => true,
                'serial_no' => $sendStatusSet['SerialNo'] ?? '',
                'message' => '发送成功',
            ];
        } catch (RequestException $e) {
            Log::error('腾讯云短信请求异常', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
            throw new \Exception('腾讯云短信请求失败: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('腾讯云短信发送异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取驱动名称
     *
     * @return string
     */
    public function getName(): string
    {
        return 'tencent';
    }

    /**
     * 检查配置是否完整
     *
     * @return bool
     */
    public function checkConfig(): bool
    {
        $requiredKeys = ['app_id', 'secret_id', 'secret_key', 'sign_name', 'template_id'];

        foreach ($requiredKeys as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 格式化手机号(添加国际区号)
     *
     * @param string $phone 手机号码
     * @return string
     */
    protected function formatPhone(string $phone): string
    {
        // 中国大陆区号
        return '+86' . $phone;
    }

    /**
     * 获取公共参数
     *
     * @return array
     */
    protected function getCommonParams(): array
    {
        return [
            'Action' => 'SendSms',
            'Version' => $this->version,
            'Region' => $this->config['region'] ?? 'ap-guangzhou',
            'Timestamp' => $this->getTimestamp(),
            'Nonce' => $this->generateNonce(),
            'SecretId' => $this->config['secret_id'],
        ];
    }

    /**
     * 计算签名
     *
     * @param array $params 请求参数
     * @return string
     */
    protected function calculateAuthorization(array $params): string
    {
        $timestamp = $this->getTimestamp();
        $date = gmdate('Y-m-d', $timestamp);

        // 构建待签名字符串
        $httpRequestMethod = 'POST';
        $canonicalUri = '/';
        $canonicalQueryString = '';
        $canonicalHeaders = "content-type:application/json\nhost:{$this->config['endpoint']}\n";
        $signedHeaders = 'content-type;host';
        $hashedRequestPayload = sha1(json_encode($params));
        $canonicalRequest = $httpRequestMethod . "\n" .
            $canonicalUri . "\n" .
            $canonicalQueryString . "\n" .
            $canonicalHeaders . "\n" .
            $signedHeaders . "\n" .
            $hashedRequestPayload;

        // 构建签名字符串
        $credentialScope = $date . '/' . $this->service . '/tc3_request';
        $hashedCanonicalRequest = sha1($canonicalRequest);
        $stringToSign = 'TC3-HMAC-SHA256' . "\n" .
            $timestamp . "\n" .
            $credentialScope . "\n" .
            $hashedCanonicalRequest;

        // 计算签名
        $secretDate = hash_hmac('SHA256', $date, 'TC3' . $this->config['secret_key'], true);
        $secretService = hash_hmac('SHA256', $this->service, $secretDate, true);
        $secretSigning = hash_hmac('SHA256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('SHA256', $stringToSign, $secretSigning);

        // 构建Authorization头
        $authorization = 'TC3-HMAC-SHA256 ' .
            'Credential=' . $this->config['secret_id'] . '/' . $credentialScope . ', ' .
            'SignedHeaders=' . $signedHeaders . ', ' .
            'Signature=' . $signature;

        return $authorization;
    }

    /**
     * 生成随机数
     *
     * @return int
     */
    protected function generateNonce(): int
    {
        return mt_rand(10000, 99999);
    }

    /**
     * 获取时间戳
     *
     * @return int
     */
    protected function getTimestamp(): int
    {
        return time();
    }

    /**
     * 构建请求URL
     *
     * @return string
     */
    protected function buildUrl(): string
    {
        $endpoint = $this->config['endpoint'] ?? 'sms.tencentcloudapi.com';
        $scheme = 'https';
        return "{$scheme}://{$endpoint}/";
    }
}
