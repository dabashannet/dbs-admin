<?php

namespace Dabashan\DbsAdmin;

use Illuminate\Support\ServiceProvider;
use Dabashan\DbsAdmin\Commands\MakeAdminCommand;
use Dabashan\DbsAdmin\Commands\MakePluginCommand;
use Dabashan\DbsAdmin\Commands\MakePluginPageCommand;

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
    }
}
