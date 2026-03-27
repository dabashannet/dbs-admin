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
            '{{ apiPrefix }}' => ($useHttp ? '/api' : '/admin') . "/plugin/{$pluginSnake}/{$pluralKebab}",
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
            $this->generateVueFiles($pluginStudly, $pluginKebab, $replacements);
        }

        $this->warn('Next steps:');
        $this->line("  1. Add routes in plugins/{$pluginStudly}/{$controllerType}/routes.php");
        $this->line("  2. Run: php artisan migrate (if migration was created)");
        if ($this->option('vue')) {
            $this->line("  3. Register the Vue router in web/src/router/plugin.ts");
            $this->line("  4. Import locale files in web/src/locale/zh-CN.ts and en-US.ts");
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
     * Generate Vue frontend files
     */
    protected function generateVueFiles(string $pluginStudly, string $pluginKebab, array $replacements): void
    {
        $webPath = base_path('web');

        // Check if web directory exists
        if (!is_dir($webPath)) {
            $this->warn('  Web directory not found, skipping Vue file generation.');
            return;
        }

        $kebabName = $replacements['{{ kebabName }}'];
        $viewName = $replacements['{{ viewName }}'];

        $this->info('Generating Vue files...');

        // 1. Generate Vue page
        $vuePagePath = "{$webPath}/src/views/plugin/{$pluginKebab}/{$viewName}/index.vue";
        $this->generateFile(
            $vuePagePath,
            'vue-page.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Vue Page: src/views/plugin/{$pluginKebab}/{$viewName}/index.vue");

        // 2. Generate API file
        $apiFileName = "plugin-{$pluginKebab}-{$kebabName}";
        $apiPath = "{$webPath}/src/api/{$apiFileName}.ts";
        $this->generateFile(
            $apiPath,
            'vue-api.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> API: src/api/{$apiFileName}.ts");

        // 3. Generate Router file
        $routerFileName = "plugin-{$pluginKebab}-{$kebabName}";
        $routerPath = "{$webPath}/src/router/routes/modules/{$routerFileName}.ts";
        $this->generateFile(
            $routerPath,
            'vue-router-plugin.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Router: src/router/routes/modules/{$routerFileName}.ts");

        // 4. Generate Locale files
        $localeZhPath = "{$webPath}/src/locale/plugin/{$pluginKebab}/{$kebabName}/zh-CN.ts";
        $this->generateFile(
            $localeZhPath,
            'vue-locale-zh.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Locale (zh-CN): src/locale/plugin/{$pluginKebab}/{$kebabName}/zh-CN.ts");

        $localeEnPath = "{$webPath}/src/locale/plugin/{$pluginKebab}/{$kebabName}/en-US.ts";
        $this->generateFile(
            $localeEnPath,
            'vue-locale-en.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Locale (en-US): src/locale/plugin/{$pluginKebab}/{$kebabName}/en-US.ts");

        $this->newLine();
    }
}
