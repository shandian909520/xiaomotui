<?php
declare (strict_types = 1);

namespace app\action\plugins;

/**
 * 发布抖音视频动作（信任模式）
 */
class DouyinPublishAction extends AbstractPublishAction
{
    public static function key(): string
    {
        return 'douyin_publish';
    }

    public function meta(): array
    {
        return [
            'name'        => '发布抖音视频',
            'icon'        => '🎵',
            'description' => '引导用户在抖音发布带话题的作品，上传截图审核后完成任务',
            'platform'    => '抖音',
        ];
    }

    protected function schemePrefix(): string
    {
        // snssdk1128 为抖音 App URL Scheme
        return 'snssdk1128://aweme/detail';
    }

    protected function platformName(): string
    {
        return '抖音';
    }
}
