<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Services;

use Dabashan\DbsAdmin\Events\PluginChanged;
use Dabashan\DbsAdmin\Models\AdminMenu;
use Dabashan\DbsAdmin\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PluginService
{
    /**
     * 获取所有插件列表
     */
    public function getAllPlugins(): array
    {
        return array_values(PluginManager::all());
    }

    /**
     * 安装插件
     */
    public function install(string $pluginName): array
    {
        PluginManager::clearCache();

        $config = PluginManager::readPluginJson($pluginName);
        if (!$config) {
            return ['success' => false, 'message' => '插件配置文件不存在或无效'];
        }

        $dependencyCheck = $this->checkDependencies($config);
        if (!$dependencyCheck['success']) {
            return $dependencyCheck;
        }

        $existing = PluginManager::findFromDb($pluginName);
        if ($existing) {
            return ['success' => false, 'message' => '插件已安装'];
        }

        $providerClass = $config['providers'][0] ?? null;
        if ($providerClass) {
            $classPath = str_replace('\\', '/', preg_replace('/^Plugins\\\\/', '', $providerClass));
            $filePath = base_path('plugins') . '/' . $classPath . '.php';
            if (!file_exists($filePath)) {
                return ['success' => false, 'message' => "插件 ServiceProvider 文件不存在: {$filePath}"];
            }
        }

        $pluginIcon = $config['icon'] ?? 'icon-apps';
        $menus = $config['menus'] ?? null;
        if (is_array($menus) && count($menus) > 0) {
            foreach ($menus as &$menu) {
                if (empty($menu['icon'])) {
                    $menu['icon'] = $pluginIcon;
                }
            }
        }

        try {
            $this->runPluginMigrations($pluginName);
        } catch (\Throwable $e) {
            Log::error('Plugin migrations failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '安装失败: ' . $e->getMessage()];
        }

        try {
            $result = DB::transaction(function () use ($config, $menus, $pluginIcon) {
                $plugin = Plugin::create([
                    'name' => $config['name'],
                    'title' => $config['title'] ?? $config['name'],
                    'version' => $config['version'] ?? '1.0.0',
                    'description' => $config['description'] ?? null,
                    'author' => $config['author'] ?? null,
                    'icon' => $pluginIcon,
                    'type' => $config['type'] ?? 'local',
                    'enabled' => true,
                    'installed' => true,
                    'menus' => $menus,
                    'permissions' => $config['permissions'] ?? null,
                    'config' => $config['config'] ?? null,
                    'providers' => $config['providers'] ?? null,
                    'show_api' => $config['show_api'] ?? true,
                ]);

                return [
                    'success' => true,
                    'message' => '插件安装成功',
                    'plugin' => $plugin->toArray(),
                ];
            });
        } catch (\Throwable $e) {
            try {
                $this->rollbackPluginMigrations($pluginName);
            } catch (\Throwable $rollbackError) {
                Log::error('Plugin rollback migrations failed: ' . $rollbackError->getMessage());
            }
            Log::error('Plugin install failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '安装失败: ' . $e->getMessage()];
        }

        if (env('PLUGIN_DELETE_JSON_ON_INSTALL', false)) {
            try {
                $jsonPath = PluginManager::getPluginJsonPath($pluginName);
                if (File::exists($jsonPath)) {
                    File::delete($jsonPath);
                }
            } catch (\Throwable $e) {
                Log::error('Plugin delete json failed: ' . $e->getMessage());
            }
        }

        PluginManager::clearCache();
        PluginRegistryGenerator::generate();
        event(new PluginChanged($pluginName, 'installed'));

        return $result;
    }

    /**
     * 卸载插件
     */
    public function uninstall(string $pluginName, bool $clearData = false): array
    {
        PluginManager::clearCache();

        $plugin = PluginManager::findFromDb($pluginName);
        if (!$plugin) {
            return ['success' => false, 'message' => '插件未安装'];
        }

        try {
            $this->unregisterPluginMenus($plugin);

            if ($clearData) {
                $this->rollbackPluginMigrations($pluginName);
            }

            $dataMessage = $clearData ? '' : '。为了数据安全，请手动删除插件数据表';

            $this->exportPluginJson($plugin);

            $plugin->delete();

            PluginManager::clearCache();
            PluginRegistryGenerator::generate();
            event(new PluginChanged($pluginName, 'uninstalled'));

            return [
                'success' => true,
                'message' => $clearData ? '插件卸载成功，数据已清除' : '插件卸载成功，数据已保留' . $dataMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Plugin uninstall failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '卸载失败: ' . $e->getMessage()];
        }
    }

    /**
     * 更新插件配置
     */
    public function update(string $pluginName, array $data): array
    {
        PluginManager::clearCache();

        $plugin = PluginManager::findFromDb($pluginName);
        if (!$plugin) {
            return ['success' => false, 'message' => '插件未安装'];
        }

        try {
            $fillable = ['title', 'description', 'icon', 'menus', 'config', 'show_api'];
            $updateData = array_intersect_key($data, array_flip($fillable));

            $plugin->update($updateData);

            if (isset($data['menus'])) {
                $this->unregisterPluginMenus($plugin);
                $this->registerPluginMenus($plugin);
            }

            PluginManager::clearCache();
            PluginRegistryGenerator::generate();

            return [
                'success' => true,
                'message' => '插件更新成功',
                'plugin' => $plugin->fresh()->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Plugin update failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '更新失败: ' . $e->getMessage()];
        }
    }

    /**
     * 升级插件
     */
    public function upgrade(string $pluginName): array
    {
        PluginManager::clearCache();

        $plugin = PluginManager::findFromDb($pluginName);
        if (!$plugin) {
            return ['success' => false, 'message' => '插件未安装'];
        }

        $config = PluginManager::readPluginJson($pluginName);
        if (!$config) {
            return ['success' => false, 'message' => '未找到插件配置文件'];
        }

        $fileVersion = $config['version'] ?? '1.0.0';
        $dbVersion = $plugin->version;

        if (version_compare($fileVersion, $dbVersion, '<=')) {
            return ['success' => false, 'message' => '当前已是最新版本'];
        }

        try {
            $this->runPluginMigrations($pluginName);

            $plugin->update([
                'version' => $fileVersion,
                'title' => $config['title'] ?? $plugin->title,
                'description' => $config['description'] ?? $plugin->description,
                'author' => $config['author'] ?? $plugin->author,
                'icon' => $config['icon'] ?? $plugin->icon,
            ]);

            if (!empty($config['menus'])) {
                $this->unregisterPluginMenus($plugin);
                $this->registerPluginMenus($plugin);
            }

            PluginManager::clearCache();
            PluginRegistryGenerator::generate();
            event(new PluginChanged($pluginName, 'upgraded'));

            return [
                'success' => true,
                'message' => "插件已从 {$dbVersion} 升级到 {$fileVersion}",
                'plugin' => $plugin->fresh()->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Plugin upgrade failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '升级失败: ' . $e->getMessage()];
        }
    }

    /**
     * 切换插件启用/禁用状态
     */
    public function toggleEnabled(string $pluginName, bool $enabled): array
    {
        PluginManager::clearCache();

        $plugin = PluginManager::findFromDb($pluginName);
        if (!$plugin) {
            return ['success' => false, 'message' => '插件未安装'];
        }

        try {
            $plugin->update(['enabled' => $enabled]);

            PluginManager::clearCache();
            PluginRegistryGenerator::generate();
            event(new PluginChanged($pluginName, $enabled ? 'enabled' : 'disabled'));

            return [
                'success' => true,
                'message' => $enabled ? '插件已启用' : '插件已禁用',
                'plugin' => $plugin->fresh()->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Plugin toggle failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '操作失败: ' . $e->getMessage()];
        }
    }

    /**
     * 刷新插件菜单
     */
    public function refreshPluginMenus(string $pluginName): void
    {
        $plugin = PluginManager::findFromDb($pluginName);
        if ($plugin) {
            $this->unregisterPluginMenus($plugin);
            $this->registerPluginMenus($plugin);
        }
    }

    // ==================== 内部方法 ====================

    protected function checkDependencies(array $config): array
    {
        $requires = $config['requires'] ?? [];
        if (empty($requires)) {
            return ['success' => true];
        }

        $installedPlugins = Plugin::installed()->get()->pluck('version', 'name')->toArray();

        foreach ($requires as $dependency => $versionConstraint) {
            if (is_int($dependency)) {
                $dependency = $versionConstraint;
                $versionConstraint = '*';
            }

            if (!isset($installedPlugins[$dependency])) {
                return [
                    'success' => false,
                    'message' => "缺少依赖插件: {$dependency}",
                ];
            }

            if ($versionConstraint !== '*' && !version_compare($installedPlugins[$dependency], $versionConstraint, '>=')) {
                return [
                    'success' => false,
                    'message' => "插件 {$dependency} 版本不满足要求 (>= {$versionConstraint})，当前版本: {$installedPlugins[$dependency]}",
                ];
            }
        }

        return ['success' => true];
    }

    protected function runPluginMigrations(string $pluginName): void
    {
        $pluginStudly = Str::studly($pluginName);
        $pluginKebab = Str::kebab($pluginName);

        $migrationsPath = base_path("plugins/{$pluginName}/database/migrations");
        $migrationsPathArg = "plugins/{$pluginName}/database/migrations";

        if (!File::isDirectory($migrationsPath)) {
            $migrationsPath = base_path("plugins/{$pluginStudly}/database/migrations");
            $migrationsPathArg = "plugins/{$pluginStudly}/database/migrations";
        }

        if (!File::isDirectory($migrationsPath)) {
            $migrationsPath = base_path("plugins/{$pluginKebab}/database/migrations");
            $migrationsPathArg = "plugins/{$pluginKebab}/database/migrations";
        }

        if (File::isDirectory($migrationsPath)) {
            $validateSchema = (bool) env('PLUGIN_MIGRATION_VALIDATE_SCHEMA', true);

            try {
                $exit = Artisan::call('migrate', [
                    '--path' => $migrationsPathArg,
                    '--force' => true,
                ]);
                if ($exit === 0) {
                    return;
                }
                $output = Artisan::output();
                $this->handlePluginMigrationAlreadyExists($migrationsPath, $migrationsPathArg, $output, $validateSchema);
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $this->handlePluginMigrationAlreadyExists($migrationsPath, $migrationsPathArg, $message, $validateSchema, $e);
            }
        }
    }

    protected function handlePluginMigrationAlreadyExists(
        string $migrationsPath,
        string $migrationsPathArg,
        string $message,
        bool $validateSchema,
        ?\Throwable $e = null
    ): void {
        $table = $this->extractAlreadyExistsTableName($message);
        if (!$table) {
            if ($e) throw $e;
            throw new \Exception(trim($message) ?: '插件迁移执行失败');
        }

        $marked = $this->markPluginMigrationsAsRanForTable($migrationsPath, $table, $validateSchema);
        if (!$marked) {
            if ($e) throw $e;
            throw new \Exception(trim($message) ?: '插件迁移执行失败');
        }

        $exit = Artisan::call('migrate', [
            '--path' => $migrationsPathArg,
            '--force' => true,
        ]);
        if ($exit !== 0) {
            $out = Artisan::output();
            throw new \Exception(trim($out) ?: '插件迁移执行失败');
        }
    }

    protected function extractAlreadyExistsTableName(string $message): ?string
    {
        if (preg_match("/Table '([^']+)' already exists/i", $message, $m)) {
            return $m[1];
        }
        if (preg_match('/create table `([^`]+)`/i', $message, $m)) {
            return $m[1];
        }
        if (preg_match('/Base table or view already exists.*?`([^`]+)`/i', $message, $m)) {
            return $m[1];
        }
        return null;
    }

    protected function markPluginMigrationsAsRanForTable(string $migrationsPath, string $table, bool $validateSchema): bool
    {
        if (!Schema::hasTable('migrations')) {
            return false;
        }
        if (!File::isDirectory($migrationsPath)) {
            return false;
        }
        if (!Schema::hasTable($table)) {
            return false;
        }

        $expected = $this->extractExpectedSchemaFromMigrations($migrationsPath, $table);
        if (!$expected) {
            return false;
        }
        if ($validateSchema && !$this->isTableSchemaCompatible($table, $expected)) {
            throw new \Exception("插件安装失败：数据表 {$table} 已存在且字段结构不匹配");
        }

        $batch = (int) (DB::table('migrations')->max('batch') ?? 0) + 1;
        $marked = false;
        foreach (File::files($migrationsPath) as $file) {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $schema = $this->extractCreateTableSchemaFromMigrationFile($file->getPathname());
            if (!$schema) continue;
            if (($schema['table'] ?? '') !== $table) continue;
            $exists = DB::table('migrations')->where('migration', $name)->exists();
            if ($exists) continue;
            DB::table('migrations')->insert([
                'migration' => $name,
                'batch' => $batch,
            ]);
            $marked = true;
        }
        return $marked;
    }

    protected function extractExpectedSchemaFromMigrations(string $migrationsPath, string $table): ?array
    {
        foreach (File::files($migrationsPath) as $file) {
            $schema = $this->extractCreateTableSchemaFromMigrationFile($file->getPathname());
            if (!$schema) continue;
            if (($schema['table'] ?? '') !== $table) continue;
            return $schema['columns'] ?? null;
        }
        return null;
    }

    protected function extractCreateTableSchemaFromMigrationFile(string $path): ?array
    {
        $content = @file_get_contents($path);
        if ($content === false) return null;
        if (!preg_match("/Schema::create\\(\\s*'([^']+)'\\s*,/i", $content, $m)) {
            return null;
        }
        $table = $m[1];
        $columns = [];

        if (preg_match('/\\$table->id\\s*\\(\\s*\\)\\s*;/', $content)) {
            $columns['id'] = ['type' => 'bigint', 'unsigned' => true];
        }
        if (preg_match_all('/\\$table->(string|text|integer|bigInteger|decimal|float|boolean|date|dateTime|timestamp|json)\\(\\s*\\\'([^\\\']+)\\\'\\s*(?:,\\s*([0-9]+))?\\s*(?:,\\s*([0-9]+))?\\s*\\)/i', $content, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $row) {
                $t = $row[1];
                $name = $row[2];
                $p1 = isset($row[3]) && $row[3] !== '' ? (int) $row[3] : null;
                $p2 = isset($row[4]) && $row[4] !== '' ? (int) $row[4] : null;
                $col = ['type' => strtolower($t)];
                if ($p1 !== null) $col['p1'] = $p1;
                if ($p2 !== null) $col['p2'] = $p2;
                $columns[$name] = $col;
            }
        }
        if (preg_match('/\\$table->timestamps\\s*\\(\\s*\\)\\s*;/', $content)) {
            $columns['created_at'] = ['type' => 'timestamp'];
            $columns['updated_at'] = ['type' => 'timestamp'];
        }
        if (preg_match('/\\$table->softDeletes\\s*\\(\\s*\\)\\s*;/', $content)) {
            $columns['deleted_at'] = ['type' => 'timestamp'];
        }

        return ['table' => $table, 'columns' => $columns];
    }

    protected function isTableSchemaCompatible(string $table, array $expectedColumns): bool
    {
        $actualColumns = Schema::getColumnListing($table);
        $actualSet = array_fill_keys($actualColumns, true);
        foreach ($expectedColumns as $name => $_) {
            if (!isset($actualSet[$name])) return false;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            return true;
        }

        $rows = DB::select("SHOW COLUMNS FROM `{$table}`");
        $map = [];
        foreach ($rows as $row) {
            $field = $row->Field ?? null;
            if (!$field) continue;
            $map[$field] = strtolower((string) ($row->Type ?? ''));
        }
        foreach ($expectedColumns as $name => $expect) {
            $actualType = $map[$name] ?? '';
            if ($actualType === '') return false;
            if (!$this->isMysqlTypeCompatible($expect, $actualType)) return false;
        }
        return true;
    }

    protected function isMysqlTypeCompatible(array $expected, string $actualType): bool
    {
        $type = $expected['type'] ?? '';
        if ($type === '') return true;

        $actual = strtolower($actualType);

        $typeMap = [
            'string' => 'varchar', 'text' => 'text', 'integer' => 'int',
            'biginteger' => 'bigint', 'decimal' => 'decimal', 'float' => 'float',
            'boolean' => 'tinyint', 'date' => 'date', 'datetime' => 'datetime',
            'timestamp' => 'timestamp', 'json' => 'json', 'bigint' => 'bigint',
        ];
        $base = $typeMap[$type] ?? $type;

        if ($base === 'timestamp') {
            if (!str_starts_with($actual, 'timestamp') && !str_starts_with($actual, 'datetime')) return false;
        } else {
            if (!str_starts_with($actual, $base)) return false;
        }

        if (!empty($expected['unsigned']) && !str_contains($actual, 'unsigned')) return false;

        if ($base === 'varchar' && isset($expected['p1'])) {
            if (preg_match('/varchar\\((\\d+)\\)/', $actual, $m)) {
                return (int) $m[1] === (int) $expected['p1'];
            }
        }

        if ($base === 'decimal' && isset($expected['p1'], $expected['p2'])) {
            if (preg_match('/decimal\\((\\d+),(\\d+)\\)/', $actual, $m)) {
                return (int) $m[1] === (int) $expected['p1'] && (int) $m[2] === (int) $expected['p2'];
            }
        }

        if ($base === 'tinyint' && str_starts_with($actual, 'tinyint')) {
            return true;
        }

        return true;
    }

    protected function rollbackPluginMigrations(string $pluginName): void
    {
        $pluginStudly = Str::studly($pluginName);
        $pluginKebab = Str::kebab($pluginName);

        $migrationsPath = base_path("plugins/{$pluginName}/database/migrations");
        $migrationsPathArg = "plugins/{$pluginName}/database/migrations";

        if (!File::isDirectory($migrationsPath)) {
            $migrationsPath = base_path("plugins/{$pluginStudly}/database/migrations");
            $migrationsPathArg = "plugins/{$pluginStudly}/database/migrations";
        }

        if (!File::isDirectory($migrationsPath)) {
            $migrationsPath = base_path("plugins/{$pluginKebab}/database/migrations");
            $migrationsPathArg = "plugins/{$pluginKebab}/database/migrations";
        }

        if (File::isDirectory($migrationsPath)) {
            $files = File::files($migrationsPath);
            $steps = count($files);

            if ($steps > 0) {
                Artisan::call('migrate:rollback', [
                    '--path' => $migrationsPathArg,
                    '--step' => $steps,
                    '--force' => true,
                ]);
            }
        }
    }

    protected function registerPluginMenus(Plugin $plugin): void
    {
        if (empty($plugin->menus)) {
            return;
        }

        $appMenu = AdminMenu::firstOrCreate(
            ['title' => '应用'],
            [
                'parent_id' => 0,
                'order' => 99,
                'icon' => 'icon-apps',
                'uri' => 'plugin',
                'show' => 1,
            ]
        );

        foreach ($plugin->menus as $menu) {
            $this->registerMenuRecursive($appMenu->id, $menu);
        }
    }

    protected function registerMenuRecursive(int $parentId, array $menu): void
    {
        $menuModel = AdminMenu::updateOrCreate(
            [
                'parent_id' => $parentId,
                'title' => $menu['title'],
            ],
            [
                'icon' => $menu['icon'] ?? '',
                'uri' => $menu['uri'] ?? '',
                'show' => 1,
            ]
        );

        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                $this->registerMenuRecursive($menuModel->id, $child);
            }
        }
    }

    protected function unregisterPluginMenus(Plugin $plugin): void
    {
        if (empty($plugin->menus)) {
            return;
        }

        $appMenu = AdminMenu::where('title', '应用')->first();
        if (!$appMenu) {
            return;
        }

        foreach ($plugin->menus as $menu) {
            $pluginMenu = AdminMenu::where('parent_id', $appMenu->id)
                ->where('title', $menu['title'])
                ->first();

            if ($pluginMenu) {
                $this->unregisterMenuRecursive($pluginMenu->id);
                $pluginMenu->delete();
            }
        }
    }

    protected function unregisterMenuRecursive(int $parentId): void
    {
        $children = AdminMenu::where('parent_id', $parentId)->get();
        foreach ($children as $child) {
            $this->unregisterMenuRecursive($child->id);
            $child->delete();
        }
    }

    protected function exportPluginJson(Plugin $plugin): void
    {
        $pluginPath = PluginManager::getPluginPath($plugin->name);
        $jsonPath = "{$pluginPath}/manifest.json";

        if (!File::isDirectory($pluginPath)) {
            return;
        }

        $config = [
            'name' => $plugin->name,
            'title' => $plugin->title,
            'description' => $plugin->description,
            'version' => $plugin->version,
            'author' => $plugin->author,
            'enabled' => false,
            'show_api' => $plugin->show_api,
            'icon' => $plugin->icon,
            'providers' => $plugin->providers ?? [],
            'permissions' => $plugin->permissions ?? [],
            'menus' => $plugin->menus ?? [],
        ];

        File::put($jsonPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
