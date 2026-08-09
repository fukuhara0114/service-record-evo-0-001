<?php

namespace App\Services;

use App\Models\ServiceRecord;
use ZBateson\MailMimeParser\Message;

class EmlReplyDraftService
{
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_QUOTE = 'quote';
    public const TYPE_WORK_CHANGE = 'work_change';

    public function templateOptions(): array
    {
        return [
            self::TYPE_RECEIPT => '① 受領メール',
            self::TYPE_QUOTE => '② 見積添付メール',
            self::TYPE_WORK_CHANGE => '③ 作業内容変更メール',
        ];
    }

    public function buildDraftEml(string $sourceEmlBinary, ServiceRecord $record, string $templateType): array
    {
        $preview = $this->buildPreview($record, $templateType, $sourceEmlBinary);
        $boundary = '----=_Alt_' . bin2hex(random_bytes(8));

        $headers = [
            'X-Unsent: 1',
            'MIME-Version: 1.0',
            'To: ' . $this->encodeAddressHeader($preview['to']),
            'Subject: ' . $this->encodeHeader($preview['subject']),
            'Date: ' . date('r'),
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        if (($preview['messageId'] ?? '') !== '') {
            $headers[] = 'In-Reply-To: ' . $preview['messageId'];
            $refs = trim(($preview['references'] ?? '') . ' ' . $preview['messageId']);
            $headers[] = 'References: ' . preg_replace('/\s+/', ' ', $refs);
        }

        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($preview['bodyText']), 76, "\r\n"),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($preview['bodyHtml']), 76, "\r\n"),
            '--' . $boundary . '--',
        ];

        $eml = implode("\r\n", $headers)
            . "\r\n\r\n"
            . implode("\r\n", $parts)
            . "\r\n";

        $safeName = preg_replace('/[\\\\\/\:\*\?\"\<\>\|]+/', '_', $preview['subject']) ?: 'reply-draft';
        $filename = 'Re_' . $safeName . '.eml';

        return [
            'filename' => $filename,
            'content' => $eml,
            'subject' => $preview['subject'],
            'to' => $preview['to'],
            'body' => $preview['bodyHtml'],
            'bodyHtml' => $preview['bodyHtml'],
            'bodyText' => $preview['bodyText'],
            'templateType' => $preview['templateType'],
            'templateLabel' => $preview['templateLabel'],
        ];
    }

    /**
     * 定型メール本文のプレビュー（source eml が無くても可）。
     *
     * @return array{
     *   to:string,
     *   subject:string,
     *   body:string,
     *   bodyHtml:string,
     *   bodyText:string,
     *   templateType:string,
     *   templateLabel:string,
     *   messageId?:string,
     *   references?:string
     * }
     */
    public function buildPreview(ServiceRecord $record, string $templateType, ?string $sourceEmlBinary = null): array
    {
        if (!array_key_exists($templateType, $this->templateOptions())) {
            throw new \InvalidArgumentException('不明な定型メール種別です。');
        }

        $original = [
            'from' => '',
            'to' => '',
            'subject' => '',
            'date' => '',
            'bodyText' => '',
            'bodyHtml' => '',
        ];
        $replyTo = (string) ($record->email ?: '');
        $replySubject = '';
        $messageId = '';
        $references = '';

        if ($sourceEmlBinary !== null && $sourceEmlBinary !== '') {
            $message = Message::from($sourceEmlBinary, false);
            $originalFrom = trim((string) $message->getHeaderValue('From'));
            $originalTo = trim((string) $message->getHeaderValue('To'));
            $originalSubject = trim((string) ($message->getHeaderValue('Subject') ?: ''));
            $originalDate = trim((string) $message->getHeaderValue('Date'));
            $messageId = trim((string) $message->getHeaderValue('Message-ID'));
            $references = trim((string) $message->getHeaderValue('References'));
            $html = (string) ($message->getHtmlContent() ?: '');
            $text = (string) ($message->getTextContent() ?: '');

            $original = [
                'from' => $originalFrom,
                'to' => $originalTo,
                'subject' => $originalSubject,
                'date' => $originalDate,
                'bodyText' => $text !== '' ? $text : strip_tags($html),
                'bodyHtml' => $html,
            ];
            $replyTo = $this->extractEmailAddress($originalFrom) ?: $originalFrom;
            $replySubject = $this->buildReplySubject($originalSubject);
        }

        if ($replySubject === '') {
            $replySubject = match ($templateType) {
                self::TYPE_RECEIPT => '製品受領のご連絡',
                self::TYPE_QUOTE => 'お見積のご案内',
                self::TYPE_WORK_CHANGE => '作業内容変更のご連絡',
                default => 'ご連絡',
            };
        }

        $bodies = $this->buildReplyBodies($record, $templateType, $original);

        return [
            'to' => $replyTo,
            'subject' => $replySubject,
            'body' => $bodies['html'],
            'bodyHtml' => $bodies['html'],
            'bodyText' => $bodies['text'],
            'templateType' => $templateType,
            'templateLabel' => $this->templateOptions()[$templateType],
            'messageId' => $messageId,
            'references' => $references,
        ];
    }

    /**
     * @return array{html:string,text:string}
     */
    private function buildReplyBodies(ServiceRecord $record, string $templateType, array $original): array
    {
        $vars = [
            'dealer' => $this->e((string) ($record->dealer ?: '（会社名）')),
            'contactPerson' => $this->e((string) ($record->contactPerson ?: 'ご担当者')),
            'productName' => $this->e((string) ($record->productName ?: '—')),
            'SN' => $this->e((string) ($record->SN ?: '—')),
            'orderID' => $this->e((string) ($record->orderID ?: '—')),
            'RMA' => $this->e((string) ($record->RMA ?: '—')),
        ];

        $plainVars = [
            'dealer' => (string) ($record->dealer ?: '（会社名）'),
            'contactPerson' => (string) ($record->contactPerson ?: 'ご担当者'),
            'productName' => (string) ($record->productName ?: '—'),
            'SN' => (string) ($record->SN ?: '—'),
            'orderID' => (string) ($record->orderID ?: '—'),
            'RMA' => (string) ($record->RMA ?: '—'),
        ];

        $htmlTemplate = match ($templateType) {
            self::TYPE_RECEIPT => $this->receiptHtmlTemplate(),
            self::TYPE_QUOTE => $this->quoteHtmlTemplate(),
            self::TYPE_WORK_CHANGE => $this->workChangeHtmlTemplate(),
            default => throw new \InvalidArgumentException('不明な定型メール種別です。'),
        };

        $textTemplate = match ($templateType) {
            self::TYPE_RECEIPT => $this->receiptTextTemplate(),
            self::TYPE_QUOTE => $this->quoteTextTemplate(),
            self::TYPE_WORK_CHANGE => $this->workChangeTextTemplate(),
            default => throw new \InvalidArgumentException('不明な定型メール種別です。'),
        };

        $htmlBody = $this->replaceVars($htmlTemplate, $vars);
        $textBody = $this->normalizeNewlines($this->replaceVars($textTemplate, $plainVars));

        $quotedHtml = $this->buildQuotedOriginalHtml($original);
        $quotedText = $this->buildQuotedOriginalText($original);

        if ($quotedHtml !== '') {
            $htmlBody .= $quotedHtml;
        }
        if ($quotedText !== '') {
            $textBody = rtrim($textBody) . "\r\n\r\n" . $quotedText . "\r\n";
        } else {
            $textBody = rtrim($textBody) . "\r\n";
        }

        $htmlDocument = $this->wrapHtmlDocument($htmlBody);

        return [
            'html' => $htmlDocument,
            'text' => $textBody,
        ];
    }

    private function wrapHtmlDocument(string $innerHtml): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>mail</title>
