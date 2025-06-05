<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

Route::get('/', function () {
    return redirect('/chat');
});

Route::get('/upload', [ChatbotController::class, 'upload']);
Route::get('/chat', [ChatbotController::class, 'index']);
Route::post('/train', [ChatbotController::class, 'train']);
Route::post('/chat', [ChatbotController::class, 'chat']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
