<?php

namespace App\Services\Gmail;

use App\Mail\UserNotificationMail;
use App\Models\ServiceRecord;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RemandNotificationMailer
{
    /**
     * receive_info が有効なユーザーへ差戻通知メールを送信する。
     *
     * @return array{sent: list<string>, skipped: list<string>, subject: string}
     */
    public function notify(ServiceRecord $record): array
    {
        $orderId = (string) $record->orderID;
        $dealer = trim((string) ($record->dealer ?? ''));
        $productName = trim((string) ($record->productName ?? ''));
        $sn = trim((string) ($record->SN ?? ''));
        $link = $this->detailLink($record);

        $subject = "【差戻】 orderID: {$orderId},   {$dealer},  {$productName}, {$sn}";
        $body = "orderID: {$orderId}\n"
            ."dealer ： {$dealer}\n"
            ."製品名 ： {$productName}\n"
            ."SN ： {$sn}\n"
            ."が差し戻されました\n"
            ."link :  [{$link}]";

        $users = User::query()
            ->where(function ($query) {
                $query->where('receive_info', 1)
                    ->orWhere('receive_info', '1')
                    ->orWhere('receive_info', true);
            })
            ->get(['userID', 'name', 'kanji_name', 'email', 'receive_info']);

        $sent = [];
        $skipped = [];

        if ($users->isEmpty()) {
            return [
                'sent' => $sent,
                'skipped' => $skipped,
                'subject' => $subject,
            ];
        }

        try {
            $this->assertSmtpConfigured();
        } catch (Throwable $e) {
            Log::warning('差戻通知メールをスキップしました（SMTP未設定）', [
                'orderID' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => $sent,
                'skipped' => $users->pluck('email')->filter()->values()->all(),
                'subject' => $subject,
                'error' => $e->getMessage(),
            ];
        }

        foreach ($users as $user) {
            $email = trim((string) ($user->email ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = (string) ($user->name ?: $user->userID);
                continue;
            }

            try {
                Mail::to($email)->send(new UserNotificationMail(
                    subjectLine: $subject,
                    bodyText: $body,
                    recipientName: (string) ($user->name ?: $email),
                    recipientKanjiName: $user->kanji_name ? (string) $user->kanji_name : null,
                ));
                $sent[] = $email;
            } catch (Throwable $e) {
                Log::error('差戻通知メールの送信に失敗しました', [
                    'orderID' => $orderId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                $skipped[] = $email;
            }
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'subject' => $subject,
        ];
    }

    public function detailLink(ServiceRecord $record): string
    {
        // メールクライアントが & を &amp; に化けさせるため、クエリ無しのパス形式にする
        return url('/servicerecord/open/'.$record->orderID);
    }

    private function assertSmtpConfigured(): void
    {
        if ((string) config('mail.default') !== 'smtp') {
            throw new \RuntimeException('MAIL_MAILER が smtp ではありません（現在: '.config('mail.default').'）。');
        }

        $username = (string) config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');
        if ($username === '' || $password === null || $password === '') {
            throw new \RuntimeException('MAIL_USERNAME / MAIL_PASSWORD が未設定です。');
        }
    }
}
