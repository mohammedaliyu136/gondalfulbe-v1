<?php

use Illuminate\Support\Facades\Route;
use Modules\MilkCollection\Http\Controllers\MilkCollectionController;

require_once base_path('Modules/MilkCollection/app/Http/Controllers/MilkCollectionController.php');
require_once base_path('Modules/MilkCollection/app/Http/Controllers/Api/MilkCollectionApiController.php');

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('milkcollection/sync', [\Modules\MilkCollection\Http\Controllers\Api\MilkCollectionApiController::class, 'sync']);
});
