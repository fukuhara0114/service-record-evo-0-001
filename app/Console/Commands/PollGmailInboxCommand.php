<?php

namespace App\Console\Commands;

use App\Services\Gmail\GmailInboxPollService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PollGmailInboxCommand extends Command
{
    protected $signature = 'gmail:poll-inbox
                            {--dry-run : DB に保存せず結果だけ表示}';

    protected $description = 'Gmail INBOX を取得し、Outlook deeplink を抽出して保存する';

    public function handle(GmailInboxPollService $poller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Gmail inbox poll (dry-run)...' : 'Gmail inbox poll...');

        try {
            $result = $poller->poll($dryRun);
        } catch (Throwable $e) {
            $this->error('ポーリングに失敗しました: '.$e->getMessage());
            Log::error('gmail.inbox.poll.failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            report($e);

            return self::FAILURE;
        }

        $this->table(
            ['mailbox', 'scanned', 'created', 'skipped', 'with_deeplink', 'last_uid'],
            [[
                $result['mailbox'],
                $result['scanned'],
                $result['created'],
                $result['skipped'],
                $result['with_deeplink'],
                $result['last_uid'],
            ]]
        );

        Log::info('gmail.inbox.poll.completed', $result);

        return self::SUCCESS;
    }
}
