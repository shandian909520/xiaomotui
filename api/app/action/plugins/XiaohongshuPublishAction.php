<?php
declare (strict_types = 1);

namespace app\action\plugins;

/**
 * 发布小红书笔记动作（信任模式）
 */
class XiaohongshuPublishAction extends AbstractPublishAction
{
    public static function key(): string
    {
        return 'xiaohongshu_publish';
    }

    public function meta(): array
    {
        return [
            'name'        => '发布小红书笔记',
            'icon'        => '📕',
            'description' => '引导用户在小红书发布带话题的笔记，上传截图审核后完成任务',
            'platform'    => '小红书',
        ];
    }

    protected function schemePrefix(): string
    {
        return 'xhsdiscover://';
    }

    protected function platformName(): string
    {
        return '小红书';
    }
}
