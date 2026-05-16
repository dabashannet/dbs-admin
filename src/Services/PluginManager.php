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

        $cached = static::cacheGet(self::CACHE_KEY_ALL);
        if ($cached !== null) {
            static::$plugins = $cached;
            return static::$plugins;
        }

        static::$plugins = [];

        if (static::checkTableExists()) {
            static::$plugins = static::loadFromDatabase();
        }

        static::loadFromRuntimeRegistry(static::$plugins, false);
        static::scanPluginsDirectory();

        static::cachePut(self::CACHE_KEY_ALL, static::$plugins, self::CACHE_TTL);

        return static::$plugins;
    }

    /**
     * 获取所有启用的插件
     */
    public static function enabled(): array
    {
        $cached = static::cacheGet(self::CACHE_KEY_ENABLED);
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

                static::loadFromRuntimeRegistry($plugins, true);

                static::cachePut(self::CACHE_KEY_ENABLED, $plugins, self::CACHE_TTL);
                return $plugins;
            } catch (\Exception $e) {
                // 回退到文件系统
            }
        }

        $plugins = array_filter(static::all(), fn($p) => ($p['enabled'] ?? false) === true);

        static::cachePut(self::CACHE_KEY_ENABLED, $plugins, self::CACHE_TTL);
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

    protected static function loadFromRuntimeRegistry(array &$plugins, bool $enabledOnly): void
    {
        $registryPath = public_path('vendor/dbs-plugins/registry.json');
        if (!File::exists($registryPath) || !static::verifyRuntimeRegistry($registryPath)) {
            return;
        }

        $registry = json_decode(File::get($registryPath), true);
        foreach (($registry['plugins'] ?? []) as $name => $entry) {
            if ($enabledOnly && empty($entry['enabled'])) {
                continue;
            }

            if (isset($plugins[$name])) {
                $plugins[$name] = array_merge($plugins[$name], [
                    'frontend' => $entry['frontend'] ?? null,
                    'registry' => true,
                    'runtime_path' => public_path('vendor/dbs-plugins/' . $name . '/' . ($entry['version'] ?? '')),
                ]);
                continue;
            }

            $plugins[$name] = [
                'name' => $name,
                'title' => $entry['name'] ?? $name,
                'version' => $entry['version'] ?? '1.0.0',
                'type' => 'cloud',
                'enabled' => (bool) ($entry['enabled'] ?? false),
                'installed' => true,
                'status' => !empty($entry['enabled']) ? 'enabled' : 'disabled',
                'menus' => $entry['menus'] ?? [],
                'permissions' => $entry['permissions'] ?? [],
                'providers' => $entry['providers'] ?? [],
                'frontend' => $entry['frontend'] ?? null,
                'registry' => true,
                'path' => public_path('vendor/dbs-plugins/' . $name . '/' . ($entry['version'] ?? '')),
                'runtime_path' => public_path('vendor/dbs-plugins/' . $name . '/' . ($entry['version'] ?? '')),
            ];
        }
    }

    protected static function verifyRuntimeRegistry(string $registryPath): bool
    {
        $sigPath = $registryPath . '.sig';
        if (!File::exists($sigPath)) {
            \Illuminate\Support\Facades\Log::warning('插件 registry.json.sig 不存在，已拒绝加载商业插件 registry');
            return false;
        }

        $secret = (string) config('dbs_agent.site_secret', config('dbs_agent.token', ''));
        if ($secret === '') {
            \Illuminate\Support\Facades\Log::warning('缺少 registry 验签密钥，已拒绝加载商业插件 registry');
            return false;
        }

        $mac = hash_hmac('sha256', File::get($registryPath), $secret);
        $sig = trim(File::get($sigPath));
        if (!hash_equals($mac, $sig)) {
            \Illuminate\Support\Facades\Log::warning('插件 registry.json 签名校验失败，已拒绝加载商业插件 registry');
            return false;
        }

        return true;
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
            $jsonPath = static::findManifestPath($dir);
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
            if ($expectedDirName !== $actualDirName && $pluginName !== $actualDirName) {
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
                $filePath = static::resolveProviderFilePath($plugin, $relativePath);
                if (!$filePath) {
                    return null;
                }
                require_once $filePath;
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
        $exactPath = base_path('plugins/' . $name);
        if (File::isDirectory($exactPath)) {
            return $exactPath;
        }

        return base_path('plugins/' . Str::studly($name));
    }

    /**
     * 获取插件 manifest 配置路径
     */
    public static function getPluginJsonPath(string $name): string
    {
        $pluginPath = static::getPluginPath($name);

        return static::findManifestPath($pluginPath);
    }

    /**
     * 读取插件 manifest 配置
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

    protected static function findManifestPath(string $pluginPath): string
    {
        return rtrim($pluginPath, '/\\') . '/manifest.json';
    }

    protected static function resolveProviderFilePath(array $plugin, string $relativePath): ?string
    {
        $relative = str_replace('\\', '/', $relativePath) . '.php';
        $pluginName = (string) ($plugin['name'] ?? '');
        $candidates = [
            base_path('plugins/' . $relative),
        ];

        if (!empty($plugin['runtime_path'])) {
            $runtimeRelative = $relative;
            $prefix = $pluginName !== '' ? $pluginName . '/' : '';
            if ($prefix !== '' && str_starts_with($runtimeRelative, $prefix)) {
                $runtimeRelative = substr($runtimeRelative, strlen($prefix));
            }
            $candidates[] = rtrim((string) $plugin['runtime_path'], '/\\') . '/' . $runtimeRelative;
        }

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
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
        static::cacheForget(self::CACHE_KEY_ENABLED);
        static::cacheForget(self::CACHE_KEY_ALL);
    }

    protected static function cacheGet(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function cachePut(string $key, mixed $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\Throwable $e) {
            //
        }
    }

    protected static function cacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $e) {
            //
        }
    }
}
