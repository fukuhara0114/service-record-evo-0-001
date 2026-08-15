<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gmail IMAP（受信監視）
    |--------------------------------------------------------------------------
    |
    | 未設定時は MAIL_USERNAME / MAIL_PASSWORD（SMTP と同じアプリパスワード）を流用します。
    |
    */

    'imap' => [
        'host' => env('GMAIL_IMAP_HOST', 'imap.gmail.com'),
        'port' => (int) env('GMAIL_IMAP_PORT', 993),
        'encryption' => env('GMAIL_IMAP_ENCRYPTION', 'ssl'),
        'username' => env('GMAIL_IMAP_USERNAME', env('MAIL_USERNAME')),
        'password' => env('GMAIL_IMAP_PASSWORD', env('MAIL_PASSWORD')),
        'mailbox' => env('GMAIL_IMAP_MAILBOX', 'INBOX'),
        'timeout' => (int) env('GMAIL_IMAP_TIMEOUT', 30),
        // 初回（カーソル未作成時）に遡って取得する日数。0 で全件対象（UID 差分のみ）
        'initial_lookback_days' => (int) env('GMAIL_IMAP_INITIAL_LOOKBACK_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outlook deeplink 判定
    |--------------------------------------------------------------------------
    |
    | URL ホストにこれらの文字列が含まれるものを Outlook deeplink とみなします。
    | 実メールの形式に合わせて .env のカンマ区切りで上書き可能です。
    |
    */

    'outlook_deeplink_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'GMAIL_OUTLOOK_DEEPLINK_HOSTS',
            'outlook.office.com,outlook.office365.com,outlook.live.com,outlook.office365.us'
        ))
    ))),

];
