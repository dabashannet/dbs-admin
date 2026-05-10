<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

/**
 * 后台管理员用户模型
 *
 * @property int $id
 * @property string $username 用户名
 * @property string $password 密码
 * @property string $name 姓名
 * @property string|null $avatar 头像
 * @property string|null $remember_token
 * @property string|null $openid 微信 OpenID
 * @property string|null $phone 手机号
 * @property int|null $parent_id 上级用户 ID
 * @property bool $status 账号状态
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminRole[] $roles
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminPermission[] $permissions
 *
 * @method static Builder active() 筛选启用状态的用户
 * @method static Builder byUsername(string $username) 按用户名筛选
 */
class AdminUser extends BaseAuthenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'admin_users';

    protected $fillable = [
        'username',
        'password',
        'name',
        'avatar',
        'openid',
        'phone',
        'parent_id',
        'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * 密码自动加密
     *
     * 使用 Laravel 的 Attribute Cast，密码在设置时自动进行 Hash 加密
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn(?string $value) => $value ? Hash::make($value) : null,
        );
    }

    /**
     * 权限检查
     *
     * 超级管理员拥有全部权限，普通用户通过权限标识检查。
     *
     * @param string|iterable $abilities 权限标识或权限标识数组
     * @param mixed $arguments
     */
    public function can($abilities, $arguments = []): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        $userPermissions = $this->allPermissions()->pluck('slug');

        if (is_string($abilities)) {
            return $userPermissions->contains($abilities);
        }

        foreach ((array) $abilities as $ability) {
            if (!$userPermissions->contains($ability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 筛选启用状态的用户
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * 按用户名筛选
     */
    public function scopeByUsername(Builder $query, string $username): Builder
    {
        return $query->where('username', $username);
    }

    /**
     * 获取用户的所有角色
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_users', 'user_id', 'role_id');
    }

    /**
     * 获取用户直接分配的权限
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_user_permissions', 'user_id', 'permission_id');
    }

    /**
     * 判断是否为超级管理员
     *
     * 满足以下任一条件即为超级管理员：
     * - 拥有 slug 为 'administrator' 的角色
     * - 用户 ID 为 1（系统初始管理员）
     */
    public function isAdministrator(): bool
    {
        return $this->roles()->where('slug', 'administrator')->exists() || $this->id === 1;
    }

    /**
     * 获取用户的所有权限（包含角色权限和直接权限）
     *
     * 合并用户直接分配的权限和通过角色继承的权限，并去重。
     */
    public function allPermissions(): Collection
    {
        $roles = $this->roles()->with('permissions')->get();
        $permissions = $this->permissions;

        foreach ($roles as $role) {
            $permissions = $permissions->merge($role->permissions);
        }

        return $permissions->unique('id');
    }

    /**
     * 判断用户是否拥有指定权限
     *
     * @param string $permissionSlug 权限标识
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        return $this->allPermissions()->pluck('slug')->contains($permissionSlug);
    }
}
