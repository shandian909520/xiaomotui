<?php
declare (strict_types = 1);

namespace app\action\plugins;

use app\action\AbstractActionPlugin;
use app\model\TaskAction;
use app\model\TaskInstance;

/**
 * 添加企业微信好友动作（回调核验，降级信任）
 * 渲染渠道活码二维码；企微 add_external_contact 回调经 TaskCallback 匹配后完成
 */
class WeworkAddFriendAction extends AbstractActionPlugin
{
    public static function key(): string
    {
        return 'wework_add_friend';
    }

    public function meta(): array
    {
        return [
            'name'        => '添加企业微信好友',
            'icon'        => '💼',
            'description' => '用户扫码添加商家企业微信好友，回调自动核验完成（未配置回调时可上传截图审核）',
            'platform'    => '企业微信',
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
                '1. 长按识别上方二维码，添加商家企业微信',
                '2. 添加成功后自动完成任务（或上传截图凭证）',
            ],
        ];
    }

    public function verify(TaskInstance $instance, TaskAction $action, array $payload = []): bool
    {
        // 回调核验：payload 携带与实例匹配的 openid（external_user/openid）时判定完成
        $callbackOpenid = (string)($payload['openid'] ?? $payload['external_openid'] ?? '');
        if ($callbackOpenid !== '' && $instance->openid && $callbackOpenid === $instance->openid) {
            return true;
        }
        // 降级信任：凭证审核驱动，此处返回待验证
        return false;
    }
}
