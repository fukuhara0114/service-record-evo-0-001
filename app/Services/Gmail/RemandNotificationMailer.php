<?php

namespace App\Services\Gmail;

use App\Mail\UserNotificationMail;
use App\Models\AttachedNote;
use App\Models\ServiceRecord;
use App\Models\Status;
use App\Models\StatusLoaner;
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
    public function notify(ServiceRecord $record, mixed $previousStatusId = null, ?string $reason = null): array
    {
        $orderId = (string) $record->orderID;
        $dealer = trim((string) ($record->dealer ?? ''));
        $productName = trim((string) ($record->productName ?? ''));
        $sn = trim((string) ($record->SN ?? ''));
        $link = $this->detailLink($record);
        $statusLabel = $this->formatPreviousStatusLabel($record, $previousStatusId);
        $reasonText = $this->resolveReasonText($record, $reason);

        $subject = "【差戻】 orderID: {$orderId},   {$dealer},  {$productName}, {$sn}";
        $body = "orderID: {$orderId}\n"
            ."dealer ： {$dealer}\n"
            ."製品名 ： {$productName}\n"
            ."SN ： {$sn}\n"
            ."が「{$statusLabel}」から差し戻されました\n"
            ."\n"
            ."理由：\n"
            ."{$reasonText}\n"
            ."\n"
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
                $mailer = Mail::to($email);
                $bcc = $this->remandNotifyBcc();
                if ($bcc !== null) {
                    $mailer->bcc($bcc);
                }
                $mailer->send(new UserNotificationMail(
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

    private function formatPreviousStatusLabel(ServiceRecord $record, mixed $previousStatusId): string
    {
        $statusId = $previousStatusId;
        if ($statusId === null || $statusId === '') {
            $statusId = $record->status;
        }
        if ($statusId === null || $statusId === '') {
            return '不明';
        }

        $id = (int) $statusId;
        $name = '';
        if (in_array($record->order_type, ['loaner'], true)) {
            $name = (string) (StatusLoaner::query()->where('processID_new', $id)->value('status') ?? '');
        } else {
            $name = (string) (Status::query()->where('processID_new', $id)->value('status') ?? '');
        }

        $name = trim($name);

        return $name !== '' ? "{$name} ({$id})" : (string) $id;
    }

    private function resolveReasonText(ServiceRecord $record, ?string $reason): string
    {
        $raw = trim((string) ($reason ?? ''));
        if ($raw === '') {
            $raw = (string) (AttachedNote::query()
                ->where('associatedID', $record->orderID)
                ->where('note', 'like', '[差戻理由]%')
                ->orderByDesc('id')
                ->value('note') ?? '');
        }

        $stripped = preg_replace('/^\[差戻理由\][　\s]*/u', '', trim($raw)) ?? '';

        return trim($stripped) !== '' ? trim($stripped) : '（理由未入力）';
    }

    private function remandNotifyBcc(): ?string
    {
        $bcc = trim((string) config('mail.remand_notify_bcc', ''));
        if ($bcc === '' || ! filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $bcc;
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
