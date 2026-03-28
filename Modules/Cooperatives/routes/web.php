<?php

use Illuminate\Support\Facades\Route;
use Modules\Cooperatives\Http\Controllers\CooperativesController;

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

Route::group(['middleware' => ['auth', 'XSS']], function () {
    Route::get('cooperatives/export', [CooperativesController::class, 'export'])->name('cooperatives.export');
    Route::get('cooperatives/import/export', [CooperativesController::class, 'fileImportExport'])->name('cooperatives.file.import');
    Route::post('cooperatives/import', [CooperativesController::class, 'fileImport'])->name('cooperatives.import');
    Route::get('cooperatives/{id}/export-farmers', [CooperativesController::class, 'exportFarmers'])->name('cooperatives.farmers.export');
    Route::resource('cooperatives', CooperativesController::class)->names('cooperatives');
});
