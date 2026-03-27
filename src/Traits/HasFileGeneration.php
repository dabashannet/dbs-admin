<?php

namespace Dabashan\DbsAdmin\Traits;

/**
 * 文件生成 Trait
 *
 * 提供 Artisan 命令共用的文件生成功能
 */
trait HasFileGeneration
{
    /**
     * 生成文件
     *
     * @param string $path 目标文件路径
     * @param string $stub stub 模板文件名
     * @param array $replacements 替换内容
     * @return void
     */
    protected function generateFile(string $path, string $stub, array $replacements): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $force = $this->hasOption('force') && $this->option('force');

        if (file_exists($path) && !$force) {
            $this->warn("File already exists: {$path} (use --force to overwrite)");
            return;
        }

        $stubPath = dirname(__DIR__, 2) . "/stubs/{$stub}";

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return;
        }

        $content = file_get_contents($stubPath);
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        file_put_contents($path, $content);

        if ($force && file_exists($path)) {
            $this->info("  Overwritten: {$path}");
        }
    }
}
