<?php
declare (strict_types = 1);

namespace app\action\plugins;

use app\action\AbstractActionPlugin;
use app\model\TaskAction;
use app\model\TaskInstance;

/**
 * 朋友圈分享动作（信任模式）
 * 微信 H5 内引导用户分享到朋友圈，jump_type=none + 引导步骤 + 截图凭证
 */
class MomentsShareAction extends AbstractActionPlugin
{
    public static function key(): string
    {
        return 'moments_share';
    }

    public function meta(): array
    {
        return [
            'name'        => '分享到朋友圈',
            'icon'        => '💬',
            'description' => '引导用户将活动内容分享到微信朋友圈，上传截图审核后完成任务',
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
        $shareTitle = (string)($this->config($action, 'share_title', '') ?: '分享好店给朋友');

        return [
            'jump_type'   => 'none',
            'scheme_url'  => null,
            'qrcode_url'  => null,
            'copy_text'   => $shareTitle,
            'guide_steps' => [
                '1. 点击右上角"..."选择"分享到朋友圈"',
                '2. 配上推荐文案发布',
                '3. 发布成功后截图上传凭证',
            ],
        ];
    }

    public function verify(TaskInstance $instance, TaskAction $action, array $payload = []): bool
    {
        // 信任模式：凭证审核驱动完成
        return false;
    }
}
