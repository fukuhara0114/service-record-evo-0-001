<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiOcrService
{
    /**
     * 添付ファイルのバイナリから案件登録フォーム用フィールドを抽出する。
     *
     * @return array{fields: array<string, mixed>, flags: array<string, mixed>, rawKeys: array<string, mixed>}
     */
    public function extractFromBinary(string $binary, string $mimeType): array
    {
        $apiKey = (string) config('services.gemini.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY が未設定です。');
        }

        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $timeout = (int) config('services.gemini.timeout', 120);
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s',
            rawurlencode($model),
            urlencode($apiKey),
        );

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $this->prompt()],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType !== '' ? $mimeType : 'application/pdf',
                                'data' => base64_encode($binary),
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0,
            ],
        ];

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (!$response->successful()) {
            $message = $response->json('error.message')
                ?? $response->body()
                ?: ('Gemini API エラー (HTTP ' . $response->status() . ')');
            throw new RuntimeException(is_string($message) ? $message : 'Gemini API エラー');
        }

        $text = $this->extractResponseText($response->json());
        $parsed = $this->parseJsonObject($text);
        $fields = $this->mapToFormFields($parsed);
        $flags = $this->mapFlags($parsed);

        return [
            'fields' => $fields,
            'flags' => $flags,
            'rawKeys' => $parsed,
        ];
    }

    private function extractResponseText(mixed $json): string
    {
        if (!is_array($json)) {
            throw new RuntimeException('Gemini 応答が不正です。');
        }

        $parts = data_get($json, 'candidates.0.content.parts');
        if (!is_array($parts) || $parts === []) {
            $blockReason = data_get($json, 'promptFeedback.blockReason');
            if (is_string($blockReason) && $blockReason !== '') {
                throw new RuntimeException('Gemini が応答を拒否しました: ' . $blockReason);
            }
            throw new RuntimeException('Gemini 応答にテキストがありません。');
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        $text = trim(implode("\n", $chunks));
        if ($text === '') {
            throw new RuntimeException('Gemini 応答テキストが空です。');
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonObject(string $text): array
    {
        $normalized = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $normalized, $matches) === 1) {
            $normalized = trim($matches[1]);
        }

        $start = strpos($normalized, '{');
        $end = strrpos($normalized, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('Gemini 応答から JSON を抽出できませんでした。');
        }

        $json = substr($normalized, $start, $end - $start + 1);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Gemini 応答 JSON の解析に失敗しました。');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function mapToFormFields(array $data): array
    {
        $dealerAddress = $this->resolveStakeholderAddress($data, '依頼者企業');
        $endUserAddress = $this->resolveStakeholderAddress($data, 'エンドユーザー企業');
        $deliveryAddress = $this->resolveStakeholderAddress($data, '送付先企業');

        return [
            'productName' => $this->stringValue($data, ['製品型名']),
            'SN' => $this->stringValue($data, ['シリアル番号']),
            'dealer' => $this->stringValue($data, ['依頼者企業名']),
            'dealer_depart' => $this->stringValue($data, ['依頼者企業部署名']),
            'contactPerson' => $this->stringValue($data, ['依頼者企業担当者名']),
            'phone' => $this->stringValue($data, ['依頼者企業電話番号']),
            'email' => $this->stringValue($data, ['依頼者企業メールアドレス']),
            'zipcode' => $this->normalizeZipcode($this->stringValue($data, ['依頼者企業郵便番号'])),
            'address1' => $dealerAddress['address1'],
            'address2' => $dealerAddress['address2'],
            'endUser' => $this->stringValue($data, ['エンドユーザー企業名']),
            'endUser_depart' => $this->stringValue($data, ['エンドユーザー企業部署名']),
            'endUser_contactPerson' => $this->stringValue($data, ['エンドユーザー企業担当者名']),
            'endUser_phone' => $this->stringValue($data, ['エンドユーザー企業電話番号']),
            'endUser_email' => $this->stringValue($data, ['エンドユーザー企業メールアドレス']),
            'endUser_zipcode' => $this->normalizeZipcode($this->stringValue($data, ['エンドユーザー企業郵便番号'])),
            'endUser_address1' => $endUserAddress['address1'],
            'endUser_address2' => $endUserAddress['address2'],
            'deliveryDestination_company' => $this->stringValue($data, ['送付先企業名']),
            'deliveryDestination_depart' => $this->stringValue($data, ['送付先企業部署名']),
            'deliveryDestination_contactPerson' => $this->stringValue($data, ['送付先企業担当者名']),
            'deliveryDestination_phone' => $this->stringValue($data, ['送付先企業電話番号']),
            'deliveryDestination_email' => $this->stringValue($data, ['送付先企業メールアドレス']),
            'deliveryDestination_zipcode' => $this->normalizeZipcode($this->stringValue($data, ['送付先企業郵便番号'])),
            'deliveryDestination_address1' => $deliveryAddress['address1'],
            'deliveryDestination_address2' => $deliveryAddress['address2'],
        ];
    }

    /**
     * 帳票の都道府県・住所1・住所2（および旧キー「住所」）を統合し、
     * address1=都道府県 / address2=都道府県以降すべて に正規化する。
     *
     * @param  array<string, mixed>  $data
     * @return array{address1: ?string, address2: ?string}
     */
    private function resolveStakeholderAddress(array $data, string $prefix): array
    {
        $prefecture = $this->stringValue($data, ["{$prefix}都道府県"]);
        $line1 = $this->stringValue($data, ["{$prefix}住所1", "{$prefix}住所１"]);
        $line2 = $this->stringValue($data, ["{$prefix}住所2", "{$prefix}住所２"]);
        $legacy = $this->stringValue($data, ["{$prefix}住所"]);

        $restParts = [];
        if ($line1 !== null && $line1 !== '') {
            $restParts[] = $line1;
        }
        if ($line2 !== null && $line2 !== '') {
            $restParts[] = $line2;
        }

        // 住所1/2 が無い旧形式、または住所2欠落時の救済
        if ($legacy !== null && $legacy !== '') {
            if ($restParts === []) {
                $restParts[] = $legacy;
            } else {
                $joined = implode('', $restParts);
                $compactLegacy = preg_replace('/\s+/u', '', $legacy) ?? $legacy;
                $compactJoined = preg_replace('/\s+/u', '', $joined) ?? $joined;
                if (!str_contains($compactJoined, $compactLegacy)
                    && !str_contains($compactLegacy, $compactJoined)
                    && ($line2 === null || $line2 === '')
                ) {
                    // 例: 住所1のみ返却され、旧キー「住所」にビル名まで含まれる場合
                    if (str_starts_with($compactLegacy, $compactJoined)) {
                        $suffix = trim(mb_substr($legacy, mb_strlen($line1 ?? '')));
                        if ($suffix !== '') {
                            $restParts[] = $suffix;
                        }
                    }
                }
            }
        }

        $rest = trim(preg_replace('/\s+/u', ' ', implode(' ', $restParts)) ?? '');

        return $this->splitPrefectureAndAddress($prefecture, $rest !== '' ? $rest : null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool|string|null>
     */
    private function mapFlags(array $data): array
    {
        return [
            'repair' => $this->boolValue($data, ['修理かどうか', '修理']),
            'recalibration' => $this->boolValue($data, ['再校正かどうか', '再校正']),
            'warrantyIn' => $this->boolValue($data, ['保証内', '保証期間内']),
            'warrantyOut' => $this->boolValue($data, ['保証外', '保証期間外', '―保証外']),
            'maintenanceContract' => $this->boolValue($data, ['保守契約があるか', '保守契約有']),
            'maintenanceContractNumber' => $this->stringValue($data, ['保守契約番号', '保守契約番号(保守契約がある場合)']),
            'loanerWish' => $this->stringValue($data, ['代替機', '代替機のご希望']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function stringValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->lookup($data, $key);
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '' && strcasecmp($text, 'null') !== 0) {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function boolValue(array $data, array $keys): ?bool
    {
        foreach ($keys as $key) {
            $value = $this->lookup($data, $key);
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return (int) $value === 1;
            }
            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if (in_array($normalized, ['true', '1', 'yes', 'y', '有', 'あり'], true)) {
                    return true;
                }
                if (in_array($normalized, ['false', '0', 'no', 'n', '無', 'なし', 'null'], true)) {
                    return false;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lookup(array $data, string $key): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $normalizedTarget = $this->normalizeKey($key);
        foreach ($data as $candidateKey => $value) {
            if (!is_string($candidateKey)) {
                continue;
            }
            if ($this->normalizeKey($candidateKey) === $normalizedTarget) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeKey(string $key): string
    {
        $key = str_replace(["\u{2015}", '―', '–', '—', '-', 'ー'], '', $key);
        $key = preg_replace('/\s+/u', '', $key) ?? $key;

        return $key;
    }

    /**
     * address1 = 都道府県、address2 = 都道府県以降の住所すべて。
     * 都道府県欄に市区町村まで入っている場合や、住所欄先頭に都道府県がある場合も正規化する。
     *
     * @return array{address1: ?string, address2: ?string}
     */
    private function splitPrefectureAndAddress(?string $prefecture, ?string $address): array
    {
        $pref = trim((string) $prefecture);
        $addr = trim((string) $address);

        // 都道府県欄に「東京都文京区…」のように余分が入っている場合
        if ($pref !== '') {
            $fromPrefField = $this->extractPrefectureFromAddress($pref);
            if (($fromPrefField['prefecture'] ?? null) !== null) {
                $pref = $fromPrefField['prefecture'];
                $extra = trim((string) ($fromPrefField['address'] ?? ''));
                if ($extra !== '') {
                    $addr = trim($extra . ($addr !== '' ? ' ' . $addr : ''));
                }
            }
        }

        // 都道府県欄が空、または住所側先頭に都道府県がある場合
        if ($addr !== '') {
            $fromAddr = $this->extractPrefectureFromAddress($addr);
            $addrPref = $fromAddr['prefecture'] ?? null;
            $addrRest = trim((string) ($fromAddr['address'] ?? ''));

            if ($pref === '' && $addrPref !== null) {
                $pref = $addrPref;
                $addr = $addrRest;
            } elseif ($pref !== '' && $addrPref !== null) {
                // 住所先頭の都道府県を除去（同一でも別でも、住所側の都道府県表記は落とす）
                if ($addrPref === $pref || str_starts_with($addr, $pref)) {
                    $addr = $addrRest !== '' ? $addrRest : trim(mb_substr($addr, mb_strlen($pref)));
                }
            } elseif ($pref !== '' && str_starts_with($addr, $pref)) {
                $addr = trim(mb_substr($addr, mb_strlen($pref)));
            }
        }

        $addr = trim(preg_replace('/\s+/u', ' ', $addr) ?? '');

        return [
            'address1' => $pref !== '' ? $pref : null,
            'address2' => $addr !== '' ? $addr : null,
        ];
    }

    /**
     * @return array{prefecture: ?string, address: ?string}
     */
    private function extractPrefectureFromAddress(string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            return ['prefecture' => null, 'address' => null];
        }

        if (preg_match('/^(東京都|北海道|(?:京都|大阪)府|.+?県)(.*)$/u', $address, $matches) === 1) {
            $pref = trim($matches[1]);
            $rest = trim($matches[2]);

            return [
                'prefecture' => $pref !== '' ? $pref : null,
                'address' => $rest !== '' ? $rest : null,
            ];
        }

        return [
            'prefecture' => null,
            'address' => $address,
        ];
    }

    private function normalizeZipcode(?string $zipcode): ?string
    {
        if ($zipcode === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $zipcode) ?? '';
        if (strlen($digits) === 7) {
            return substr($digits, 0, 3) . '-' . substr($digits, 3);
        }

        $trimmed = trim($zipcode);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
添付されたファイルの内容を解析し、すべての情報を項目ごとに整理してください。

【出力形式の厳守事項】
- 出力は「完全に有効なJSON」のみとすること
- ```（バッククォート）やコードブロックは絶対に含めないこと
- 「json」や説明文、注釈、前置き、後書きは一切出力しないこと
- 改行や空白はJSONとして問題ない範囲で許可
- 必ず先頭は { 、末尾は } のみとすること
- 文字列は必ずダブルクォートで囲むこと
- 不明な値は null とすること

【禁止事項】
- ```json や ``` の出力
- 「以下がJSONです」などの説明文
- Markdown形式の出力
- JSON以外の一切の文字
- keyの文字は元が日本語の場合は日本語で、日本語以外であれば日本語に翻訳する
- 「製品型名」のすぐ下の文字が「製品型名」のvalue
- 「シリアル番号」のすぐ下の文字が「シリアル番号」のvalue
【出力例（あくまで形式）】
{
  "製品型名": "value"
}
【絶対に欲しい情報】
-製品型名
-シリアル番号
-修理かどうか
-再校正かどうか
-保証内
-保証外
-保守契約があるか
-保守契約番号(保守契約がある場合)
-依頼者企業名
-依頼者企業部署名
-依頼者企業担当者名
-依頼者企業郵便番号
-依頼者企業都道府県
-依頼者企業住所1
-依頼者企業住所2
-依頼者企業電話番号
-依頼者企業メールアドレス
-エンドユーザー企業名
-エンドユーザー企業部署名
-エンドユーザー企業担当者名
-エンドユーザー企業郵便番号
-エンドユーザー企業都道府県
-エンドユーザー企業住所1
-エンドユーザー企業住所2
-エンドユーザー企業電話番号
-エンドユーザー企業メールアドレス
-送付先企業名
-送付先企業部署名
-送付先企業担当者名
-送付先企業郵便番号
-送付先企業都道府県
-送付先企業住所1
-送付先企業住所2
-送付先企業電話番号
-送付先企業メールアドレス
-代替機

【jsonの出力の配列構成】
jsonは必ず以下の順番で構成して下さい
 1. -製品型名
 2. -シリアル番号
 3. -修理かどうか
 4. -再校正かどうか
 5. -保証内
 6. -保証外
 7. -保守契約があるか
 8. -保守契約番号(保守契約がある場合)
 9. -依頼者企業名
10. -依頼者企業部署名
11. -依頼者企業担当者名
12. -依頼者企業郵便番号
13. -依頼者企業都道府県
14. -依頼者企業住所1
15. -依頼者企業住所2
16. -依頼者企業電話番号
17. -依頼者企業メールアドレス
18. -エンドユーザー企業名
19. -エンドユーザー企業部署名
20. -エンドユーザー企業担当者名
21. -エンドユーザー企業郵便番号
22. -エンドユーザー企業都道府県
23. -エンドユーザー企業住所1
24. -エンドユーザー企業住所2
25. -エンドユーザー企業電話番号
26. -エンドユーザー企業メールアドレス
27. -送付先企業名
28. -送付先企業部署名
29. -送付先企業担当者名
30. -送付先企業郵便番号
31. -送付先企業都道府県
32. -送付先企業住所1
33. -送付先企業住所2
34. -送付先企業電話番号
35. -送付先企業メールアドレス
36. -代替機

【keyの推測】
製品型名は最初の１ワードで良いです。
依頼者企業は画面の中段にあって、エンドユーザー情報の上にあります。
OCRした情報のkeyが一致しなくても出来るだけ適合するkeyを上記から探して当て嵌めてください。
「営業所」が付くワードは部署名に含めてください
【住所の読み取りルール（最重要）】
帳票の住所欄は通常「都道府県」「住所1」「住所2」の3欄です。必ず3欄とも読み取り、住所2（ビル名・部屋番号など）を絶対に落とさないこと。
例:
- 都道府県: 東京都
- 住所1: 文京区後楽1-4-25
- 住所2: 日教販ビル
この場合は
- 依頼者企業都道府県 = "東京都"
- 依頼者企業住所1 = "文京区後楽1-4-25"
- 依頼者企業住所2 = "日教販ビル"
とする。

【住所の正規化ルール（最終判定）】
最終的にシステムへ渡す意味としては
- 都道府県 = 都道府県名のみ
- 住所1+住所2 = 都道府県以降のすべての住所情報
である。記入者が欄を間違えても、内容を見て正しく振り分けること。

具体例:
1. 正常記入
   都道府県=東京都 / 住所1=文京区後楽1-4-25 / 住所2=日教販ビル
   → 都道府県="東京都", 住所1="文京区後楽1-4-25", 住所2="日教販ビル"
2. 住所1に都道府県から書いてある場合
   都道府県=(空または重複) / 住所1=東京都文京区後楽1-4-25 / 住所2=日教販ビル
   → 都道府県="東京都", 住所1="文京区後楽1-4-25", 住所2="日教販ビル"
3. 都道府県欄に市区町村まで入っている場合
   都道府県=東京都文京区後楽1-4-25 / 住所1=(空) / 住所2=日教販ビル
   → 都道府県="東京都", 住所1="文京区後楽1-4-25", 住所2="日教販ビル"
4. 住所2が空で住所1に全部ある場合
   → 都道府県を分離し、残りは住所1へ。住所2は null

追加ルール:
- 「都道府県」キーには都道府県名のみ（東京都/北海道/大阪府/京都府/○○県）
- 「住所1」「住所2」には都道府県名を含めない
- 「住所2」はビル名・館名・階・号室・マンション名などを必ず入れる。空欄でなければ null にしない
- 郵便番号から分かる住所と突き合わせ、抜けや誤読を補正する
- エンドユーザー・送付先も同じルールで処理する
製品型名に「ngh」と付いていたら「exact」として下さい
製品型名の先頭が「11」になっていたら「11」の代わりに「i1」として下さい
-依頼者企業担当者名と-エンドユーザー企業担当者名と-送付先企業担当者名は苗字と名前の間に1文字分のスペースを入れて下さい。
【記載情報のグループ】
情報のグループは
1. エックスライトに関する情報
2．製品に関する情報
3．作業内容・保証・保守・提出書類に関する情報
4．依頼者企業情報
5．エンドユーザー情報
6．送付先情報
という構成になります。

【不要な情報】
1．エックスライトに関する情報は必要ありません。
フリガナの情報は要りません。
「exact/500アパーチャ径」の下にある情報は不要です。

【booleanの認識】
-修理
-再校正
-保証期間内
-保証期間外
-保守契約有
-A2LA検査成績書類
上記の6項目については各キーワードの最初の文字の直ぐ左側に小さい四角があるので、黒い四角がある場合または四角にチェックマークがついていればtrueと判断して下さい。

【代替機の認識】
「代替機のご希望」というワードの下に
-希望する
-希望しない
以上の２つの各キーワードの最初の文字の直ぐ左側に小さい四角があるので、黒い四角がある場合またはチェックマークが入っている場合は「代替機：希望する」または「代替機：希望しない」という回答、どちらにも黒い四角かチェックマークがなければ「代替機：希望しない」という回答にして下さい。
以上を踏まえて抽出して下さい。
PROMPT;
    }
}
