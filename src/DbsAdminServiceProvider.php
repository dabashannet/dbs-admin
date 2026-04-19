<?php

namespace Dabashan\DbsAdmin;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Dabashan\DbsAdmin\Commands\MakeAdminCommand;
use Dabashan\DbsAdmin\Commands\MakePluginCommand;
use Dabashan\DbsAdmin\Commands\MakePluginPageCommand;
use Dabashan\DbsAdmin\Controllers\CodeGeneratorController;

class DbsAdminServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeAdminCommand::class,
                MakePluginCommand::class,
                MakePluginPageCommand::class,
            ]);
        }

        // 注册代码生成器路由
        Route::prefix('admin')
            ->middleware('api')
            ->group(function () {
                Route::get('/code-generator', [CodeGeneratorController::class, 'index']);
                Route::get('/code-generator/config', [CodeGeneratorController::class, 'generatorConfig']);
                Route::get('/code-generator/plugins', [CodeGeneratorController::class, 'plugins']);
                Route::post('/code-generator/preview', [CodeGeneratorController::class, 'preview']);
                Route::post('/code-generator/generate', [CodeGeneratorController::class, 'generate']);
                Route::post('/code-generator/delete', [CodeGeneratorController::class, 'delete']);
                Route::post('/code-generator/files', [CodeGeneratorController::class, 'files']);
            });
    }
}
