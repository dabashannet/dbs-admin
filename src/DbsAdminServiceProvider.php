<?php

namespace Dabashan\DbsAdmin;

use Illuminate\Support\ServiceProvider;
use Dabashan\DbsAdmin\Commands\MakeAdminController;
use Dabashan\DbsAdmin\Commands\MakeAdminPluginController;
use Dabashan\DbsAdmin\Commands\MakeAdminModel;
use Dabashan\DbsAdmin\Commands\MakeAdminPluginModel;

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
                MakeAdminController::class,
                MakeAdminPluginController::class,
                MakeAdminModel::class,
                MakeAdminPluginModel::class,
            ]);
        }
    }
}
