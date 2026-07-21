<?php

// routes/web.php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ServiceRecordController;
use Illuminate\Support\Facades\Route;

// 1. ログイン画面・ログイン処理のルーティング
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// ログイン必須のルート（Vueを動かす土台）
Route::middleware('auth')->group(function () {

 // ✨ 新設：ログイン直後に着地するシンプルなホーム画面
    Route::get('/home', function () {
        return view('home'); // resources/views/home.blade.php を返す
    })->name('home');

    Route::get('/', function () {
        return view('app'); // resources/views/app.blade.php を返す
    });
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


    Route::get('/servicerecord',[ServiceRecordController::class, 'index'])->name('servicerecord.index');
    Route::get('/servicerecord/administrator',[ServiceRecordController::class, 'administrator'])->name('servicerecord.administrator');
    Route::get('/servicerecords/detail/{orderID}', [ServiceRecordController::class, 'detail'])
     ->name('servicerecords.detail');

    
    Route::get('/servicerecord_q',[ServiceRecordController::class, 'index_q'])->name('servicerecord.index_q');

});
