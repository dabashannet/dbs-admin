<?php

namespace Dabashan\DbsAdmin\Commands;

use Dabashan\DbsAdmin\Traits\HasFileGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminPluginController extends Command
{
    use HasFileGeneration;

    protected $signature = 'make:admin-plugin-controller
                            {plugin : Plugin name (e.g. DemoPlugin, Shop)}
                            {name : Controller name (e.g. User, Order)}
                            {--view-name= : Custom Vue view directory name (kebab-case)}
                            {--no-web : Skip generating Vue frontend files}
                            {--force : Overwrite existing files}
                            {--model : Also generate the Model file}
                            {--migration : Generate migration file (requires --model)}';

    protected $description = 'Create a new Admin Plugin controller with Grid/Form, Vue page, API, Router and Locale files';

    public function handle(): int
    {
        $plugin = $this->argument('plugin');
        $name = $this->argument('name');

        $pluginStudly = Str::studly($plugin);
        $pluginKebab = Str::kebab($plugin);
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        $viewName = $this->option('view-name') ?: $kebabName;
        $modelName = $studlyName;
        $controllerName = $studlyName . 'Controller';
        $pluralKebab = Str::plural($kebabName);
        $titleEn = $studlyName . ' Management';

        $replacements = [
            '{{ class }}' => $controllerName,
            '{{ model }}' => $modelName,
            '{{ name }}' => $studlyName,
            '{{ plugin }}' => $pluginStudly,
            '{{ pluginName }}' => $pluginStudly,
            '{{ pluginKebab }}' => $pluginKebab,
            '{{ kebabName }}' => $kebabName,
            '{{ viewName }}' => $viewName,
            '{{ parentPath }}' => "plugin.{$pluginKebab}",
            '{{ apiPrefix }}' => '/admin/plugins/' . $pluginKebab . '/' . $pluralKebab,
            '{{ title }}' => $studlyName,
            '{{ titleEn }}' => $titleEn,
            '{{ pluralKebab }}' => $pluralKebab,
        ];

        $this->info("Creating Admin Plugin Controller for [{$pluginStudly}]...");
        $this->newLine();

        // 1. Generate Controller
        $controllerPath = app_path("Admin/Controllers/Plugins/{$pluginStudly}/{$controllerName}.php");
        $this->generateFile($controllerPath, 'controller.plugin.stub', $replacements);
        $this->line("  <fg=green>✓</> Controller: {$controllerPath}");

        // 2. Generate Model if requested
        if ($this->option('model')) {
            $modelArgs = ['name' => $modelName];
            if ($this->option('migration')) {
                $modelArgs['--migration'] = true;
            }
            if ($this->option('force')) {
                $modelArgs['--force'] = true;
            }
            $this->call('make:admin-plugin-model', $modelArgs);
        }

        // Skip Vue files if --no-web
        if ($this->option('no-web')) {
            $this->newLine();
            $this->info('Skipped Vue frontend files (--no-web)');
            return Command::SUCCESS;
        }

        // 3. Generate Vue page
        $vuePath = base_path("web/src/views/plugin/{$pluginKebab}/{$viewName}/index.vue");
        $this->generateFile($vuePath, 'vue-page.stub', $replacements);
        $this->line("  <fg=green>✓</> Vue Page: {$vuePath}");

        // 4. Generate API file
        $apiPath = base_path("web/src/api/{$pluginKebab}-{$kebabName}.ts");
        $this->generateFile($apiPath, 'vue-api.stub', $replacements);
        $this->line("  <fg=green>✓</> API File: {$apiPath}");

        // 5. Generate Router module file
        $routerPath = base_path("web/src/router/routes/modules/plugin-{$pluginKebab}-{$kebabName}.ts");
        $this->generateFile($routerPath, 'vue-router-plugin.stub', $replacements);
        $this->line("  <fg=green>✓</> Router: {$routerPath}");

        // 6. Generate Locale files
        $localeZhPath = base_path("web/src/views/plugin/{$pluginKebab}/{$viewName}/locale/zh-CN.ts");
        $this->generateFile($localeZhPath, 'vue-locale-zh.stub', $replacements);
        $this->line("  <fg=green>✓</> Locale ZH: {$localeZhPath}");

        $localeEnPath = base_path("web/src/views/plugin/{$pluginKebab}/{$viewName}/locale/en-US.ts");
        $this->generateFile($localeEnPath, 'vue-locale-en.stub', $replacements);
        $this->line("  <fg=green>✓</> Locale EN: {$localeEnPath}");

        $this->newLine();
        $this->info('Admin Plugin Controller created successfully!');
        $this->newLine();
        $this->warn('Note: You may need to import the locale file in your main locale config:');
        $this->line("  import locale{$pluginStudly}{$studlyName} from '@/views/plugin/{$pluginKebab}/{$viewName}/locale/zh-CN';");
        $this->line("  Then spread it: ...locale{$pluginStudly}{$studlyName}");

        return Command::SUCCESS;
    }
}
