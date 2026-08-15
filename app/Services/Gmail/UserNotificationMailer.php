<?php

namespace App\Services\Gmail;

use App\Mail\UserNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

class UserNotificationMailer
{
    /**
     * ログインユーザー名（users.name）を指定して通知メールを送信する。
     *
     * @return array{user: User, email: string}
     */
    public function sendByUserName(string $userName, string $subject, string $body): array
    {
        $userName = trim($userName);
        $subject = trim($subject);
        $body = trim($body);

        if ($userName === '') {
            throw new InvalidArgumentException('ユーザー名が空です。');
        }
        if ($subject === '') {
            throw new InvalidArgumentException('件名が空です。');
        }
        if ($body === '') {
            throw new InvalidArgumentException('本文が空です。');
        }

        /** @var User|null $user */
        $user = User::query()->where('name', $userName)->first();
        if ($user === null) {
            throw new InvalidArgumentException("ユーザー「{$userName}」が見つかりません。");
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("ユーザー「{$userName}」に有効な email が登録されていません。");
        }

        $this->assertSmtpConfigured();

        $displayName = trim((string) ($user->kanji_name ?: $user->name));

        Mail::to($email)->send(new UserNotificationMail(
            subjectLine: $subject,
            bodyText: $body,
            recipientName: (string) $user->name,
            recipientKanjiName: $user->kanji_name ? (string) $user->kanji_name : null,
        ));

        return [
            'user' => $user,
            'email' => $email,
            'display_name' => $displayName,
        ];
    }

    private function assertSmtpConfigured(): void
    {
        if ((string) config('mail.default') !== 'smtp') {
            throw new RuntimeException('MAIL_MAILER が smtp ではありません（現在: '.config('mail.default').'）。');
        }

        $username = (string) config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');
        if ($username === '' || $password === null || $password === '') {
            throw new RuntimeException('MAIL_USERNAME / MAIL_PASSWORD が未設定です。');
        }
    }
}
