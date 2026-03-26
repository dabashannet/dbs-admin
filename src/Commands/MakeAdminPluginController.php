<?php

namespace Dabashan\DbsAdmin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminPluginController extends Command
{
    protected $signature = 'make:admin-plugin-controller 
                            {plugin : Plugin name (e.g. DemoPlugin)}
                            {name : Controller name (e.g. User, Order)}';

    protected $description = 'Create a new Admin Plugin controller with Grid/Form, Vue page and API file';

    public function handle(): int
    {
        $plugin = $this->argument('plugin');
        $name = $this->argument('name');

        $pluginStudly = Str::studly($plugin);
        $pluginKebab = Str::kebab($plugin);
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        $modelName = $studlyName;
        $controllerName = $studlyName . 'Controller';

        // 1. Generate Controller
        $controllerPath = app_path("Admin/Controllers/Plugins/{$pluginStudly}/{$controllerName}.php");
        $this->generateFile($controllerPath, 'controller.plugin.stub', [
            '{{ class }}' => $controllerName,
            '{{ model }}' => $modelName,
            '{{ name }}' => $studlyName,
            '{{ plugin }}' => $pluginStudly,
        ]);

        // 2. Generate Vue page
        $vuePath = base_path("web/src/views/plugin/{$pluginKebab}/{$kebabName}/index.vue");
        $this->generateFile($vuePath, 'vue-page.stub', [
            '{{ name }}' => $studlyName,
            '{{ kebabName }}' => $kebabName,
            '{{ title }}' => $studlyName . ' Management',
        ]);

        // 3. Generate API file
        $apiPath = base_path("web/src/api/{$pluginKebab}-{$kebabName}.ts");
        $this->generateFile($apiPath, 'vue-api.stub', [
            '{{ name }}' => $studlyName,
            '{{ kebabName }}' => $kebabName,
            '{{ apiPrefix }}' => '/admin/plugins/' . $pluginKebab . '/' . Str::plural($kebabName),
        ]);

        $this->info('Admin Plugin Controller created successfully:');
        $this->line("  Controller: {$controllerPath}");
        $this->line("  Vue Page:   {$vuePath}");
        $this->line("  API File:   {$apiPath}");

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
