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
 * 后台角色模型
 *
 * @property int $id
 * @property string $name 角色名称
 * @property string $slug 角色标识
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminPermission[] $permissions
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminMenu[] $menus
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminUser[] $users
 *
 * @method static Builder bySlug(string $slug) 按角色标识筛选
 */
class AdminRole extends BaseAdminModel
{
    protected $table = 'admin_roles';

    /**
     * 获取角色的所有权限
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_role_permissions', 'role_id', 'permission_id');
    }

    /**
     * 获取角色的所有菜单
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(AdminMenu::class, 'admin_role_menu', 'role_id', 'menu_id');
    }

    /**
     * 获取拥有该角色的所有用户
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, 'admin_role_users', 'role_id', 'user_id');
    }

    /**
     * 按角色标识筛选
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
