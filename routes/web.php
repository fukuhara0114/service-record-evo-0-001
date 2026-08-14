<?php

// routes/web.php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FileImportController;
use App\Http\Controllers\IntakeOcrController;
use App\Http\Controllers\LoanerCalendarController;
use App\Http\Controllers\LoanerRecordController;
use App\Http\Controllers\MaintenanceContractController;
use App\Http\Controllers\MasterPriceRevisionController;
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
    Route::get('/servicerecord/engineer',[ServiceRecordController::class, 'engineer'])->name('servicerecord.engineer');
    Route::get('/servicerecord/logistics',[ServiceRecordController::class, 'logistics'])->name('servicerecord.logistics');
    Route::get('/servicerecord/shipping-prep',[ServiceRecordController::class, 'shippingPrep'])->name('servicerecord.shipping-prep');
    Route::get('/servicerecord/intake', [ServiceRecordController::class, 'intakeList'])->name('servicerecord.intake');
    Route::get('/servicerecord/camera', [ServiceRecordController::class, 'camera'])->name('servicerecord.camera');
    Route::get('/servicerecord/gallery', [ServiceRecordController::class, 'gallery'])->name('servicerecord.gallery');
    Route::get('/servicerecord/camera/images', [ServiceRecordController::class, 'listCapturedImages'])->name('servicerecord.camera.images');
    Route::post('/servicerecord/camera/upload', [ServiceRecordController::class, 'uploadCameraImage'])->name('servicerecord.camera.upload');
    Route::post('/servicerecord/camera/edit', [ServiceRecordController::class, 'editCapturedImage'])->name('servicerecord.camera.edit');
    Route::post('/servicerecord/camera/associate', [ServiceRecordController::class, 'associateCapturedImages'])->name('servicerecord.camera.associate');
    Route::post('/servicerecord/camera/disassociate', [ServiceRecordController::class, 'disassociateCapturedImages'])->name('servicerecord.camera.disassociate');
    Route::post('/servicerecord/camera/delete', [ServiceRecordController::class, 'deleteCapturedImages'])->name('servicerecord.camera.delete');
    Route::get('/servicerecord/camera/image/{fileName}', [ServiceRecordController::class, 'showCapturedImage'])->where('fileName', '[A-Za-z0-9._-]+')->name('servicerecord.camera.image');
    Route::get('/servicerecord/camera/thumbnail/{fileName}', [ServiceRecordController::class, 'showCapturedThumbnail'])->where('fileName', '[A-Za-z0-9._-]+')->name('servicerecord.camera.thumbnail');
    Route::get('/servicerecord/intake/create', [ServiceRecordController::class, 'createWithoutFile'])->name('servicerecord.intake.create-blank');
    Route::post('/servicerecord/intake/upload', [ServiceRecordController::class, 'uploadForIntake'])->name('servicerecord.intake.upload');
    Route::post('/servicerecord/file-import/start', [FileImportController::class, 'start'])->name('servicerecord.file-import.start');
    Route::get('/servicerecord/intake/{fileId}/create', [ServiceRecordController::class, 'createFromFile'])->name('servicerecord.intake.create');
    Route::get('/servicerecord/search-existing', [ServiceRecordController::class, 'searchExisting'])->name('servicerecord.search-existing');
    Route::post('/servicerecord/intake/link-existing', [ServiceRecordController::class, 'linkToExisting'])->name('servicerecord.intake.link-existing');
    Route::get('/servicerecord/record/{orderID}', [ServiceRecordController::class, 'record'])->name('servicerecord.record');
    Route::get('/servicerecord/attachments/{orderID}', [ServiceRecordController::class, 'attachments'])->name('servicerecord.attachments');
    Route::get('/servicerecord/files/{fileId}', [ServiceRecordController::class, 'fileContent'])->name('servicerecord.file-content');
    Route::get('/servicerecord/files/{fileId}/eml-preview', [ServiceRecordController::class, 'emlPreview'])->name('servicerecord.files.eml-preview');
    Route::get('/servicerecord/files/{fileId}/eml-attachment/{index}', [ServiceRecordController::class, 'emlAttachment'])->name('servicerecord.files.eml-attachment');
    Route::post('/servicerecord/files/{fileId}/eml-reply-draft', [ServiceRecordController::class, 'emlReplyDraft'])->name('servicerecord.files.eml-reply-draft');
    Route::post('/servicerecord/intake/store', [ServiceRecordController::class, 'storeFromIntake'])->name('servicerecord.intake.store');
    Route::post('/servicerecord/intake/ocr', IntakeOcrController::class)->name('servicerecord.intake.ocr');
    Route::get('/servicerecord/loaner/create', [LoanerRecordController::class, 'create'])->name('servicerecord.loaner.create');
    Route::get('/servicerecord/loaner/availability', [LoanerRecordController::class, 'availability'])->name('servicerecord.loaner.availability');
    Route::post('/servicerecord/loaner/store', [LoanerRecordController::class, 'store'])->name('servicerecord.loaner.store');
    Route::get('/servicerecord/loaner/detail/{id}', [LoanerRecordController::class, 'detail'])->name('servicerecord.loaner.detail');
    Route::put('/servicerecord/loaner/detail/{id}', [LoanerRecordController::class, 'updateDetail'])->name('servicerecord.loaner.detail.update');
    Route::post('/servicerecord/loaner/detail/{id}/promote', [LoanerRecordController::class, 'promoteFromWaiting'])->name('servicerecord.loaner.detail.promote');
    Route::get('/servicerecord/loaner/period/{id}', [LoanerRecordController::class, 'editPeriod'])->name('servicerecord.loaner.period.edit');
    Route::put('/servicerecord/loaner/period/{id}', [LoanerRecordController::class, 'updatePeriod'])->name('servicerecord.loaner.period.update');
    Route::post('/servicerecord/loaner/period/{id}/parent', [LoanerRecordController::class, 'linkParent'])->name('servicerecord.loaner.period.parent');
    Route::get('/servicerecord/loaner/calendar', [LoanerCalendarController::class, 'index'])->name('servicerecord.loaner.calendar');
    Route::get('/servicerecord/loaner/calendar/events', [LoanerCalendarController::class, 'events'])->name('servicerecord.loaner.calendar.events');
    Route::get('/servicerecord/maintenance-contracts', [MaintenanceContractController::class, 'index'])->name('servicerecord.maintenance-contracts');
    Route::get('/servicerecord/maintenance-contracts/search', [MaintenanceContractController::class, 'search'])->name('servicerecord.maintenance-contracts.search');
    Route::get('/servicerecord/maintenance-contracts/{id}', [MaintenanceContractController::class, 'edit'])->name('servicerecord.maintenance-contracts.edit');
    Route::put('/servicerecord/maintenance-contracts/{id}', [MaintenanceContractController::class, 'update'])->name('servicerecord.maintenance-contracts.update');
    Route::get('/servicerecord/shipping-calendar', [ServiceRecordController::class, 'shippingCalendar'])->name('servicerecord.shipping-calendar');
    Route::get('/servicerecord/shipping-calendar/events', [ServiceRecordController::class, 'shippingCalendarEvents'])->name('servicerecord.shipping-calendar.events');
    Route::get('/servicerecord/master-price-revision', [MasterPriceRevisionController::class, 'index'])->name('servicerecord.master-price-revision');
    Route::post('/servicerecord/master-price-revision', [MasterPriceRevisionController::class, 'store'])->name('servicerecord.master-price-revision.store');
    Route::post('/servicerecord/notes', [ServiceRecordController::class, 'storeNote'])->name('servicerecord.notes.store');
    Route::put('/servicerecord/notes/{id}', [ServiceRecordController::class, 'updateNote'])->name('servicerecord.notes.update');
    Route::delete('/servicerecord/notes/{id}', [ServiceRecordController::class, 'destroyNote'])->name('servicerecord.notes.destroy');
    Route::get('/servicerecord/unregistered-email-notes', [ServiceRecordController::class, 'listUnregisteredEmailNotes'])->name('servicerecord.unregistered-email-notes.index');
    Route::post('/servicerecord/unregistered-email-notes/{id}/link', [ServiceRecordController::class, 'linkUnregisteredEmailNote'])->name('servicerecord.unregistered-email-notes.link');
    Route::delete('/servicerecord/unregistered-email-notes/{id}', [ServiceRecordController::class, 'destroyUnregisteredEmailNote'])->name('servicerecord.unregistered-email-notes.destroy');
    Route::post('/servicerecord/files', [ServiceRecordController::class, 'storeFile'])->name('servicerecord.files.store');
    Route::put('/servicerecord/files/{id}', [ServiceRecordController::class, 'updateFile'])->name('servicerecord.files.update');
    Route::put('/servicerecord/files/{id}/content', [ServiceRecordController::class, 'updateFileContent'])->name('servicerecord.files.content.update');
    Route::delete('/servicerecord/files/{id}', [ServiceRecordController::class, 'destroyFile'])->name('servicerecord.files.destroy');
    Route::post('/servicerecord/parts', [ServiceRecordController::class, 'storePart'])->name('servicerecord.parts.store');
    Route::delete('/servicerecord/parts/{id}', [ServiceRecordController::class, 'destroyPart'])->name('servicerecord.parts.destroy');
    Route::post('/servicerecord/stocked-parts', [ServiceRecordController::class, 'storeStockedPart'])->name('servicerecord.stocked-parts.store');
    Route::put('/servicerecord/stocked-parts/{id}', [ServiceRecordController::class, 'updateStockedPart'])->name('servicerecord.stocked-parts.update');
    Route::delete('/servicerecord/stocked-parts/{id}', [ServiceRecordController::class, 'destroyStockedPart'])->name('servicerecord.stocked-parts.destroy');
    Route::post('/servicerecord/{orderID}/email-draft-preview', [ServiceRecordController::class, 'emailDraftPreview'])->name('servicerecord.email-draft-preview');
    Route::put('/servicerecord/{orderID}', [ServiceRecordController::class, 'update'])->name('servicerecord.update');
    Route::post('/servicerecord/{orderID}', [ServiceRecordController::class, 'update'])->name('servicerecord.update.post');
    Route::get('/servicerecords/detail/{orderID}', [ServiceRecordController::class, 'detail'])->name('servicerecords.detail');

    Route::get('/servicerecord_q',[ServiceRecordController::class, 'index_q'])->name('servicerecord.index_q');

});
