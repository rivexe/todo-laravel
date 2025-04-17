<?php

use App\Http\Controllers\TaskController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Главная страница - список задач
Route::get('/', [TaskController::class, 'index'])->name('tasks.index');

// API для задач
Route::prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/list', [TaskController::class, 'getTasks'])->name('list');
    Route::get('/{task}', [TaskController::class, 'show'])->name('show');
    Route::post('/', [TaskController::class, 'store'])->name('store');
    Route::patch('/{task}/status', [TaskController::class, 'updateStatus'])->name('update.status');
    Route::put('/{task}', [TaskController::class, 'update'])->name('update');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
});

// Страница тегов
Route::get('/tags', [TagController::class, 'index'])->name('tags.index');

// API для тегов
Route::prefix('tags')->name('tags.')->group(function () {
    Route::get('/list', [TagController::class, 'getTags'])->name('list');
    Route::post('/', [TagController::class, 'store'])->name('store');
    Route::put('/{tag}', [TagController::class, 'update'])->name('update');
    Route::delete('/{tag}', [TagController::class, 'destroy'])->name('destroy');
});
