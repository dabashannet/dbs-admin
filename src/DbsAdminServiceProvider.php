<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:44:11
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin;

use Dabashan\DbsAdmin\Commands\MakeAdminCommand;
use Dabashan\DbsAdmin\Commands\MakePluginCommand;
use Dabashan\DbsAdmin\Commands\MakePluginPageCommand;
use Dabashan\DbsAdmin\Controllers\CodeGeneratorController;
use Dabashan\DbsAdmin\Controllers\TaskController;
use Dabashan\DbsAdmin\Services\PluginService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DbsAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginService::class);
    }

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
                Route::post('/code-generator/preview-all', [CodeGeneratorController::class, 'previewAll']);
                Route::post('/code-generator/generate', [CodeGeneratorController::class, 'generate']);
                Route::post('/code-generator/delete', [CodeGeneratorController::class, 'delete']);
                Route::post('/code-generator/files', [CodeGeneratorController::class, 'files']);
                Route::get('/tasks/{id}/status', [TaskController::class, 'status']);
                Route::get('/tasks/{id}/logs', [TaskController::class, 'logs']);
                Route::get('/tasks/{id}/stream', [TaskController::class, 'stream']);
                Route::post('/tasks/{id}/cancel', [TaskController::class, 'cancel']);
            });
    }
}
