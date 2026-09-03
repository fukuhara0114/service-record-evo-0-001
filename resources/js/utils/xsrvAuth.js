/**
 * xsrv 認証（Sync SM / OCR などの起動前チェック）。
 * 401/419 はセッション切れ。それ以外の失敗は XsrvAuthDeniedError。
 */

export class XsrvAuthDeniedError extends Error {
    constructor(message) {
        super(message || 'このシステムを利用する権限がありません、または有効期限が切れています。')
        this.name = 'XsrvAuthDeniedError'
        this.code = 'XSRV_AUTH_DENIED'
    }
}

/**
 * @param {string} authorizeUrl 例: `${appBaseUrl}/servicerecord/smsync/authorize`
 */
export async function ensureXsrvAuth(authorizeUrl) {
    const response = await fetch(authorizeUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    const data = await response.json().catch(() => ({}))

    if (response.status === 401 || response.status === 419) {
        throw new Error(data?.message || 'セッションが切れました。再ログインしてください。')
    }

    if (!response.ok || data?.status !== 'success') {
        throw new XsrvAuthDeniedError(
            data?.message
            || 'このシステムを利用する権限がありません、または有効期限が切れています。',
        )
    }

    return true
}

export function isXsrvAuthDenied(error) {
    return error instanceof XsrvAuthDeniedError || error?.code === 'XSRV_AUTH_DENIED' || error?.message === 'XSRV_AUTH_DENIED'
}
