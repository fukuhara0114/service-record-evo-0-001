<?php

$configured = (string) env('CAPTURED_IMAGE_ROOT', 'uploadedImage');
$isAbsolute = str_starts_with($configured, '/')
    || str_starts_with($configured, '\\')
    || (strlen($configured) > 2 && ctype_alpha($configured[0]) && $configured[1] === ':');

// 相対パスは public ではなく storage/app 配下（アプリ経由配信前提）
$root = $isAbsolute
    ? $configured
    : storage_path('app' . DIRECTORY_SEPARATOR . $configured);
$root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);

$maxEdge = (int) env('CAPTURED_IMAGE_MAX_EDGE', 1024);
if ($maxEdge < 1) {
    $maxEdge = 1024;
}

$jpegQuality = (int) env('CAPTURED_IMAGE_JPEG_QUALITY', 90);
if ($jpegQuality < 1) {
    $jpegQuality = 1;
}
if ($jpegQuality > 100) {
    $jpegQuality = 100;
}

$thumbnailMaxEdge = (int) env('CAPTURED_IMAGE_THUMBNAIL_MAX_EDGE', 320);
if ($thumbnailMaxEdge < 1) {
    $thumbnailMaxEdge = 320;
}

return [

    /*
    |--------------------------------------------------------------------------
    | 撮影画像の保存ルート
    |--------------------------------------------------------------------------
    |
    | .env の CAPTURED_IMAGE_ROOT で指定する。
    | - 絶対パス: そのまま使用
    | - 相対パス: storage/app/{相対パス}
    | このディレクトリ直下に /image と /thumbnail を作成して保存する。
    | 公開フォルダには置かず、認証付きルート経由でのみ配信する。
    |
    */
    'root' => $root,

    /*
    |--------------------------------------------------------------------------
    | 撮影画像の最大辺ピクセル数
    |--------------------------------------------------------------------------
    */
    'max_edge' => $maxEdge,

    /*
    |--------------------------------------------------------------------------
    | 撮影画像の JPEG 品質（1-100）
    |--------------------------------------------------------------------------
    */
    'jpeg_quality' => $jpegQuality,

    /*
    |--------------------------------------------------------------------------
    | サムネイルの最大辺ピクセル数
    |--------------------------------------------------------------------------
    */
    'thumbnail_max_edge' => $thumbnailMaxEdge,

];
