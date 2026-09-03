<?php

namespace App\Http\Controllers;

use App\Models\AttachedFile;
use App\Services\GeminiOcrService;
use App\Services\XsrvAuthService;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class IntakeOcrController extends Controller
{
    public function __invoke(Request $request, GeminiOcrService $ocr, XsrvAuthService $xsrvAuth)
    {
        $auth = $xsrvAuth->check();
        if (! ($auth['ok'] ?? false)) {
            return response()->json([
                'status' => 'error',
                'message' => $auth['message'] ?? '認証に失敗しました。',
            ], 403);
        }

        $validated = $request->validate([
            'fileId' => 'required|integer',
        ]);

        $file = AttachedFile::query()->findOrFail($validated['fileId']);
        $binary = $this->decodeAttachedFileBinary($file);
        $mimeType = trim((string) ($file->fileType ?? ''));
        if ($mimeType === '') {
            $mimeType = 'application/pdf';
        }

        if (@set_time_limit(180) === false) {
            // IIS 等で無効でも処理は継続
        }

        try {
            $result = $ocr->extractFromBinary($binary, $mimeType);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'OCR 処理に失敗しました。',
            ], 500);
        }

        return response()->json([
            'message' => 'OCR 読み取りが完了しました。',
            'fields' => $result['fields'],
            'flags' => $result['flags'],
        ]);
    }

    private function decodeAttachedFileBinary(AttachedFile $file): string
    {
        $raw = $file->content ?? '';
        if ($raw === '') {
            abort(404, 'ファイル内容がありません。');
        }

        if (str_starts_with($raw, 'data:')) {
            $parts = explode(',', $raw, 2);
            $raw = $parts[1] ?? $raw;
        }

        $binary = base64_decode($raw, true);
        if ($binary === false) {
            $binary = $raw;
        }

        return $binary;
    }
}
