<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-10 21:42:20
 * @LastEditTime: 2026-05-10 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

use DateTimeInterface;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * 认证端基础模型
 *
 * 所有需要认证（Authenticatable）的业务模型应继承此类。
 * 确保日期序列化格式全局统一为 Y-m-d H:i:s。
 */
abstract class BaseAuthenticatable extends Authenticatable
{
    /**
     * 日期序列化格式
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
