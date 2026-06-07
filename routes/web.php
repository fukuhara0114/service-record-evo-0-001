<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return view('welcome');
});

// 表示用画面（Blade）を返すルート
Route::get('/users', function () {
    return view('users.index');
});

// Vueからアクセスしてユーザーデータを取得するためのAPI用ルート
Route::get('/api/users', [UserController::class, 'index']);