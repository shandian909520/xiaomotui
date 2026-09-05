<?php
declare(strict_types=1);

namespace app\controller;

use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

class PublicController extends BaseController
{
    /**
     * 获取系统配置
     * GET /api/public/config
     */
    public function getConfig()
    {
        try {
            $config = [
                'app_name' => Config::get('app.name', '小磨推'),
                'version' => '1.0.0',
                'upload_limits' => [
                    'image_max_size' => 5,
                    'video_max_size' => 100,
                ],
                'platforms' => \app\model\PlatformAccount::getSupportedPlatforms(),
            ];

            return $this->success($config, '获取成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_config_failed');
        }
    }

    /**
     * 提交反馈
     * POST /api/public/feedback
     */
    public function feedback()
    {
        $data = $this->request->post();

        try {
            $this->validate($data, [
                'content' => 'require|max:500',
                'contact' => 'max:100',
                'type'    => 'in:bug,suggestion,complaint,other',
            ]);

            $feedbackData = [
                'content'   => $data['content'],
                'contact'   => $data['contact'] ?? '',
                'type'      => $data['type'] ?? 'other',
                'user_id'   => $this->request->user_id ?? 0,
                'ip'        => $this->request->ip(),
                'user_agent' => $this->request->header('user-agent', ''),
                'create_time' => date('Y-m-d H:i:s'),
            ];

            // 检查反馈表是否存在
            try {
                Db::name('feedback')->insert($feedbackData);
            } catch (\Exception $dbEx) {
                Log::warning('反馈表不存在，记录到日志', [
                    'data' => $feedbackData,
                    'error' => $dbEx->getMessage(),
                ]);
            }

            return $this->success(null, '反馈提交成功，感谢您的意见');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'feedback_failed');
        }
    }

    /**
     * 版本信息
     * GET /api/public/version
     */
    public function version()
    {
        return $this->success([
            'version'     => '1.0.0',
            'build'       => '20250525',
            'min_version' => '1.0.0',
            'update_url'  => '',
            'changelog'   => '',
        ], '获取成功');
    }
}
