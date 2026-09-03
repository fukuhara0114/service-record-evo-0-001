<?php

namespace App\Services;

class XsrvAuthService
{
    /**
     * @return array{ok: bool, message: string, http_status?: int}
     */
    public function check(): array
    {
        $apiUrl = trim((string) config('services.xsrv.auth_url', 'https://xs202381.xsrv.jp/servicerecord/auth.php'));
        $apiKey = trim((string) config('services.xsrv.api_key', ''));

        if ($apiUrl === '' || $apiKey === '') {
            return [
                'ok' => false,
                'message' => '認証設定が不足しています（XSRV_AUTH_URL / XSRV_API_KEY）。',
            ];
        }

        $separator = str_contains($apiUrl, '?') ? '&' : '?';
        $requestUrl = $apiUrl.$separator.'key='.rawurlencode($apiKey);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($requestUrl, false, $context);
        $httpStatus = $this->httpStatusFromResponseHeaders($http_response_header ?? []);

        if ($response === false) {
            return [
                'ok' => false,
                'message' => 'このシステムを利用する権限がありません、または有効期限が切れています。',
                'http_status' => $httpStatus,
            ];
        }

        $result = json_decode($response, true);
        $remoteStatus = is_array($result) ? ($result['status'] ?? null) : null;
        $remoteMessage = is_array($result) ? trim((string) ($result['message'] ?? '')) : '';

        if (
            ($httpStatus !== null && ($httpStatus < 200 || $httpStatus >= 300))
            || $remoteStatus !== 'success'
        ) {
            return [
                'ok' => false,
                'message' => $remoteMessage !== ''
                    ? $remoteMessage
                    : '認証に失敗しました。',
                'http_status' => $httpStatus,
            ];
        }

        return [
            'ok' => true,
            'message' => 'success',
            'http_status' => $httpStatus,
        ];
    }

    /**
     * @param  list<string>  $headers
     */
    private function httpStatusFromResponseHeaders(array $headers): ?int
    {
        if ($headers === [] || ! isset($headers[0])) {
            return null;
        }

        if (preg_match('/\s(\d{3})\s/', (string) $headers[0], $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
