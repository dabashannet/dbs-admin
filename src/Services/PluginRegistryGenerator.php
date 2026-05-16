<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * 前端插件组件注册表生成器
 *
 * 扫描已启用插件的 resources/views/ 目录，
 * 生成 web/src/plugin-registry.ts 静态注册表，
 * 替代运行时 import.meta.glob 全量扫描。
 */
class PluginRegistryGenerator
{
    /**
     * 注册表输出目录（相对于 base_path），可被宿主应用覆盖
     */
    public static string $webDir = 'web';

    /**
     * 生成前端插件注册表文件
     *
     * @return int 注册的组件数量
     */
    public static function generate(): int
    {
        $registryPath = base_path(static::$webDir . '/src/plugin-registry.ts');
        $plugins = PluginManager::enabled();

        $entries = [];
        $studlyCache = [];

        foreach ($plugins as $plugin) {
            $name = $plugin['name'] ?? '';
            if (empty($name)) {
                continue;
            }

            if (!isset($studlyCache[$name])) {
                $studlyCache[$name] = Str::studly($name);
            }
            $studly = $studlyCache[$name];

            $pluginDir = base_path("plugins/{$name}");
            if (!File::isDirectory($pluginDir)) {
                $pluginDir = base_path("plugins/{$studly}");
            }
            $viewsDir = $pluginDir . '/resources/views';
            if (!File::isDirectory($viewsDir)) {
                continue;
            }

            $files = File::allFiles($viewsDir);
            foreach ($files as $file) {
                if ($file->getExtension() !== 'vue') {
                    continue;
                }

                $relativePath = $file->getRelativePathname();
                $key = substr($relativePath, 0, -4);
                $key = str_replace('\\', '/', $key);

                $importPath = '@plugins/' . basename($pluginDir) . '/resources/views/' . $relativePath;

                $entries[] = [
                    'key' => $name . ':' . $key,
                    'importPath' => $importPath,
                ];
            }
        }

        $content = static::buildRegistryContent($entries);

        File::ensureDirectoryExists(dirname($registryPath));
        File::put($registryPath, $content);

        return count($entries);
    }

    /**
     * 构建注册表文件内容
     */
    protected static function buildRegistryContent(array $entries): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $lines = [];

        $lines[] = '// 自动生成 — 请勿手动修改';
        $lines[] = '// 生成时间: ' . $timestamp;
        $lines[] = '// 当插件安装/卸载/启用/禁用时自动更新';
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * 插件组件注册表';
        $lines[] = ' * key 格式: {plugin_name}:{views_relative_path}';
        $lines[] = ' */';
        $lines[] = 'const registry: Record<string, () => Promise<any>> = {';

        foreach ($entries as $entry) {
            $lines[] = "  '{$entry['key']}': () => import('{$entry['importPath']}'),";
        }

        $lines[] = '};';
        $lines[] = '';
        $lines[] = 'export default registry;';

        return implode("\n", $lines) . "\n";
    }
}
