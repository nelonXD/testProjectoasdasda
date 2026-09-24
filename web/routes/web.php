<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CasoController;
use App\Http\Controllers\ModeloLocalController;
use App\Http\Controllers\OpenAiController;
use App\Http\Controllers\NvidiaCloudController;

Route::get('/', [CasoController::class, 'create'])->name('home');

Route::get('/registro-caso', [CasoController::class, 'create'])->name('casos.create');
Route::post('/registro-caso', [CasoController::class, 'store'])->name('casos.store');
Route::post('/api/modelo-local', [ModeloLocalController::class, 'analyze'])->name('api.modelo_local');
Route::post('/api/nvidia', [ModeloLocalController::class, 'analyze'])->name('api.nvidia');
Route::post('/api/openai', [OpenAiController::class, 'analyze'])->name('api.openai');
Route::post('/api/nvidiacloud', [NvidiaCloudController::class, 'analyze'])->name('api.nvidiacloud');
Route::get('/api/modelo-local/models', [ModeloLocalController::class, 'getModels'])->name('api.modelo_local.models');
Route::get('/api/models', [ModeloLocalController::class, 'getModels'])->name('api.models');
