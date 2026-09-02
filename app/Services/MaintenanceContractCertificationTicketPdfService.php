<?php

namespace App\Services;

use Carbon\Carbon;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * 保守サービス 再校正チケット PDF
 * テンプレート: storage/app/template/certification_ticket.pdf
 */
class MaintenanceContractCertificationTicketPdfService
{
    /**
     * @var array<string, array{x:float,y:float,size?:float,max_w?:float}>
     */
    private const POSITIONS = [
        'ref_number' => ['x' => 35.0, 'y' => 103.5, 'size' => 9],
        'instrument_name' => ['x' => 35.0, 'y' => 109.5, 'size' => 9, 'max_w' => 110.0],
        'sn' => ['x' => 35.0, 'y' => 115.5, 'size' => 9, 'max_w' => 110.0],
        'remarks' => ['x' => 35.0, 'y' => 121.5, 'size' => 8, 'max_w' => 110.0],
        'service_period' => ['x' => 58.0, 'y' => 129.0, 'size' => 8, 'max_w' => 62.0],
        'recert_period' => ['x' => 58.0, 'y' => 134.8, 'size' => 8, 'max_w' => 62.0],
        'dealer' => ['x' => 38.0, 'y' => 145.0, 'size' => 9, 'max_w' => 100.0],
        'branch' => ['x' => 38.0, 'y' => 149.5, 'size' => 9, 'max_w' => 100.0],
        'phone' => ['x' => 42.0, 'y' => 155.0, 'size' => 9, 'max_w' => 100.0],
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function generate(array $data): string
    {
        $template = $this->resolveTemplatePath();
        if (! is_file($template)) {
            throw new \RuntimeException('再校正チケットテンプレートが見つかりません: certification_ticket.pdf');
        }

        $commonFields = $this->buildCommonFields($data);
        $yearPeriods = $this->buildYearPeriods($data['startDate'] ?? null, $data['expireDate'] ?? null);

        $pdf = new Fpdi();
        $pdf->setPageUnit('mm');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('service-record');
        $pdf->SetAuthor('service-record');
        $pdf->SetTitle('保守サービス 再校正チケット');

        $fontName = app(JapanesePdfFontResolver::class)->resolve(true);
        $pdf->setSourceFile($template);
        $tpl = $pdf->importPage(1);
        $size = $pdf->getTemplatesize($tpl);

        foreach ($yearPeriods as $period) {
            $fields = array_merge($commonFields, $period);
            $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);

            foreach (self::POSITIONS as $key => $pos) {
                $text = (string) ($fields[$key] ?? '');
                if ($text === '') {
                    continue;
                }

                $pdf->SetTextColor(0, 0, 0);
                $sizePt = (float) ($pos['size'] ?? 9);
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
        }

        return $pdf->Output('', 'S');
    }

    public function resolveTemplatePath(): string
    {
        return storage_path('app/template/certification_ticket.pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function buildCommonFields(array $data): array
    {
        return [
            'ref_number' => trim((string) ($data['RefNumber'] ?? '')),
            'instrument_name' => trim((string) ($data['instrumentName'] ?? '')),
            'sn' => trim((string) ($data['SN'] ?? '')),
            'remarks' => $this->buildRemarks($data),
            'dealer' => trim((string) ($data['dealer'] ?? '')),
            'branch' => trim((string) ($data['branch'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
        ];
    }

    /**
     * startDate〜expireDate を1年単位に分割し、各年の期間を返す。
     *
     * @return array<int, array{service_period:string,recert_period:string}>
     */
    private function buildYearPeriods(mixed $startDate, mixed $expireDate): array
    {
        $start = $this->parseDate($startDate);
        $end = $this->parseDate($expireDate);

        if ($start === null || $end === null || $start->gt($end)) {
            return [[
                'service_period' => '',
                'recert_period' => '',
            ]];
        }

        $periods = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $yearEnd = $cursor->copy()->addYear()->subDay();
            if ($yearEnd->gt($end)) {
                $yearEnd = $end->copy();
            }

            $recertStart = $yearEnd->copy()->subMonths(3);
            if ($recertStart->lt($cursor)) {
                $recertStart = $cursor->copy();
            }

            $periods[] = [
                'service_period' => $this->formatPeriodRange($cursor, $yearEnd),
                'recert_period' => $this->formatPeriodRange($recertStart, $yearEnd),
            ];

            $cursor = $yearEnd->copy()->addDay();
        }

        return $periods !== [] ? $periods : [[
            'service_period' => '',
            'recert_period' => '',
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildRemarks(array $data): string
    {
        $description = trim((string) ($data['description'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        $additional = trim((string) ($data['additional_information'] ?? ''));
        if ($additional !== '') {
            return $additional;
        }

        return trim((string) ($data['contractTypeDescription'] ?? ''));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $raw = trim((string) $value);
        if ($raw === '' || preg_match('/^0{4}-0{2}-0{2}/', $raw) === 1) {
            return null;
        }

        try {
            $date = Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ((int) $date->format('Y') < 1901) {
            return null;
        }

        return $date;
    }

    private function formatPeriodRange(Carbon $from, Carbon $to): string
    {
        return $from->format('Y/m/d').' ～ '.$to->format('Y/m/d');
    }

    public function pdfToPng(string $pdfBinary): string
    {
        $pages = $this->pdfToPngPages($pdfBinary);
        if ($pages === []) {
            throw new \RuntimeException('プレビュー PNG の読み込みに失敗しました。');
        }

        return $pages[0];
    }

    /**
     * PDF 全ページを PNG に変換（プレビュー用）
     *
     * @return list<string>
     */
    public function pdfToPngPages(string $pdfBinary): array
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
        $pdfPath = $dir.DIRECTORY_SEPARATOR.'ct_preview_'.$id.'.pdf';
        $pngPattern = $dir.DIRECTORY_SEPARATOR.'ct_preview_'.$id.'-%d.png';
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
                '-sOutputFile='.$pngPattern,
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

            $pngPaths = glob($dir.DIRECTORY_SEPARATOR.'ct_preview_'.$id.'-*.png') ?: [];
            natsort($pngPaths);

            if ($code !== 0 || $pngPaths === []) {
                throw new \RuntimeException('Ghostscript 変換に失敗しました: '.$err);
            }

            $pages = [];
            foreach ($pngPaths as $pngPath) {
                $png = file_get_contents($pngPath);
                if ($png === false || $png === '') {
                    throw new \RuntimeException('プレビュー PNG の読み込みに失敗しました。');
                }
                $pages[] = $png;
            }

            return $pages;
        } finally {
            @unlink($pdfPath);
            foreach (glob($dir.DIRECTORY_SEPARATOR.'ct_preview_'.$id.'-*.png') ?: [] as $pngPath) {
                @unlink($pngPath);
            }
        }
    }
}
