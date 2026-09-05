<?php
declare (strict_types = 1);

namespace app\action\plugins;

use app\action\AbstractActionPlugin;
use app\model\TaskAction;
use app\model\TaskInstance;

/**
 * 短视频/图文发布类动作公共基类（抖音/快手/小红书）
 * 信任模式：start 返回唤起 scheme + 话题口令，完成凭证由 TaskVerifyService 审核驱动
 */
abstract class AbstractPublishAction extends AbstractActionPlugin
{
    /**
     * 平台 scheme 前缀，如 snssdk1128://aweme/detail
     */
    abstract protected function schemePrefix(): string;

    /**
     * 平台名称，如 抖音
     */
    abstract protected function platformName(): string;

    public function capability(): array
    {
        return [
            'verify_method'         => 'trust',
            'fallback_verify_method'=> null,
            'need_proof'            => true,
            'env'                   => ['browser', 'wechat_h5'],
        ];
    }

    public function renderCard(TaskInstance $instance, TaskAction $action): array
    {
        $topic    = (string)($this->config($action, 'topic', '') ?: '');
        $poiName  = (string)($this->config($action, 'poi_name', '') ?: '');
        $scheme   = (string)($this->config($action, 'scheme_url', '') ?: $this->schemePrefix());

        // 拼装话题口令：#话题# @门店名
        $copyText = '';
        if ($topic !== '') {
            $copyText .= '#' . str_replace('#', '', $topic) . '#';
        }
        if ($poiName !== '') {
            $copyText .= ($copyText !== '' ? ' ' : '') . '@' . $poiName;
        }

        $steps = [
            '1. 点击下方按钮打开' . $this->platformName() . ' App',
            '2. 使用推荐视频/素材发布作品',
        ];
        if ($copyText !== '') {
            array_push($steps, '3. 复制话题口令并粘贴到作品文案中', '4. 发布成功后截图上传凭证');
        } else {
            $steps[] = '3. 发布成功后截图上传凭证';
        }

        return [
            'jump_type'   => 'scheme',
            'scheme_url'  => $scheme,
            'qrcode_url'  => null,
            'copy_text'   => $copyText !== '' ? $copyText : null,
            'guide_steps' => $steps,
        ];
    }

    public function verify(TaskInstance $instance, TaskAction $action, array $payload = []): bool
    {
        // 信任模式：真正完成由凭证审核（TaskVerifyService::auditProof APPROVED）驱动
        return false;
    }
}
