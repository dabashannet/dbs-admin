<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

/**
 * 插件模型
 * 
 * 存储已安装插件的持久化信息，支持安装/卸载/启用/禁用生命周期管理
 *
 * @property int $id
 * @property string $name 插件标识(唯一)
 * @property string $title 插件名称
 * @property string $version 当前版本
 * @property string|null $description 插件描述
 * @property string|null $author 插件作者
 * @property string|null $icon 插件图标
 * @property string $type 插件类型:local/cloud
 * @property bool $enabled 启用状态
 * @property bool $installed 安装状态
 * @property array|null $menus 插件菜单配置
 * @property array|null $permissions 插件权限
 * @property array|null $config 插件扩展配置
 * @property array|null $providers 插件 ServiceProvider 列表
 * @property bool $show_api 是否在API管理中显示
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Plugin extends BaseAdminModel
{
    protected $table = 'plugins';

    protected $fillable = [
        'name',
        'title',
        'version',
        'description',
        'author',
        'icon',
        'type',
        'enabled',
        'installed',
        'menus',
        'permissions',
        'config',
        'providers',
        'show_api',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'installed' => 'boolean',
        'show_api' => 'boolean',
        'menus' => 'array',
        'permissions' => 'array',
        'config' => 'array',
        'providers' => 'array',
    ];

    /**
     * 仅启用的插件
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * 仅已安装的插件
     */
    public function scopeInstalled($query)
    {
        return $query->where('installed', true);
    }

    /**
     * 转换为前端兼容格式
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        // 添加 path 兼容旧结构
        $studlyName = \Illuminate\Support\Str::studly($this->name);
        $data['path'] = base_path("plugins/{$studlyName}");
        return $data;
    }
}
