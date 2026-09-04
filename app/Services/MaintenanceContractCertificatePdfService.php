<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * 保守サービス保証書 PDF
 * テンプレート: maintenance_contract.pdf（PdfTemplatePathResolver で配置差を吸収）
 */
class MaintenanceContractCertificatePdfService
{
    /**
     * @var array<string, array{x:float,y:float,size?:float,color?:array{0:int,1:int,2:int},max_w?:float}>
     */
    private const POSITIONS = [
        // No. の直後（左へ 5mm。高さは維持）
        'ref_number' => ['x' => 167.5, 'y' => 18.1, 'size' => 10],
        // オレンジ帯（白文字）— 下へ 2mm
        'instrument_name' => ['x' => 47.5, 'y' => 44.8, 'size' => 13, 'color' => [255, 255, 255], 'max_w' => 72.0],
        'sn' => ['x' => 138.5, 'y' => 44.8, 'size' => 13, 'color' => [255, 255, 255], 'max_w' => 52.0],
        // 保守サービス期間（右へ +8mm。高さは維持）
        'period' => ['x' => 66.0, 'y' => 55.0, 'size' => 10, 'max_w' => 122.0],
        // エンドユーザー様情報 — 会社名・部署名は上へ 2mm
        'end_user' => ['x' => 66.0, 'y' => 65.5, 'size' => 10, 'max_w' => 122.0],
        'end_user_depart' => ['x' => 66.0, 'y' => 71.5, 'size' => 10, 'max_w' => 122.0],
        'end_user_address' => ['x' => 66.0, 'y' => 79.5, 'size' => 9, 'max_w' => 122.0],
        // 電話番号は下へ 3mm
        'end_user_phone' => ['x' => 66.0, 'y' => 88.5, 'size' => 10, 'max_w' => 122.0],
        // 販売店様情報（期間と同じだけ右へ +8mm、上へ -3.5mm）
        'dealer' => ['x' => 66.0, 'y' => 96.5, 'size' => 10, 'max_w' => 122.0],
        'branch' => ['x' => 66.0, 'y' => 104.5, 'size' => 10, 'max_w' => 122.0],
        // ご担当 / TEL は正しい位置のまま
        'contact' => ['x' => 88.0, 'y' => 112.2, 'size' => 10, 'max_w' => 95.0],
        'phone' => ['x' => 88.0, 'y' => 118.0, 'size' => 10, 'max_w' => 95.0],
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function generate(array $data): string
    {
        $template = $this->resolveTemplatePath();
        if (! is_file($template)) {
            throw new \RuntimeException('保守サービス保証書テンプレートが見つかりません: maintenance_contract.pdf');
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
        $pdf->SetTitle('保守サービス保証書');

        $fontName = app(JapanesePdfFontResolver::class)->resolve(true);

        $pdf->setSourceFile($template);
        $tpl = $pdf->importPage(1);
        $size = $pdf->getTemplatesize($tpl);
        $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);

        foreach (self::POSITIONS as $key => $pos) {
            $text = (string) ($fields[$key] ?? '');
            if ($text === '') {
                continue;
            }

            $color = $pos['color'] ?? [0, 0, 0];
            $pdf->SetTextColor($color[0], $color[1], $color[2]);

            $sizePt = (float) ($pos['size'] ?? 10);
            $maxW = isset($pos['max_w']) ? (float) $pos['max_w'] : 0.0;
            if ($maxW > 0) {
                while ($sizePt > 6.0) {
                    $pdf->SetFont($fontName, 'B', $sizePt, '', true);
                    if ($pdf->GetStringWidth($text) <= $maxW) {
                        break;
                    }
                    $sizePt -= 0.5;
                }
            }
            $pdf->SetFont($fontName, 'B', $sizePt, '', true);
            $pdf->Text($pos['x'], $pos['y'], $text);
        }

        return $pdf->Output('', 'S');
    }

    /**
     * ルートディレクトリが変わっても参照できるよう複数候補から解決する。
     */
    public function resolveTemplatePath(): string
    {
        return app(PdfTemplatePathResolver::class)->resolve('maintenance_contract');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function buildFields(array $data): array
    {
        $ref = trim((string) ($data['RefNumber'] ?? $data['ref_number'] ?? ''));
        $instrument = trim((string) ($data['instrumentName'] ?? ''));
        $sn = trim((string) ($data['SN'] ?? ''));
        $period = $this->formatPeriod($data['startDate'] ?? null, $data['expireDate'] ?? null);

        $endUser = trim((string) ($data['endUser'] ?? ''));
        $endUserDepart = trim((string) ($data['endUser_depart'] ?? ''));
        $endUserAddress = $this->formatAddress(trim((string) ($data['endUser_address'] ?? '')));
        $endUserPhone = trim((string) ($data['endUser_phone'] ?? ''));

        $dealer = trim((string) ($data['dealer'] ?? ''));
        $branch = trim((string) ($data['branch'] ?? ''));
        $contact = $this->formatContactHonorific(trim((string) ($data['contact'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));

        return [
            'ref_number' => $ref,
            'instrument_name' => $instrument,
            'sn' => $sn,
            'period' => $period,
            'end_user' => $endUser,
            'end_user_depart' => $endUserDepart,
            'end_user_address' => $endUserAddress,
            'end_user_phone' => $endUserPhone,
            'dealer' => $dealer,
            'branch' => $branch,
            'contact' => $contact,
            'phone' => $phone,
        ];
    }

    private function formatPeriod(mixed $start, mixed $end): string
    {
        $from = $this->formatDateSlash($start);
        $to = $this->formatDateSlash($end);
        if ($from === '' && $to === '') {
            return '';
        }
        if ($from === '') {
            return $to;
        }
        if ($to === '') {
            return $from;
        }

        return $from.' - '.$to;
    }

    private function formatDateSlash(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y/m/d');
        }

        $raw = trim((string) $value);
        if (preg_match('/^(\d{4})[-\/](\d{2})[-\/](\d{2})/', $raw, $m) !== 1) {
            return '';
        }
        if ((int) $m[1] < 1901) {
            return '';
        }

        return $m[1].'/'.$m[2].'/'.$m[3];
    }

    private function formatAddress(string $address): string
    {
        if ($address === '') {
            return '';
        }
        if (str_starts_with($address, '〒') || str_starts_with($address, '〒')) {
            return $address;
        }

        // 郵便番号から始まる場合も 〒 を付ける
        return '〒'.$address;
    }

    private function formatContactHonorific(string $contact): string
    {
        if ($contact === '') {
            return '';
        }
        if (str_ends_with($contact, '様')) {
            return $contact;
        }

        return $contact.' 様';
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
        $pdfPath = $dir.DIRECTORY_SEPARATOR.'mc_preview_'.$id.'.pdf';
        $pngPath = $dir.DIRECTORY_SEPARATOR.'mc_preview_'.$id.'.png';
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
}
