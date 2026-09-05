<?php
declare (strict_types = 1);

namespace app\controller;

use app\model\TaskAction;
use app\model\TaskInstance as TaskInstanceModel;
use app\service\TaskVerifyService;
use think\exception\ValidateException;
use think\facade\Log;
use think\Response;

/**
 * 碰一碰任务回调控制器（公开访问）
 * 企业微信 add_external_contact / 公众号 subscribe 事件回调核验
 */
class TaskCallback extends BaseController
{
    protected TaskVerifyService $verifyService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->verifyService = new TaskVerifyService();
    }

    /**
     * 企业微信回调
     * GET  /api/task/callback/wework/:instance_id/:action_id  URL 验证（echostr）
     * POST 同路径 —— 解析 XML/JSON 事件，验签后调 handleCallback
     */
    public function wework()
    {
        return $this->handle('wework');
    }

    /**
     * 公众号回调（subscribe 事件）
     * GET  /api/task/callback/official/:instance_id/:action_id  URL 验证（echostr）
     * POST 同路径
     */
    public function official()
    {
        return $this->handle('official');
    }

    /**
     * 统一回调处理
     */
    protected function handle(string $type)
    {
        try {
            $instanceId = (int)$this->request->param('instance_id', 0);
            $actionId   = (int)$this->request->param('action_id', 0);

            // GET：微信服务器 URL 有效性验证，原样回显 echostr
            if ($this->request->isGet()) {
                $echostr = (string)$this->request->get('echostr', '');
                if ($echostr !== '') {
                    return Response::create($echostr, 'html');
                }
                return Response::create('success', 'html', 200);
            }

            // POST：解析回调消息（企微为 JSON 或加密 XML，公众号为 XML）
            $raw = $this->request->getContent();
            $payload = $this->parseMessage($raw);

            // 验签（配置了 token 时启用）
            $this->checkSignature($type);

            $instance = TaskInstanceModel::find($instanceId);
            $action = TaskAction::find($actionId);
            if (!$instance || !$action || (int)$action->bundle_id !== (int)$instance->bundle_id) {
                Log::warning('任务回调数据无效', ['type' => $type, 'instance_id' => $instanceId, 'action_id' => $actionId]);
                return Response::create('success', 'html');
            }

            $passed = $this->verifyService->handleCallback($type, $instance, $action, $payload);

            Log::info('任务回调处理完成', [
                'type' => $type,
                'instance_id' => $instanceId,
                'action_id' => $actionId,
                'passed' => $passed,
            ]);

            // 微信服务器要求 200 + success/空串，避免重推
            return Response::create('success', 'html');
        } catch (\Exception $e) {
            Log::error('任务回调处理失败', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            // 回包 200 防止微信重推风暴，错误已记录日志
            return Response::create('success', 'html');
        }
    }

    /**
     * 解析回调报文（XML/JSON）
     */
    protected function parseMessage(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        // JSON（企微新版本回调）
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        // XML
        $xml = @simplexml_load_string($raw);
        if ($xml !== false) {
            return json_decode(json_encode($xml), true) ?? [];
        }
        return [];
    }

    /**
     * 微信公众号/企微服务器签名校验（配置 token 时启用）
     */
    protected function checkSignature(string $type): void
    {
        $token = (string)config('wechat.official.token', '');
        if ($type === 'wework') {
            $token = (string)config('wechat.wework.token', $token);
        }
        if ($token === '') {
            // 未配置 token，跳过验签（一期内网/测试环境）
            return;
        }

        $signature = (string)$this->request->get('msg_signature', $this->request->get('signature', ''));
        $timestamp = (string)$this->request->get('timestamp', '');
        $nonce     = (string)$this->request->get('nonce', '');

        // 明文模式签名算法：sha1(sort(token, timestamp, nonce))
        $params = [$token, $timestamp, $nonce];
        sort($params, SORT_STRING);
        if ($signature !== '' && sha1(implode('', $params)) !== $signature) {
            throw new ValidateException('回调签名校验失败');
        }
    }
}
