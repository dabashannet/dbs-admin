<?php

namespace Dabashan\DbsAdmin\Providers;

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
        return base_path('plugins/' . Str::studly($this->pluginName));
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
