<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/all-clear', static function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('config:cache');
    Artisan::call('route:cache');
    return "All cleared!";
});

Route::get('/routes-clear', static function () {
    Artisan::call('route:clear');
    Artisan::call('route:cache');
    return "Routes cached successfully.";
});
