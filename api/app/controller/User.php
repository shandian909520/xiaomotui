<?php
declare(strict_types=1);

namespace app\controller;

use app\model\User as UserModel;
use app\model\ContentTask;
use think\facade\Log;
use think\facade\Hash;

class User extends BaseController
{
    /**
     * 获取用户资料
     * GET /api/user/profile
     */
    public function profile()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $user = UserModel::find($userId);

            if (!$user) {
                return $this->error('用户不存在', 404, 'user_not_found');
            }

            return $this->success($user->toArray(), '获取成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_profile_failed');
        }
    }

    /**
     * 更新用户资料
     * POST /api/user/profile
     */
    public function updateProfile()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        $data = $this->request->post();

        try {
            $this->validate($data, [
                'nickname' => 'length:1,50',
                'gender'   => 'in:0,1,2',
                'phone'    => 'mobile',
            ]);

            $user = UserModel::find($userId);
            if (!$user) {
                return $this->error('用户不存在', 404, 'user_not_found');
            }

            $allowFields = ['nickname', 'gender', 'phone'];
            foreach ($allowFields as $field) {
                if (isset($data[$field])) {
                    $user->$field = $data[$field];
                }
            }

            $user->save();

            return $this->success($user->toArray(), '更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'update_failed');
        }
    }

    /**
     * 更新头像
     * POST /api/user/avatar
     */
    public function updateAvatar()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        $avatar = $this->request->post('avatar');
        if (empty($avatar)) {
            return $this->error('头像地址不能为空', 400, 'avatar_required');
        }

        try {
            $user = UserModel::find($userId);
            if (!$user) {
                return $this->error('用户不存在', 404, 'user_not_found');
            }

            $user->avatar = $avatar;
            $user->save();

            return $this->success(['avatar' => $user->avatar], '头像更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'update_avatar_failed');
        }
    }

    /**
     * 修改密码
     * POST /api/user/password
     */
    public function changePassword()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        $data = $this->request->post();

        try {
            $this->validate($data, [
                'old_password' => 'require',
                'new_password' => 'require|length:6,20',
            ]);

            $user = UserModel::find($userId);
            if (!$user) {
                return $this->error('用户不存在', 404, 'user_not_found');
            }

            // 微信小程序用户没有密码，允许直接设置
            return $this->error('当前登录方式不支持修改密码', 400, 'not_supported');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'change_password_failed');
        }
    }

    /**
     * 用户发布的内容
     * GET /api/user/posts
     */
    public function getPosts()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $limit = min($limit, 50);

            $query = ContentTask::where('user_id', $userId);

            $status = $this->request->param('status');
            if ($status !== null) {
                $query->where('status', $status);
            }

            $total = $query->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $limit)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $limit);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_posts_failed');
        }
    }

    /**
     * 粉丝列表
     * GET /api/user/followers
     */
    public function getFollowers()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $limit = min($limit, 50);

            // 预留：粉丝系统需配套follower表
            return $this->paginate([], 0, $page, $limit);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_followers_failed');
        }
    }

    /**
     * 关注列表
     * GET /api/user/following
     */
    public function getFollowing()
    {
        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $limit = min($limit, 50);

            // 预留：关注系统需配套following表
            return $this->paginate([], 0, $page, $limit);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_following_failed');
        }
    }
}
