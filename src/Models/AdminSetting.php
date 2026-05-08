<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * 后台系统设置模型
 *
 * 采用键值对方式存储系统配置，以 slug 作为主键。
 * 支持按 group 分组管理配置项。
 *
 * @property string $slug 配置标识（主键）
 * @property string|null $value 配置值
 * @property string $group 配置分组
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @method static Builder byGroup(string $group) 按分组筛选
 * @method static Builder bySlug(string $slug) 按标识筛选
 */
class AdminSetting extends BaseAdminModel
{
    protected $table = 'admin_settings';

    protected $primaryKey = 'slug';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * 按分组筛选
     */
    public function scopeByGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * 按标识筛选
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * 根据 slug 获取配置记录
     */
    public static function getBySlug(string $slug): ?self
    {
        return static::find($slug);
    }

    /**
     * 根据 slug 获取配置值
     *
     * @param string $slug 配置标识
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getValueBySlug(string $slug, mixed $default = null): mixed
    {
        $setting = static::find($slug);

        return $setting ? $setting->value : $default;
    }
}
