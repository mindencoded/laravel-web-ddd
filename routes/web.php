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

$myStartTime = number_format(microtime(true), 6, '.', '');

Route::get('/', static function () use ($myStartTime) {

    $myStartDateTimeFormat = DateTime::createFromFormat('U.u', $myStartTime)->format("r (u)");

    $myLocalStartDateTimeFormat = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''))->format("r (u)");

    return $myStartDateTimeFormat . " - " . $myLocalStartDateTimeFormat;
});

Route::get('/static-class', static function (MyClass $myClass) {
    $myClass->add();
    print $myClass->get();
    return true;
});

Route::get('/home', [HomeController::class, 'index'])->name('home');
