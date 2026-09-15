<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CasoController;
use App\Http\Controllers\NvidiaApiController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/registro-caso', [CasoController::class, 'create'])->name('casos.create');
Route::post('/registro-caso', [CasoController::class, 'store'])->name('casos.store');
Route::post('/api/nvidia', [NvidiaApiController::class, 'process'])->name('api.nvidia');
