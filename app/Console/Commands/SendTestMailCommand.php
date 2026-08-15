<?php

namespace App\Console\Commands;

use App\Mail\SystemTestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTestMailCommand extends Command
{
    protected $signature = 'mail:test
                            {to? : 宛先メールアドレス（省略時は MAIL_USERNAME）}
                            {--subject= : 件名を上書き}';

    protected $description = 'Gmail SMTP 通電確認用のテストメールを送信する';

    public function handle(): int
    {
        $to = $this->argument('to')
            ?: (string) config('mail.mailers.smtp.username')
            ?: (string) config('mail.from.address');

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('宛先メールアドレスを指定してください。例: php artisan mail:test you@example.com');
            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $port = (string) config('mail.mailers.smtp.port');
        $username = (string) config('mail.mailers.smtp.username');
        $from = (string) config('mail.from.address');

        $this->info('Mailer : '.$mailer);
        $this->info('SMTP   : '.$host.':'.$port);
        $this->info('User   : '.$username);
        $this->info('From   : '.$from);
        $this->info('To     : '.$to);

        if ($mailer !== 'smtp') {
            $this->warn('MAIL_MAILER が smtp ではありません（現在: '.$mailer.'）。.env を確認してください。');
        }

        if ($username === '' || config('mail.mailers.smtp.password') === null || config('mail.mailers.smtp.password') === '') {
            $this->error('MAIL_USERNAME / MAIL_PASSWORD が未設定です。Gmail アプリパスワードを .env に設定してください。');
            return self::FAILURE;
        }

        try {
            $mailable = new SystemTestMail(now()->format('Y-m-d H:i:s'));
            $customSubject = $this->option('subject');
            if (is_string($customSubject) && $customSubject !== '') {
                $mailable->subject($customSubject);
            }

            Mail::to($to)->send($mailable);
        } catch (Throwable $e) {
            $this->error('送信に失敗しました: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info('テストメールを送信しました。受信箱を確認してください。');

        return self::SUCCESS;
    }
}
