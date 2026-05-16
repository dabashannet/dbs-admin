<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-16 12:19:09
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */

namespace Dabashan\DbsAdmin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class PackagePluginCommand extends Command
{
    protected $signature = 'plugin:package
                            {name : 插件标识（snake_case，如 dbs_plugin）}
                            {--version= : 覆盖 manifest 版本号}
                            {--output= : 输出目录，默认 storage/app/plugin-packages}
                            {--include-source : 同时打包 resources 源码}';

    protected $description = '将本地插件打包为授权端可上传的 .dbspkg 商业插件包';

    public function handle(): int
    {
        if (!class_exists(ZipArchive::class)) {
            $this->error('当前 PHP 未启用 ZipArchive，无法生成 .dbspkg');
            return Command::FAILURE;
        }

        $name = Str::snake((string) $this->argument('name'));
        $pluginPath = base_path("plugins/{$name}");
        $manifestPath = "{$pluginPath}/manifest.json";

        if (!File::isDirectory($pluginPath) || !File::exists($manifestPath)) {
            $this->error("未找到插件 manifest：{$manifestPath}");
            return Command::FAILURE;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        if (!is_array($manifest) || ($manifest['name'] ?? null) !== $name) {
            $this->error('manifest.json 无效，或 name 与插件目录不一致');
            return Command::FAILURE;
        }

        $version = (string) ($this->option('version') ?: ($manifest['version'] ?? '1.0.0'));
        $manifest['version'] = $version;
        $manifest['type'] = 'commercial';
        $manifest['frontend_entry'] = $manifest['frontend_entry'] ?? "assets/{$name}.js";
        $manifest['frontend_css'] = $manifest['frontend_css'] ?? "assets/{$name}.css";
        $manifest['requires_build'] = false;

        $outputDir = (string) ($this->option('output') ?: storage_path('app/plugin-packages'));
        File::ensureDirectoryExists($outputDir);

        $buildDir = storage_path("app/plugin-packages/.build/{$name}-{$version}");
        File::deleteDirectory($buildDir);
        File::ensureDirectoryExists($buildDir);

        File::put("{$buildDir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->copyPackageFiles($pluginPath, $buildDir, (bool) $this->option('include-source'));
        $this->ensureRuntimeAssets($buildDir, $name);

        $packagePath = "{$outputDir}/{$name}-v{$version}.dbspkg";
        if (File::exists($packagePath)) {
            File::delete($packagePath);
        }

        $zip = new ZipArchive();
        if ($zip->open($packagePath, ZipArchive::CREATE) !== true) {
            $this->error("无法创建包：{$packagePath}");
            return Command::FAILURE;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen($buildDir) + 1));
            $zip->addFile($absolute, $relative);
        }
        $zip->close();

        File::deleteDirectory($buildDir);

        $hash = hash_file('sha256', $packagePath);
        $this->info("插件包已生成：{$packagePath}");
        $this->line("SHA256：{$hash}");
        $this->line('授权端上传：release/app-updates/create?application_id=46，文件类型选择 dbspkg，版本号填写 manifest/version。');

        return Command::SUCCESS;
    }

    protected function copyPackageFiles(string $pluginPath, string $buildDir, bool $includeSource): void
    {
        $allowedDirs = ['Providers', 'Admin', 'Http', 'Models', 'Services', 'config', 'database', 'assets'];
        if ($includeSource) {
            $allowedDirs[] = 'resources';
        }

        foreach ($allowedDirs as $dir) {
            $source = "{$pluginPath}/{$dir}";
            if (File::isDirectory($source)) {
                File::copyDirectory($source, "{$buildDir}/{$dir}");
            }
        }
    }

    protected function ensureRuntimeAssets(string $buildDir, string $name): void
    {
        $assetsDir = "{$buildDir}/assets";
        File::ensureDirectoryExists($assetsDir);

        $jsPath = "{$assetsDir}/{$name}.js";
        if (!File::exists($jsPath)) {
            File::put($jsPath, "window.DbsPluginRuntime && window.DbsPluginRuntime.register && window.DbsPluginRuntime.register('{$name}', {});\n");
        }

        $cssPath = "{$assetsDir}/{$name}.css";
        if (!File::exists($cssPath)) {
            File::put($cssPath, "/* {$name} runtime styles */\n");
        }
    }
}
