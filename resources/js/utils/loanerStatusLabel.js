/**
 * statusmaster_loaner の表記名。status_new を優先し、空なら status。
 *
 * @param {{ status?: string|null, status_new?: string|null }|null|undefined} row
 * @returns {string}
 */
export function loanerStatusLabel(row) {
    if (!row) return ''
    const statusNew = String(row.status_new ?? '').trim()
    if (statusNew) return statusNew
    return String(row.status ?? '').trim()
}

/**
 * @param {{ processID_new?: number|string|null, status?: string|null, status_new?: string|null }|null|undefined} row
 * @returns {string}
 */
export function loanerStatusOptionLabel(row) {
    const label = loanerStatusLabel(row)
    if (!label) return ''
    const id = row?.processID_new
    return id != null && id !== '' ? `${label} (${id})` : label
}
