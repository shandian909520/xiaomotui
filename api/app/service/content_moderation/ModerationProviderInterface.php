<?php
declare(strict_types=1);

namespace app\service\content_moderation;

/**
 * 内容审核服务商接口
 * 所有内容审核服务商必须实现此接口
 */
interface ModerationProviderInterface
{
    /**
     * 检查文本内容
     *
     * @param string $text 文本内容
     * @param array $options 附加选项
     * @return array 返回格式: [
     *     'pass' => bool,        // 是否通过
     *     'score' => int,        // 评分(0-100)
     *     'confidence' => float, // 置信度(0-1)
     *     'violations' => array, // 违规详情
     *     'suggestion' => string, // 建议: pass|review|reject
     *     'provider' => string,  // 服务商名称
     * ]
     */
    public function checkText(string $text, array $options = []): array;

    /**
     * 检查图片内容
     *
     * @param string $imageUrl 图片URL或Base64编码
     * @param array $options 附加选项
     * @return array 返回格式同checkText
     */
    public function checkImage(string $imageUrl, array $options = []): array;

    /**
     * 检查视频内容
     *
     * @param string $videoUrl 视频URL
     * @param array $options 附加选项,如: frames截帧数
     * @return array 返回格式同checkText
     */
    public function checkVideo(string $videoUrl, array $options = []): array;

    /**
     * 检查音频内容
     *
     * @param string $audioUrl 音频URL
     * @param array $options 附加选项
     * @return array 返回格式同checkText
     */
    public function checkAudio(string $audioUrl, array $options = []): array;

    /**
     * 批量检查文本
     *
     * @param array $texts 文本数组
     * @param array $options 附加选项
     * @return array
     */
    public function batchCheckText(array $texts, array $options = []): array;

    /**
     * 批量检查图片
     *
     * @param array $imageUrls 图片URL数组
     * @param array $options 附加选项
     * @return array
     */
    public function batchCheckImage(array $imageUrls, array $options = []): array;

    /**
     * 获取服务商名称
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * 检查服务商是否可用
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * 获取服务商优先级
     *
     * @return int 数字越小优先级越高
     */
    public function getPriority(): int;
}
