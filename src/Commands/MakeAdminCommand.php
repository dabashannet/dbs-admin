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

        // 3. Generate Vue page
        $vuePath = base_path("web/src/views/system/{$viewName}/index.vue");
        $this->generateFile($vuePath, 'vue-page.stub', $replacements);
        $this->line("  <fg=green>✓</> Vue Page: {$vuePath}");

        // 4. Generate API file
        $apiPath = base_path("web/src/api/{$kebabName}.ts");
        $this->generateFile($apiPath, 'vue-api.stub', $replacements);
        $this->line("  <fg=green>✓</> API File: {$apiPath}");

        // 5. Generate Router module file
        $routerPath = base_path("web/src/router/routes/modules/system-{$kebabName}.ts");
        $this->generateFile($routerPath, 'vue-router-core.stub', $replacements);
        $this->line("  <fg=green>✓</> Router: {$routerPath}");

        // 6. Generate Locale files
        $localeZhPath = base_path("web/src/views/system/{$viewName}/locale/zh-CN.ts");
        $this->generateFile($localeZhPath, 'vue-locale-zh.stub', $replacements);
        $this->line("  <fg=green>✓</> Locale ZH: {$localeZhPath}");

        $localeEnPath = base_path("web/src/views/system/{$viewName}/locale/en-US.ts");
        $this->generateFile($localeEnPath, 'vue-locale-en.stub', $replacements);
        $this->line("  <fg=green>✓</> Locale EN: {$localeEnPath}");

        $this->newLine();
        $this->info('Admin resource created successfully!');
        $this->newLine();
        $this->warn('Note: You may need to import the locale file in your main locale config:');
        $this->line("  import locale{$studlyName} from '@/views/system/{$viewName}/locale/zh-CN';");
        $this->line("  Then spread it: ...locale{$studlyName}");

        return Command::SUCCESS;
    }
}
