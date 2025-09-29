<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 用户模型
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $email
 * @property string $phone
 * @property string $nickname
 * @property string $avatar
 * @property int $gender
 * @property string $birthday
 * @property string $bio
 * @property int $status
 * @property string $last_login_time
 * @property string $create_time
 * @property string $update_time
 * @property string $delete_time
 */
class User extends Model
{
    use SoftDelete;

    protected $name = 'user';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'username'        => 'string',
        'password'        => 'string',
        'email'           => 'string',
        'phone'           => 'string',
        'nickname'        => 'string',
        'avatar'          => 'string',
        'gender'          => 'int',
        'birthday'        => 'string',
        'bio'             => 'string',
        'status'          => 'int',
        'last_login_time' => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
        'delete_time'     => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $deleteTime = 'delete_time';

    // 隐藏字段
    protected $hidden = ['password', 'delete_time'];

    // 字段类型转换
    protected $type = [
        'id'              => 'integer',
        'gender'          => 'integer',
        'status'          => 'integer',
        'last_login_time' => 'timestamp',
        'create_time'     => 'timestamp',
        'update_time'     => 'timestamp',
    ];

    /**
     * 密码修改器
     */
    public function setPasswordAttr($value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $status = [0 => '禁用', 1 => '正常', 2 => '待审核'];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 性别获取器
     */
    public function getGenderTextAttr($value, $data): string
    {
        $gender = [0 => '未知', 1 => '男', 2 => '女'];
        return $gender[$data['gender']] ?? '未知';
    }

    /**
     * 头像获取器
     */
    public function getAvatarAttr($value): string
    {
        return $value ? (strpos($value, 'http') === 0 ? $value : request()->domain() . $value) : '';
    }

    /**
     * 验证密码
     */
    public function checkPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 更新最后登录时间
     */
    public function updateLastLoginTime(): bool
    {
        $this->last_login_time = time();
        return $this->save();
    }

    /**
     * 用户帖子关联
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * 用户关注关联
     */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'user_follow', 'followed_id', 'follower_id');
    }

    /**
     * 用户粉丝关联
     */
    public function following()
    {
        return $this->belongsToMany(User::class, 'user_follow', 'follower_id', 'followed_id');
    }
}