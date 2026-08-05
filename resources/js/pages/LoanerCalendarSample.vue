<template>
    <div class="calendar-page">
        <div class="page-header">
            <div>
                <h1>貸出カレンダー（サンプル）</h1>
                <p class="subtitle">
                    attachedloaners の予約期間を表示します。loaner status 35=赤 / 40以上=青。
                    予定をドラッグで移動、端をドラッグして期間を変更できます。
                </p>
            </div>
            <div class="header-actions">
                <a :href="homeUrl" class="btn btn-secondary">Home</a>
                <a :href="loanerCreateUrl" class="btn btn-secondary">貸出機登録</a>
                <a :href="adminUrl" class="btn btn-secondary">既存案件一覧</a>
            </div>
        </div>

        <p v-if="error" class="global-error">{{ error }}</p>
        <p v-if="success" class="global-success">{{ success }}</p>

        <div class="toolbar">
            <label class="filter-field">
                <span>loanerID 絞り込み</span>
                <select v-model="selectedLoanerId" @change="reloadEvents">
                    <option value="">すべて</option>
                    <option
                        v-for="loaner in loaners"
                        :key="loaner.loanerID"
                        :value="String(loaner.loanerID)"
                    >
                        {{ loaner.label }}
                    </option>
                </select>
            </label>
            <div class="legend">
                <span class="legend-item status-35">仮予約 (35)</span>
                <span class="legend-item status-40">予約済以降 (40+)</span>
                <span class="legend-item waiting">waiting_list</span>
            </div>
        </div>

        <div class="calendar-shell">
            <FullCalendar ref="calendarRef" :options="calendarOptions" />
        </div>

        <aside v-if="selectedEvent" class="detail-panel">
            <div class="detail-header">
                <h2>予約詳細</h2>
                <button type="button" class="close-btn" @click="selectedEvent = null">×</button>
            </div>
            <dl class="detail-grid">
                <div><dt>title</dt><dd>{{ selectedEvent.title }}</dd></div>
                <div><dt>orderID</dt><dd>{{ selectedEvent.extendedProps?.associatedID ?? '—' }}</dd></div>
                <div><dt>loanerID</dt><dd>{{ selectedEvent.extendedProps?.loanerID ?? '—' }}</dd></div>
                <div><dt>order_type</dt><dd>{{ selectedEvent.extendedProps?.order_type ?? '—' }}</dd></div>
                <div>
                    <dt>status</dt>
                    <dd>
                        <span
                            class="status-chip"
                            :style="{ background: selectedEventColor }"
                        >
                            {{ selectedEvent.extendedProps?.status ?? '—' }}
                        </span>
                    </dd>
                </div>
                <div><dt>assignStatus</dt><dd>{{ selectedEvent.extendedProps?.assignStatus ?? '—' }}</dd></div>
                <div><dt>dealer</dt><dd>{{ selectedEvent.extendedProps?.dealer ?? '—' }}</dd></div>
                <div><dt>dealer_depart</dt><dd>{{ selectedEvent.extendedProps?.dealer_depart ?? '—' }}</dd></div>
                <div><dt>contactPerson</dt><dd>{{ selectedEvent.extendedProps?.contactPerson ?? '—' }}</dd></div>
                <div><dt>SN</dt><dd>{{ selectedEvent.extendedProps?.SN ?? '—' }}</dd></div>
                <div><dt>sentDate</dt><dd>{{ selectedEvent.extendedProps?.sentDate ?? '—' }}</dd></div>
                <div><dt>returnedDate</dt><dd>{{ selectedEvent.extendedProps?.returnedDate ?? '—' }}</dd></div>
                <div><dt>plannedSentDate</dt><dd>{{ selectedEvent.extendedProps?.plannedSentDate ?? '—' }}</dd></div>
                <div><dt>plannedReturnedDate</dt><dd>{{ selectedEvent.extendedProps?.plannedReturnedDate ?? '—' }}</dd></div>
                <div><dt>comment</dt><dd>{{ selectedEvent.extendedProps?.comment ?? '—' }}</dd></div>
            </dl>
            <div class="detail-actions">
                <a
                    v-if="selectedEvent.id"
                    class="btn btn-primary"
                    :href="periodEditUrl(selectedEvent.id)"
                >
                    貸出期間を編集
                </a>
            </div>
        </aside>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'
