<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * 代替機申込書 PDF
 * 座標・印字は現行 edit_pdf2.php（mm + Text）に準拠。
 */
class LoanerApplicationPdfService
{
    /**
     * @var array<string, array{x:float,y:float,size?:float,max_w?:float}>
     */
    private const POSITIONS = [
        'fax_company' => ['x' => 30.0, 'y' => 35.0, 'size' => 8],
        'fax_contactPerson' => ['x' => 44.0, 'y' => 40.0, 'size' => 8],
        'fax_phone' => ['x' => 44.0, 'y' => 44.9, 'size' => 8],
        'fax_fax' => ['x' => 44.0, 'y' => 49.9, 'size' => 8],

        'date_issue' => ['x' => 50.0, 'y' => 68.5, 'size' => 8],

        '1-1' => ['x' => 35.0, 'y' => 87.0, 'size' => 8],
        '1-2' => ['x' => 62.0, 'y' => 87.0, 'size' => 5.5, 'max_w' => 32.0],
        '1-3' => ['x' => 96.0, 'y' => 87.0, 'size' => 8],
        '1-4' => ['x' => 130.0, 'y' => 87.0, 'size' => 8],
        '1-5' => ['x' => 167.0, 'y' => 87.0, 'size' => 8],

        'date_from' => ['x' => 44.0, 'y' => 113.0, 'size' => 8],
        'date_to' => ['x' => 120.0, 'y' => 113.0, 'size' => 8],
        'price' => ['x' => 95.0, 'y' => 123.0, 'size' => 8],

        'dealer_name' => ['x' => 60.0, 'y' => 180.8, 'size' => 8],
        'dealer_depart' => ['x' => 60.0, 'y' => 186.9, 'size' => 8],
        'dealer_contactPerson' => ['x' => 60.0, 'y' => 192.5, 'size' => 8],
        'dealer_zip' => ['x' => 60.0, 'y' => 198.2, 'size' => 8],
        'dealer_address1' => ['x' => 90.0, 'y' => 198.2, 'size' => 8],
        'dealer_address2' => ['x' => 60.0, 'y' => 204.2, 'size' => 8],
        'dealer_phone' => ['x' => 60.0, 'y' => 210.0, 'size' => 8],
        'dealer_fax' => ['x' => 112.0, 'y' => 210.0, 'size' => 8],

        'user_name' => ['x' => 75.0, 'y' => 223.1, 'size' => 8],
        'user_depart' => ['x' => 75.0, 'y' => 226.8, 'size' => 8],
        'user_contactPerson' => ['x' => 75.0, 'y' => 232.2, 'size' => 8],
        'user_zip' => ['x' => 75.0, 'y' => 237.8, 'size' => 8],
        'user_address1' => ['x' => 105.0, 'y' => 237.8, 'size' => 8],
        'user_address2' => ['x' => 75.0, 'y' => 243.8, 'size' => 8],
        'user_phone' => ['x' => 60.0, 'y' => 250.1, 'size' => 8],
        'user_fax' => ['x' => 132.0, 'y' => 250.1, 'size' => 8],

        // 修理機材 SN（enduser_SN）
        'repair_sn' => ['x' => 52.0, 'y' => 270.1, 'size' => 13],
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function generate(array $data): string
    {
        $template = storage_path('app/template/template_loaner.pdf');
        if (! is_file($template)) {
            throw new \RuntimeException('申込書テンプレートが見つかりません: template_loaner.pdf');
        }

        $fields = $this->buildFields($data);

        $pdf = new Fpdi();
        $pdf->setPageUnit('mm');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('service-record');
        $pdf->SetAuthor('service-record');
        $pdf->SetTitle('代替機申込書');
        $pdf->SetTextColor(0, 0, 0);

        $fontName = $this->resolveJapaneseFont($pdf, true);
        $pdf->SetFont($fontName, 'B', 8, '', true);

        $pdf->setSourceFile($template);
        $tpl = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        foreach (self::POSITIONS as $key => $pos) {
            $text = (string) ($fields[$key] ?? '');
            if ($text === '') {
                continue;
            }
            $size = (float) ($pos['size'] ?? 8);
            if ($key === '1-2') {
                // 文字数に応じて基準サイズを上げる（20文字以上=現状 5.5）
                $len = mb_strlen($text);
                if ($len >= 20) {
                    $size = 5.5;
                } elseif ($len >= 15) {
                    $size = 6.5; // +1
                } elseif ($len >= 10) {
                    $size = 7.5; // +2
                } else {
                    $size = 8.5; // +3（10文字未満）
                }
            }
            $maxW = isset($pos['max_w']) ? (float) $pos['max_w'] : 0.0;
            if ($maxW > 0) {
                while ($size > 4.0) {
                    $pdf->SetFont($fontName, 'B', $size, '', true);
                    if ($pdf->GetStringWidth($text) <= $maxW) {
                        break;
                    }
                    $size -= 0.5;
                }
                $pdf->SetFont($fontName, 'B', $size, '', true);
            } else {
                $pdf->SetFont($fontName, 'B', $size, '', true);
            }
            $pdf->Text($pos['x'], $pos['y'], $text);
        }

        return $pdf->Output('', 'S');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function buildFields(array $data): array
    {
        $contact = trim((string) ($data['contactPerson'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $fax = trim((string) ($data['fax'] ?? ''));
        $dealer = trim((string) ($data['dealer'] ?? ''));
        $dealerDepart = trim((string) ($data['dealer_depart'] ?? ''));
        $zip = trim((string) ($data['zipcode'] ?? ''));
        $address1 = trim((string) ($data['address1'] ?? ''));
        $address2 = trim((string) ($data['address2'] ?? ''));

        $deliveryCompany = trim((string) ($data['deliveryDestination_company'] ?? ''));
        $deliveryDepart = trim((string) ($data['deliveryDestination_depart'] ?? ''));
        $deliveryPerson = trim((string) ($data['deliveryDestination_contactPerson'] ?? ''));
        $deliveryZip = trim((string) ($data['deliveryDestination_zipcode'] ?? ''));
        $deliveryAddress1 = trim((string) ($data['deliveryDestination_address1'] ?? ''));
        $deliveryAddress2 = trim((string) ($data['deliveryDestination_address2'] ?? ''));
        $deliveryPhone = trim((string) ($data['deliveryDestination_phone'] ?? ''));
        $deliveryFax = trim((string) ($data['deliveryDestination_fax'] ?? ''));

        $manageNum = trim((string) ($data['manageNum'] ?? ''));
        $item = $this->sanitizeItemLabel(trim((string) ($data['item'] ?? '')));
        $sn = trim((string) ($data['SN'] ?? ''));
        $repairSn = trim((string) ($data['enduser_SN'] ?? $data['repairSN'] ?? ''));

        $chargeType = ($data['chargeType'] ?? 'paid') === 'free' ? 'free' : 'paid';
        if ($chargeType === 'free') {
            $price = '0';
            $priceLabel = '¥0';
        } else {
            $price = $this->formatPrice($data['price'] ?? null);
            if ($price === '') {
                $price = '0';
            }
            $priceLabel = '¥'.$price;
        }

        $sentDate = $this->formatDateDash($data['sentDate'] ?? null);

        // 直送先が空なら貸出先を流用（正解PDFと同じ）
        if ($deliveryCompany === '' && $deliveryPerson === '') {
            $deliveryCompany = $dealer;
            $deliveryDepart = $dealerDepart;
            $deliveryPerson = $contact;
            $deliveryZip = $zip;
            $deliveryAddress1 = $address1;
            $deliveryAddress2 = $address2;
            $deliveryPhone = $phone;
            $deliveryFax = $fax;
        }

        return [
            // Email送付先: 会社名 / 担当 / TEL / FAX
            'fax_company' => $dealer,
            'fax_contactPerson' => $contact,
            'fax_phone' => $phone,
            'fax_fax' => $fax,

            'date_issue' => $this->tokyoTodayJp(),

            '1-1' => $manageNum !== '' ? $manageNum : '-',
            '1-2' => $item,
            '1-3' => $manageNum !== '' ? $manageNum : '-',
            '1-4' => $sn,
            '1-5' => $price,

            'date_from' => $sentDate,
            'date_to' => '作業',
            'price' => $priceLabel,

            'dealer_name' => $dealer,
            'dealer_depart' => $dealerDepart,
            'dealer_contactPerson' => $contact,
            'dealer_zip' => $zip,
            'dealer_address1' => $address1,
            'dealer_address2' => $address2,
            'dealer_phone' => $phone,
            'dealer_fax' => $fax,

            'user_name' => $deliveryCompany,
            'user_depart' => $deliveryDepart,
            'user_contactPerson' => $deliveryPerson,
            'user_zip' => $deliveryZip,
            'user_address1' => $deliveryAddress1,
            'user_address2' => $deliveryAddress2,
            'user_phone' => $deliveryPhone,
            'user_fax' => $deliveryFax,

            'repair_sn' => $repairSn,
        ];
    }

    private function resolveJapaneseFont(Fpdi $pdf, bool $bold = false): string
    {
        $tcpdfFonts = $bold
            ? ['bizudgothicb', 'bizudgothicr', 'yugothr', 'ipag']
            : ['bizudgothicr', 'bizudgothicb', 'yugothr', 'ipag'];
        $fontDir = defined('K_PATH_FONTS') ? K_PATH_FONTS : (base_path('vendor/tecnickcom/tcpdf/fonts').DIRECTORY_SEPARATOR);
        foreach ($tcpdfFonts as $name) {
            if (is_file($fontDir.$name.'.php')) {
                return $name;
            }
        }

        $fontCandidates = $bold
            ? [
                storage_path('fonts/BIZ-UDGothicB.ttf'),
                storage_path('fonts/BIZ-UDGothicR.ttf'),
            ]
            : [
                storage_path('fonts/BIZ-UDGothicR.ttf'),
                storage_path('fonts/BIZ-UDGothicB.ttf'),
            ];
        $fontCandidates = array_merge($fontCandidates, [
            storage_path('fonts/YuGothR.ttf'),
            storage_path('fonts/ipag.ttf'),
        ]);

        foreach ($fontCandidates as $path) {
            if (! is_file($path)) {
                continue;
            }
            try {
                $name = \TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 32);
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            } catch (\Throwable) {
                // try next
            }
        }

        return 'cid0jp';
    }

    /**
     * PDF バイナリを 1 ページ PNG に変換（プレビュー用）
     */
    public function pdfToPng(string $pdfBinary): string
    {
        $gs = (string) config('pdf_import.ghostscript_path', '');
        if ($gs === '' || ! is_file($gs)) {
            $fallback = 'C:\\Program Files\\gs\\gs10.07.1\\bin\\gswin64c.exe';
            $gs = is_file($fallback) ? $fallback : '';
        }
        if ($gs === '') {
            throw new \RuntimeException('Ghostscript が見つかりません。プレビュー画像を生成できません。');
        }

        $dir = storage_path('app/temp');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('一時フォルダを作成できません: '.$dir);
        }

        $id = bin2hex(random_bytes(8));
        $pdfPath = $dir.DIRECTORY_SEPARATOR.'loaner_preview_'.$id.'.pdf';
        $pngPath = $dir.DIRECTORY_SEPARATOR.'loaner_preview_'.$id.'.png';
        file_put_contents($pdfPath, $pdfBinary);

        try {
            $cmd = [
                $gs,
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-dQUIET',
                '-sDEVICE=png16m',
                '-r144',
                '-sOutputFile='.$pngPath,
                $pdfPath,
            ];
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell' => true]);
            if (! is_resource($proc)) {
                throw new \RuntimeException('Ghostscript の起動に失敗しました。');
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($proc);
            if ($code !== 0 || ! is_file($pngPath)) {
                throw new \RuntimeException('Ghostscript 変換に失敗しました: '.$err);
            }
            $png = file_get_contents($pngPath);
            if ($png === false || $png === '') {
                throw new \RuntimeException('プレビュー PNG の読み込みに失敗しました。');
            }

            return $png;
        } finally {
            @unlink($pdfPath);
            @unlink($pngPath);
        }
    }

    private function sanitizeItemLabel(string $item): string
    {
        if ($item === '') {
            return '';
        }

        return trim(str_replace('【簿外】', '', $item));
    }

    private function formatDateDash(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $raw = substr((string) $value, 0, 10);
        if (preg_match('/^(\d{4})[-\/](\d{2})[-\/](\d{2})$/', $raw, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }

        return (string) $value;
    }

    private function formatPrice(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (! is_numeric($value)) {
            return (string) $value;
        }

        return number_format((float) $value);
    }

    private function tokyoTodayJp(): string
    {
        $d = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tokyo'));

        return $d->format('Y年n月j日');
    }
}
