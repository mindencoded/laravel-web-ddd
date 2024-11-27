<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\HomeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

if (config('app.debug') === true) {
    Route::get('/clear', static function () {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        return "All cleared!";
    });

    Route::get('/routes-clear', static function () {
        Artisan::call('route:clear');
        return "Routes cached successfully.";
    });
}

$myStartTime = microtime(true);

Route::get('/', static function () use ($myStartTime) {
    $myLocalStartTime = microtime(true);
    $basePath = $_SERVER['APP_BASE_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? $serverState['octaneConfig']['base_path'] ?? null;
    return $basePath;
    /*return DateTime::createFromFormat('U.u', $myStartTime)
            ->format("r (u)") . " - " .
        DateTime::createFromFormat('U.u', $myLocalStartTime)
            ->format("r (u)");*/
});

Route::get('/home', [HomeController::class, 'index'])->name('home');