</head>
<body style="margin:0;padding:0;background:#ffffff;">
<div style="font-family:'Segoe UI',Meiryo,'Hiragino Kaku Gothic ProN',sans-serif;font-size:14px;line-height:1.7;color:#111827;padding:16px;">
{$innerHtml}
</div>
</body>
</html>
HTML;
    }

    private function receiptHtmlTemplate(): string
    {
        return <<<'TPL'
<p style="margin:0 0 12px;">{dealer}<br>{contactPerson} 様</p>
<p style="margin:0 0 12px;">お世話になっております。</p>
<p style="margin:0 0 12px;">下記製品のご依頼を受領いたしましたのでご連絡いたします。</p>
<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;">
<tr><td style="padding:4px 12px 4px 0;color:#64748b;">製品名</td><td style="padding:4px 0;font-weight:700;">{productName}</td></tr>
<tr><td style="padding:4px 12px 4px 0;color:#64748b;">S/N</td><td style="padding:4px 0;font-weight:700;">{SN}</td></tr>
</table>
<p style="margin:0 0 12px;">進捗があり次第、改めてご連絡いたします。<br>何卒よろしくお願いいたします。</p>
TPL;
    }

    private function quoteHtmlTemplate(): string
    {
        return <<<'TPL'
<p style="margin:0 0 12px;">{dealer}<br>{contactPerson} 様</p>
<p style="margin:0 0 12px;">お世話になっております。</p>
<p style="margin:0 0 12px;">下記製品につきまして、お見積内容をご案内いたします。<br>（見積書がある場合は、Outlook 上で添付をご確認・追加してください）</p>
<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;">
<tr><td style="padding:4px 12px 4px 0;color:#64748b;">製品名</td><td style="padding:4px 0;font-weight:700;">{productName}</td></tr>
<tr><td style="padding:4px 12px 4px 0;color:#64748b;">S/N</td><td style="padding:4px 0;font-weight:700;">{SN}</td></tr>
</table>
<p style="margin:0 0 12px;">ご確認のほど、よろしくお願いいたします。</p>
TPL;
    }

    private function workChangeHtmlTemplate(): string
    {
        return <<<'TPL'
<p style="margin:0 0 12px;">{dealer}<br>{contactPerson} 様</p>
<p style="margin:0 0 12px;">お世話になっております。</p>
<p style="margin:0 0 12px;">下記製品の作業内容に変更がございますのでご連絡いたします。</p>
<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;">
<tr><td style="padding:4px 12px 4px 0;color:#64748b;">製品名</td><td style="padding:4px 0;font-weight:700;">{productName}</td></tr>
<tr><td style="padding:4px 12px 4px 0;color:#64748b;">S/N</td><td style="padding:4px 0;font-weight:700;">{SN}</td></tr>
</table>
<p style="margin:0 0 12px;">変更内容の詳細は本文をご確認ください。<br>ご不明点がございましたらお知らせください。<br>何卒よろしくお願いいたします。</p>
TPL;
    }

    private function receiptTextTemplate(): string
    {
        return <<<'TPL'
{dealer}
{contactPerson} 様

お世話になっております。

下記製品のご依頼を受領いたしましたのでご連絡いたします。

製品名: {productName}
S/N: {SN}

進捗があり次第、改めてご連絡いたします。
何卒よろしくお願いいたします。
TPL;
    }

    private function quoteTextTemplate(): string
    {
        return <<<'TPL'
{dealer}
{contactPerson} 様

お世話になっております。

下記製品につきまして、お見積内容をご案内いたします。
（見積書がある場合は、Outlook 上で添付をご確認・追加してください）

製品名: {productName}
S/N: {SN}

ご確認のほど、よろしくお願いいたします。
TPL;
    }

    private function workChangeTextTemplate(): string
    {
        return <<<'TPL'
{dealer}
{contactPerson} 様

お世話になっております。

下記製品の作業内容に変更がございますのでご連絡いたします。

製品名: {productName}
S/N: {SN}

変更内容の詳細は本文をご確認ください。
ご不明点がございましたらお知らせください。
何卒よろしくお願いいたします。
TPL;
    }

    private function replaceVars(string $template, array $vars): string
    {
        $replaced = $template;
        foreach ($vars as $key => $value) {
            $replaced = str_replace('{' . $key . '}', $value, $replaced);
        }

        return $replaced;
    }

    private function normalizeNewlines(string $text): string
    {
        return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $text));
    }

    private function buildQuotedOriginalHtml(array $original): string
    {
        $hasOriginal = trim((string) ($original['from'] ?? '')) !== ''
            || trim((string) ($original['to'] ?? '')) !== ''
            || trim((string) ($original['subject'] ?? '')) !== ''
            || trim((string) ($original['date'] ?? '')) !== ''
            || trim((string) ($original['bodyText'] ?? '')) !== ''
            || trim((string) ($original['bodyHtml'] ?? '')) !== '';

        if (!$hasOriginal) {
            return '';
        }

        $from = $this->e((string) ($original['from'] ?: '—'));
        $sent = $this->e((string) ($original['date'] ?: '—'));
        $to = $this->e((string) ($original['to'] ?: '—'));
        $subject = $this->e((string) ($original['subject'] ?: '(件名なし)'));
        $bodyHtml = trim((string) ($original['bodyHtml'] ?? ''));
        if ($bodyHtml === '') {
            $bodyHtml = nl2br($this->e(trim((string) ($original['bodyText'] ?? '')) ?: '(元メール本文なし)'));
        }

        return <<<HTML

<hr style="border:none;border-top:1px solid #cbd5e1;margin:20px 0 12px;">
<div style="color:#475569;font-size:12px;margin:0 0 8px;">-----Original Message-----</div>
<div style="color:#475569;font-size:12px;margin:0 0 12px;">
<div>From: {$from}</div>
<div>Sent: {$sent}</div>
<div>To: {$to}</div>
<div>Subject: {$subject}</div>
</div>
<blockquote style="margin:0;padding-left:12px;border-left:3px solid #cbd5e1;color:#334155;">
{$bodyHtml}
</blockquote>
HTML;
    }

    private function buildQuotedOriginalText(array $original): string
    {
        $hasOriginal = trim((string) ($original['from'] ?? '')) !== ''
            || trim((string) ($original['to'] ?? '')) !== ''
            || trim((string) ($original['subject'] ?? '')) !== ''
            || trim((string) ($original['date'] ?? '')) !== ''
            || trim((string) ($original['bodyText'] ?? '')) !== ''
            || trim((string) ($original['bodyHtml'] ?? '')) !== '';

        if (!$hasOriginal) {
            return '';
        }

        $lines = [
            '-----Original Message-----',
            'From: ' . ($original['from'] ?: '—'),
            'Sent: ' . ($original['date'] ?: '—'),
            'To: ' . ($original['to'] ?: '—'),
            'Subject: ' . ($original['subject'] ?: '(件名なし)'),
            '',
        ];

        $body = trim((string) ($original['bodyText'] ?? ''));
        if ($body === '') {
            $body = trim(strip_tags((string) ($original['bodyHtml'] ?? '')));
        }
        if ($body === '') {
            $body = '(元メール本文なし)';
        }

        foreach (preg_split("/\r\n|\n|\r/", $body) as $line) {
            $lines[] = '> ' . $line;
        }

        return implode("\r\n", $lines);
    }

    private function buildReplySubject(string $originalSubject): string
    {
        $subject = trim($originalSubject);
        if ($subject === '') {
            return 'Re: ';
        }
        if (preg_match('/^(re|RE|Re|ｒｅ|Ｒｅ)\s*:\s*/u', $subject)) {
            return $subject;
        }

        return 'Re: ' . $subject;
    }

    private function extractEmailAddress(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return trim($matches[1]);
        }
        if (filter_var(trim($from), FILTER_VALIDATE_EMAIL)) {
            return trim($from);
        }

        return '';
    }

    private function encodeHeader(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        if (!preg_match('/[^\x20-\x7E]/', $value)) {
            return $value;
        }

        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    private function encodeAddressHeader(string $value): string
    {
        $email = $this->extractEmailAddress($value);
        if ($email !== '' && $email !== $value) {
            $name = trim(str_replace(['<', '>'], '', str_replace($email, '', $value)));
            $name = trim($name, " \t\"'");
            if ($name !== '') {
                return $this->encodeHeader($name) . ' <' . $email . '>';
            }

            return $email;
        }

        return $this->encodeHeader($value);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
