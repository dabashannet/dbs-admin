<?php

namespace Dabashan\DbsAdmin\Commands;

use Dabashan\DbsAdmin\Traits\HasFileGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePluginCommand extends Command
{
    use HasFileGeneration;

    protected $signature = 'make:plugin
                            {name : Plugin name in snake_case (e.g. demo_plugin, shop)}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a complete plugin structure with Admin/Http controllers, routes, config, and ServiceProvider';

    public function handle(): int
    {
        $name = Str::snake($this->argument('name'));
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        $pluginPath = base_path("plugins/{$studlyName}");

        if (is_dir($pluginPath) && !$this->option('force')) {
            $this->error("Plugin directory already exists: {$pluginPath}");
            $this->line('  Use --force to overwrite');
            return Command::FAILURE;
        }

        $replacements = [
            '{{ pluginName }}' => $name,
            '{{ pluginStudly }}' => $studlyName,
            '{{ pluginTitle }}' => $studlyName,
            '{{ pluginKebab }}' => $kebabName,
        ];

        $this->info("Creating plugin [{$name}]...");
        $this->newLine();

        // 1. plugin.json
        $this->generateFile(
            "{$pluginPath}/plugin.json",
            'plugin.json.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> plugin.json");

        // 2. Config
        $this->generateFile(
            "{$pluginPath}/config/{$name}.php",
            'plugin.config.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> config/{$name}.php");

        // 3. ServiceProvider
        $this->generateFile(
            "{$pluginPath}/Providers/PluginServiceProvider.php",
            'plugin.provider.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Providers/PluginServiceProvider.php");

        // 4. Admin Controller
        $this->generateFile(
            "{$pluginPath}/Admin/Controllers/{$studlyName}Controller.php",
            'plugin.admin-controller.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Admin/Controllers/{$studlyName}Controller.php");

        // 5. Admin Routes
        $this->generateFile(
            "{$pluginPath}/Admin/routes.php",
            'plugin.admin-routes.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Admin/routes.php");

        // 6. Http Controller
        $this->generateFile(
            "{$pluginPath}/Http/Controllers/{$studlyName}Controller.php",
            'plugin.http-controller.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Http/Controllers/{$studlyName}Controller.php");

        // 7. Http Routes
        $this->generateFile(
            "{$pluginPath}/Http/routes.php",
            'plugin.http-routes.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Http/routes.php");

        // 8. Empty directories
        foreach (['Models', 'Services', 'Support', 'database/migrations', 'static'] as $dir) {
            $dirPath = "{$pluginPath}/{$dir}";
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            if (!file_exists("{$dirPath}/.gitkeep")) {
                file_put_contents("{$dirPath}/.gitkeep", '');
            }
        }
        $this->line("  <fg=green>✓</> Directory structure");

        $this->newLine();
        $this->info("Plugin [{$name}] created successfully!");
        $this->newLine();
        $this->line("  Plugin path: {$pluginPath}");
        $this->line("  Admin API:   /admin/plugin/{$name}/*");
        $this->line("  Business API: /api/plugin/{$name}/*");
        $this->newLine();
        $this->warn("Next steps:");
        $this->line("  1. Review and customize the generated files");
        $this->line("  2. Set 'enabled' to true in plugin.json");
        $this->line("  3. Run: composer dump-autoload");
        $this->line("  4. If using migrations: php artisan migrate");

        return Command::SUCCESS;
    }
}
