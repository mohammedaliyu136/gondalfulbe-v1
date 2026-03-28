<?php

use Illuminate\Support\Facades\Route;

require_once base_path('Modules/MilkCollection/app/Http/Controllers/MilkCollectionController.php');

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['web', 'auth', 'XSS'])->group(function() {
    Route::resource('milkcollection', \Modules\MilkCollection\Http\Controllers\MilkCollectionController::class)->names('milkcollection');
    Route::resource('mcc', \Modules\MilkCollection\Http\Controllers\MilkCollectionCenterController::class);
});
