<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 后台权限模型
 *
 * @property int $id
 * @property string $name 权限名称
 * @property string $slug 权限标识
 * @property array|null $http_method 允许的 HTTP 方法
 * @property string|null $http_path 允许的 HTTP 路径
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminRole[] $roles
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminUser[] $users
 *
 * @method static Builder bySlug(string $slug) 按权限标识筛选
 * @method static Builder byHttpMethod(string $method) 按 HTTP 方法筛选
 */
class AdminPermission extends BaseAdminModel
{
    protected $table = 'admin_permissions';

    protected $casts = [
        'http_method' => 'json',
    ];

    /**
     * 获取拥有该权限的所有角色
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_permissions', 'permission_id', 'role_id');
    }

    /**
     * 获取直接拥有该权限的所有用户
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, 'admin_user_permissions', 'permission_id', 'user_id');
    }

    /**
     * 按权限标识筛选
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * 按 HTTP 方法筛选（JSON 字段包含匹配）
     */
    public function scopeByHttpMethod(Builder $query, string $method): Builder
    {
        return $query->whereJsonContains('http_method', $method);
    }
}
