<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Services;

use Dabashan\DbsAdmin\Models\AdminSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    /**
     * 读取设置项（带缓存，TTL 3600秒）
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember('admin_settings.' . $key, 3600, function () use ($key, $default) {
            $setting = AdminSetting::find($key);
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * 写入设置项并清除缓存
     */
    public static function set(string $key, mixed $value, string $group = 'default'): void
    {
        AdminSetting::updateOrCreate(
            ['slug' => $key],
            ['value' => $value, 'group' => $group]
        );
        Cache::forget('admin_settings.' . $key);
    }

    /**
     * 删除设置项并清除缓存
     */
    public static function forget(string $key): void
    {
        AdminSetting::where('slug', $key)->delete();
        Cache::forget('admin_settings.' . $key);
    }
}
