<?php

namespace App\Console\Commands;

use App\Services\Gmail\UserNotificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendUserNotificationMailCommand extends Command
{
    protected $signature = 'mail:notify
                            {name : ログインユーザー名（users.name）}
                            {--subject= : 件名}
                            {--body= : 本文}
                            {--body-file= : 本文をファイルから読み込む}';

    protected $description = 'ユーザー名を指定して Gmail から通知メールを送信する';

    public function handle(UserNotificationMailer $mailer): int
    {
        $name = (string) $this->argument('name');
        $subject = (string) ($this->option('subject') ?: '');
        $body = (string) ($this->option('body') ?: '');
        $bodyFile = $this->option('body-file');

        if ($subject === '') {
            $subject = (string) $this->ask('件名', '[Service Record] 通知');
        }

        if (is_string($bodyFile) && $bodyFile !== '') {
            if (! is_file($bodyFile)) {
                $this->error("本文ファイルが見つかりません: {$bodyFile}");

                return self::FAILURE;
            }
            $body = (string) file_get_contents($bodyFile);
        }

        if (trim($body) === '') {
            $body = (string) $this->ask('本文');
        }

        try {
            $result = $mailer->sendByUserName($name, $subject, $body);
        } catch (Throwable $e) {
            $this->error('送信に失敗しました: '.$e->getMessage());
            Log::error('mail.notify.failed', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return self::FAILURE;
        }

        $this->info("送信しました: {$result['display_name']} <{$result['email']}>");
        Log::info('mail.notify.sent', [
            'name' => $name,
            'email' => $result['email'],
            'subject' => $subject,
        ]);

        return self::SUCCESS;
    }
}
