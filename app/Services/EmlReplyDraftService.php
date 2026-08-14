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

        if ($templateType === self::TYPE_RECEIPT) {
            $productName = (string) ($record->productName ?: '—');
            $sn = (string) ($record->SN ?: '—');
            $replySubject = '【機材受領のご連絡】（製品:' . $productName . ' SN:' . $sn . '）';
        } elseif ($templateType === self::TYPE_QUOTE) {
            $productName = (string) ($record->productName ?: '—');
            $sn = (string) ($record->SN ?: '—');
            $quoteNum = trim((string) ($record->quoteNum ?: $record->sm_quote ?: ''));
            if ($quoteNum === '') {
                $quoteNum = '—';
            }
            $replySubject = '【御見積書の送付No.' . $quoteNum . '】（製品:' . $productName . '　SN:' . $sn . '）';
        } elseif ($replySubject === '') {
            $replySubject = match ($templateType) {
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
        $contactPerson = trim((string) ($record->contactPerson ?? ''));
        if ($contactPerson === '') {
            $contactPerson = '御担当者';
        }

        $sender = $this->resolveSenderName();
        $displayDate = $this->formatDisplayDate($record->receivedDate ?? null);

        $vars = [
            'dealer' => $this->e((string) ($record->dealer ?: '（会社名）')),
            'contactPerson' => $this->e($contactPerson),
            'productName' => $this->e((string) ($record->productName ?: '—')),
            'SN' => $this->e((string) ($record->SN ?: '—')),
            'orderID' => $this->e((string) ($record->orderID ?: '—')),
            'RMA' => $this->e((string) ($record->RMA ?: '—')),
            'sender' => $this->e($sender),
            'displayDate' => $this->e($displayDate),
        ];

        $plainVars = [
            'dealer' => (string) ($record->dealer ?: '（会社名）'),
            'contactPerson' => $contactPerson,
            'productName' => (string) ($record->productName ?: '—'),
            'SN' => (string) ($record->SN ?: '—'),
            'orderID' => (string) ($record->orderID ?: '—'),
            'RMA' => (string) ($record->RMA ?: '—'),
            'sender' => $sender,
            'displayDate' => $displayDate,
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

    private function resolveSenderName(): string
    {
        $user = auth()->user();
        $kanji = trim((string) ($user?->kanji_name ?? ''));
        if ($kanji !== '') {
            return $kanji;
        }
        $name = trim((string) ($user?->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return '担当';
    }

    private function formatDisplayDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y年n月j日');
        }

        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return now()->timezone(config('app.timezone', 'Asia/Tokyo'))->format('Y年n月j日');
        }

        try {
            return \Carbon\Carbon::parse($raw)
                ->timezone(config('app.timezone', 'Asia/Tokyo'))
                ->format('Y年n月j日');
        } catch (\Throwable) {
            return now()->timezone(config('app.timezone', 'Asia/Tokyo'))->format('Y年n月j日');
        }
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
<div style="color:#000;">
<p style="margin:0 0 12px;">{dealer}&emsp;{contactPerson} 様</p>
<p style="margin:0 0 12px;">いつも大変お世話になっております。<br>エックスライトサービスセンター{sender}です。</p>
<p style="margin:0 0 12px;">ご依頼いただきました機材（製品:{productName} SN:{SN}）を{displayDate}にX-riteにて受領いたしました。<br></p>
<div id="gallery-images"></div>
<p style="margin:0 0 12px;"><br>作業を進めさせていただきます。<br>何卒よろしくお願いいたします。<br><br></p>
<p style="margin:0 0 12px;">なお、お問い合わせは出来るだけメールにてお願いいたします。</p>
<p style="margin:0 0 16px;">また、電子帳簿保存法の改正に伴い、発注書・納品書等の書類はメールでの送付をお願いいたします。</p>
<p style="margin:0 0 4px;">*******************************************************************</p>
<p style="margin:0 0 4px;">エックスライト社 サービスセンター</p>
<p style="margin:0 0 4px;">〒135-0064 東京都江東区青海2-5-10 テレコムセンタービル 西棟6F</p>
<p style="margin:0 0 4px;">TEL: 03-6374-8730</p>
<p style="margin:0 0 12px;">japanserviceorder@xrite.com</p>
<p style="margin:0 0 4px;">*******************************************************************</p>
</div>
<div id="ms-outlook-signature"></div>
TPL;
    }

    private function quoteHtmlTemplate(): string
    {
        return <<<'TPL'
<div class="copy-target-quote" style="color:#000; font-weight:bold;">
{dealer} {contactPerson}　様<br><br>
いつも大変お世話になっております。<br>
エックスライトサービスセンター{sender}です。<br><br>
お預かりしております 機材（製品:{productName}　SN:{SN}）の御見積書を添付いたしますのでご査収ください。<br>
（こちらの機材は{displayDate}にX-riteにて受領しております）<br><br>
<div id="gallery-images"></div>
尚、正式御発注後（ご注文書受領後）の着手になりますので御留意下さい。<br><br>
ご確認よろしくお願いいたします。<br><br>
●電子帳簿保存法改正に伴いご依頼・ご注文書等メールにてお願いしております。<br>
&nbsp;&nbsp;（FAXでいただきましても、受注出来兼ねます事、ご了承ください。）<br>
●ご注文書いただきました際にはご返信しておりますので、<br>
ご送付後2営業日が過ぎましても弊社より返信がないもに関しましては<br>
お手数ですが、お問い合わせいただけますようお願い申し上げます。<br>
***********************************************************<br>
エックスライト社 サービスセンター<br>
〒135-0064　東京都江東区青海2-5-10<br>
テレコムセンタービル　西棟6F<br>
TEL:　03-6374-8730<br>
Mail: japanserviceorder@xrite.com<br>
**********************************************************<br>
<div id="ms-outlook-signature"></div>
</div>
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
<div id="gallery-images"></div>
<p style="margin:0 0 12px;">変更内容の詳細は本文をご確認ください。<br>ご不明点がございましたらお知らせください。<br>何卒よろしくお願いいたします。</p>
TPL;
    }

    private function receiptTextTemplate(): string
    {
        return <<<'TPL'
{dealer}
{contactPerson} 様

いつも大変お世話になっております。
エックスライトサービスセンター{sender}です。

ご依頼いただきました機材（製品:{productName} SN:{SN}）を{displayDate}にX-riteにて受領いたしました。
作業を進めさせていただきます。
何卒よろしくお願いいたします。

なお、お問い合わせは出来るだけメールにてお願いいたします。

また、電子帳簿保存法の改正に伴い、発注書・納品書等の書類はメールでの送付をお願いいたします。

エックスライト社 サービスセンター
〒135-0064 東京都江東区青海2-5-10 テレコムセンタービル 西棟6F
TEL: 03-6374-8730
japanserviceorder@xrite.com
TPL;
    }

    private function quoteTextTemplate(): string
    {
        return <<<'TPL'
{dealer} {contactPerson}　様

いつも大変お世話になっております。
エックスライトサービスセンター{sender}です。

お預かりしております 機材（製品:{productName}　SN:{SN}）の御見積書を添付いたしますのでご査収ください。
（こちらの機材は{displayDate}にX-riteにて受領しております）

尚、正式御発注後（ご注文書受領後）の着手になりますので御留意下さい。

ご確認よろしくお願いいたします。

●電子帳簿保存法改正に伴いご依頼・ご注文書等メールにてお願いしております。
  （FAXでいただきましても、受注出来兼ねます事、ご了承ください。）
●ご注文書いただきました際にはご返信しておりますので、
ご送付後2営業日が過ぎましても弊社より返信がないもに関しましては
お手数ですが、お問い合わせいただけますようお願い申し上げます。
***********************************************************
エックスライト社 サービスセンター
〒135-0064　東京都江東区青海2-5-10
テレコムセンタービル　西棟6F
TEL:　03-6374-8730
Mail: japanserviceorder@xrite.com
**********************************************************
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
