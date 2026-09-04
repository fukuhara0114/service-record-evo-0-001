/**
 * 郵便番号ユーティリティ。
 * API 照合は数字のみ、画面表示はハイフン（xxx-xxxx）を維持する。
 */

/** 数字以外を除去（住所検索 API 用） */
export function zipcodeDigits(value) {
    return String(value ?? '').replace(/\D/g, '')
}

/**
 * 表示用に整形。
 * - 7桁そろった場合は xxx-xxxx
 * - 未完成の入力は数字とハイフンのみ残す（既存ハイフンを消さない）
 */
export function formatZipcodeDisplay(value) {
    const raw = String(value ?? '')
    const digits = zipcodeDigits(raw)
    if (digits.length === 7) {
        return `${digits.slice(0, 3)}-${digits.slice(3)}`
    }
    // 入力途中: 先頭の数字・ハイフンを維持（連続ハイフンは1つに）
    return raw
        .replace(/[^\d-]/g, '')
        .replace(/-{2,}/g, '-')
}

export function isCompleteZipcode(value) {
    return zipcodeDigits(value).length === 7
}
