<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Services;

use Dabashan\DbsAdmin\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PluginManager
{
    protected static ?array $plugins = null;
    protected static ?bool $tableExists = null;

    const CACHE_KEY_ENABLED = 'plugin_manager_enabled';
    const CACHE_KEY_ALL = 'plugin_manager_all';
    const CACHE_TTL = 3600;

    public static array $registeredProviders = [];

    /**
     * 检查 plugins 表是否存在（含 per-request 静态缓存）
     */
    protected static function checkTableExists(): bool
    {
        if (static::$tableExists === null) {
            try {
                static::$tableExists = Schema::hasTable('plugins');
            } catch (\Exception $e) {
                static::$tableExists = false;
            }
        }
        return static::$tableExists;
    }

    /**
     * 获取所有插件信息（已安装 + 未安装）
     */
    public static function all(): array
    {
        if (static::$plugins !== null) {
            return static::$plugins;
        }

        $cached = Cache::get(self::CACHE_KEY_ALL);
        if ($cached !== null) {
            static::$plugins = $cached;
            return static::$plugins;
        }

        static::$plugins = [];

        if (static::checkTableExists()) {
            static::$plugins = static::loadFromDatabase();
        }

        static::scanPluginsDirectory();

        Cache::put(self::CACHE_KEY_ALL, static::$plugins, self::CACHE_TTL);

        return static::$plugins;
    }

    /**
     * 获取所有启用的插件
     */
    public static function enabled(): array
    {
        $cached = Cache::get(self::CACHE_KEY_ENABLED);
        if ($cached !== null) {
            return $cached;
        }

        if (static::checkTableExists()) {
            $plugins = [];
            try {
                $records = Plugin::enabled()->installed()->get();
                foreach ($records as $record) {
                    $plugins[$record->name] = array_merge($record->toArray(), [
                        'status' => 'enabled',
                    ]);
                }

                Cache::put(self::CACHE_KEY_ENABLED, $plugins, self::CACHE_TTL);
                return $plugins;
            } catch (\Exception $e) {
                // 回退到文件系统
            }
        }

        $plugins = array_filter(static::all(), fn($p) => ($p['enabled'] ?? false) === true);

        Cache::put(self::CACHE_KEY_ENABLED, $plugins, self::CACHE_TTL);
        return $plugins;
    }

    /**
     * 从数据库加载已安装的插件
     */
    protected static function loadFromDatabase(): array
    {
        $plugins = [];
        try {
            $records = Plugin::all();
            foreach ($records as $record) {
                $plugins[$record->name] = array_merge($record->toArray(), [
                    'installed' => true,
                    'status' => $record->enabled ? 'enabled' : 'disabled',
                ]);
            }
        } catch (\Exception $e) {
            // 数据库异常，返回空数组
        }
        return $plugins;
    }

    /**
     * 扫描 plugins 目录，发现未安装的插件
     */
    protected static function scanPluginsDirectory(): void
    {
        $pluginsPath = base_path('plugins');

        if (!File::isDirectory($pluginsPath)) {
            return;
        }

        foreach (File::directories($pluginsPath) as $dir) {
            $jsonPath = $dir . '/plugin.json';
            if (!File::exists($jsonPath)) {
                continue;
            }

            $config = json_decode(File::get($jsonPath), true);
            if (!$config || empty($config['name'])) {
                continue;
            }

            $pluginName = $config['name'];

            if (isset(static::$plugins[$pluginName])) {
                continue;
            }

            $expectedDirName = Str::studly($pluginName);
            $actualDirName = basename($dir);
            if ($expectedDirName !== $actualDirName) {
                continue;
            }

            $config['path'] = $dir;
            $config['installed'] = false;
            $config['enabled'] = false;
            $config['status'] = 'not_installed';
            $config['type'] = $config['type'] ?? 'local';
            static::$plugins[$pluginName] = $config;
        }
    }

    /**
     * 注册所有启用插件的 ServiceProvider
     */
    public static function boot(\Illuminate\Foundation\Application $app): void
    {
        foreach (static::enabled() as $plugin) {
            $providerClass = static::getProviderClass($plugin);
            if ($providerClass && !isset(static::$registeredProviders[$providerClass])) {
                try {
                    static::$registeredProviders[$providerClass] = true;
                    $app->register($providerClass);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Plugin boot failed [{$plugin['name']}]: {$e->getMessage()}");
                    static::$registeredProviders[$providerClass] = 'failed';
                }
            }
        }
    }

    /**
     * 获取插件 ServiceProvider 类名
     */
    public static function getProviderClass(array $plugin): ?string
    {
        $studlyName = Str::studly($plugin['name']);

        if (!empty($plugin['providers']) && is_array($plugin['providers'])) {
            $providerClass = $plugin['providers'][0];
            if (preg_match('/Plugins\\\\(.+)$/', $providerClass, $matches)) {
                $relativePath = $matches[1];
                $basePath = base_path('plugins');
                $filePath = $basePath . '/' . str_replace('\\', '/', $relativePath) . '.php';
                if (!file_exists($filePath)) {
                    return null;
                }
            }
            return $providerClass;
        }

        $newPath = "Plugins\\{$studlyName}\\Providers\\PluginServiceProvider";
        $oldPath = "Plugins\\{$studlyName}\\PluginServiceProvider";

        $basePath = base_path('plugins');
        $newFilePath = $basePath . '/' . $studlyName . '/Providers/PluginServiceProvider.php';
        $oldFilePath = $basePath . '/' . $studlyName . '/PluginServiceProvider.php';

        if (file_exists($newFilePath)) {
            return $newPath;
        }
        if (file_exists($oldFilePath)) {
            return $oldPath;
        }
        return null;
    }

    /**
     * 获取插件目录路径
     */
    public static function getPluginPath(string $name): string
    {
        return base_path('plugins/' . Str::studly($name));
    }

    /**
     * 获取插件 JSON 配置路径
     */
    public static function getPluginJsonPath(string $name): string
    {
        return static::getPluginPath($name) . '/plugin.json';
    }

    /**
     * 读取插件 JSON 配置
     */
    public static function readPluginJson(string $name): ?array
    {
        $jsonPath = static::getPluginJsonPath($name);
        if (!File::exists($jsonPath)) {
            return null;
        }

        $config = json_decode(File::get($jsonPath), true);
        return $config && !empty($config['name']) ? $config : null;
    }

    /**
     * 获取单个插件信息
     */
    public static function find(string $name): ?array
    {
        return static::all()[$name] ?? null;
    }

    /**
     * 从数据库获取插件记录
     */
    public static function findFromDb(string $name): ?Plugin
    {
        if (!static::checkTableExists()) {
            return null;
        }

        try {
            return Plugin::where('name', $name)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 清除所有缓存
     */
    public static function clearCache(): void
    {
        static::$plugins = null;
        static::$tableExists = null;
        Cache::forget(self::CACHE_KEY_ENABLED);
        Cache::forget(self::CACHE_KEY_ALL);
    }
}
