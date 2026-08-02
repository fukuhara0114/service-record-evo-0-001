<?php

namespace App\Http\Controllers;

use App\Models\UnregisteredEmailNote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class PowerAutomateController extends Controller
{
    /**
     * Power Automate から Outlook メールリンク（webLink）を未登録メール Note として登録する。
     *
     * POST /api/power-automate/email-notes
     * Header: X-API-Key: {POWER_AUTOMATE_API_KEY}
     */
    public function storeEmailMessageNote(Request $request)
    {
        $validated = $request->validate([
            'mailLink' => 'required|string|max:998',
            'subject' => 'nullable|string|max:998',
            'from' => 'nullable|string|max:998',
            'to' => 'nullable|string|max:2000',
            'cc' => 'nullable|string|max:2000',
            'date' => 'nullable|string|max:255',
            'bodyPreview' => 'nullable|string|max:4000',
            'whoWrote' => 'nullable|string|max:100',
        ]);

        $mailLink = $this->normalizeMailLink($validated['mailLink']);
        if ($mailLink === '') {
            return response()->json([
                'message' => 'mailLink が空です。',
            ], 422);
        }

        $existing = UnregisteredEmailNote::query()
            ->where('mailLinkHash', UnregisteredEmailNote::hashMailLink($mailLink))
            ->first();

        if ($existing) {
            return response()->json([
                'message' => '同じ mailLink の未登録メール Note が既に存在します。',
                'note' => $existing,
                'created' => false,
            ], 200);
        }

        $whoWrote = trim((string) ($validated['whoWrote'] ?? ''));
        if ($whoWrote === '') {
            $whoWrote = trim((string) ($validated['from'] ?? ''));
        }
        if ($whoWrote === '') {
            $whoWrote = 'Power Automate';
        }
        $whoWrote = Str::limit($whoWrote, 100, '');

        $note = UnregisteredEmailNote::create([
            'mailLink' => $mailLink,
            'mailLinkHash' => UnregisteredEmailNote::hashMailLink($mailLink),
            'whoWrote' => $whoWrote,
            'whenWrote' => $this->resolveWhenWrote($validated['date'] ?? null),
            'subject' => isset($validated['subject']) ? trim((string) $validated['subject']) : null,
            'fromAddress' => isset($validated['from']) ? trim((string) $validated['from']) : null,
        ]);

        return response()->json([
            'message' => '未登録メール Note を登録しました。',
            'note' => $note,
            'created' => true,
        ], 201);
    }

    private function normalizeMailLink(string $mailLink): string
    {
        return trim(preg_replace('/\s+/', '', $mailLink) ?? $mailLink);
    }

    private function resolveWhenWrote(?string $date): Carbon
    {
        if ($date === null || trim($date) === '') {
            return now();
        }

        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            return now();
        }
    }
}
