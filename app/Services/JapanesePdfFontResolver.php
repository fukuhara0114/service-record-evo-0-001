<?php

namespace App\Services;

/**
 * PDF 用日本語フォント解決。
 * storage/fonts に置いたフォントを優先する（Git 管理・本番デプロイ対象）。
 */
class JapanesePdfFontResolver
{
    /** @var array<string, string> */
    private static array $cache = [];

    public function resolve(bool $bold = false): string
    {
        $cacheKey = $bold ? 'bold' : 'regular';
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        foreach ($this->storageFontCandidates($bold) as $path) {
            if (! is_file($path)) {
                continue;
            }

            try {
                $name = \TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 32);
                if (is_string($name) && $name !== '') {
                    return self::$cache[$cacheKey] = $name;
                }
            } catch (\Throwable) {
                // try next
            }
        }

        $fontDir = defined('K_PATH_FONTS')
            ? K_PATH_FONTS
            : (base_path('vendor/tecnickcom/tcpdf/fonts').DIRECTORY_SEPARATOR);

        $tcpdfFonts = $bold
            ? ['bizudgothicb', 'bizudgothicr', 'yugothr', 'ipag']
            : ['bizudgothicr', 'bizudgothicb', 'yugothr', 'ipag'];

        foreach ($tcpdfFonts as $name) {
            if (is_file($fontDir.$name.'.php')) {
                return self::$cache[$cacheKey] = $name;
            }
        }

        return self::$cache[$cacheKey] = 'cid0jp';
    }

    /**
     * @return list<string>
     */
    private function storageFontCandidates(bool $bold): array
    {
        $primary = $bold
            ? ['BIZ-UDGothicB.ttf', 'BIZ-UDGothicR.ttf']
            : ['BIZ-UDGothicR.ttf', 'BIZ-UDGothicB.ttf'];

        $fallback = [
            'YuGothR.ttf',
            'NotoSansCJKjp-Regular.otf',
            'SourceHanSansJP-Regular.otf',
        ];

        return array_map(
            static fn (string $file) => storage_path('fonts/'.$file),
            array_merge($primary, $fallback),
        );
    }
}
