<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Providers;

use Dabashan\DbsAdmin\Models\Plugin;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * 插件 ServiceProvider 基类
 *
 * 插件 ServiceProvider 只需继承此类并设置 $pluginName，无需重复编写路由/迁移加载逻辑。
 *
 * 路由加载：所有请求都会加载 Admin/routes.php 和 Http/routes.php（如文件存在）。
 * 迁移加载：仅在 Console 上下文（artisan migrate / plugin install）执行，Web 请求零损耗。
 *
 * 如需自定义 boot 逻辑，覆写 boot() 并调用 parent::boot()：
 *   public function boot(): void
 *   {
 *       parent::boot();
 *       // 自定义逻辑...
 *   }
 */
abstract class PluginBaseProvider extends ServiceProvider
{
    /**
     * 插件标识（snake_case），必须由子类定义
     * 如 'demo_plugin'、'shop'
     */
    protected string $pluginName = '';

    public function boot(): void
    {
        if (empty($this->pluginName)) {
            return;
        }

        if (!$this->passesInstallationCheck()) {
            logger()->warning("插件 {$this->pluginName} 安装校验失败，已拒绝注册路由");
            return;
        }

        $pluginDir = $this->pluginDir();

        // 路由加载（所有请求）
        $this->loadRouteIfExists($pluginDir . '/Admin/routes.php');
        $this->loadRouteIfExists($pluginDir . '/Http/routes.php');

        // 迁移加载（仅 Console，避免 Web 请求不必要的文件系统检查）
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsIfExists($pluginDir . '/database/migrations');
        }
    }

    /**
     * 获取插件根目录绝对路径
     */
    protected function pluginDir(): string
    {
        $exactPath = base_path('plugins/' . $this->pluginName);
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        $registryPath = public_path('vendor/dbs-plugins/registry.json');
        if (is_file($registryPath)) {
            $registry = json_decode((string) file_get_contents($registryPath), true);
            $version = $registry['plugins'][$this->pluginName]['version'] ?? null;
            if (is_string($version) && $version !== '') {
                $runtimePath = public_path("vendor/dbs-plugins/{$this->pluginName}/{$version}");
                if (is_dir($runtimePath)) {
                    return $runtimePath;
                }
            }
        }

        return base_path('plugins/' . Str::studly($this->pluginName));
    }

    protected function passesInstallationCheck(): bool
    {
        try {
            $plugin = Plugin::query()->where('name', $this->pluginName)->first();
            if (!$plugin || $plugin->type !== 'cloud') {
                return true;
            }

            return $plugin->isValidInstallation();
        } catch (\Throwable $e) {
            logger()->warning("插件 {$this->pluginName} 安装校验异常: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * 文件存在时加载路由
     */
    protected function loadRouteIfExists(string $path): void
    {
        if (file_exists($path)) {
            $this->loadRoutesFrom($path);
        }
    }

    /**
     * 目录存在时加载迁移
     */
    protected function loadMigrationsIfExists(string $path): void
    {
        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }
}
