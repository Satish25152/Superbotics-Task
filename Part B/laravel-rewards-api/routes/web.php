<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Rewards Redemption API',
    'status' => 'ok',
]));
