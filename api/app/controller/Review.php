<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\ReviewConfigService;
use app\service\ReviewService;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 点评控制器(模块4)
 * 顾客端入口:
 *   GET  /api/review/config?device_id=xx        -> getReviewConfig
 *   GET  /api/review/draft?device_id=xx&platform=DIANPING  -> getReviewDraft
 *   POST /api/review/action                     -> recordReviewAction
 *
 * 合规约束:
 *   - 不实现"自动好评";仅返回 AI 评价灵感草稿 + 合规提示
 *   - 所有动作通过 xmt_review_actions 埋点
 */
class Review extends BaseController
{
    protected ReviewService $reviewService;
    protected ReviewConfigService $configService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->reviewService  = new ReviewService();
        $this->configService  = new ReviewConfigService();
    }

    /**
     * 获取点评配置(平台入口/开关)
     */
    public function getReviewConfig()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            $data = $this->reviewService->getConfig($deviceId);

            // 进入页埋点
            try {
                $this->reviewService->recordAction(
                    $deviceId,
                    'ALL',
                    \app\model\ReviewAction::ACTION_VIEW,
                    ['source' => 'config']
                );
            } catch (\Throwable $e) {
                Log::warning('Review controller: 埋点失败', ['error' => $e->getMessage()]);
            }

            return $this->success($data, '获取点评配置成功');
        } catch (ValidateException $e) {
            return $this->validationError(['review' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('获取点评配置失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_review_config_failed');
        }
    }

    /**
     * 获取评价灵感草稿
     * 不会触达点评平台的"写评"功能,只返回参考文案
     */
    public function getReviewDraft()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            $platform = (string)$this->request->param('platform', '');
            $count    = (int)$this->request->param('count', 3);

            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            if ($platform === '') {
                return $this->validationError(['platform' => '平台不能为空']);
            }
            if ($count < 1 || $count > 5) {
                $count = 3;
            }

            $data = $this->reviewService->generateDraft($deviceId, $platform, $count);

            return $this->success($data, '评价灵感生成成功');
        } catch (ValidateException $e) {
            return $this->validationError(['draft' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('生成评价灵感失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'generate_review_draft_failed');
        }
    }

    /**
     * 记录点评行为(view/jump/feedback/draft_copy 等)
     */
    public function recordReviewAction()
    {
        try {
            $payload = $this->request->post();
            $deviceId   = (int)($payload['device_id'] ?? 0);
            $platform   = (string)($payload['platform'] ?? '');
            $action     = (string)($payload['action'] ?? '');
            $draftIndex = isset($payload['draft_index']) ? (int)$payload['draft_index'] : null;
            $extra      = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];

            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            if ($platform === '' || !in_array($platform, [
                \app\model\ReviewAction::PLATFORM_DIANPING,
                \app\model\ReviewAction::PLATFORM_MEITUAN,
                \app\model\ReviewAction::PLATFORM_GAODE,
                \app\model\ReviewAction::PLATFORM_BAIDU,
                \app\model\ReviewAction::PLATFORM_DOUYIN,
            ], true)) {
                return $this->validationError(['platform' => '平台不合法']);
            }
            if ($action === '') {
                $action = \app\model\ReviewAction::ACTION_VIEW;
            }
            if ($draftIndex !== null) {
                $extra['draft_index'] = $draftIndex;
            }

            $ok = $this->reviewService->recordAction($deviceId, $platform, $action, $extra);
            return $this->success(['recorded' => $ok], '记录成功');
        } catch (ValidateException $e) {
            return $this->validationError(['action' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('记录点评行为失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'record_review_action_failed');
        }
    }

    // ========================================================================
    // Agent C 业务闭环:商家后台配置 + 草稿模板管理(鉴权)
    // ========================================================================

    /**
     * 商家更新点评配置(平台 URL + AI 草稿开关 + default_count)
     * POST /api/review/admin/config  { device_id, enabled?, ai_draft_enabled?, default_count?, platforms:[{key,name,jump_url,icon}] }
     */
    public function updateConfig()
    {
        try {
            $payload  = $this->request->post();
            $deviceId = (int)($payload['device_id'] ?? 0);
            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            $data = $this->configService->updateConfig($deviceId, $payload);
            return $this->success($data, '更新点评配置成功');
        } catch (ValidateException $e) {
            return $this->validationError(['config' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新点评配置失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_review_config_failed');
        }
    }

    /**
     * 商家查询草稿模板列表
     * GET /api/review/admin/draft-templates?device_id=&platform=&scope=
     */
    public function getDraftTemplates()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            $platform = (string)$this->request->param('platform', '');
            $scope    = (string)$this->request->param('scope', 'all');
            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            $list = $this->configService->getDraftTemplates($deviceId, $platform, $scope);
            return $this->success([
                'list'  => $list,
                'total' => count($list),
            ], '获取草稿模板成功');
        } catch (ValidateException $e) {
            return $this->validationError(['templates' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('获取草稿模板失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'list_review_templates_failed');
        }
    }

    /**
     * 商家新增草稿模板
     * POST /api/review/admin/draft-template  { device_id, platform, title, prompt, style?, weight?, sort?, status? }
     */
    public function addDraftTemplate()
    {
        try {
            $payload = $this->request->post();
            if (empty($payload['device_id'])) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            $row = $this->configService->addDraftTemplate($payload);
            return $this->success($row, '新增模板成功');
        } catch (ValidateException $e) {
            return $this->validationError(['template' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('新增草稿模板失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'add_review_template_failed');
        }
    }

    /**
     * 商家删除草稿模板
     * DELETE /api/review/admin/draft-template/:id?device_id=
     */
    public function deleteDraftTemplate($id)
    {
        try {
            $deviceId   = (int)$this->request->param('device_id', 0);
            $merchantId = 0;
            if ($deviceId > 0) {
                $device = \app\model\NfcDevice::find($deviceId);
                if ($device) {
                    $merchantId = (int)$device->merchant_id;
                }
            }
            $ok = $this->configService->deleteDraftTemplate((int)$id, $merchantId);
            return $this->success(['id' => (int)$id, 'deleted' => $ok], '删除模板成功');
        } catch (ValidateException $e) {
            return $this->validationError(['template' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('删除草稿模板失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'delete_review_template_failed');
        }
    }
}
