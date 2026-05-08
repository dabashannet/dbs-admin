<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 后台菜单模型
 *
 * 采用邻接表模式实现菜单树结构，支持无限层级嵌套。
 *
 * @property int $id
 * @property int $parent_id 父级菜单 ID（0 表示顶级）
 * @property int $order 排序值
 * @property string $title 菜单标题
 * @property string|null $icon 菜单图标
 * @property string|null $uri 菜单路径
 * @property bool $show 是否显示
 * @property string $extension 所属扩展标识
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read AdminMenu|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|AdminMenu[] $children
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminRole[] $roles
 *
 * @method static Builder show() 仅显示可见菜单
 * @method static Builder topLevel() 仅顶级菜单
 * @method static Builder byExtension(string $extension) 按扩展标识筛选
 * @method static Builder ordered() 按 order 字段排序
 */
class AdminMenu extends BaseAdminModel
{
    protected $table = 'admin_menus';

    protected $casts = [
        'show' => 'boolean',
        'parent_id' => 'integer',
        'order' => 'integer',
    ];

    /**
     * 获取父级菜单
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AdminMenu::class, 'parent_id');
    }

    /**
     * 获取子级菜单（按 order 排序）
     */
    public function children(): HasMany
    {
        return $this->hasMany(AdminMenu::class, 'parent_id')->orderBy('order');
    }

    /**
     * 获取关联的角色
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_menu', 'menu_id', 'role_id');
    }

    /**
     * 仅显示可见菜单
     */
    public function scopeShow(Builder $query): Builder
    {
        return $query->where('show', true);
    }

    /**
     * 仅顶级菜单（parent_id 为 0）
     */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->where('parent_id', 0);
    }

    /**
     * 按扩展标识筛选
     */
    public function scopeByExtension(Builder $query, string $extension): Builder
    {
        return $query->where('extension', $extension);
    }

    /**
     * 按 order 字段排序
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
