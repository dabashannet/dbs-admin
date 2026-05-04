<?php

namespace Dabashan\DbsAdmin\Commands;

use Dabashan\DbsAdmin\Traits\HasFileGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePluginCommand extends Command
{
    use HasFileGeneration;

    protected $signature = 'make:plugin
                            {name : 插件标识（snake_case，如 demo_plugin、shop）}
                            {--force : 覆盖已有文件}';

    protected $description = '创建完整插件骨架（Admin/Http 控制器、路由、前端资源目录、ServiceProvider）';

    public function handle(): int
    {
        $name = Str::snake($this->argument('name'));
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        $pluginPath = base_path("plugins/{$studlyName}");

        if (is_dir($pluginPath) && !$this->option('force')) {
            $this->error("插件目录已存在：{$pluginPath}");
            $this->line('  使用 --force 可覆盖');
            return Command::FAILURE;
        }

        $replacements = [
            '{{ pluginName }}' => $name,
            '{{ pluginStudly }}' => $studlyName,
            '{{ pluginTitle }}' => $studlyName,
            '{{ pluginKebab }}' => $kebabName,
        ];

        $this->info("正在创建插件 [{$name}]...");
        $this->newLine();

        // 1. plugin.json
        $this->generateFile(
            "{$pluginPath}/plugin.json",
            'plugin.json.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> plugin.json");

        // 2. ServiceProvider
        $this->generateFile(
            "{$pluginPath}/Providers/PluginServiceProvider.php",
            'plugin.provider.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Providers/PluginServiceProvider.php");

        // 3. Admin Controller
        $this->generateFile(
            "{$pluginPath}/Admin/Controllers/{$studlyName}Controller.php",
            'plugin.admin-controller.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Admin/Controllers/{$studlyName}Controller.php");

        // 4. Admin Routes
        $this->generateFile(
            "{$pluginPath}/Admin/routes.php",
            'plugin.admin-routes.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Admin/routes.php");

        // 5. Http Controller
        $this->generateFile(
            "{$pluginPath}/Http/Controllers/{$studlyName}Controller.php",
            'plugin.http-controller.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Http/Controllers/{$studlyName}Controller.php");

        // 6. Http Routes
        $this->generateFile(
            "{$pluginPath}/Http/routes.php",
            'plugin.http-routes.stub',
            $replacements
        );
        $this->line("  <fg=green>✓</> Http/routes.php");

        // 7. 前端资源目录（自包含）
        foreach ([
            'resources/views',
            'resources/routes',
            'resources/static/images',
        ] as $dir) {
            $dirPath = "{$pluginPath}/{$dir}";
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
        }
        $this->line("  <fg=green>✓</> resources/ （前端资源目录）");

        // 8. 后端空目录（Support/ 和 static/ 按需手动创建，不再默认生成）
        foreach (['Models', 'Services', 'database/migrations'] as $dir) {
            $dirPath = "{$pluginPath}/{$dir}";
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            if (!file_exists("{$dirPath}/.gitkeep")) {
                file_put_contents("{$dirPath}/.gitkeep", '');
            }
        }
        $this->line("  <fg=green>✓</> 后端目录结构");

        $this->newLine();
        $this->info("插件 [{$name}] 创建成功！");
        $this->newLine();
        $this->line("  插件路径：{$pluginPath}");
        $this->line("  后台接口：/plugin/{$name}/admin/*");
        $this->line("  业务接口：/plugin/{$name}/api/*");
        $this->line("  前端资源：{$pluginPath}/resources/");
        $this->newLine();
        $this->warn("后续步骤：");
        $this->line("  1. 编辑 plugin.json 完善插件信息");
        $this->line("  2. 将 enabled 设为 true");
        $this->line("  3. 运行 composer dump-autoload");
        $this->line("  4. 如有迁移文件：php artisan migrate");
        $this->line("  5. 使用 make:plugin-page 添加页面");

        return Command::SUCCESS;
    }
}
