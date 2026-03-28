<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;


class AppServiceProvider extends ServiceProvider
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
        
        // Manual loading for Milk Collection module bypass
        $this->loadViewsFrom(base_path('Modules/MilkCollection/resources/views'), 'milkcollection');
        $this->loadMigrationsFrom(base_path('Modules/MilkCollection/database/migrations'));
        $this->loadRoutesFrom(base_path('Modules/MilkCollection/routes/web.php'));
        $this->loadRoutesFrom(base_path('Modules/MilkCollection/routes/api.php'));
    }
}
