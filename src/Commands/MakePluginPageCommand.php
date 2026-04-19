<?php

namespace Dabashan\DbsAdmin\Commands;

use Dabashan\DbsAdmin\Traits\HasFileGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePluginPageCommand extends Command
{
    use HasFileGeneration;

    protected $signature = 'make:plugin-page
                            {plugin : Existing plugin name (e.g. shop, demo_plugin)}
                            {name : Page/Resource name (e.g. product, order)}
                            {--admin : Generate controller in Admin directory (default)}
                            {--http : Generate controller in Http directory}
                            {--no-model : Skip generating Model file}
                            {--migration : Generate migration file}
                            {--vue : Generate Vue frontend files (page, api, router, locale)}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a page/resource within an existing plugin (Controller, Model, and optionally Vue files)';

    public function handle(): int
    {
        $plugin = $this->argument('plugin');
        $name = $this->argument('name');

        $pluginStudly = Str::studly($plugin);
        $pluginSnake = Str::snake($plugin);
        $pluginKebab = Str::kebab($plugin);
        $pluginPath = base_path("plugins/{$pluginStudly}");

        // Check if plugin exists
        if (!is_dir($pluginPath)) {
            $this->error("Plugin [{$pluginStudly}] does not exist at: {$pluginPath}");
            $this->line("  Run 'php artisan make:plugin {$pluginSnake}' to create it first.");
            return Command::FAILURE;
        }

        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        $controllerName = $studlyName . 'Controller';
        $pluralKebab = Str::plural($kebabName);
        $titleEn = $studlyName . ' Management';

        // Determine controller location (admin by default, or http if --http flag)
        $useHttp = $this->option('http');
        $controllerType = $useHttp ? 'Http' : 'Admin';

        // Table name: plugin_{plugin_snake}_{plural_snake}
        $tableName = 'plugin_' . $pluginSnake . '_' . Str::snake(Str::plural($studlyName));

        $replacements = [
            '{{ class }}' => $controllerName,
            '{{ model }}' => $studlyName,
            '{{ name }}' => $studlyName,
            '{{ plugin }}' => $pluginStudly,
            '{{ pluginName }}' => $pluginStudly,
            '{{ pluginKebab }}' => $pluginKebab,
            '{{ pluginSnake }}' => $pluginSnake,
            '{{ kebabName }}' => $kebabName,
            '{{ viewName }}' => $kebabName,
            '{{ parentPath }}' => "plugin.{$pluginKebab}",
            '{{ apiPrefix }}' => ($useHttp ? '/plugin' : '/plugin') . "/{$pluginSnake}/" . ($useHttp ? 'api' : 'admin') . "/{$pluralKebab}",
            '{{ title }}' => $studlyName,
            '{{ titleEn }}' => $titleEn,
            '{{ pluralKebab }}' => $pluralKebab,
            '{{ table }}' => $tableName,
        ];

        $this->info("Creating plugin page [{$studlyName}] in [{$pluginStudly}]...");
        $this->newLine();

        // 1. Generate Controller
        $controllerPath = "{$pluginPath}/{$controllerType}/Controllers/{$controllerName}.php";
        $controllerNamespace = "Plugins\\{$pluginStudly}\\{$controllerType}\\Controllers";

        $this->generatePluginController($controllerPath, $controllerNamespace, $replacements, $useHttp);
        $this->line("  <fg=green>✓</> Controller: {$controllerPath}");

        // 2. Generate Model (unless --no-model)
        if (!$this->option('no-model')) {
            $modelPath = "{$pluginPath}/Models/{$studlyName}.php";
            $this->generatePluginModel($modelPath, $pluginStudly, $replacements);
            $this->line("  <fg=green>✓</> Model: {$modelPath}");

            // Generate migration if requested
            if ($this->option('migration')) {
                $migrationPath = "{$pluginPath}/database/migrations";
                if (!is_dir($migrationPath)) {
                    mkdir($migrationPath, 0755, true);
                }
                $this->call('make:migration', [
                    'name' => "create_{$tableName}_table",
                    '--create' => $tableName,
                    '--path' => "plugins/{$pluginStudly}/database/migrations",
                ]);
            }
        }

        $this->newLine();
        $this->info('Plugin page created successfully!');
        $this->newLine();

        // 8. Generate Vue files if --vue flag is set
        if ($this->option('vue')) {
            $this->generatePluginVueFiles($pluginStudly, $pluginKebab, $replacements);
        }

        $this->warn('后续步骤：');
        $this->line("  1. 在 plugins/{$pluginStudly}/{$controllerType}/routes.php 中注册路由");
        $this->line("  2. 在 plugin.json 中配置菜单和权限");
        if ($this->option('vue')) {
            $this->line("  3. 在 plugins/{$pluginStudly}/resources/routes/ 中创建路由文件");
            $this->line("  4. Vite 构建时会自动发现插件路由");
        }

        return Command::SUCCESS;
    }

    /**
     * Generate plugin controller file
     */
    protected function generatePluginController(string $path, string $namespace, array $replacements, bool $isHttp): void
    {
        $modelNamespace = "Plugins\\{$replacements['{{ plugin }}']}\\Models\\{$replacements['{{ model }}']}";

        if ($isHttp) {
            // Simple HTTP controller (non-admin)
            $content = <<<PHP
<?php

namespace {$namespace};

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use {$modelNamespace};

class {$replacements['{{ class }}']} extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'msg' => 'success',
            'data' => {$replacements['{{ model }}']}::paginate(15),
        ]);
    }

    public function show(int \$id): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'msg' => 'success',
            'data' => {$replacements['{{ model }}']}::findOrFail(\$id),
        ]);
    }
}
PHP;
        } else {
            // Admin controller with Grid/Form
            $content = <<<PHP
<?php

namespace {$namespace};

use Dabashan\DbsAdmin\Controllers\AdminController;
use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Form\Form;
use {$modelNamespace};

class {$replacements['{{ class }}']} extends AdminController
{
    protected string \$model = {$replacements['{{ model }}']}::class;

    protected function grid(): Grid
    {
        return Grid::make({$replacements['{{ model }}']}::query())
            ->column('id', 'ID')->sortable()
            ->column('created_at', '创建时间')->sortable()
            ->perPage(15);
    }

    protected function form(): Form
    {
        return Form::make({$replacements['{{ model }}']}::class)
            ->text('name', '名称')->required();
    }
}
PHP;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * Generate plugin model file
     */
    protected function generatePluginModel(string $path, string $pluginStudly, array $replacements): void
    {
        $namespace = "Plugins\\{$pluginStudly}\\Models";
        $content = <<<PHP
<?php

namespace {$namespace};

use Illuminate\Database\Eloquent\Model;

class {$replacements['{{ model }}']} extends Model
{
    protected \$table = '{$replacements['{{ table }}']}';

    protected \$fillable = [
        //
    ];
}
PHP;

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * 在插件 resources/ 目录下生成 Vue 前端文件
     */
    protected function generatePluginVueFiles(string $pluginStudly, string $pluginKebab, array $replacements): void
    {
        $pluginPath = base_path("plugins/{$pluginStudly}");

        $kebabName = $replacements['{{ kebabName }}'];
        $viewName = $replacements['{{ viewName }}'];
        $title = $replacements['{{ title }}'];
        $parentPath = $replacements['{{ parentPath }}'];
        $apiPrefix = $replacements['{{ apiPrefix }}'];

        $this->info('正在生成插件前端文件...');

        // 1. Vue 页面（DynamicCrud 包装器）
        $vuePagePath = "{$pluginPath}/resources/views/{$viewName}/index.vue";
        $vuePageContent = <<<VUE
<template>
  <DynamicCrud
    api-prefix="{$apiPrefix}"
    :breadcrumb="['menu.{$parentPath}', 'menu.{$parentPath}.{$kebabName}']"
    add-title="新增{$title}"
    edit-title="编辑{$title}"
  />
</template>

<script lang="ts" setup>
  import DynamicCrud from '@/components/dynamic/DynamicCrud.vue';
</script>
VUE;
        $this->ensureDirectoryExists(dirname($vuePagePath));
        file_put_contents($vuePagePath, $vuePageContent);
        $this->line("  <fg=green>✓</> Vue 页面：resources/views/{$viewName}/index.vue");

        // 2. 前端路由文件（Vite 自动发现）
        $routerPath = "{$pluginPath}/resources/routes/{$kebabName}.ts";
        $routerContent = <<<TS
export default [
  {
    path: '/plugin/{$pluginKebab}/{$kebabName}',
    name: '{$pluginKebab}-{$kebabName}',
    component: () => import('@plugins/{$pluginStudly}/resources/views/{$kebabName}/index.vue'),
    meta: {
      title: 'menu.{$parentPath}.{$kebabName}',
      locale: true,
    },
  },
];
TS;
        $this->ensureDirectoryExists(dirname($routerPath));
        file_put_contents($routerPath, $routerContent);
        $this->line("  <fg=green>✓</> 前端路由：resources/routes/{$kebabName}.ts");

        // 3. 国际化文件
        $localeZhPath = "{$pluginPath}/resources/views/{$viewName}/locale/zh-CN.ts";
        $localeZhContent = <<<TS
export default {
  'menu.{$parentPath}': '{$title}管理',
  'menu.{$parentPath}.{$kebabName}': '{$kebabName}列表',
};
TS;
        $this->ensureDirectoryExists(dirname($localeZhPath));
        file_put_contents($localeZhPath, $localeZhContent);
        $this->line("  <fg=green>✓</> 中文语言包：resources/views/{$viewName}/locale/zh-CN.ts");

        $localeEnPath = "{$pluginPath}/resources/views/{$viewName}/locale/en-US.ts";
        $localeEnContent = <<<TS
export default {
  'menu.{$parentPath}': '{$title} Management',
  'menu.{$parentPath}.{$kebabName}': '{$kebabName} List',
};
TS;
        $this->ensureDirectoryExists(dirname($localeEnPath));
        file_put_contents($localeEnPath, $localeEnContent);
        $this->line("  <fg=green>✓</> 英文语言包：resources/views/{$viewName}/locale/en-US.ts");

        // 4. 确保 DynamicCrud 组件存在
        $this->ensureDynamicComponentsExist();

        $this->newLine();
    }

    /**
     * Ensure dynamic components exist in the web frontend
     */
    protected function ensureDynamicComponentsExist(): void
    {
        $targetDir = base_path('web/src/components/dynamic');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $stubsDir = dirname(__DIR__, 2) . '/stubs';
        $components = [
            'DynamicCrud.vue' => 'vue-dynamic-crud.stub',
            'DynamicTable.vue' => 'vue-dynamic-table.stub',
            'DynamicForm.vue' => 'vue-dynamic-form.stub',
        ];

        foreach ($components as $filename => $stub) {
            $targetPath = "{$targetDir}/{$filename}";
            $stubPath = "{$stubsDir}/{$stub}";
            if (!file_exists($targetPath) && file_exists($stubPath)) {
                copy($stubPath, $targetPath);
                $this->line("  <fg=green>✓</> Dynamic component created: {$filename}");
            }
        }
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
