<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('rewards:info', function (): void {
    $this->info('Run php artisan migrate:fresh --seed to populate demo rewards data.');
})->purpose('Show the rewards demo setup command');
