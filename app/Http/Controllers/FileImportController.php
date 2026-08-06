<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImportFilesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FileImportController extends Controller
{
    /**
     * ファイル置き場の取込ジョブを起動する。
     * QUEUE_CONNECTION=sync の場合はリクエスト内で即実行（queue:work 不要）。
     * 長時間の PDF 変換は PHP / IIS のリクエストタイムアウトに注意。
     * 実行中ロックがある場合は 423、それ以外はディスパッチして 200。
     * （sync でも Job の finally で running ロックを解放するため、正常完了時にロックは残らない）
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'associatedID' => 'nullable|integer',
        ]);

        $lockKey = (string) config('pdf_import.lock.key', 'file_import_lock');
        $lockSeconds = max(10, (int) config('pdf_import.lock.seconds', 60));
        $runningKey = $lockKey . ':running';

        // 実際にジョブが走っている間は再起動させない
        $runningLock = Cache::lock($runningKey, 1);
        if (!$runningLock->get()) {
            return response()->json([
                'message' => '現在他のユーザーが処理中です',
                'status' => 'locked',
            ], 423);
        }
        // プローブ用にすぐ解放（実行中判定のみ）
        try {
            $runningLock->release();
        } catch (\Throwable) {
            // ignore
        }

        $dispatchLock = Cache::lock($lockKey . ':dispatch', 10);
        if (!$dispatchLock->get()) {
            return response()->json([
                'message' => '現在他のユーザーが処理中です',
                'status' => 'locked',
            ], 423);
        }

        $associatedID = array_key_exists('associatedID', $validated)
            ? (int) $validated['associatedID']
            : (int) config('pdf_import.db.default_associated_id', -1);

        try {
            ProcessImportFilesJob::dispatch($associatedID);

            Log::info('PDF import job dispatched.', [
                'associatedID' => $associatedID,
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            return response()->json([
                'message' => '処理を開始しました',
                'status' => 'started',
                'associatedID' => $associatedID,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('PDF import job dispatch failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => '処理の開始に失敗しました。',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            try {
                $dispatchLock->release();
            } catch (\Throwable) {
                // ignore
            }
        }
    }
}