import { apiFetch } from '@/utils/apiFetch'

defineProps({
    loaners: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const calendarRef = ref(null)
const selectedLoanerId = ref('')
const selectedEvent = ref(null)
const error = ref('')
const success = ref('')
const periodSaving = ref(false)

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)
const loanerCreateUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/loaner/create`)
const selectedEventColor = computed(() =>
    resolveColors(
        selectedEvent.value?.extendedProps?.order_type,
        selectedEvent.value?.extendedProps?.status,
    ).background,
)

function resolveColors(orderType, statusRaw) {
    const status = statusRaw === null || statusRaw === undefined || statusRaw === ''
        ? null
        : Number(statusRaw)

    if (orderType === 'loaner') {
        if (status === 35) {
            return { background: '#dc2626', border: '#b91c1c', className: 'loaner-status-35' }
        }
        if (status !== null && !Number.isNaN(status) && status >= 40) {
            return { background: '#2563eb', border: '#1d4ed8', className: 'loaner-status-40' }
        }
        return { background: '#64748b', border: '#475569', className: 'loaner-status-other' }
    }

    if (orderType === 'waiting_list') {
        return { background: '#d97706', border: '#b45309', className: 'loaner-status-waiting' }
    }

    return { background: '#94a3b8', border: '#64748b', className: 'loaner-status-legacy' }
}

function paintEventElement(el, background, border) {
    if (!el) return

    el.style.setProperty('background-color', background, 'important')
    el.style.setProperty('border-color', border, 'important')
    el.style.setProperty('color', '#ffffff', 'important')
    el.style.setProperty('--fc-event-bg-color', background, 'important')
    el.style.setProperty('--fc-event-border-color', border, 'important')
    el.style.setProperty('--fc-event-text-color', '#ffffff', 'important')

    el.querySelectorAll('.fc-event-main, .fc-event-title, .fc-list-event-dot, .fc-daygrid-event-dot').forEach((node) => {
        node.style.setProperty('background-color', background, 'important')
        node.style.setProperty('border-color', border, 'important')
        node.style.setProperty('color', '#ffffff', 'important')
    })
}

function applyEventColors(info) {
    const colors = resolveColors(
        info.event.extendedProps?.order_type,
        info.event.extendedProps?.status,
    )
    paintEventElement(info.el, colors.background, colors.border)
}

function decorateEvents(events) {
    return (events ?? [])
        .filter((event) => {
            const orderType = event?.extendedProps?.order_type
            return orderType === 'loaner' || orderType === 'waiting_list'
        })
        .map((event) => {
            const colors = resolveColors(
                event?.extendedProps?.order_type,
                event?.extendedProps?.status,
            )
            const status = event?.extendedProps?.status
            const statusPrefix = status != null ? `[${status}] ` : ''

            return {
                ...event,
                title: `${statusPrefix}${event.title || ''}`.trim(),
                color: colors.background,
                backgroundColor: colors.background,
                borderColor: colors.border,
                textColor: '#ffffff',
                classNames: [colors.className, ...(event.classNames ?? [])],
                display: 'block',
            }
        })
}

const calendarOptions = {
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'ja',
    height: '100%',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek',
    },
    buttonText: {
        today: '今日',
        month: '月',
        week: '週',
        list: 'リスト',
    },
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    selectable: false,
    dayMaxEvents: true,
    eventDisplay: 'block',
    events: fetchEvents,
    eventClick: handleEventClick,
    eventDrop: handleEventDropOrResize,
    eventResize: handleEventDropOrResize,
    eventDidMount: applyEventColors,
    eventContent(arg) {
        const colors = resolveColors(
            arg.event.extendedProps?.order_type,
            arg.event.extendedProps?.status,
        )
        const title = arg.event.title || ''
        return {
            html: `<div class="loaner-event-chip" style="background:${colors.background};border-color:${colors.border};">${title}</div>`,
        }
    },
}

async function fetchEvents(info, successCallback, failureCallback) {
    error.value = ''
    try {
        const params = new URLSearchParams({
            start: info.startStr.slice(0, 10),
            end: info.endStr.slice(0, 10),
        })
        if (selectedLoanerId.value) {
            params.set('loanerID', selectedLoanerId.value)
        }

        const url = `${page.props.appBaseUrl}/servicerecord/loaner/calendar/events?${params.toString()}`
        const result = await apiFetch(url)
        if (!result) {
            successCallback([])
            return
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `イベント取得に失敗しました。（HTTP ${response.status}）`)
        }

        successCallback(decorateEvents(data.events ?? []))
    } catch (e) {
        error.value = e.message || 'イベント取得に失敗しました。'
        failureCallback(e)
    }
}

function handleEventClick(clickInfo) {
    selectedEvent.value = {
        id: clickInfo.event.id,
        title: clickInfo.event.title,
        extendedProps: { ...clickInfo.event.extendedProps },
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function toYmd(value) {
    if (!value) return null
    if (typeof value === 'string') return value.slice(0, 10)
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        const y = value.getFullYear()
        const m = String(value.getMonth() + 1).padStart(2, '0')
        const d = String(value.getDate()).padStart(2, '0')
        return `${y}-${m}-${d}`
    }
    return null
}

function addDaysYmd(ymd, days) {
    const base = toYmd(ymd)
    if (!base) return null
    const date = new Date(`${base}T12:00:00`)
    date.setDate(date.getDate() + days)
    return toYmd(date)
}

/** FullCalendar allDay の end は exclusive → DB の inclusive 終了日へ変換 */
function resolvePlannedDatesFromEvent(event) {
    const plannedSentDate = toYmd(event.startStr || event.start)
    if (!plannedSentDate) {
        return null
    }

    const exclusiveEnd = toYmd(event.endStr || event.end)
    const plannedReturnedDate = exclusiveEnd
        ? addDaysYmd(exclusiveEnd, -1)
        : plannedSentDate

    if (!plannedReturnedDate || plannedReturnedDate < plannedSentDate) {
        return null
    }

    return { plannedSentDate, plannedReturnedDate }
}

async function handleEventDropOrResize(changeInfo) {
    const event = changeInfo.event
    const attachedId = event.id
    if (!attachedId) {
        changeInfo.revert()
        error.value = 'この予定は更新できません。'
        return
    }

    const dates = resolvePlannedDatesFromEvent(event)
    if (!dates) {
        changeInfo.revert()
        error.value = '移動後の期間が不正です。'
        return
    }

    periodSaving.value = true
    error.value = ''
    success.value = ''

    try {
        const url = `${page.props.appBaseUrl}/servicerecord/loaner/period/${attachedId}`
        const result = await apiFetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                // 実日付は維持し、予定期間のみ更新
                sentDate: event.extendedProps?.sentDate || null,
                returnedDate: event.extendedProps?.returnedDate || null,
                comment: event.extendedProps?.comment || null,
                plannedSentDate: dates.plannedSentDate,
                plannedReturnedDate: dates.plannedReturnedDate,
            }),
        })

        if (!result) {
            changeInfo.revert()
            return
        }

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(
                validationMessage || data.message || `期間の更新に失敗しました。（HTTP ${response.status}）`,
            )
        }

        const nextSent = data.attached?.plannedSentDate || dates.plannedSentDate
        const nextReturned = data.attached?.plannedReturnedDate || dates.plannedReturnedDate

        event.setExtendedProp('plannedSentDate', nextSent)
        event.setExtendedProp('plannedReturnedDate', nextReturned)

        if (selectedEvent.value?.id === attachedId) {
            selectedEvent.value = {
                ...selectedEvent.value,
                extendedProps: {
                    ...selectedEvent.value.extendedProps,
                    plannedSentDate: nextSent,
                    plannedReturnedDate: nextReturned,
                },
            }
        }

        success.value = data.message || `期間を更新しました。（${nextSent} 〜 ${nextReturned}）`
    } catch (e) {
        changeInfo.revert()
        error.value = e.message || '期間の更新に失敗しました。'
    } finally {
        periodSaving.value = false
    }
}

function periodEditUrl(id) {
    return `${page.props.appBaseUrl}/servicerecord/loaner/period/${id}`
}

function reloadEvents() {
    selectedEvent.value = null
    success.value = ''
    const api = calendarRef.value?.getApi?.()
    api?.refetchEvents()
}
</script>

<style scoped>
.calendar-page {
    height: 100vh;
    padding: 12px 16px;
    background: #e2e8f0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow: hidden;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-shrink: 0;
}

.page-header h1 {
    margin: 0 0 4px;
    font-size: 22px;
    color: #1e293b;
}

.subtitle {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
    color: #fff;
    display: inline-flex;
    align-items: center;
}

.btn-secondary {
    background: #64748b;
}

.btn-primary {
    background: #2563eb;
}

.global-error {
    margin: 0;
    padding: 10px 14px;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    background: #fef2f2;
    color: #b91c1c;
    flex-shrink: 0;
}

.global-success {
    margin: 0;
    padding: 10px 14px;
    border: 1px solid #86efac;
    border-radius: 6px;
    background: #f0fdf4;
    color: #166534;
    flex-shrink: 0;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    color: #475569;
}

.filter-field select {
    min-width: 280px;
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
}

.legend {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
}

.legend-item.status-35 {
    background: #dc2626;
}

.legend-item.status-40 {
    background: #2563eb;
}

.legend-item.waiting {
    background: #d97706;
}

.calendar-shell {
    flex: 1;
    min-height: 0;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    overflow: hidden;
}

.detail-panel {
    position: fixed;
    right: 16px;
    bottom: 16px;
    width: min(360px, calc(100vw - 32px));
    max-height: 50vh;
    overflow: auto;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2);
    z-index: 20;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: #1e293b;
    color: #fff;
}

.detail-header h2 {
    margin: 0;
    font-size: 14px;
}

.close-btn {
    border: none;
    background: transparent;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
}

.detail-grid {
    margin: 0;
    padding: 12px;
    display: grid;
    gap: 8px;
}

.detail-grid div {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 8px;
    font-size: 12px;
}

.detail-grid dt {
    margin: 0;
    color: #64748b;
}

.detail-grid dd {
    margin: 0;
    color: #1e293b;
    word-break: break-word;
}

.status-chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    color: #fff;
    font-weight: 700;
}

.detail-actions {
    padding: 0 12px 12px;
    display: flex;
    justify-content: flex-end;
}

.detail-actions .btn {
    padding: 8px 14px;
}
</style>

<style>
/* FullCalendar は scoped 外で色を強制する */
.calendar-shell .fc .fc-event,
.calendar-shell .fc .fc-event .fc-event-main {
    border-style: solid !important;
}

.calendar-shell .fc .loaner-event-chip {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid transparent;
    color: #fff !important;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.calendar-shell .fc .fc-event.loaner-status-35,
.calendar-shell .fc .fc-event.loaner-status-35 .fc-event-main,
.calendar-shell .fc .fc-event.loaner-status-35 .loaner-event-chip {
    background-color: #dc2626 !important;
    border-color: #b91c1c !important;
    color: #fff !important;
}

.calendar-shell .fc .fc-event.loaner-status-40,
.calendar-shell .fc .fc-event.loaner-status-40 .fc-event-main,
.calendar-shell .fc .fc-event.loaner-status-40 .loaner-event-chip {
    background-color: #2563eb !important;
    border-color: #1d4ed8 !important;
    color: #fff !important;
}

.calendar-shell .fc .fc-event.loaner-status-waiting,
.calendar-shell .fc .fc-event.loaner-status-waiting .fc-event-main,
.calendar-shell .fc .fc-event.loaner-status-waiting .loaner-event-chip {
    background-color: #d97706 !important;
    border-color: #b45309 !important;
    color: #fff !important;
}
</style>
