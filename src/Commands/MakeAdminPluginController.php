<?php

namespace Dabashan\DbsAdmin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminPluginController extends Command
{
    protected $signature = 'make:admin-plugin-controller
                            {plugin : Plugin name (e.g. DemoPlugin, Shop)}
                            {name : Controller name (e.g. User, Order)}
                            {--view-name= : Custom Vue view directory name (kebab-case)}';

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

        // 1. Generate Controller
        $controllerPath = app_path("Admin/Controllers/Plugins/{$pluginStudly}/{$controllerName}.php");
        $this->generateFile($controllerPath, 'controller.plugin.stub', $replacements);

        // 2. Generate Vue page
        $vuePath = base_path("web/src/views/plugin/{$pluginKebab}/{$viewName}/index.vue");
        $this->generateFile($vuePath, 'vue-page.stub', $replacements);

        // 3. Generate API file
        $apiPath = base_path("web/src/api/{$pluginKebab}-{$kebabName}.ts");
        $this->generateFile($apiPath, 'vue-api.stub', $replacements);

        // 4. Generate Router module file
        $routerPath = base_path("web/src/router/routes/modules/plugin-{$pluginKebab}-{$kebabName}.ts");
        $this->generateFile($routerPath, 'vue-router-plugin.stub', $replacements);

        // 5. Generate Locale files
        $localeZhPath = base_path("web/src/views/plugin/{$pluginKebab}/{$viewName}/locale/zh-CN.ts");
        $this->generateFile($localeZhPath, 'vue-locale-zh.stub', $replacements);

        $localeEnPath = base_path("web/src/views/plugin/{$pluginKebab}/{$viewName}/locale/en-US.ts");
        $this->generateFile($localeEnPath, 'vue-locale-en.stub', $replacements);

        $this->info('Admin Plugin Controller created successfully:');
        $this->line("  Controller: {$controllerPath}");
        $this->line("  Vue Page:   {$vuePath}");
        $this->line("  API File:   {$apiPath}");
        $this->line("  Router:     {$routerPath}");
        $this->line("  Locale ZH:  {$localeZhPath}");
        $this->line("  Locale EN:  {$localeEnPath}");
        $this->newLine();
        $this->warn('Note: You may need to import the locale file in your main locale config:');
        $this->line("  import locale{$pluginStudly}{$studlyName} from '@/views/plugin/{$pluginKebab}/{$viewName}/locale/zh-CN';");
        $this->line("  Then spread it: ...locale{$pluginStudly}{$studlyName}");

        return Command::SUCCESS;
    }

    protected function generateFile(string $path, string $stub, array $replacements): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->warn("File already exists: {$path}");
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
    }
}
