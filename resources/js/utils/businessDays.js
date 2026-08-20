import HolidayJp from '@holiday-jp/holiday_jp'

export function tokyoTodayYmd() {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date())
}

export function normalizeDateYmd(value) {
    if (value == null || value === '') return null
    const text = String(value).trim()
    const match = text.match(/^(\d{4}-\d{2}-\d{2})/)
    return match ? match[1] : null
}

export function addDaysYmd(ymd, days) {
    const [y, m, d] = ymd.split('-').map(Number)
    const utc = new Date(Date.UTC(y, m - 1, d + days))
    const pad = (n) => String(n).padStart(2, '0')
    return `${utc.getUTCFullYear()}-${pad(utc.getUTCMonth() + 1)}-${pad(utc.getUTCDate())}`
}

export function isNonBusinessDayYmd(ymd) {
    const [y, m, d] = ymd.split('-').map(Number)
    const date = new Date(y, m - 1, d, 12, 0, 0)
    const day = date.getDay()
    if (day === 0 || day === 6) return true
    return HolidayJp.isHoliday(date)
}

/**
 * orderDate から work_completion_date までの営業日数（土日祝除外）。
 * orderDate の翌日〜 work_completion_date（両端のうち終了日を含む）を数える。
 * いずれかが null の場合は null。
 */
export function countBusinessDaysBetween(fromYmd, toYmd) {
    const start = normalizeDateYmd(fromYmd)
    const end = normalizeDateYmd(toYmd)
    if (!start || !end) return null
    if (start > end) return 0

    let count = 0
    let current = addDaysYmd(start, 1)
    while (current <= end) {
        if (!isNonBusinessDayYmd(current)) count++
        current = addDaysYmd(current, 1)
    }
    return count
}
