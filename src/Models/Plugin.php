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
 * @property string|null $installation_hash Agent 安装哈希
 * @property string|null $installed_at Agent 安装时间
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
        'installation_hash',
        'installed_at',
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
        'installed_at' => 'datetime',
    ];

    public function isValidInstallation(): bool
    {
        if ($this->type !== 'cloud') {
            return true;
        }

        if (empty($this->installation_hash) || empty($this->installed_at)) {
            return false;
        }

        $secret = (string) config('dbs_agent.site_secret', config('dbs_agent.token', ''));
        $siteId = (string) config('dbs_agent.site_id', env('DBS_SITE_ID', ''));
        if ($secret === '' || $siteId === '') {
            return false;
        }

        $installedAt = method_exists($this->installed_at, 'toISOString')
            ? $this->installed_at->toISOString()
            : (string) $this->installed_at;

        $expected = hash_hmac('sha256', $siteId . '|' . $this->name . '|' . $this->version . '|' . $installedAt, $secret);

        return hash_equals($expected, (string) $this->installation_hash);
    }

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
