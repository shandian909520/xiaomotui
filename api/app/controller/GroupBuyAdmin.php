<?php
declare (strict_types = 1);

namespace app\controller;

use app\model\GroupBuyItem;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 团购商品 Admin 控制器(模块5)
 *
 * 顾客端展示走 Nfc::getGroupBuyItems;Admin CRUD 在本控制器。
 * 跳转走 GroupBuyService::ItemRedirect(已扩展),自动埋点 group_buy_redirects。
 */
class GroupBuyAdmin extends BaseController
{
    /**
     * 商品列表
     */
    public function list()
    {
        try {
            $merchantId = (int)$this->request->param('merchant_id', 0);
            $page       = (int)$this->request->param('page', 1);
            $limit      = (int)$this->request->param('limit', 20);
            $status     = $this->request->param('status', '');
            $platform   = $this->request->param('platform', '');

            $where = [];
            if ($merchantId > 0) {
                $where[] = ['merchant_id', '=', $merchantId];
            }
            if ($status !== '') {
                $where[] = ['status', '=', (int)$status];
            }
            if ($platform !== '') {
                $where[] = ['platform', '=', $platform];
            }

            $total = GroupBuyItem::where($where)->count();
            $list  = GroupBuyItem::where($where)
                ->order('sort', 'desc')
                ->order('id', 'desc')
                ->page($page, $limit)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $limit, '获取商品列表成功');
        } catch (\Exception $e) {
            Log::error('获取团购商品列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'list_items_failed');
        }
    }

    /**
     * 商品详情
     */
    public function detail($id = null)
    {
        try {
            $iid = (int)($id ?: $this->request->param('id', 0));
            if ($iid <= 0) {
                return $this->validationError(['id' => '商品ID不能为空']);
            }
            $row = GroupBuyItem::find($iid);
            if (!$row) {
                return $this->error('商品不存在', 404, 'item_not_found');
            }
            return $this->success($row->toArray(), '获取商品详情成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'detail_item_failed');
        }
    }

    /**
     * 新增商品
     */
    public function create()
    {
        try {
            $data = $this->request->post();
            $this->validateItem($data);
            $data['status'] = isset($data['status']) ? (int)$data['status'] : GroupBuyItem::STATUS_ONLINE;
            $row = GroupBuyItem::create($data);
            return $this->success($row->toArray(), '创建商品成功');
        } catch (ValidateException $e) {
            return $this->validationError(['item' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建团购商品失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_item_failed');
        }
    }

    /**
     * 更新商品
     */
    public function update($id = null)
    {
        try {
            $iid = (int)($id ?: $this->request->param('id', 0));
            $row = GroupBuyItem::find($iid);
            if (!$row) {
                return $this->error('商品不存在', 404, 'item_not_found');
            }
            $data = $this->request->post();
            $this->validateItem($data, true);
            $row->save($data);
            return $this->success($row->toArray(), '更新商品成功');
        } catch (ValidateException $e) {
            return $this->validationError(['item' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新团购商品失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_item_failed');
        }
    }

    /**
     * 删除商品(软删:status=0)
     */
    public function delete($id = null)
    {
        try {
            $iid = (int)($id ?: $this->request->param('id', 0));
            $row = GroupBuyItem::find($iid);
            if (!$row) {
                return $this->error('商品不存在', 404, 'item_not_found');
            }
            $row->status = GroupBuyItem::STATUS_OFFLINE;
            $row->save();
            return $this->success(['id' => $iid], '商品已下架');
        } catch (\Exception $e) {
            Log::error('删除团购商品失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'delete_item_failed');
        }
    }

    /**
     * 字段校验
     */
    protected function validateItem(array $data, bool $partial = false): void
    {
        if (!$partial || array_key_exists('merchant_id', $data)) {
            if (empty($data['merchant_id'])) {
                throw new ValidateException('商家ID不能为空');
            }
        }
        if (!$partial || array_key_exists('title', $data)) {
            if (empty($data['title'])) {
                throw new ValidateException('商品名称不能为空');
            }
        }
        if (!$partial || array_key_exists('platform', $data)) {
            $valid = ['MEITUAN', 'DOUYIN', 'ELEME', 'CUSTOM'];
            if (empty($data['platform']) || !in_array($data['platform'], $valid, true)) {
                throw new ValidateException('平台必须为 MEITUAN/DOUYIN/ELEME/CUSTOM');
            }
        }
        if (!$partial || array_key_exists('jump_url', $data)) {
            if (empty($data['jump_url'])) {
                throw new ValidateException('跳转链接不能为空');
            }
            if (!filter_var($data['jump_url'], FILTER_VALIDATE_URL)) {
                throw new ValidateException('跳转链接不是合法的 URL');
            }
        }
        if (isset($data['price'])) {
            if ((float)$data['price'] < 0) {
                throw new ValidateException('团购价不能为负数');
            }
        }
        if (isset($data['original_price'])) {
            if ((float)$data['original_price'] < 0) {
                throw new ValidateException('原价不能为负数');
            }
        }
        if (isset($data['price'], $data['original_price'])
            && (float)$data['price'] > (float)$data['original_price']
            && (float)$data['original_price'] > 0) {
            throw new ValidateException('团购价不能大于原价');
        }
    }
}
