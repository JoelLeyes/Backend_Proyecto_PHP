<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        Broadcast::routes(['middleware' => ['auth:sanctum']]);
        require base_path('routes/channels.php');
    }
}
