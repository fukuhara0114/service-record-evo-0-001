/**
 * Laravel の /servicerecord ルートまでのベースパスを返す。
 * 例:
 *   /servicerecord/administrator → /servicerecord
 *   /dev/stage001/service-record-evo-0-001/servicerecord/shipping-calendar
 *     → /dev/stage001/service-record-evo-0-001/servicerecord
 */
export function getServiceRecordBasePath() {
    if (typeof window === 'undefined') return '/servicerecord'
    const path = window.location.pathname || ''
    const marker = '/servicerecord'
    const idx = path.indexOf(marker)
    if (idx === -1) return marker
    return path.slice(0, idx + marker.length)
}

export function serviceRecordUrl(suffix = '') {
    const base = getServiceRecordBasePath()
    const path = suffix.startsWith('/') ? suffix : (suffix ? `/${suffix}` : '')
    return `${window.location.origin}${base}${path}`
}

/**
 * 貸出 / waiting_list 詳細 URL（サブディレクトリ配備でも現在のパス基準で組み立てる）
 * @param {string|number} id attachedloaners.id または orderID
 * @param {Record<string, string|number|boolean|null|undefined>} [query]
 */
export function loanerDetailUrl(id, query = {}) {
    const url = new URL(serviceRecordUrl(`loaner/detail/${id}`))
    Object.entries(query || {}).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') return
        url.searchParams.set(key, String(value))
    })
    return url.href
}
