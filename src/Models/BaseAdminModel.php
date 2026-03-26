<?php

namespace Dabashan\DbsAdmin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 后台管理模型基类
 *
 * 提供通用的查询作用域和基础配置，所有后台管理相关的非认证模型应继承此类。
 * 认证相关模型（如 AdminUser）因需继承 Authenticatable，不继承此类但应保持一致的风格。
 *
 * @method static Builder latestFirst() 按创建时间倒序排列
 * @method static Builder oldestFirst() 按创建时间正序排列
 * @method static Builder createdBetween(string $start, string $end) 按创建时间范围筛选
 */
abstract class BaseAdminModel extends Model
{
    /**
     * 批量赋值保护 - 后台模型统一使用 guarded 策略
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * 按创建时间倒序排列
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * 按创建时间正序排列
     */
    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at');
    }

    /**
     * 按创建时间范围筛选
     */
    public function scopeCreatedBetween(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
