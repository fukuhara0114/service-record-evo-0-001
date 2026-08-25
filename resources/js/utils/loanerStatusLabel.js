/**
 * statusmaster_loaner の表記名。status_new のみを参照する。
 *
 * @param {{ status_new?: string|null }|null|undefined} row
 * @returns {string}
 */
export function loanerStatusLabel(row) {
    if (!row) return ''
    const value = row.status_new ?? row.statusNew ?? row.STATUS_NEW ?? ''
    return String(value).trim()
}

/**
 * @param {{ processID_new?: number|string|null, status_new?: string|null }|null|undefined} row
 * @returns {string}
 */
export function loanerStatusOptionLabel(row) {
    const label = loanerStatusLabel(row)
    if (!label) return ''
    const id = row?.processID_new
    return id != null && id !== '' ? `${label} (${id})` : label
}
