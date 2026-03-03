<?php
declare (strict_types = 1);

namespace app\service\sms\driver;

use think\facade\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * 阿里云短信驱动
 *
 * 基于阿里云短信服务API实现
 */
class AliyunDriver implements SmsDriverInterface
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
    protected string $version = '2017-05-25';

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
                throw new \Exception('阿里云短信配置不完整');
            }

            // 构建请求参数
            $params = [
                'PhoneNumbers' => $phone,
                'SignName' => $this->config['sign_name'],
                'TemplateCode' => $this->config['template_code'],
                'TemplateParam' => json_encode(['code' => $code], JSON_UNESCAPED_UNICODE),
            ];

            // 添加公共参数
            $params = array_merge($params, $this->getCommonParams());

            // 计算签名
            $params['Signature'] = $this->calculateSignature($params);

            // 发送HTTP请求
            $response = $this->httpClient->post(
                $this->buildUrl(),
                [
                    'form_params' => $params,
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                ]
            );

            // 解析响应
            $result = json_decode($response->getBody()->getContents(), true);

            // 检查响应结果
            if (!isset($result['Code']) || $result['Code'] !== 'OK') {
                $errorMessage = $result['Message'] ?? '发送失败';
                throw new \Exception("阿里云短信发送失败: {$errorMessage}");
            }

            return [
                'driver' => 'aliyun',
                'success' => true,
                'request_id' => $result['RequestId'] ?? '',
                'biz_id' => $result['BizId'] ?? '',
                'message' => '发送成功',
            ];
        } catch (RequestException $e) {
            Log::error('阿里云短信请求异常', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
            throw new \Exception('阿里云短信请求失败: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('阿里云短信发送异常', [
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
        return 'aliyun';
    }

    /**
     * 检查配置是否完整
     *
     * @return bool
     */
    public function checkConfig(): bool
    {
        $requiredKeys = ['access_key_id', 'access_key_secret', 'sign_name', 'template_code'];

        foreach ($requiredKeys as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }

        return true;
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
            'Format' => 'JSON',
            'AccessKeyId' => $this->config['access_key_id'],
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => $this->generateNonce(),
            'Timestamp' => $this->getTimestamp(),
        ];
    }

    /**
     * 计算签名
     *
     * @param array $params 请求参数
     * @return string
     */
    protected function calculateSignature(array $params): string
    {
        // 参数排序
        ksort($params);

        // 构建待签名字符串
        $stringToSign = 'POST&%2F&' . urlencode(http_build_query($params, '', '&', PHP_QUERY_RFC3986));

        // 计算HMAC-SHA1签名
        $signature = base64_encode(
            hash_hmac(
                'sha1',
                $stringToSign,
                $this->config['access_key_secret'] . '&',
                true
            )
        );

        return $signature;
    }

    /**
     * 生成随机数
     *
     * @return string
     */
    protected function generateNonce(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * 获取时间戳
     *
     * @return string
     */
    protected function getTimestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * 构建请求URL
     *
     * @return string
     */
    protected function buildUrl(): string
    {
        $endpoint = $this->config['endpoint'] ?? 'dysmsapi.aliyuncs.com';
        $scheme = 'https';
        return "{$scheme}://{$endpoint}/";
    }
}
