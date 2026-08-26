/**
 * Notes の whenWrote をタイムゾーン変換せず壁時計のまま表示する。
 * Laravel が UTC(Z) 付き ISO で返すとブラウザが +9h してしまうため。
 */
export function formatNoteDateTime(value) {
    if (!value) return '—'
    const text = String(value).trim()
    const match = text.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/)
    if (match) {
        return `${match[1]} ${match[2]}:${match[3]}`
    }
    const date = new Date(text)
    if (Number.isNaN(date.getTime())) return text
    const pad = (n) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export function noteWroteTimestamp(value) {
    if (!value) return 0
    const text = String(value).trim().replace(' ', 'T')
    const naive = text.replace(/[Zz]$/, '').replace(/[+-]\d{2}:\d{2}$/, '')
    const time = new Date(naive).getTime()
    return Number.isNaN(time) ? 0 : time
}
