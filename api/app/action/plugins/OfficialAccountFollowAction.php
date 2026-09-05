<?php
declare (strict_types = 1);

namespace app\action\plugins;

use app\action\AbstractActionPlugin;
use app\model\TaskAction;
use app\model\TaskInstance;

/**
 * 关注公众号动作（回调核验，降级信任）
 * 渲染公众号带参二维码；subscribe 回调经 TaskCallback 匹配后完成
 */
class OfficialAccountFollowAction extends AbstractActionPlugin
{
    public static function key(): string
    {
        return 'official_account_follow';
    }

    public function meta(): array
    {
        return [
            'name'        => '关注公众号',
            'icon'        => '📢',
            'description' => '用户扫码关注商家公众号，回调自动核验完成（未配置回调时可上传截图审核）',
            'platform'    => '微信公众号',
        ];
    }

    public function capability(): array
    {
        return [
            'verify_method'         => 'callback',
            'fallback_verify_method'=> 'trust',
            'need_proof'            => true,
            'env'                   => ['wechat_h5', 'browser'],
        ];
    }

    public function renderCard(TaskInstance $instance, TaskAction $action): array
    {
        $qrcodeUrl = (string)($this->config($action, 'qrcode_url', '') ?: '');

        return [
            'jump_type'   => $qrcodeUrl !== '' ? 'qrcode' : 'none',
            'scheme_url'  => null,
            'qrcode_url'  => $qrcodeUrl !== '' ? $qrcodeUrl : null,
            'copy_text'   => null,
            'guide_steps' => [
                '1. 长按识别上方二维码，关注商家公众号',
                '2. 关注成功后自动完成任务（或上传截图凭证）',
            ],
        ];
    }

    public function verify(TaskInstance $instance, TaskAction $action, array $payload = []): bool
    {
        // 回调核验：subscribe 事件携带的 FromUserName 与实例 openid 匹配时判定完成
        $callbackOpenid = (string)($payload['openid'] ?? $payload['FromUserName'] ?? '');
        if ($callbackOpenid !== '' && $instance->openid && $callbackOpenid === $instance->openid) {
            return true;
        }
        return false;
    }
}
