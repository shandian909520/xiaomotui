<?php
declare (strict_types = 1);

namespace app\action\plugins;

use app\action\AbstractActionPlugin;
use app\model\TaskAction;
use app\model\TaskInstance;

/**
 * 分享到微信群动作（信任模式）
 * 微信 H5 内引导分享到群聊 + 截图凭证审核
 */
class GroupShareAction extends AbstractActionPlugin
{
    public static function key(): string
    {
        return 'group_share';
    }

    public function meta(): array
    {
        return [
            'name'        => '分享到微信群',
            'icon'        => '👥',
            'description' => '引导用户将活动内容分享到微信群，上传群内截图审核后完成任务',
            'platform'    => '微信',
        ];
    }

    public function capability(): array
    {
        return [
            'verify_method'         => 'trust',
            'fallback_verify_method'=> null,
            'need_proof'            => true,
            'env'                   => ['wechat_h5'],
        ];
    }

    public function renderCard(TaskInstance $instance, TaskAction $action): array
    {
        $shareTitle = (string)($this->config($action, 'share_title', '') ?: '把好店分享到群里');

        return [
            'jump_type'   => 'none',
            'scheme_url'  => null,
            'qrcode_url'  => null,
            'copy_text'   => $shareTitle,
            'guide_steps' => [
                '1. 点击右上角"..."选择"发送给朋友"',
                '2. 选择一个微信群发送',
                '3. 发送成功后截图上传凭证',
            ],
        ];
    }

    public function verify(TaskInstance $instance, TaskAction $action, array $payload = []): bool
    {
        // 信任模式：凭证审核驱动完成
        return false;
    }
}
