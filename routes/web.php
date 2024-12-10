<?php

use App\MyClass;
use Illuminate\Support\Facades\Route;
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

$myStartTime = microtime(true);

Route::get('/', static function () use ($myStartTime) {
    $myLocalStartTime = microtime(true);
    return DateTime::createFromFormat('U.u', $myStartTime)
            ->format("r (u)") . " - " .
        DateTime::createFromFormat('U.u', $myLocalStartTime)
            ->format("r (u)");
});

Route::get('/static-class', static function (MyClass $myClass) {
    //xdebug_break();
    $myClass->add();
    print $myClass->get();
    return false;
});

Route::get('/home', [HomeController::class, 'index'])->name('home');
