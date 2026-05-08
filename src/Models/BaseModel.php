<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 公共 HTTP 端基础模型
 *
 * 面向业务端模型的基础类，提供统一的日期序列化和查询便利方法。
 * 后台管理模型请继承 BaseAdminModel。
 */
abstract class BaseModel extends Model
{
    /**
     * 日期序列化格式
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 默认作用域：按创建时间降序
     */
    protected static function booted(): void
    {
        static::addGlobalScope('sorted', function ($builder) {
            $builder->orderByDesc('created_at');
        });
    }
}
