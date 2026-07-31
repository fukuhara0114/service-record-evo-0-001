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
        if (!array_key_exists($templateType, $this->templateOptions())) {
            throw new \InvalidArgumentException('不明な定型メール種別です。');
        }

        $message = Message::from($sourceEmlBinary, false);

        $originalFrom = trim((string) $message->getHeaderValue('From'));
        $originalTo = trim((string) $message->getHeaderValue('To'));
        $originalSubject = trim((string) ($message->getHeaderValue('Subject') ?: ''));
        $originalDate = trim((string) $message->getHeaderValue('Date'));
        $messageId = trim((string) $message->getHeaderValue('Message-ID'));
        $references = trim((string) $message->getHeaderValue('References'));

        $replyTo = $this->extractEmailAddress($originalFrom) ?: $originalFrom;
        $replySubject = $this->buildReplySubject($originalSubject);
        $replyBody = $this->buildReplyBody($record, $templateType, [
            'from' => $originalFrom,
            'to' => $originalTo,
            'subject' => $originalSubject,
            'date' => $originalDate,
            'bodyText' => (string) ($message->getTextContent() ?: strip_tags((string) $message->getHtmlContent())),
        ]);

        $headers = [
            'X-Unsent: 1',
            'MIME-Version: 1.0',
            'To: ' . $this->encodeAddressHeader($replyTo),
            'Subject: ' . $this->encodeHeader($replySubject),
            'Date: ' . date('r'),
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        if ($messageId !== '') {
            $headers[] = 'In-Reply-To: ' . $messageId;
            $refs = trim($references . ' ' . $messageId);
            $headers[] = 'References: ' . preg_replace('/\s+/', ' ', $refs);
        }

        $eml = implode("\r\n", $headers)
            . "\r\n\r\n"
            . chunk_split(base64_encode($replyBody), 76, "\r\n");

        $safeName = preg_replace('/[\\\\\/\:\*\?\"\<\>\|]+/', '_', $replySubject) ?: 'reply-draft';
        $filename = 'Re_' . $safeName . '.eml';

        return [
            'filename' => $filename,
            'content' => $eml,
            'subject' => $replySubject,
            'to' => $replyTo,
            'templateType' => $templateType,
            'templateLabel' => $this->templateOptions()[$templateType],
        ];
    }

    private function buildReplyBody(ServiceRecord $record, string $templateType, array $original): string
    {
        $vars = [
            'dealer' => (string) ($record->dealer ?: '（会社名）'),
            'contactPerson' => (string) ($record->contactPerson ?: 'ご担当者'),
            'productName' => (string) ($record->productName ?: '—'),
            'SN' => (string) ($record->SN ?: '—'),
            'orderID' => (string) ($record->orderID ?: '—'),
            'RMA' => (string) ($record->RMA ?: '—'),
        ];

        $template = match ($templateType) {
            self::TYPE_RECEIPT => $this->receiptTemplate(),
            self::TYPE_QUOTE => $this->quoteTemplate(),
            self::TYPE_WORK_CHANGE => $this->workChangeTemplate(),
            default => throw new \InvalidArgumentException('不明な定型メール種別です。'),
        };

        $body = $this->replaceVars($template, $vars);
        $quoted = $this->buildQuotedOriginal($original);

        return rtrim($body) . "\r\n\r\n" . $quoted . "\r\n";
    }

    private function receiptTemplate(): string
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

    private function quoteTemplate(): string
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

    private function workChangeTemplate(): string
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

        return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $replaced));
    }

    private function buildQuotedOriginal(array $original): string
    {
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
}
