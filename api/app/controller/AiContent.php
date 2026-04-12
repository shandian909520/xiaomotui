<?php
declare(strict_types=1);

namespace app\controller;

use app\service\WenxinService;
use app\model\ContentTask;
use think\facade\Log;
use think\Response;

/**
 * AI内容生成控制器
 * 提供AI文案生成相关的API接口
 */
class AiContent extends BaseController
{
    protected array $middleware = [
        'app\middleware\JwtAuth'
    ];
    /**
     * 生成营销文案
     *
     * @return Response
     */
    public function generateText(): Response
    {
        try {
            // 获取请求参数 - 支持 JSON body 和 form-data 两种格式
            // 优先从 JSON body 获取参数
            $jsonInput = file_get_contents('php://input');
            $jsonParams = !empty($jsonInput) ? json_decode($jsonInput, true) : [];
            if (json_last_error() !== JSON_ERROR_NONE && !empty($jsonInput)) {
                return $this->error('JSON解析失败: ' . json_last_error_msg());
            }

            // 合并 JSON 参数和 form参数，JSON 优先
            $params = array_merge(
                $this->request->param([
                    'scene', 'style', 'platform', 'category', 'requirements', 'provider'
                ], null, 'trim'),
                $jsonParams
            );

            // 获取 AI 服务提供商，默认为 minimax
            $provider = $params['provider'] ?? 'minimax';
            unset($params['provider']); // 从 params 中移除，避免传递给服务

            // 参数验证
            $this->validateParams($params);

            // 初始化文心一言服务（支持 minimax）
            $wenxinService = new WenxinService($provider);

            // 生成文案
            $result = $wenxinService->generateText($params);

            // 保存生成记录到任务历史
            try {
                $userId = $this->request->user_id ?? 0;
                $merchantId = $this->request->merchant_id ?? 0;

                ContentTask::create([
                    'user_id' => $userId,
                    'merchant_id' => $merchantId,
                    'type' => 'TEXT',
                    'status' => ContentTask::STATUS_COMPLETED,
                    'input_data' => $params,
                    'output_data' => [
                        'text' => $result['text'],
                        'tokens' => $result['tokens'] ?? 0,
                        'model' => $result['model'] ?? ''
                    ],
                    'ai_provider' => $provider,
                    'generation_time' => isset($result['time']) ? intval($result['time']) : 0,
                    'complete_time' => date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                // 记录日志但不影响主流程返回
                Log::error('保存AI生成历史记录失败', ['error' => $e->getMessage()]);
            }

            return $this->success([
                'text' => $result['text'],
                'tokens' => $result['tokens'],
                'time' => $result['time'],
                'model' => $result['model'],
                'provider' => $provider,
                'params' => $params,
            ], '文案生成成功');

        } catch (\Exception $e) {
            Log::error('AI文案生成失败', [
                'params' => $params ?? [],
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量生成文案
     *
     * @return Response
     */
    public function batchGenerateText(): Response
    {
        try {
            // 获取批量参数
            $batchParams = $this->request->post('batch_params', []);

            if (empty($batchParams) || !is_array($batchParams)) {
                return $this->error('批量参数不能为空');
            }

            // 限制批量数量
            if (count($batchParams) > 10) {
                return $this->error('单次批量生成最多支持10条');
            }

            // 验证每个参数
            foreach ($batchParams as $params) {
                $this->validateParams($params);
            }

            // 初始化文心一言服务
            $wenxinService = new WenxinService();

            // 批量生成
            $results = $wenxinService->batchGenerateText($batchParams);

            // 统计结果
            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failCount = count($results) - $successCount;

            return $this->success([
                'results' => $results,
                'total' => count($results),
                'success_count' => $successCount,
                'fail_count' => $failCount,
            ], "批量生成完成，成功{$successCount}条，失败{$failCount}条");

        } catch (\Exception $e) {
            Log::error('批量AI文案生成失败', [
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取创作历史
     *
     * @return Response
     */
    public function history(): Response
    {
        try {
            $userId = $this->request->user_id ?? 0;
            $limit = $this->request->get('limit', 10);
            
            $list = ContentTask::where('user_id', $userId)
                ->where('type', 'TEXT')
                ->where('status', ContentTask::STATUS_COMPLETED)
                ->order('create_time', 'desc')
                ->limit((int)$limit)
                ->select();
                
            $result = [];
            foreach ($list as $item) {
                // 确保 output_data 是数组
                $outputData = $item->output_data;
                if (is_string($outputData)) {
                    $outputData = json_decode($outputData, true);
                } elseif (is_object($outputData)) {
                    $outputData = (array)$outputData;
                }
                
                // 确保 input_data 是数组
                $inputData = $item->input_data;
                if (is_string($inputData)) {
                    $inputData = json_decode($inputData, true);
                } elseif (is_object($inputData)) {
                    $inputData = (array)$inputData;
                }
                
                $result[] = [
                    'id' => $item->id,
                    'content' => $outputData['text'] ?? '',
                    'create_time' => $item->create_time,
                    // 前端期望 params 是 JSON 字符串以便 JSON.parse
                    'params' => json_encode($inputData)
                ];
            }
            
            return $this->success($result, '获取历史记录成功');
            
        } catch (\Exception $e) {
            return $this->error('获取历史记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试AI服务连接
     *
     * @return Response
     */
    public function testConnection(): Response
    {
        try {
            $wenxinService = new WenxinService();
            $result = $wenxinService->testConnection();

            if ($result['success']) {
                return $this->success($result, '连接测试成功');
            } else {
                return $this->error($result['message'], $result);
            }

        } catch (\Exception $e) {
            return $this->error('连接测试失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取AI服务状态
     *
     * @return Response
     */
    public function getStatus(): Response
    {
        try {
            $wenxinService = new WenxinService();
            $status = $wenxinService->getStatus();

            return $this->success($status, '获取状态成功');

        } catch (\Exception $e) {
            return $this->error('获取状态失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取配置信息
     *
     * @return Response
     */
    public function getConfig(): Response
    {
        try {
            $wenxinService = new WenxinService();
            $config = $wenxinService->getConfig();

            return $this->success($config, '获取配置成功');

        } catch (\Exception $e) {
            return $this->error('获取配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 清除Token缓存
     *
     * @return Response
     */
    public function clearCache(): Response
    {
        try {
            $wenxinService = new WenxinService();
            $result = $wenxinService->clearTokenCache();

            if ($result) {
                return $this->success([], 'Token缓存清除成功');
            } else {
                return $this->error('Token缓存清除失败');
            }

        } catch (\Exception $e) {
            return $this->error('清除缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取支持的风格列表
     *
     * @return Response
     */
    public function getStyles(): Response
    {
        $styles = [
            ['value' => '温馨', 'label' => '温馨', 'description' => '温暖、亲切、有人情味，营造舒适放松的氛围'],
            ['value' => '时尚', 'label' => '时尚', 'description' => '前卫、新潮、个性化，突出流行趋势'],
            ['value' => '文艺', 'label' => '文艺', 'description' => '有情怀、有格调、富有诗意和文化气息'],
            ['value' => '潮流', 'label' => '潮流', 'description' => '年轻活力、时尚动感、紧跟潮流'],
            ['value' => '高端', 'label' => '高端', 'description' => '精致优雅、品质感强、体现尊贵体验'],
            ['value' => '亲民', 'label' => '亲民', 'description' => '接地气、实惠、贴近生活'],
        ];

        return $this->success($styles, '获取风格列表成功');
    }

    /**
     * 获取支持的平台列表
     *
     * @return Response
     */
    public function getPlatforms(): Response
    {
        $platforms = [
            [
                'value' => 'DOUYIN',
                'label' => '抖音',
                'description' => '简短有力，20-50字，节奏感强，适合短视频',
            ],
            [
                'value' => 'XIAOHONGSHU',
                'label' => '小红书',
                'description' => '真实生活化，100-200字，分享式语气',
            ],
            [
                'value' => 'WECHAT',
                'label' => '微信',
                'description' => '亲切自然，内容详实，适合朋友圈分享',
            ],
        ];

        return $this->success($platforms, '获取平台列表成功');
    }

    /**
     * 参数验证
     *
     * @param array $params 参数
     * @throws \Exception
     */
    private function validateParams(array $params): void
    {
        // 必填字段验证
        if (empty($params['scene'])) {
            throw new \Exception('场景描述不能为空');
        }

        if (empty($params['style'])) {
            throw new \Exception('风格不能为空');
        }

        if (empty($params['platform'])) {
            throw new \Exception('平台不能为空');
        }

        // 风格验证
        $validStyles = ['温馨', '时尚', '文艺', '潮流', '高端', '亲民', '专业权威', '幽默风趣', '亲切自然', '激情促销', '亲切', '专业', '幽默', '激情'];
        if (!in_array($params['style'], $validStyles)) {
            throw new \Exception('不支持的风格: ' . $params['style']);
        }

        // 平台验证
        $validPlatforms = ['DOUYIN', 'XIAOHONGSHU', 'WECHAT', 'KUAISHOU', 'VIDEO', 'RED'];
        if (!in_array(strtoupper($params['platform']), $validPlatforms)) {
            throw new \Exception('不支持的平台: ' . $params['platform']);
        }

        // 长度验证
        if (mb_strlen($params['scene']) > 50) {
            throw new \Exception('场景描述不能超过50个字符');
        }

        if (!empty($params['requirements']) && mb_strlen($params['requirements']) > 200) {
            throw new \Exception('特殊要求不能超过200个字符');
        }
    }
}