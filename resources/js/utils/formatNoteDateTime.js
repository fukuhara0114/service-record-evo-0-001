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

/**
 * lastEditDate は app timezone UTC の now() で保存される。
 * 一覧プレビューでは Asia/Tokyo の壁時計で表示する。
 */
export function formatLastEditDateTime(value) {
    if (value == null || value === '') return '—'
    const text = String(value).trim()
    if (!text || text.startsWith('0000-00-00')) return '—'

    const formatTokyo = (date) => {
        if (Number.isNaN(date.getTime())) return null
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Tokyo',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(date)
        const get = (type) => parts.find((part) => part.type === type)?.value ?? ''
        return `${get('year')}-${get('month')}-${get('day')} ${get('hour')}:${get('minute')}:${get('second')}`
    }

    if (/^\d{4}-\d{2}-\d{2}T/.test(text) || /[Zz]$/.test(text) || /[+-]\d{2}:\d{2}$/.test(text)) {
        const formatted = formatTokyo(new Date(text))
        if (formatted) return formatted
    }

    const match = text.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/)
    if (match) {
        const formatted = formatTokyo(new Date(`${match[1]}T${match[2]}:${match[3]}:${match[4] || '00'}Z`))
        if (formatted) return formatted
    }

    return formatNoteDateTime(value)
}
