<?php
declare (strict_types = 1);

namespace app\action\plugins;

/**
 * 发布快手视频动作（信任模式）
 */
class KuaishouPublishAction extends AbstractPublishAction
{
    public static function key(): string
    {
        return 'kuaishou_publish';
    }

    public function meta(): array
    {
        return [
            'name'        => '发布快手视频',
            'icon'        => '🎬',
            'description' => '引导用户在快手发布带话题的作品，上传截图审核后完成任务',
            'platform'    => '快手',
        ];
    }

    protected function schemePrefix(): string
    {
        return 'kwai://';
    }

    protected function platformName(): string
    {
        return '快手';
    }
}
