<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF テンプレート共通ディレクトリ
    |--------------------------------------------------------------------------
    |
    | 絶対パス、またはアプリルートからの相対パスを指定できます。
    | 未設定時は下記の候補パスを順に探索します。
    |
    */
    'dir' => env('PDF_TEMPLATES_DIR'),

    /*
    |--------------------------------------------------------------------------
    | 個別テンプレートの上書き（任意）
    |--------------------------------------------------------------------------
    */
    'files' => [
        'loaner_application' => env('PDF_TEMPLATE_LOANER_APPLICATION'),
        'maintenance_contract' => env('PDF_TEMPLATE_MAINTENANCE_CONTRACT'),
        'certification_ticket' => env('PDF_TEMPLATE_CERTIFICATION_TICKET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 論理名 → ファイル名候補
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'loaner_application' => [
            'template_loaner.pdf',
            'template_pdf.pdf',
            'loaner_application.pdf',
        ],
        'maintenance_contract' => [
            'maintenance_contract.pdf',
        ],
        'certification_ticket' => [
            'certification_ticket.pdf',
        ],
    ],

];
