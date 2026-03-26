<?php

namespace Dabashan\DbsAdmin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminPluginModel extends Command
{
    protected $signature = 'make:admin-plugin-model 
                            {name : Model name (e.g. Order, Product)}
                            {--m|migration : Create a new migration file for the model}';

    protected $description = 'Create a new Admin Plugin model extending BaseAdminModel';

    public function handle(): int
    {
        $name = $this->argument('name');
        $studlyName = Str::studly($name);
        $tableName = Str::snake(Str::plural($studlyName));

        // Generate Model
        $modelPath = app_path("Admin/Models/Plugins/{$studlyName}.php");
        $this->generateFile($modelPath, 'model.plugin.stub', [
            '{{ class }}' => $studlyName,
            '{{ table }}' => $tableName,
        ]);

        $this->info("Admin Plugin Model created successfully:");
        $this->line("  Model: {$modelPath}");

        // Generate migration if requested
        if ($this->option('migration')) {
            $this->call('make:migration', [
                'name' => "create_{$tableName}_table",
                '--create' => $tableName,
            ]);
        }

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
