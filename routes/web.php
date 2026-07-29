<?php

// routes/web.php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LoanerCalendarController;
use App\Http\Controllers\LoanerRecordController;
use App\Http\Controllers\ServiceRecordController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth; // ✨ 追加

// ========================================================
// 🌟 修正ポイント1: `/`（ルート）のアクセスは「auth」の外に置く
// ========================================================
Route::get('/', function () {
    // ログインしている場合は /home へリダイレクト
    if (Auth::check()) {
        return redirect()->route('home');
    }
    // ログインしていない場合は /login へリダイレクト
    return redirect()->route('login');
});


// 1. ログイン画面・ログイン処理のルーティング（未ログインのみ）
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

    // 🌟 修正ポイント2: ここにあった Route::get('/', ... view('app')) は削除しました
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


    Route::get('/servicerecord',[ServiceRecordController::class, 'index'])->name('servicerecord.index');
    Route::get('/servicerecord/administrator',[ServiceRecordController::class, 'administrator'])->name('servicerecord.administrator');
    Route::get('/servicerecord/intake', [ServiceRecordController::class, 'intakeList'])->name('servicerecord.intake');
    Route::get('/servicerecord/intake/{fileId}/create', [ServiceRecordController::class, 'createFromFile'])->name('servicerecord.intake.create');
    Route::get('/servicerecord/search-existing', [ServiceRecordController::class, 'searchExisting'])->name('servicerecord.search-existing');
    Route::post('/servicerecord/intake/link-existing', [ServiceRecordController::class, 'linkToExisting'])->name('servicerecord.intake.link-existing');
    Route::get('/servicerecord/record/{orderID}', [ServiceRecordController::class, 'record'])->name('servicerecord.record');
    Route::get('/servicerecord/attachments/{orderID}', [ServiceRecordController::class, 'attachments'])->name('servicerecord.attachments');
    Route::get('/servicerecord/files/{fileId}', [ServiceRecordController::class, 'fileContent'])->name('servicerecord.file-content');
    Route::post('/servicerecord/intake/store', [ServiceRecordController::class, 'storeFromIntake'])->name('servicerecord.intake.store');
    Route::get('/servicerecord/loaner/create', [LoanerRecordController::class, 'create'])->name('servicerecord.loaner.create');
    Route::get('/servicerecord/loaner/availability', [LoanerRecordController::class, 'availability'])->name('servicerecord.loaner.availability');
    Route::post('/servicerecord/loaner/store', [LoanerRecordController::class, 'store'])->name('servicerecord.loaner.store');
    Route::get('/servicerecord/loaner/calendar', [LoanerCalendarController::class, 'index'])->name('servicerecord.loaner.calendar');
    Route::get('/servicerecord/loaner/calendar/events', [LoanerCalendarController::class, 'events'])->name('servicerecord.loaner.calendar.events');
    Route::post('/servicerecord/notes', [ServiceRecordController::class, 'storeNote'])->name('servicerecord.notes.store');
    Route::put('/servicerecord/notes/{id}', [ServiceRecordController::class, 'updateNote'])->name('servicerecord.notes.update');
    Route::delete('/servicerecord/notes/{id}', [ServiceRecordController::class, 'destroyNote'])->name('servicerecord.notes.destroy');
    Route::post('/servicerecord/files', [ServiceRecordController::class, 'storeFile'])->name('servicerecord.files.store');
    Route::put('/servicerecord/files/{id}/content', [ServiceRecordController::class, 'updateFileContent'])->name('servicerecord.files.content.update');
    Route::delete('/servicerecord/files/{id}', [ServiceRecordController::class, 'destroyFile'])->name('servicerecord.files.destroy');
    Route::post('/servicerecord/parts', [ServiceRecordController::class, 'storePart'])->name('servicerecord.parts.store');
    Route::delete('/servicerecord/parts/{id}', [ServiceRecordController::class, 'destroyPart'])->name('servicerecord.parts.destroy');
    Route::put('/servicerecord/{orderID}', [ServiceRecordController::class, 'update'])->name('servicerecord.update');
    Route::post('/servicerecord/{orderID}', [ServiceRecordController::class, 'update'])->name('servicerecord.update.post');
    Route::get('/servicerecords/detail/{orderID}', [ServiceRecordController::class, 'detail'])->name('servicerecords.detail');

    Route::get('/servicerecord_q',[ServiceRecordController::class, 'index_q'])->name('servicerecord.index_q');

});
