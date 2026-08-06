<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ghostscript 実行ファイル
    |--------------------------------------------------------------------------
    |
    | Windows 例: C:\Program Files\gs\gs10.04.0\bin\gswin64c.exe
    |
    */
    'ghostscript_path' => env('PDF_IMPORT_GHOSTSCRIPT_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | フォルダパス（いずれも絶対パス推奨）
    |--------------------------------------------------------------------------
    */
    'paths' => [
        // 未処理ファイルの置き場（ジョブが走査する入力）
        'inbox' => env('PDF_IMPORT_INBOX_PATH', storage_path('app/pdf_import/inbox')),

        // 変換成功後のオリジナル退避先
        'converted' => env('PDF_IMPORT_CONVERTED_PATH', storage_path('app/pdf_import/converted')),

        // ページ PDF / 画像の参照用置き場（UUID ファイル名）
        'reference' => env('PDF_IMPORT_REFERENCE_PATH', storage_path('app/pdf_import/reference')),

        // Ghostscript / FPDI / 画像コピーの作業用一時フォルダ
        'temp' => env('PDF_IMPORT_TEMP_PATH', storage_path('app/pdf_import/temp')),

        // 失敗ファイルの隔離先
        'error' => env('PDF_IMPORT_ERROR_PATH', storage_path('app/pdf_import/error')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ghostscript パラメータ
    |--------------------------------------------------------------------------
    |
    | PDF 取込は pdfwrite による互換変換が主経路。
    | JPG ラスタ化用キー（dpi / paper_size 等）は画像パスや将来用に残す。
    |
    */
    'ghostscript' => [
        // PDF 互換レベル（FPDI 向けに 1.4 推奨）
        'compatibility_level' => env('PDF_IMPORT_COMPATIBILITY_LEVEL', '1.4'),

        // -dPDFSETTINGS=（/prepress, /printer, /default, /ebook, /screen）
        'pdf_settings' => env('PDF_IMPORT_PDF_SETTINGS', '/prepress'),

        // 解像度 DPI（旧 JPG ラスタ化用。現行 PDF 経路では未使用）
        'dpi' => (int) env('PDF_IMPORT_DPI', 150),

        // 用紙サイズ（旧 JPG ラスタ化用）
        'paper_size' => env('PDF_IMPORT_PAPER_SIZE', 'a4'),

        // 縦横比を保って用紙にフィット（旧 JPG ラスタ化用）
        'fit_page' => filter_var(env('PDF_IMPORT_FIT_PAGE', true), FILTER_VALIDATE_BOOLEAN),

        // 出力デバイス（旧 JPG ラスタ化用）
        'device' => env('PDF_IMPORT_GS_DEVICE', 'jpeg'),

        // JPEG 品質（PNG→JPEG 変換向け）
        'jpeg_quality' => (int) env('PDF_IMPORT_JPEG_QUALITY', 85),

        // プロセスタイムアウト秒
        'timeout_seconds' => (int) env('PDF_IMPORT_GS_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | DB 登録
    |--------------------------------------------------------------------------
    */
    'db' => [
        // AttachedFile.associatedID（未紐づけは -1）
        'default_associated_id' => (int) env('PDF_IMPORT_DEFAULT_ASSOCIATED_ID', -1),

        // sortNum の刻み
        'sort_step' => (int) env('PDF_IMPORT_SORT_STEP', 10),

        // documentType（画像取込）
        'document_type' => env('PDF_IMPORT_DOCUMENT_TYPE', '画像'),

        // documentType（PDF ページ取込）
        'pdf_document_type' => env('PDF_IMPORT_PDF_DOCUMENT_TYPE', 'PDF'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ロック
    |--------------------------------------------------------------------------
    */
    'lock' => [
        'key' => env('PDF_IMPORT_LOCK_KEY', 'file_import_lock'),
        'seconds' => (int) env('PDF_IMPORT_LOCK_SECONDS', 60),
    ],

];
