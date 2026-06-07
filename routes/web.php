<?php

// routes/web.php
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// 1. ログイン画面・ログイン処理のルーティング
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// ログイン必須のルート（Vueを動かす土台）
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return view('app'); // resources/views/app.blade.php を返す
    });
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
