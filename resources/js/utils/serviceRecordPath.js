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
