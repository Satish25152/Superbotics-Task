<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Laravel's container automatically resolves service constructor dependencies.
    }

    public function boot(): void
    {
    }
}
