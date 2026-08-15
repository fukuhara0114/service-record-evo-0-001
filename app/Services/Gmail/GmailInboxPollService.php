<?php

namespace App\Services\Gmail;

use App\Models\GmailImapCursor;
use App\Models\GmailInboxMessage;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class GmailInboxPollService
{
    public function __construct(
        private readonly OutlookDeeplinkExtractor $deeplinkExtractor,
    ) {}

    /**
     * @return array{
     *     scanned: int,
     *     created: int,
     *     skipped: int,
     *     with_deeplink: int,
     *     last_uid: int,
     *     mailbox: string
     * }
     */
    public function poll(bool $dryRun = false): array
    {
        $config = config('gmail.imap');
        $mailboxName = (string) ($config['mailbox'] ?? 'INBOX');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new \RuntimeException('Gmail IMAP のユーザー／パスワードが未設定です（GMAIL_IMAP_* または MAIL_USERNAME / MAIL_PASSWORD）。');
        }

        $cursor = GmailImapCursor::query()->find($mailboxName);
        $lastUid = (int) ($cursor?->last_uid ?? 0);
        $isFirstPoll = $cursor === null;

        $mailbox = new Mailbox([
            'host' => (string) ($config['host'] ?? 'imap.gmail.com'),
            'port' => (int) ($config['port'] ?? 993),
            'encryption' => (string) ($config['encryption'] ?? 'ssl'),
            'username' => $username,
            'password' => $password,
            'timeout' => (int) ($config['timeout'] ?? 30),
            'validate_cert' => true,
        ]);

        $inbox = $mailbox->inbox();
        $query = $inbox->messages()
            ->withHeaders()
            ->withBody();

        if ($lastUid > 0) {
            // RawQueryValue で UID 範囲を送る（where('UID','4:*') だと "4:*" になり Gmail が BAD を返す）
            $query->uid($lastUid + 1, INF);
        } elseif ($isFirstPoll) {
            $lookbackDays = (int) ($config['initial_lookback_days'] ?? 7);
            if ($lookbackDays > 0) {
                $query->since(Carbon::now()->subDays($lookbackDays));
            }
        }

        $hostNeedles = config('gmail.outlook_deeplink_hosts', []);
        $scanned = 0;
        $created = 0;
        $skipped = 0;
        $withDeeplink = 0;
        $maxUid = $lastUid;

        /** @var Message $message */
        foreach ($query->get() as $message) {
            $scanned++;
            $uid = (int) $message->uid();
            if ($uid > $maxUid) {
                $maxUid = $uid;
            }

            if ($uid > 0 && $uid <= $lastUid) {
                $skipped++;
                continue;
            }

            $result = $this->persistMessage($message, $mailboxName, $hostNeedles, $dryRun);
            if ($result['status'] === 'created') {
                $created++;
                if ($result['has_deeplink']) {
                    $withDeeplink++;
                }
            } else {
                $skipped++;
            }
        }

        if (! $dryRun) {
            GmailImapCursor::query()->updateOrCreate(
                ['mailbox' => $mailboxName],
                [
                    'last_uid' => $maxUid,
                    'last_polled_at' => now(),
                ]
            );
        }

        return [
            'scanned' => $scanned,
            'created' => $created,
            'skipped' => $skipped,
            'with_deeplink' => $withDeeplink,
            'last_uid' => $maxUid,
            'mailbox' => $mailboxName,
        ];
    }

    /**
     * @param  list<string>  $hostNeedles
     * @return array{status: 'created'|'skipped', has_deeplink: bool}
     */
    private function persistMessage(Message $message, string $mailboxName, array $hostNeedles, bool $dryRun): array
    {
        $messageId = $this->resolveMessageId($message);
        if ($messageId === '') {
            $messageId = 'uid:'.$mailboxName.':'.$message->uid();
        }

        if (GmailInboxMessage::query()->where('message_id', $messageId)->exists()) {
            return ['status' => 'skipped', 'has_deeplink' => false];
        }

        $html = (string) ($message->html() ?? '');
        $text = (string) ($message->text() ?? '');
        if ($html === '' && $text === '') {
            $text = (string) ($message->body() ?? '');
        }

        $deeplinks = $this->deeplinkExtractor->extract($html, $text, $hostNeedles);
        $hasDeeplink = $deeplinks !== [];

        $from = null;
        try {
            $from = $message->from()?->email();
        } catch (Throwable) {
            $from = null;
        }

        $receivedAt = null;
        try {
            $date = $message->date();
            $receivedAt = $date ? Carbon::parse($date) : null;
        } catch (Throwable) {
            $receivedAt = null;
        }

        $payload = [
            'message_id' => $messageId,
            'imap_uid' => (int) $message->uid(),
            'mailbox' => $mailboxName,
            'subject' => $this->truncate((string) ($message->subject() ?? ''), 500),
            'from_address' => $this->truncate((string) ($from ?? ''), 255) ?: null,
            'received_at' => $receivedAt,
            'has_deeplink' => $hasDeeplink,
            'deeplink_url' => $hasDeeplink ? $deeplinks[0] : null,
            'deeplink_urls' => $hasDeeplink ? $deeplinks : null,
            'processed_at' => now(),
        ];

        if ($dryRun) {
            Log::info('gmail.inbox.poll.dry_run', [
                'message_id' => $messageId,
                'subject' => $payload['subject'],
                'has_deeplink' => $hasDeeplink,
                'deeplink_url' => $payload['deeplink_url'],
            ]);

            return ['status' => 'created', 'has_deeplink' => $hasDeeplink];
        }

        GmailInboxMessage::query()->create($payload);

        return ['status' => 'created', 'has_deeplink' => $hasDeeplink];
    }

    private function resolveMessageId(Message $message): string
    {
        try {
            $id = trim((string) ($message->messageId() ?? ''));
            if ($id !== '') {
                return $this->truncate($id, 255);
            }
        } catch (Throwable) {
            // fall through
        }

        return '';
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }
}
