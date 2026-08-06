import { apiFetch } from '@/utils/apiFetch'

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

/**
 * PDF/画像取込ジョブを起動する（画面オープン時トリガー用）。
 * @returns {Promise<{ ok: boolean, status: number, message: string, data?: object }>}
 */
export async function startFileImport({ appBaseUrl, associatedID = null } = {}) {
    const base = String(appBaseUrl || '').replace(/\/$/, '')
    const url = `${base}/servicerecord/file-import/start`

    const body = {}
    if (associatedID != null && associatedID !== '') {
        body.associatedID = Number(associatedID)
    }

    try {
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        })

        if (!result) {
            return { ok: false, status: 401, message: '認証が必要です。' }
        }

        const { response, data } = result
        const message = data.message || (
            response.status === 423
                ? '現在他のユーザーが処理中です'
                : `取込開始に失敗しました。（HTTP ${response.status}）`
        )

        return {
            ok: response.ok,
            status: response.status,
            message,
            data,
        }
    } catch (e) {
        return {
            ok: false,
            status: 0,
            message: e.message || '取込開始に失敗しました。',
        }
    }
}
