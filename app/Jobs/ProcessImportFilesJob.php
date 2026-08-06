<?php

namespace App\Jobs;

use App\Models\AttachedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;

class ProcessImportFilesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * ジョブ失敗までの試行回数
     */
    public int $tries = 1;

    /**
     * ジョブ全体のタイムアウト（秒）
     */
    public int $timeout = 600;

    public function __construct(
        public readonly ?int $associatedID = null,
    ) {
    }

    public function handle(): void
    {
        $processLockKey = (string) config('pdf_import.lock.key', 'file_import_lock') . ':running';
        $processLock = Cache::lock($processLockKey, max(60, (int) $this->timeout + 30));

        if (!$processLock->get()) {
            Log::info('PDF import: another job is already running.');

            return;
        }

        try {
            $paths = $this->resolvedPaths();
            $this->ensureDirectories($paths);

            $inboxFiles = $this->listInboxFiles($paths['inbox']);
            if ($inboxFiles === []) {
                Log::info('PDF import: inbox is empty.', ['inbox' => $paths['inbox']]);

                return;
            }

            $needsGhostscript = false;
            foreach ($inboxFiles as $sourcePath) {
                $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $needsGhostscript = true;
                    break;
                }
            }

            $gsPath = (string) config('pdf_import.ghostscript_path', '');
            $gsAvailable = $gsPath !== '' && is_file($gsPath);
            if ($needsGhostscript && !$gsAvailable) {
                Log::warning('PDF import: Ghostscript unavailable; PDF files will be registered as-is.', [
                    'ghostscript_path' => $gsPath,
                ]);
            }

            $associatedID = $this->associatedID
                ?? (int) config('pdf_import.db.default_associated_id', -1);

            foreach ($inboxFiles as $sourcePath) {
                try {
                    $this->processOneFile($sourcePath, $paths, $gsPath, $associatedID, $gsAvailable);
                } catch (Throwable $e) {
                    Log::error('PDF import: file failed.', [
                        'file' => $sourcePath,
                        'error' => $e->getMessage(),
                    ]);
                    $this->quarantineFile($sourcePath, $paths['error'], $e->getMessage());
                }
            }
        } finally {
            try {
                $processLock->release();
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    /**
     * @param  array{inbox:string,converted:string,reference:string,temp:string,error:string}  $paths
     */
    private function processOneFile(
        string $sourcePath,
        array $paths,
        string $gsPath,
        int $associatedID,
        bool $gsAvailable,
    ): void {
        $basename = basename($sourcePath);
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        $workDir = $paths['temp'] . DIRECTORY_SEPARATOR . Str::uuid()->toString();

        if (!mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            throw new \RuntimeException('一時作業フォルダを作成できませんでした: ' . $workDir);
        }

        try {
            if ($ext === 'pdf') {
                $this->processPdfFile($sourcePath, $paths, $gsPath, $associatedID, $gsAvailable, $workDir, $basename);

                return;
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $generatedJpgs = $this->copyImageToWorkDir($sourcePath, $workDir, $ext);
                $this->registerImagePagesAsAttachedFiles($generatedJpgs, $sourcePath, $paths, $associatedID, $basename);

                return;
            }

            throw new \RuntimeException('未対応の拡張子です: ' . $ext);
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    /**
     * Ghostscript で FPDI/TCPDF 互換 PDF に変換し、複数ページなら 1 ページ PDF に分割して登録する。
     *
     * @param  array{inbox:string,converted:string,reference:string,temp:string,error:string}  $paths
     */
    private function processPdfFile(
        string $sourcePath,
        array $paths,
        string $gsPath,
        int $associatedID,
        bool $gsAvailable,
        string $workDir,
        string $basename,
    ): void {
        $splitSource = $sourcePath;

        if ($gsAvailable) {
            try {
                $compatiblePdf = $this->convertPdfToCompatiblePdf($sourcePath, $workDir, $gsPath);
                if (is_file($compatiblePdf)) {
                    $splitSource = $compatiblePdf;
                }
            } catch (Throwable $e) {
                Log::warning('PDF import: Ghostscript compatibility conversion failed; trying FPDI split on original.', [
                    'file' => $basename,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('PDF import: Ghostscript unavailable; trying FPDI split on original.', [
                'file' => $basename,
                'ghostscript_path' => $gsPath,
            ]);
        }

        try {
            $pagePdfs = $this->splitPdfIntoPagePdfs($splitSource, $workDir);
        } catch (Throwable $e) {
            // 互換変換済みで失敗した場合は原本でも再試行
            if ($splitSource !== $sourcePath) {
                Log::warning('PDF import: FPDI split on compatible PDF failed; retrying on original.', [
                    'file' => $basename,
                    'error' => $e->getMessage(),
                ]);
                try {
                    $pagePdfs = $this->splitPdfIntoPagePdfs($sourcePath, $workDir);
                } catch (Throwable $e2) {
                    Log::warning('PDF import: FPDI/TCPDF page split failed; registering original PDF as reference.', [
                        'file' => $basename,
                        'error' => $e2->getMessage(),
                    ]);
                    $this->registerOriginalPdfAsAttachedFile($sourcePath, $paths, $associatedID, $basename);

                    return;
                }
            } else {
                Log::warning('PDF import: FPDI/TCPDF page split failed; registering original PDF as reference.', [
                    'file' => $basename,
                    'error' => $e->getMessage(),
                ]);
                $this->registerOriginalPdfAsAttachedFile($sourcePath, $paths, $associatedID, $basename);

                return;
            }
        }

        if ($pagePdfs === []) {
            Log::warning('PDF import: no page PDFs produced; registering original PDF as reference.', [
                'file' => $basename,
            ]);
            $this->registerOriginalPdfAsAttachedFile($sourcePath, $paths, $associatedID, $basename);

            return;
        }

        $this->registerPdfPagesAsAttachedFiles($pagePdfs, $sourcePath, $paths, $associatedID, $basename);
    }

    /**
     * @param  list<string>  $pagePdfs
     * @param  array{inbox:string,converted:string,reference:string,temp:string,error:string}  $paths
     */
    private function registerPdfPagesAsAttachedFiles(
        array $pagePdfs,
        string $sourcePath,
        array $paths,
        int $associatedID,
        string $basename,
    ): void {
        DB::transaction(function () use ($pagePdfs, $sourcePath, $paths, $associatedID, $basename) {
            $sortNum = $this->nextSortNum($associatedID);
            $step = max(1, (int) config('pdf_import.db.sort_step', 10));
            $documentType = (string) config('pdf_import.db.pdf_document_type', 'PDF');

            foreach ($pagePdfs as $pdfPath) {
                $binary = file_get_contents($pdfPath);
                if ($binary === false || $binary === '') {
                    throw new \RuntimeException('生成 PDF の読み込みに失敗: ' . $pdfPath);
                }

                $uniqueName = Str::uuid()->toString() . '.pdf';
                $referencePath = $paths['reference'] . DIRECTORY_SEPARATOR . $uniqueName;

                // DB 成功後に reference へ移動（失敗時に孤児ファイルを残さない）
                AttachedFile::create([
                    'associatedID' => $associatedID,
                    'content' => base64_encode($binary),
                    'documentType' => $documentType,
                    'documentName' => $uniqueName,
                    'fileType' => 'application/pdf',
                    'sortNum' => $sortNum,
                ]);

                if (!@rename($pdfPath, $referencePath) && !@copy($pdfPath, $referencePath)) {
                    throw new \RuntimeException('参照用フォルダへの移動に失敗: ' . $uniqueName);
                }
                @unlink($pdfPath);

                $sortNum = $this->advanceSortNum($sortNum, $step);
            }

            $this->moveSourceToConverted($sourcePath, $paths['converted'], $basename);
        });

        Log::info('PDF import: file processed as page PDFs.', [
            'source' => $basename,
            'pages' => count($pagePdfs),
            'associatedID' => $associatedID,
        ]);
    }

    /**
     * @param  list<string>  $generatedJpgs
     * @param  array{inbox:string,converted:string,reference:string,temp:string,error:string}  $paths
     */
    private function registerImagePagesAsAttachedFiles(
        array $generatedJpgs,
        string $sourcePath,
        array $paths,
        int $associatedID,
        string $basename,
    ): void {
        if ($generatedJpgs === []) {
            throw new \RuntimeException('変換結果の JPG が 0 件です。');
        }

        DB::transaction(function () use ($generatedJpgs, $sourcePath, $paths, $associatedID, $basename) {
            $sortNum = $this->nextSortNum($associatedID);
            $step = max(1, (int) config('pdf_import.db.sort_step', 10));
            $documentType = (string) config('pdf_import.db.document_type', '画像');

            foreach ($generatedJpgs as $jpgPath) {
                $binary = file_get_contents($jpgPath);
                if ($binary === false || $binary === '') {
                    throw new \RuntimeException('生成 JPG の読み込みに失敗: ' . $jpgPath);
                }

                $uniqueName = Str::uuid()->toString() . '.jpg';
                $referencePath = $paths['reference'] . DIRECTORY_SEPARATOR . $uniqueName;

                AttachedFile::create([
                    'associatedID' => $associatedID,
                    'content' => base64_encode($binary),
                    'documentType' => $documentType,
                    'documentName' => $uniqueName,
                    'fileType' => 'image/jpeg',
                    'sortNum' => $sortNum,
                ]);

                if (!@rename($jpgPath, $referencePath) && !@copy($jpgPath, $referencePath)) {
                    throw new \RuntimeException('参照用フォルダへの移動に失敗: ' . $uniqueName);
                }
                @unlink($jpgPath);

                $sortNum = $this->advanceSortNum($sortNum, $step);
            }

            $this->moveSourceToConverted($sourcePath, $paths['converted'], $basename);
        });

        Log::info('PDF import: image file processed.', [
            'source' => $basename,
            'pages' => count($generatedJpgs),
            'associatedID' => $associatedID,
        ]);
    }

    /**
     * Ghostscript 変換できない PDF をそのまま reference / AttachedFile に登録する。
     *
     * @param  array{inbox:string,converted:string,reference:string,temp:string,error:string}  $paths
     */
    private function registerOriginalPdfAsAttachedFile(
        string $sourcePath,
        array $paths,
        int $associatedID,
        string $basename,
    ): void {
        DB::transaction(function () use ($sourcePath, $paths, $associatedID, $basename) {
            $binary = file_get_contents($sourcePath);
            if ($binary === false || $binary === '') {
                throw new \RuntimeException('PDF の読み込みに失敗: ' . $basename);
            }

            $uniqueName = Str::uuid()->toString() . '.pdf';
            $referencePath = $paths['reference'] . DIRECTORY_SEPARATOR . $uniqueName;

            if (!@copy($sourcePath, $referencePath)) {
                throw new \RuntimeException('参照用フォルダへのコピーに失敗: ' . $uniqueName);
            }

            AttachedFile::create([
                'associatedID' => $associatedID,
                'content' => base64_encode($binary),
                'documentType' => (string) config('pdf_import.db.pdf_document_type', 'PDF'),
                'documentName' => $uniqueName,
                'fileType' => 'application/pdf',
                'sortNum' => $this->nextSortNum($associatedID),
            ]);

            $this->moveSourceToConverted($sourcePath, $paths['converted'], $basename);
        });

        Log::info('PDF import: file processed as original PDF.', [
            'source' => $basename,
            'associatedID' => $associatedID,
        ]);
    }

    private function moveSourceToConverted(string $sourcePath, string $convertedDir, string $basename): void
    {
        $convertedName = now('Asia/Tokyo')->format('Ymd-His') . '_' . $basename;
        $convertedPath = $convertedDir . DIRECTORY_SEPARATOR . $convertedName;
        if (!@rename($sourcePath, $convertedPath)) {
            if (!@copy($sourcePath, $convertedPath) || !@unlink($sourcePath)) {
                throw new \RuntimeException('変換済みフォルダへの移動に失敗: ' . $basename);
            }
        }
    }

    /**
     * Ghostscript pdfwrite で FPDI が読める互換 PDF に変換する。
     *
     * @return string 変換後 PDF の絶対パス
     */
    private function convertPdfToCompatiblePdf(string $pdfPath, string $workDir, string $gsPath): string
    {
        $compatibility = (string) config('pdf_import.ghostscript.compatibility_level', '1.4');
        $pdfSettings = (string) config('pdf_import.ghostscript.pdf_settings', '/prepress');
        // Windows の引数解釈で /prepress がパス扱いされるのを避けるため正規化
        $pdfSettings = '/' . ltrim($pdfSettings, '/');
        $timeout = max(30, (int) config('pdf_import.ghostscript.timeout_seconds', 300));

        $outputPath = $workDir . DIRECTORY_SEPARATOR . 'compatible.pdf';

        // -o を独立引数にすると -sOutputFile= より Windows で安定する
        $command = [
            $gsPath,
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=' . $compatibility,
            '-dPDFSETTINGS=' . $pdfSettings,
            '-o',
            $outputPath,
            $pdfPath,
        ];

        $result = Process::timeout($timeout)->run($command);

        if (!$result->successful()) {
            $detail = trim($result->errorOutput() ?: $result->output());
            throw new \RuntimeException(
                'Ghostscript 互換変換に失敗しました'
                . ' (exit=' . var_export($result->exitCode(), true) . ')'
                . ($detail !== '' ? ': ' . $detail : '')
            );
        }

        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Ghostscript 互換変換の出力 PDF が空です。');
        }

        return $outputPath;
    }

    /**
     * FPDI + TCPDF で複数ページ PDF を 1 ページずつの PDF に分割する。
     * 単一ページの場合も 1 ファイルとして出力する。
     *
     * @return list<string> 生成されたページ PDF の絶対パス
     */
    private function splitPdfIntoPagePdfs(string $pdfPath, string $workDir): array
    {
        $probe = new Fpdi();
        $probe->setPrintHeader(false);
        $probe->setPrintFooter(false);
        $pageCount = $probe->setSourceFile($pdfPath);

        if ($pageCount < 1) {
            throw new \RuntimeException('PDF のページ数が 0 です。');
        }

        // 単一ページ: 互換 PDF をそのまま page-001 として使う（再エンコード不要）
        if ($pageCount === 1) {
            $dest = $workDir . DIRECTORY_SEPARATOR . 'page-001.pdf';
            if (!@copy($pdfPath, $dest)) {
                throw new \RuntimeException('単一ページ PDF の一時コピーに失敗しました。');
            }

            return [$dest];
        }

        $files = [];
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->setSourceFile($pdfPath);

            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $dest = $workDir . DIRECTORY_SEPARATOR . sprintf('page-%03d.pdf', $pageNo);
            $pdf->Output($dest, 'F');

            if (!is_file($dest) || filesize($dest) === 0) {
                throw new \RuntimeException('ページ PDF の出力に失敗: page ' . $pageNo);
            }

            $files[] = $dest;
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function copyImageToWorkDir(string $sourcePath, string $workDir, string $ext): array
    {
        $dest = $workDir . DIRECTORY_SEPARATOR . 'page-001.jpg';

        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            if (!@copy($sourcePath, $dest)) {
                throw new \RuntimeException('JPG の一時コピーに失敗しました。');
            }

            return [$dest];
        }

        // PNG → JPEG（GD があれば変換、無ければコピーして拡張子のみ jpg）
        if (extension_loaded('gd') && function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
            $img = @imagecreatefrompng($sourcePath);
            if ($img === false) {
                throw new \RuntimeException('PNG の読み込みに失敗しました。');
            }
            $canvas = imagecreatetruecolor(imagesx($img), imagesy($img));
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopy($canvas, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            $ok = imagejpeg($canvas, $dest, (int) config('pdf_import.ghostscript.jpeg_quality', 85));
            imagedestroy($img);
            imagedestroy($canvas);
            if (!$ok) {
                throw new \RuntimeException('PNG→JPEG 変換に失敗しました。');
            }

            return [$dest];
        }

        if (!@copy($sourcePath, $dest)) {
            throw new \RuntimeException('PNG の一時コピーに失敗しました。');
        }

        return [$dest];
    }

    /**
     * attachedfiles.sortNum は tinyint（signed: -128..127）。
     * 上限を超えないよう次値を返す。
     */
    private function nextSortNum(int $associatedID): int
    {
        $step = max(1, (int) config('pdf_import.db.sort_step', 10));
        $maxAllowed = 127;

        $max = AttachedFile::query()
            ->where('associatedID', $associatedID)
            ->max('sortNum');

        $next = $max === null ? $step : ((int) $max + $step);

        if ($next > $maxAllowed) {
            $next = $step;
        }

        return max(1, min($maxAllowed, $next));
    }

    /**
     * 連続ページ登録用。tinyint 上限を超えたら step から巻き戻す。
     */
    private function advanceSortNum(int $current, int $step): int
    {
        $next = $current + max(1, $step);

        return $next > 127 ? max(1, $step) : $next;
    }

    /**
     * @return list<string>
     */
    private function listInboxFiles(string $inbox): array
    {
        $files = [];
        foreach (scandir($inbox) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name === '.gitkeep') {
                continue;
            }
            if (str_starts_with($name, '.')) {
                continue;
            }
            $full = $inbox . DIRECTORY_SEPARATOR . $name;
            if (is_file($full)) {
                $files[] = $full;
            }
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }

    private function quarantineFile(string $sourcePath, string $errorDir, string $reason): void
    {
        if (!is_file($sourcePath)) {
            return;
        }

        $name = now('Asia/Tokyo')->format('Ymd-His') . '_' . basename($sourcePath);
        $dest = $errorDir . DIRECTORY_SEPARATOR . $name;
        if (!@rename($sourcePath, $dest)) {
            @copy($sourcePath, $dest);
            @unlink($sourcePath);
        }

        $meta = $dest . '.error.txt';
        @file_put_contents($meta, $reason . PHP_EOL);
    }

    /**
     * @return array{inbox:string,converted:string,reference:string,temp:string,error:string}
     */
    private function resolvedPaths(): array
    {
        $keys = ['inbox', 'converted', 'reference', 'temp', 'error'];
        $paths = [];

        foreach ($keys as $key) {
            $path = (string) config('pdf_import.paths.' . $key, '');
            $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
            if ($path === '') {
                throw new \RuntimeException("pdf_import.paths.{$key} が空です。.env を確認してください。");
            }
            $paths[$key] = $path;
        }

        return $paths;
    }

    /**
     * @param  array<string,string>  $paths
     */
    private function ensureDirectories(array $paths): void
    {
        foreach ($paths as $key => $path) {
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException("ディレクトリを作成できませんでした [{$key}]: {$path}");
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
