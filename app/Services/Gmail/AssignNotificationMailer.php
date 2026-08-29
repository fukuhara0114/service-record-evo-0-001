<?php

namespace App\Services\Gmail;

use App\Mail\UserNotificationMail;
use App\Models\ReturnCode;
use App\Models\ServiceRecord;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssignNotificationMailer
{
    /**
     * 指定 laborID の通知対象ユーザー一覧。
     *
     * @return list<array{userID:int|string,name:string,kanji_name:?string,email:string}>
     */
    public function targetsForLabor(mixed $laborId): array
    {
        if ($laborId === null || $laborId === '') {
            return [];
        }

        $users = User::query()
            ->where('laborID', $laborId)
            ->get(['userID', 'name', 'kanji_name', 'email', 'laborID']);

        $targets = [];
        foreach ($users as $user) {
            $email = trim((string) ($user->email ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $targets[] = [
                'userID' => $user->userID,
                'name' => (string) ($user->name ?? ''),
                'kanji_name' => $user->kanji_name ? (string) $user->kanji_name : null,
                'email' => $email,
            ];
        }

        return $targets;
    }

    /**
     * laborID が一致するユーザーへアサイン通知メールを送信する。
     *
     * @return array{sent: list<string>, skipped: list<string>, subject: string}
     */
    public function notify(ServiceRecord $record): array
    {
        $orderId = (string) $record->orderID;
        $dealer = trim((string) ($record->dealer ?? ''));
        $productName = trim((string) ($record->productName ?? ''));
        $sn = trim((string) ($record->SN ?? ''));
        $workDescription = $this->returnCodeDescription($record->returnCode);

        $subject = "【新規案件assign】  orderID: {$orderId}, {$dealer}, {$productName}, {$sn}";
        // DetailFormA「受注」アサイン通知: Engineer 一覧で詳細を開く
        $link = url('/servicerecord/engineer').'?'.http_build_query([
            'orderType' => 'service',
            'arrival' => 'all',
            'openOrderID' => $orderId,
        ]);
        $body = "orderID: {$orderId}\n"
            ."dealer ： {$dealer}\n"
            ."製品名 ： {$productName}\n"
            ."SN ： {$sn}\n"
            ."作業内容：{$workDescription}\n"
            ."\n"
            ."がアサインされました。\n"
            ."作業内容を御確認下さい。\n"
            ."\n"
            ."link :  [{$link}]";

        return $this->sendToLaborTargets($record, $subject, $body);
    }

    /**
     * 貸出機・受け入れ確認中(396) の機材チェック通知。
     *
     * @return array{sent: list<string>, skipped: list<string>, subject: string}
     */
    public function notifyLoanerEquipmentCheck(ServiceRecord $record, int $loanerDetailId): array
    {
        $orderId = (string) $record->orderID;
        $productName = trim((string) ($record->productName ?? ''));
        $sn = trim((string) ($record->SN ?? ''));
        $managementNumber = trim((string) ($record->managementNumber ?? ''));

        $subject = "【貸出機、機材チェック】  orderID: {$orderId},  {$productName}, {$sn}";
        // Engineer 一覧から開くときと同じ: /loaner/detail/{orderID}?from=engineer&returnUrl=...
        $detailKey = $orderId !== '' ? $orderId : (string) $loanerDetailId;
        $returnUrl = url('/servicerecord/engineer').'?orderType=loaner';
        $link = url('/servicerecord/loaner/detail/'.$detailKey).'?'.http_build_query([
            'from' => 'engineer',
            'returnUrl' => $returnUrl,
        ]);
        $body = "orderID: {$orderId}\n"
            ."機材名 ： {$productName}\n"
            ."SN ： {$sn}\n"
            ."管理番号 ： {$managementNumber}\n"
            ."作業内容：貸出機、返却後の機材チェック\n"
            ."\n"
            ."がアサインされました。\n"
            ."作業内容を御確認下さい。\n"
            ."\n"
            ."link :  [{$link}]";

        return $this->sendToLaborTargets($record, $subject, $body);
    }

    /**
     * @return array{sent: list<string>, skipped: list<string>, subject: string, error?: string}
     */
    private function sendToLaborTargets(ServiceRecord $record, string $subject, string $body): array
    {
        $orderId = (string) $record->orderID;
        $targets = $this->targetsForLabor($record->laborID);
        $sent = [];
        $skipped = [];

        if ($targets === []) {
            return [
                'sent' => $sent,
                'skipped' => $skipped,
                'subject' => $subject,
            ];
        }

        try {
            $this->assertSmtpConfigured();
        } catch (Throwable $e) {
            Log::warning('アサイン通知メールをスキップしました（SMTP未設定）', [
                'orderID' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => $sent,
                'skipped' => array_column($targets, 'email'),
                'subject' => $subject,
                'error' => $e->getMessage(),
            ];
        }

        foreach ($targets as $target) {
            try {
                Mail::to($target['email'])->send(new UserNotificationMail(
                    subjectLine: $subject,
                    bodyText: $body,
                    recipientName: $target['name'] !== '' ? $target['name'] : $target['email'],
                    recipientKanjiName: $target['kanji_name'],
                ));
                $sent[] = $target['email'];
            } catch (Throwable $e) {
                Log::error('アサイン通知メールの送信に失敗しました', [
                    'orderID' => $orderId,
                    'email' => $target['email'],
                    'error' => $e->getMessage(),
                ]);
                $skipped[] = $target['email'];
            }
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'subject' => $subject,
        ];
    }

    private function returnCodeDescription(mixed $returnCodeId): string
    {
        if ($returnCodeId === null || $returnCodeId === '') {
            return '';
        }
        $description = ReturnCode::query()->where('id', $returnCodeId)->value('description');

        return trim((string) ($description ?? ''));
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
