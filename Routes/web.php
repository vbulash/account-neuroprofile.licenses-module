<?php

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

use Modules\Licenses\Http\Controllers\LicensesController;

// Лицензии контракта
Route::group(['prefix' => '/contracts/{contract}', 'middleware' => 'auth', 'controller' => LicensesController::class], function () {
	Route::get('/licenses', 'index')->name('contracts.licenses.index');
	Route::get('/licenses.data', 'getData')->name('contracts.licenses.index.data');
	Route::post('/licenses.export', 'export')->name('contracts.licenses.export');
	Route::post('/licenses.repair', 'repair')->name('contracts.licenses.repair');
	Route::post('/licenses.info', 'info')->name('contracts.licenses.info');
});