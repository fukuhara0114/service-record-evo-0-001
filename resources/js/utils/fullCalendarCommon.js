import HolidayJp from '@holiday-jp/holiday_jp'

/** ローリング月表示（今日を含む週＝先頭週、日曜始まり） */
export const ROLLING_MONTH_VIEW = 'dayGridRollingMonth'

export const rollingMonthViewConfig = {
    type: 'dayGrid',
    duration: { weeks: 5 },
    buttonText: '月',
    titleFormat: { year: 'numeric', month: 'short', day: 'numeric' },
}

/**
 * 土日・祝日のセルクラス。
 * @param {{ date: Date }} arg
 * @returns {string[]}
 */
export function fullCalendarDayCellClassNames(arg) {
    const date = arg?.date
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return []

    const classes = []
    const day = date.getDay()
    if (day === 0) classes.push('fc-day-sun-tint')
    if (day === 6) classes.push('fc-day-sat-tint')
    if (HolidayJp.isHoliday(date)) classes.push('fc-day-holiday-tint')
    return classes
}

/**
 * 既存の dayCellClassNames と土日祝クラスを合成する。
 * @param {(arg: object) => string[]|string|undefined|null} existing
 */
export function mergeDayCellClassNames(existing) {
    return (arg) => {
        const base = typeof existing === 'function' ? existing(arg) : []
        const list = Array.isArray(base) ? base : (base ? [base] : [])
        return [...list, ...fullCalendarDayCellClassNames(arg)]
    }
}

/**
 * 月表示のセル背景ダブルクリックで日表示へ切り替える。
 * @param {{ view?: { type?: string, calendar?: { changeView?: Function } }, jsEvent?: MouseEvent, dateStr?: string }} info
 * @param {string} dayViewName
 */
export function handleMonthCellDoubleClickToDayView(info, dayViewName = 'dayGridDay') {
    if (info?.view?.type !== ROLLING_MONTH_VIEW) return
    if ((info?.jsEvent?.detail || 0) < 2) return
    if (!info?.dateStr) return
    info.view.calendar?.changeView?.(dayViewName, info.dateStr)
}

/** カレンダー共通オプションの一部 */
export function fullCalendarCommonOptions(extra = {}) {
    const existingDayCell = extra.dayCellClassNames
    const views = {
        ...(extra.views || {}),
        [ROLLING_MONTH_VIEW]: {
            ...rollingMonthViewConfig,
            ...(extra.views?.[ROLLING_MONTH_VIEW] || {}),
        },
    }

    return {
        locale: 'ja',
        firstDay: 0,
        ...extra,
        views,
        dayCellClassNames: mergeDayCellClassNames(existingDayCell),
    }
}
