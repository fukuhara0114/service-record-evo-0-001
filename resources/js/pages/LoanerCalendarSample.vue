<template>
    <div class="calendar-page">
        <div class="page-header">
            <div>
                <h1>貸出カレンダー（サンプル）</h1>
                <p class="subtitle">
                    attachedloaners の予約期間を表示します（dealer 等は servicerecord 側）。
                </p>
            </div>
            <div class="header-actions">
                <a :href="homeUrl" class="btn btn-secondary">Home</a>
                <a :href="loanerCreateUrl" class="btn btn-secondary">貸出機登録</a>
                <a :href="adminUrl" class="btn btn-secondary">既存案件一覧</a>
            </div>
        </div>

        <p v-if="error" class="global-error">{{ error }}</p>

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
                <span class="legend-item loaner">loaner 予約</span>
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
                <div><dt>assignStatus</dt><dd>{{ selectedEvent.extendedProps?.assignStatus ?? '—' }}</dd></div>
                <div><dt>dealer</dt><dd>{{ selectedEvent.extendedProps?.dealer ?? '—' }}</dd></div>
                <div><dt>SN</dt><dd>{{ selectedEvent.extendedProps?.SN ?? '—' }}</dd></div>
                <div><dt>sentDate</dt><dd>{{ selectedEvent.extendedProps?.sentDate ?? '—' }}</dd></div>
                <div><dt>returnedDate</dt><dd>{{ selectedEvent.extendedProps?.returnedDate ?? '—' }}</dd></div>
                <div><dt>plannedSentDate</dt><dd>{{ selectedEvent.extendedProps?.plannedSentDate ?? '—' }}</dd></div>
                <div><dt>plannedReturnedDate</dt><dd>{{ selectedEvent.extendedProps?.plannedReturnedDate ?? '—' }}</dd></div>
                <div><dt>comment</dt><dd>{{ selectedEvent.extendedProps?.comment ?? '—' }}</dd></div>
            </dl>
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

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)
const loanerCreateUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/loaner/create`)

const calendarOptions = computed(() => ({
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
    editable: false,
    selectable: false,
    dayMaxEvents: true,
    events: fetchEvents,
    eventClick: handleEventClick,
}))

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

        successCallback(data.events ?? [])
    } catch (e) {
        error.value = e.message || 'イベント取得に失敗しました。'
        failureCallback(e)
    }
}

function handleEventClick(clickInfo) {
    selectedEvent.value = {
        title: clickInfo.event.title,
        extendedProps: clickInfo.event.extendedProps,
    }
}

function reloadEvents() {
    selectedEvent.value = null
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

.global-error {
    margin: 0;
    padding: 10px 14px;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    background: #fef2f2;
    color: #b91c1c;
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
    gap: 10px;
}

.legend-item {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
}

.legend-item.loaner {
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

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

:deep(.fc) {
    --fc-border-color: #e2e8f0;
    --fc-button-bg-color: #334155;
    --fc-button-border-color: #334155;
    --fc-button-hover-bg-color: #1e293b;
    --fc-button-hover-border-color: #1e293b;
    --fc-button-active-bg-color: #0f172a;
    --fc-button-active-border-color: #0f172a;
    font-size: 13px;
}
</style>
