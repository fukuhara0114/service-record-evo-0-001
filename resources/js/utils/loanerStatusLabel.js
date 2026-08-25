/**
 * statusmaster_loaner の表記名。status_new のみを参照する。
 *
 * @param {{ status_new?: string|null }|null|undefined} row
 * @returns {string}
 */
export function loanerStatusLabel(row) {
    if (!row) return ''
    return String(row.status_new ?? '').trim()
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
