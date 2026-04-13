<?php

namespace Dabashan\DbsAdmin\Commands;

use Dabashan\DbsAdmin\Traits\HasFileGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminCommand extends Command
{
    use HasFileGeneration;

    protected $signature = 'make:admin
                            {name : Resource name (e.g. User, Order)}
                            {--view-name= : Custom Vue view directory name (kebab-case)}
                            {--no-model : Skip generating Model file}
                            {--no-web : Skip generating Vue frontend files}
                            {--migration : Generate migration file}
                            {--force : Overwrite existing files}';

    protected $description = 'Create Admin Controller and Model with Grid/Form, Vue page, API, Router and Locale files';

    public function handle(): int
    {
        $name = $this->argument('name');
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        $viewName = $this->option('view-name') ?: $kebabName;
        $modelName = 'Admin' . $studlyName;
        $controllerName = $studlyName . 'Controller';
        $pluralKebab = Str::plural($kebabName);
        $titleEn = $studlyName . ' Management';

        // Table name: admin_{plural_snake}
        $tableName = 'admin_' . Str::snake(Str::plural($studlyName));

        $replacements = [
            '{{ class }}' => $controllerName,
            '{{ model }}' => $modelName,
            '{{ name }}' => $studlyName,
            '{{ kebabName }}' => $kebabName,
            '{{ viewName }}' => $viewName,
            '{{ parentPath }}' => 'system',
            '{{ apiPrefix }}' => '/admin/' . $pluralKebab,
            '{{ title }}' => $studlyName,
            '{{ titleEn }}' => $titleEn,
            '{{ pluralKebab }}' => $pluralKebab,
            '{{ table }}' => $tableName,
        ];

        $this->info("Creating Admin resource [{$studlyName}]...");
        $this->newLine();

        // 1. Generate Controller
        $controllerPath = app_path("Admin/Controllers/{$controllerName}.php");
        $this->generateFile($controllerPath, 'controller.core.stub', $replacements);
        $this->line("  <fg=green>✓</> Controller: {$controllerPath}");

        // 2. Generate Model (unless --no-model)
        if (!$this->option('no-model')) {
            $modelPath = app_path("Admin/Models/{$modelName}.php");
            $this->generateFile($modelPath, 'model.core.stub', $replacements);
            $this->line("  <fg=green>✓</> Model: {$modelPath}");

            // Generate migration if requested
            if ($this->option('migration')) {
                $this->call('make:migration', [
                    'name' => "create_{$tableName}_table",
                    '--create' => $tableName,
                ]);
            }
        }

        // Skip Vue files if --no-web
        if ($this->option('no-web')) {
            $this->newLine();
            $this->info('Skipped Vue frontend files (--no-web)');
            return Command::SUCCESS;
        }

        // 3. Generate Vue page (DynamicCrud wrapper — no manual page needed)
        $vuePath = base_path("web/src/views/system/{$viewName}/index.vue");
        $this->generateFile($vuePath, 'vue-page.stub', $replacements);
        $this->line("  <fg=green>✓</> Vue Page: {$vuePath} (DynamicCrud — auto-rendered from PHP metadata)");

        // 4. Generate Router module file
        $routerPath = base_path("web/src/router/routes/modules/system-{$kebabName}.ts");
        $this->generateFile($routerPath, 'vue-router-core.stub', $replacements);
        $this->line("  <fg=green>✓</> Router: {$routerPath}");

        // 5. Generate Locale files
        $localeZhPath = base_path("web/src/views/system/{$viewName}/locale/zh-CN.ts");
        $this->generateFile($localeZhPath, 'vue-locale-zh.stub', $replacements);
        $this->line("  <fg=green>✓</> Locale ZH: {$localeZhPath}");

        $localeEnPath = base_path("web/src/views/system/{$viewName}/locale/en-US.ts");
        $this->generateFile($localeEnPath, 'vue-locale-en.stub', $replacements);
        $this->line("  <fg=green>✓</> Locale EN: {$localeEnPath}");

        // 6. Copy dynamic components (only if they don't exist)
        $this->ensureDynamicComponentsExist();

        $this->newLine();
        $this->info('Admin resource created successfully!');
        $this->newLine();
        $this->warn('Note: The Vue page uses DynamicCrud which auto-renders from PHP Grid/Form metadata.');
        $this->line('  Just define grid() and form() in your Controller — no manual Vue code needed.');
        $this->line("  To customize: edit app/Admin/Controllers/{$controllerName}.php");

        return Command::SUCCESS;
    }

    /**
     * Ensure the DynamicCrud/DynamicTable/DynamicForm components exist in the web frontend
     */
    protected function ensureDynamicComponentsExist(): void
    {
        $targetDir = base_path('web/src/components/dynamic');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $components = [
            'DynamicCrud.vue' => 'vue-dynamic-crud.stub',
            'DynamicTable.vue' => 'vue-dynamic-table.stub',
            'DynamicForm.vue' => 'vue-dynamic-form.stub',
        ];

        foreach ($components as $filename => $stub) {
            $targetPath = "{$targetDir}/{$filename}";
            if (file_exists($targetPath)) {
                $this->line("  <fg=blue>ℹ</> Dynamic component exists: {$filename}");
                continue;
            }

            $stubPath = dirname(__DIR__, 2) . "/stubs/{$stub}";
            if (file_exists($stubPath)) {
                copy($stubPath, $targetPath);
                $this->line("  <fg=green>✓</> Dynamic component created: {$filename}");
            }
        }
    }
}
