<?php

namespace App\Services\Gmail;

class OutlookDeeplinkExtractor
{
    /**
     * @param  list<string>  $hostNeedles
     * @return list<string>
     */
    public function extract(string $htmlBody, string $textBody, array $hostNeedles): array
    {
        $needles = array_values(array_filter(array_map(
            static fn ($h) => strtolower(trim((string) $h)),
            $hostNeedles
        )));

        if ($needles === []) {
            return [];
        }

        $candidates = [];

        if ($htmlBody !== '') {
            if (preg_match_all('/href\s*=\s*(["\'])(.*?)\1/iu', $htmlBody, $hrefMatches)) {
                foreach ($hrefMatches[2] as $href) {
                    $candidates[] = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }

        $blob = trim($htmlBody."\n".$textBody);
        if ($blob !== '' && preg_match_all('#https?://[^\s<>"\'\)\]]+#iu', $blob, $urlMatches)) {
            foreach ($urlMatches[0] as $url) {
                $candidates[] = rtrim($url, '.,;:)>]');
            }
        }

        $found = [];
        foreach ($candidates as $url) {
            $normalized = $this->normalizeUrl($url);
            if ($normalized === null) {
                continue;
            }
            if (! $this->matchesOutlookHost($normalized, $needles)) {
                continue;
            }
            $found[$normalized] = true;
        }

        return array_keys($found);
    }

    /**
     * @param  list<string>  $needles
     */
    private function matchesOutlookHost(string $url, array $needles): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = rtrim($url, '.,;:)>]');
        if ($url === '') {
            return null;
        }

        // 相対パスや mailto は除外
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }
}
