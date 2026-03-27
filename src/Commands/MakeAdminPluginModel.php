<?php

namespace Dabashan\DbsAdmin\Commands;

use Dabashan\DbsAdmin\Traits\HasFileGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminPluginModel extends Command
{
    use HasFileGeneration;

    protected $signature = 'make:admin-plugin-model 
                            {name : Model name (e.g. Order, Product)}
                            {--m|migration : Create a new migration file for the model}
                            {--force : Overwrite existing files}';

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
        $this->line("  <fg=green>✓</> Model: {$modelPath}");

        // Generate migration if requested
        if ($this->option('migration')) {
            $this->call('make:migration', [
                'name' => "create_{$tableName}_table",
                '--create' => $tableName,
            ]);
        }

        return Command::SUCCESS;
    }
}
