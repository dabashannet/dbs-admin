<?php

namespace Dabashan\DbsAdmin\Commands;

use Dabashan\DbsAdmin\Traits\HasFileGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminModel extends Command
{
    use HasFileGeneration;

    protected $signature = 'make:admin-model 
                            {name : Model name (e.g. AdminOrder)}
                            {--m|migration : Create a new migration file for the model}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a new Admin Core model extending BaseAdminModel';

    public function handle(): int
    {
        $name = $this->argument('name');
        $studlyName = Str::studly($name);
        $tableName = Str::snake(Str::plural(
            // Remove 'Admin' prefix for table name if exists
            Str::startsWith($studlyName, 'Admin')
                ? Str::substr($studlyName, 5)
                : $studlyName
        ));
        // Add admin_ prefix
        $tableName = 'admin_' . $tableName;

        // Generate Model
        $modelPath = app_path("Admin/Models/Core/{$studlyName}.php");
        $this->generateFile($modelPath, 'model.core.stub', [
            '{{ class }}' => $studlyName,
            '{{ table }}' => $tableName,
        ]);

        $this->info("Admin Model created successfully:");
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
