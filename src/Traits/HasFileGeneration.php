<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Traits;

/**
 * 文件生成 Trait
 *
 * 提供 Artisan 命令共用的文件生成功能
 */
trait HasFileGeneration
{
    protected function fileHeader(string $type): string
    {
        if ($type === 'vue') {
            return <<<VUE
<!--
 * @Author: quickly generate using dbs-admin
 * @Date: 2025-09-05 18:47:23
 * @Help: wiki.dabashan.cc
-->

VUE;
        }

        if ($type === 'php') {
            return <<<PHP
/*
 * @Author: quickly generate using dbs-admin
 * @Date: 2025-09-05 18:47:23
 * @Help: wiki.dabashan.cc
 */

PHP;
        }

        return <<<TS
/*
 * @Author: quickly generate using dbs-admin
 * @Date: 2025-09-05 18:47:23
 * @Help: wiki.dabashan.cc
 */

TS;
    }

    protected function normalizeHeader(string $path, string $content): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            return $content;
        }

        if ($ext === 'vue') {
            $header = $this->fileHeader('vue');
            $trimmed = ltrim($content);
            if (str_starts_with($trimmed, '<!--')) {
                $end = strpos($trimmed, '-->');
                if ($end !== false) {
                    $rest = ltrim(substr($trimmed, $end + 3));
                    return $header . $rest;
                }
            }
            return $header . $content;
        }

        if ($ext === 'php') {
            $header = $this->fileHeader('php');
            if (preg_match('/^<\?php\s*/', $content, $m)) {
                $offset = strlen($m[0]);
                $after = substr($content, $offset);
                $afterTrim = ltrim($after);
                if (preg_match('/^(\/\*\*?[\s\S]*?\*\/\s*)/', $afterTrim, $cm)) {
                    $afterTrim = substr($afterTrim, strlen($cm[1]));
                }
                return "<?php\n\n" . $header . $afterTrim;
            }
            return $header . $content;
        }

        if (in_array($ext, ['ts', 'tsx', 'js', 'jsx'], true)) {
            $header = $this->fileHeader('ts');
            $trimmed = ltrim($content);
            if (str_starts_with($trimmed, '/*')) {
                $end = strpos($trimmed, '*/');
                if ($end !== false) {
                    $rest = ltrim(substr($trimmed, $end + 2));
                    return $header . $rest;
                }
            }
            return $header . $content;
        }

        return $content;
    }

    protected function writeFile(string $path, string $content): void
    {
        $content = $this->normalizeHeader($path, $content);
        file_put_contents($path, $content);
    }

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
        $this->writeFile($path, $content);

        if ($force && file_exists($path)) {
            $this->info("  Overwritten: {$path}");
        }
    }
}
