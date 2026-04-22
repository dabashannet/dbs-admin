<?php

namespace Dabashan\DbsAdmin\Commands;

use Dabashan\DbsAdmin\Traits\HasFileGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminCommand extends Command
{
    use HasFileGeneration;

    protected $signature = 'make:admin
                            {name : Resource name (e.g. User, Order, ProductCategory)}
                            {--view-name= : Custom Vue view directory name (kebab-case)}
                            {--parent=system : Vue view parent directory (e.g. system, settings)}
                            {--icon= : Menu icon (e.g. icon-user, icon-settings)}
                            {--order=90 : Menu sort order}
                            {--controller : Generate Controller file only}
                            {--model : Generate Model file only}
                            {--vue : Generate Vue page file only}
                            {--router : Generate Router file only}
                            {--locale : Generate Locale files only}
                            {--all : Generate all files (default behavior)}
                            {--no-model : Skip generating Model file}
                            {--no-web : Skip generating all Vue frontend files}
                            {--no-router : Skip generating Router file}
                            {--no-locale : Skip generating Locale files}
                            {--no-dynamic : Skip ensuring Dynamic components exist}
                            {--migration : Generate migration file}
                            {--api-prefix= : Custom API prefix (default: /admin/{parent}/{plural})}
                            {--table= : Custom table name (default: admin_{plural_snake})}
                            {--fillable= : Comma-separated fillable fields for Model}
                            {--force : Overwrite existing files}';

    protected $description = 'Create Admin resource with Controller, Model, Vue page, Router, and Locale files (flexible selective generation)';

    /**
     * 文件类型标记
     */
    protected array $generatedFiles = [];

    public function handle(): int
    {
        $name = $this->argument('name');
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        $parentPath = $this->option('parent') ?: 'system';
        $viewName = $this->option('view-name') ?: $kebabName;
        $icon = $this->option('icon') ?: 'icon-file';
        $order = (int) ($this->option('order') ?: 90);
        $modelName = 'Admin' . $studlyName;
        $controllerName = $studlyName . 'Controller';
        $pluralKebab = Str::plural($kebabName);
        $pluralSnake = Str::snake(Str::plural($studlyName));
        $titleEn = $studlyName . ' Management';

        // 表名
        $tableName = $this->option('table') ?: ('admin_' . $pluralSnake);

        // API 前缀
        $apiPrefix = $this->option('api-prefix') ?: ('/admin/' . $parentPath . '/' . $pluralKebab);

        // Fillable 字段
        $fillable = $this->option('fillable') ? array_map('trim', explode(',', $this->option('fillable'))) : [];

        $replacements = [
            '{{ class }}' => $controllerName,
            '{{ model }}' => $modelName,
            '{{ name }}' => $studlyName,
            '{{ kebabName }}' => $kebabName,
            '{{ viewName }}' => $viewName,
            '{{ parentPath }}' => $parentPath,
            '{{ apiPrefix }}' => $apiPrefix,
            '{{ title }}' => $studlyName,
            '{{ titleEn }}' => $titleEn,
            '{{ pluralKebab }}' => $pluralKebab,
            '{{ table }}' => $tableName,
            '{{ icon }}' => $icon,
            '{{ order }}' => $order,
            '{{ fillable }}' => $this->formatFillable($fillable),
        ];

        // 判断是否为选择性生成模式
        $selectiveMode = $this->isSelectiveMode();
        $generateAll = !$selectiveMode && !$this->option('no-web') && !$this->option('controller');

        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║          DbsAdmin Resource Generator                   ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("Resource: <fg=cyan>{$studlyName}</>");
        $this->line("  Parent:   <fg=yellow>{$parentPath}</>");
        $this->line("  View:     <fg=yellow>{$viewName}</>");
        $this->line("  API:      <fg=yellow>{$apiPrefix}</>");
        $this->line("  Table:    <fg=yellow>{$tableName}</>");
        $this->newLine();

        // ==================== Controller ====================
        if ($generateAll || $this->option('controller')) {
            $this->generateController($controllerName, $replacements);
        }

        // ==================== Model ====================
        if (($generateAll && !$this->option('no-model')) || $this->option('model')) {
            $this->generateModel($modelName, $replacements, $fillable);

            if ($this->option('migration')) {
                $this->generateMigration($tableName);
            }
        }

        // ==================== Vue files ====================
        $skipWeb = $this->option('no-web') || $this->option('controller') || $this->option('model');

        if (!$skipWeb || $this->option('vue') || $this->option('router') || $this->option('locale')) {
            // Vue page
            if (($generateAll && !$this->option('no-web')) || $this->option('vue')) {
                $this->generateVuePage($viewName, $parentPath, $replacements);
            }

            // Router
            if (($generateAll && !$this->option('no-web') && !$this->option('no-router')) || $this->option('router')) {
                $this->generateRouter($studlyName, $kebabName, $viewName, $parentPath, $icon, $order, $replacements);
            }

            // Locale
            if (($generateAll && !$this->option('no-web') && !$this->option('no-locale')) || $this->option('locale')) {
                $this->generateLocale($viewName, $parentPath, $kebabName, $studlyName, $replacements);
            }

            // Dynamic components
            if ($generateAll && !$this->option('no-web') && !$this->option('no-dynamic')) {
                $this->ensureDynamicComponentsExist();
            }
        }

        // ==================== 输出结果 ====================
        $this->newLine();
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║          Generation Complete                           ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->newLine();

        if (empty($this->generatedFiles)) {
            $this->warn('No files were generated. Use --help to see available options.');
            return Command::FAILURE;
        }

        $this->line("  <fg=green>Generated " . count($this->generatedFiles) . " file(s):</>");
        $this->newLine();

        foreach ($this->generatedFiles as $file) {
            $this->line("  <fg=green>✓</> {$file}");
        }

        $this->newLine();

        // 提示后续步骤
        $this->warn("Next steps:");
        $this->line("  1. Edit <fg=cyan>app/Admin/Controllers/{$controllerName}.php</> to define grid() and form()");
        if (!$this->option('no-model') || $this->option('model')) {
            $this->line("  2. Edit <fg=cyan>app/Admin/Models/{$modelName}.php</> to set fillable fields");
        }
        if (!$skipWeb) {
            $this->line("  3. Register route in <fg=cyan>routes/admin.php</>");
            $this->line("  4. Add locale keys in <fg=cyan>web/src/locale/zh-CN.ts</>");
        }

        $this->newLine();
        return Command::SUCCESS;
    }

    /**
     * 检查是否为选择性生成模式
     */
    protected function isSelectiveMode(): bool
    {
        return $this->option('controller')
            || $this->option('model')
            || $this->option('vue')
            || $this->option('router')
            || $this->option('locale');
    }

    /**
     * 格式化 fillable 字段
     */
    protected function formatFillable(array $fillable): string
    {
        if (empty($fillable)) {
            return '//';
        }

        return "'" . implode("',\n        '", $fillable) . "',";
    }

    /**
     * 生成 Controller
     */
    protected function generateController(string $controllerName, array $replacements): void
    {
        $controllerPath = app_path("Admin/Controllers/{$controllerName}.php");
        $this->generateFile($controllerPath, 'controller.core.stub', $replacements);
        $this->generatedFiles[] = $controllerPath;
    }

    /**
     * 生成 Model
     */
    protected function generateModel(string $modelName, array $replacements, array $fillable): void
    {
        $modelPath = app_path("Admin/Models/{$modelName}.php");

        // 如果有 fillable 字段，生成自定义 model stub
        if (!empty($fillable)) {
            $content = file_get_contents(dirname(__DIR__, 2) . '/stubs/model.core.stub');
            $content = str_replace(
                '{{ fillable }}',
                $this->formatFillable($fillable),
                $content
            );
            $content = str_replace(array_keys($replacements), array_values($replacements), $content);

            $dir = dirname($modelPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $this->writeFile($modelPath, $content);
        } else {
            $this->generateFile($modelPath, 'model.core.stub', $replacements);
        }

        $this->generatedFiles[] = $modelPath;
    }

    /**
     * 生成迁移
     */
    protected function generateMigration(string $tableName): void
    {
        $this->line("  <fg=green>→</> Running: make:migration create_{$tableName}_table");
        $this->call('make:migration', [
            'name' => "create_{$tableName}_table",
            '--create' => $tableName,
        ]);
        $this->generatedFiles[] = "database/migrations/*_create_{$tableName}_table.php";
    }

    /**
     * 生成 Vue 页面
     */
    protected function generateVuePage(string $viewName, string $parentPath, array $replacements): void
    {
        $vuePath = base_path("web/src/views/{$parentPath}/{$viewName}/index.vue");
        $this->generateFile($vuePath, 'vue-page.stub', $replacements);
        $this->generatedFiles[] = $vuePath;
    }

    /**
     * 生成路由
     */
    protected function generateRouter(string $studlyName, string $kebabName, string $viewName, string $parentPath, string $icon, int $order, array $replacements): void
    {
        $routerPath = base_path("web/src/router/routes/modules/{$parentPath}-{$kebabName}.ts");

        // 生成自定义路由 stub
        $stubPath = dirname(__DIR__, 2) . '/stubs/vue-router-core.stub';
        $content = file_get_contents($stubPath);

        $routerReplacements = array_merge($replacements, [
            '{{ name }}' => $studlyName,
            '{{ kebabName }}' => $kebabName,
            '{{ viewName }}' => $viewName,
            '{{ parentPath }}' => $parentPath,
            '{{ parentStudly }}' => Str::studly($parentPath),
            '{{ icon }}' => $icon,
            '{{ order }}' => $order,
        ]);

        $content = str_replace(array_keys($routerReplacements), array_values($routerReplacements), $content);

        // 替换路由结构以支持自定义 parentPath
        $parentStudly = Str::studly($parentPath);
        $content = str_replace("path: '/system'", "path: '/{$parentPath}'", $content);
        $content = str_replace("name: '{{ name }}Parent'", "name: '{$parentStudly}Parent'", $content);
        $content = str_replace("'menu.system'", "'menu.{$parentPath}'", $content);
        $content = str_replace("import('@/views/system/", "import('@/views/{$parentPath}/", $content);

        $dir = dirname($routerPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $force = $this->hasOption('force') && $this->option('force');
        if (file_exists($routerPath) && !$force) {
            $this->warn("  File already exists: {$routerPath} (use --force to overwrite)");
            return;
        }

        $this->writeFile($routerPath, $content);
        $this->generatedFiles[] = $routerPath;
    }

    /**
     * 生成语言文件
     */
    protected function generateLocale(string $viewName, string $parentPath, string $kebabName, string $studlyName, array $replacements): void
    {
        // 中文
        $localeZhPath = base_path("web/src/views/{$parentPath}/{$viewName}/locale/zh-CN.ts");
        $this->generateFile($localeZhPath, 'vue-locale-zh.stub', $replacements);
        $this->generatedFiles[] = $localeZhPath;

        // 英文
        $localeEnPath = base_path("web/src/views/{$parentPath}/{$viewName}/locale/en-US.ts");
        $this->generateFile($localeEnPath, 'vue-locale-en.stub', $replacements);
        $this->generatedFiles[] = $localeEnPath;
    }

    /**
     * 确保 DynamicCrud 组件存在
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
                continue;
            }

            $stubPath = dirname(__DIR__, 2) . "/stubs/{$stub}";
            if (file_exists($stubPath)) {
                $content = file_get_contents($stubPath);
                $this->writeFile($targetPath, $content);
                $this->generatedFiles[] = $targetPath;
            }
        }
    }
}
