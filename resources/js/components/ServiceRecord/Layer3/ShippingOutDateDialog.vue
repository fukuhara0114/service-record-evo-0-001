<template>
    <BaseDialog :title="dialogTitle" large :plain="plain" :show-close="!plain" @close="onCancel">
        <div class="shipping-dialog">
            <div v-if="mode === 'complete'" class="shipping-summary">
                <div>
                    <strong>対象案件</strong>
                    <span>OrderID: {{ orderId }}</span>
                    <span v-if="productName">{{ productName }}</span>
                    <span v-if="serialNumber">SN: {{ serialNumber }}</span>
                    <span v-if="dealer">{{ dealer }}</span>
                    <span v-if="contactPerson">{{ contactPerson }}</span>
                </div>
                <div class="selected-line">
                    出荷日:
                    <strong>{{ selectedDate || '（未選択・日付をクリック）' }}</strong>
                    <span class="hint">バーをドラッグで日付変更／クリックで詳細。上限 {{ capacity }} 台/日を超えても登録可（超過日は赤表示）</span>
                </div>
            </div>
            <div v-else class="shipping-summary">
                <div class="selected-line">
                    <span class="hint">バーをドラッグで出荷予定日を変更／クリックで詳細。上限 {{ capacity }} 台/日を超えても登録可（超過日は赤表示）</span>
                </div>
            </div>

            <p v-if="error" class="shipping-error">{{ error }}</p>

            <Splitpanes class="default-theme shipping-split" horizontal @resized="onSplitResized">
                <Pane class="shipping-split-pane" :size="topPaneSize" :min-size="28">
                    <div class="calendar-shell">
                        <FullCalendar ref="calendarRef" :options="calendarOptions" />
                    </div>
                </Pane>
                <Pane class="shipping-split-pane" :size="bottomPaneSize" :min-size="20">
                    <aside class="detail-panel">
                        <div class="detail-panel-header">
                            <h4>案件詳細</h4>
                            <button
                                v-if="selectedDetail"
                                type="button"
                                class="detail-clear"
                                @click="clearDetail"
                            >
                                クリア
                            </button>
                        </div>

                        <p v-if="detailLoading" class="detail-empty">読み込み中...</p>
                        <p v-else-if="detailError" class="shipping-error">{{ detailError }}</p>
                        <p v-else-if="!selectedDetail" class="detail-empty">
                            カレンダー上の案件バーをクリックすると、ここに詳細が表示されます。
                        </p>

                        <div v-else class="detail-scroll">
                            <section class="detail-section">
                                <h5>基本</h5>
                                <dl class="detail-grid">
                                    <div><dt>OrderID</dt><dd>{{ display(selectedDetail.orderID) }}</dd></div>
                                    <div><dt>status</dt><dd>{{ display(selectedDetail.status) }}</dd></div>
                                    <div><dt>製品名</dt><dd>{{ display(selectedDetail.productName) }}</dd></div>
                                    <div><dt>S/N</dt><dd>{{ display(selectedDetail.SN) }}</dd></div>
                                    <div><dt>作業内容</dt><dd>{{ returnCodeLabel(selectedDetail) }}</dd></div>
                                    <div><dt>A2LA</dt><dd>{{ a2laLabel(selectedDetail.a2la) }}</dd></div>
                                    <div><dt>RMA</dt><dd>{{ display(selectedDetail.RMA) }}</dd></div>
                                    <div><dt>出荷予定日</dt><dd>{{ displayDate(selectedDetail.shippingOut_requiredDate) }}</dd></div>
                                    <div><dt>受領日</dt><dd>{{ displayDate(selectedDetail.receivedDate) }}</dd></div>
                                    <div><dt>作業担当</dt><dd>{{ laborLabel(selectedDetail) }}</dd></div>
                                    <div><dt>coNum</dt><dd>{{ display(selectedDetail.coNum) }}</dd></div>
                                    <div><dt>見積番号</dt><dd>{{ display(selectedDetail.quoteNum || selectedDetail.sm_quote) }}</dd></div>
                                    <div><dt>受注番号</dt><dd>{{ display(selectedDetail.orderNum) }}</dd></div>
                                    <div><dt>発注番号</dt><dd>{{ display(selectedDetail.poNum) }}</dd></div>
                                    <div><dt>価格</dt><dd>{{ formatPrice(selectedDetail.price) }}</dd></div>
                                </dl>
                            </section>

                            <section class="detail-section">
                                <h5>Dealer</h5>
                                <dl class="detail-grid">
                                    <div><dt>会社名</dt><dd>{{ display(selectedDetail.dealer) }}</dd></div>
                                    <div><dt>部署</dt><dd>{{ display(selectedDetail.dealer_depart) }}</dd></div>
                                    <div><dt>担当者</dt><dd>{{ display(selectedDetail.contactPerson) }}</dd></div>
                                    <div><dt>電話</dt><dd>{{ display(selectedDetail.phone) }}</dd></div>
                                    <div class="span2"><dt>E-mail</dt><dd>{{ display(selectedDetail.email) }}</dd></div>
                                    <div><dt>〒</dt><dd>{{ display(selectedDetail.zipcode) }}</dd></div>
                                    <div><dt>都道府県</dt><dd>{{ display(selectedDetail.address1) }}</dd></div>
                                    <div class="span2"><dt>住所</dt><dd>{{ display(selectedDetail.address2) }}</dd></div>
                                </dl>
                            </section>

                            <section class="detail-section">
                                <h5>E/U</h5>
                                <dl class="detail-grid">
                                    <div><dt>会社名</dt><dd>{{ display(selectedDetail.endUser) }}</dd></div>
                                    <div><dt>部署</dt><dd>{{ display(selectedDetail.endUser_depart) }}</dd></div>
                                    <div><dt>担当者</dt><dd>{{ display(selectedDetail.endUser_contactPerson) }}</dd></div>
                                    <div><dt>電話</dt><dd>{{ display(selectedDetail.endUser_phone) }}</dd></div>
                                    <div class="span2"><dt>E-mail</dt><dd>{{ display(selectedDetail.endUser_email) }}</dd></div>
                                    <div><dt>〒</dt><dd>{{ display(selectedDetail.endUser_zipcode) }}</dd></div>
                                    <div><dt>都道府県</dt><dd>{{ display(selectedDetail.endUser_address1) }}</dd></div>
                                    <div class="span2"><dt>住所</dt><dd>{{ display(selectedDetail.endUser_address2) }}</dd></div>
                                </dl>
                            </section>

                            <section class="detail-section">
                                <h5>Delivery</h5>
                                <dl class="detail-grid">
                                    <div><dt>会社名</dt><dd>{{ display(selectedDetail.deliveryDestination_company) }}</dd></div>
                                    <div><dt>部署</dt><dd>{{ display(selectedDetail.deliveryDestination_depart) }}</dd></div>
                                    <div><dt>担当者</dt><dd>{{ display(selectedDetail.deliveryDestination_contactPerson) }}</dd></div>
                                    <div><dt>電話</dt><dd>{{ display(selectedDetail.deliveryDestination_phone) }}</dd></div>
                                    <div class="span2"><dt>E-mail</dt><dd>{{ display(selectedDetail.deliveryDestination_email) }}</dd></div>
                                    <div><dt>〒</dt><dd>{{ display(selectedDetail.deliveryDestination_zipcode) }}</dd></div>
                                    <div><dt>都道府県</dt><dd>{{ display(selectedDetail.deliveryDestination_address1) }}</dd></div>
                                    <div class="span2"><dt>住所</dt><dd>{{ display(selectedDetail.deliveryDestination_address2) }}</dd></div>
                                </dl>
                            </section>
                        </div>
                    </aside>
                </Pane>
            </Splitpanes>
        </div>

        <template v-if="mode === 'complete'" #footer>
            <button type="button" class="btn btn-secondary" :disabled="confirming" @click="onCancel">キャンセル</button>
            <button
                type="button"
                class="btn btn-primary"
                :disabled="!selectedDate || confirming"
                @click="onConfirm"
            >
                {{ confirming ? '処理中...' : 'この日で完了（status=300）' }}
            </button>
        </template>
        <template v-else-if="plain" #footer>
            <a :href="listUrl" class="btn btn-secondary">一覧へ戻る</a>
        </template>
    </BaseDialog>
</template>

<script setup>
import { reactive, ref, watch, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import BaseDialog from './BaseDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { SHIPPING_DAILY_CAPACITY } from '@/constants/shipping'
import { getServiceRecordBasePath, serviceRecordUrl } from '@/utils/serviceRecordPath'

const props = defineProps({
    mode: { type: String, default: 'complete' }, // complete | browse
    plain: { type: Boolean, default: false },
    orderId: { type: [Number, String], default: null },
    productName: { type: String, default: '' },
    serialNumber: { type: String, default: '' },
    dealer: { type: String, default: '' },
    contactPerson: { type: String, default: '' },
    previewRecord: { type: Object, default: null },
    confirming: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'confirm'])
const page = usePage()

const calendarRef = ref(null)
const selectedDate = ref(null)
/** 1日あたりの出荷予定台数上限（目安）。API の capacity または SHIPPING_DAILY_CAPACITY */
const capacity = ref(SHIPPING_DAILY_CAPACITY)
const dayCounts = ref({})
const error = ref('')
const dropSaving = ref(false)
const currentViewType = ref('dayGridMonth')
const selectedDetail = ref(null)
const selectedEventId = ref(null)
const detailLoading = ref(false)
const detailError = ref('')
const topPaneSize = ref(58)
const bottomPaneSize = ref(42)

const dialogTitle = computed(() =>
    props.mode === 'browse' ? '出荷予定カレンダー' : '出荷予定日の選択',
)

const listUrl = computed(() => serviceRecordUrl('/administrator'))

const PENDING_ID = computed(() => `pending-${props.orderId}`)

function getApiBasePath() {
    return getServiceRecordBasePath()
}

function onSplitResized(panes) {
    if (!Array.isArray(panes) || panes.length < 2) return
    topPaneSize.value = panes[0].size
    bottomPaneSize.value = panes[1].size
    requestAnimationFrame(() => {
        calendarRef.value?.getApi?.()?.updateSize()
    })
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function todayStr() {
    const d = new Date()
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function toDateStr(input) {
    if (!input) return null
    if (typeof input === 'string') return input.slice(0, 10)
    if (input instanceof Date) {
        const pad = (n) => String(n).padStart(2, '0')
        return `${input.getFullYear()}-${pad(input.getMonth() + 1)}-${pad(input.getDate())}`
    }
    if (typeof input?.toISOString === 'function') {
        return input.toISOString().slice(0, 10)
    }
    return String(input).slice(0, 10)
}

function isPastDate(dateStr) {
    return Boolean(dateStr) && dateStr < todayStr()
}

function getApiCount(dateStr) {
    return Number(dayCounts.value[dateStr] || 0)
}

function displayCount(dateStr) {
    let count = getApiCount(dateStr)
    if (props.mode === 'complete' && selectedDate.value === dateStr) count += 1
    return count
}

function display(value) {
    if (value === null || value === undefined || value === '') return '—'
    return String(value)
}

function displayDate(value) {
    const date = toDateStr(value)
    return date || '—'
}

function a2laLabel(value) {
    return value === 1 || value === '1' || value === true ? '有り（ON）' : '無し（OFF）'
}

function returnCodeLabel(record) {
    if (record?.return_code_master?.description) {
        return `${record.return_code_master.description} (${record.returnCode ?? ''})`
    }
    const id = record?.returnCode
    const found = (page.props.returnCodes ?? []).find(item => String(item.id) === String(id))
    if (found?.description) return `${found.description} (${id})`
    return display(id)
}

function laborLabel(record) {
    if (record?.labor_master?.laborName) {
        return `${record.labor_master.laborName} (${record.laborID ?? ''})`
    }
    const id = record?.laborID
    const found = (page.props.labors ?? []).find(item => String(item.laborID) === String(id))
    if (found?.laborName) return `${found.laborName} (${id})`
    return display(id)
}

function formatPrice(value) {
    const num = Number(value)
    if (!Number.isFinite(num)) return '—'
    return new Intl.NumberFormat('ja-JP').format(num)
}

function pendingTitle() {
    const parts = [
        String(props.orderId),
        props.serialNumber,
        props.productName,
        props.dealer,
        props.contactPerson,
    ].filter(Boolean)
    return `【仮】${parts.join(' / ') || props.orderId}`
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

function formatEventLines(event) {
    const propsX = event.extendedProps || {}
    const line1 = [
        propsX.pending ? '【仮】' : '',
        propsX.orderID ?? event.id,
        propsX.SN,
        propsX.productName,
    ].filter(Boolean).join(' / ')

    const line2 = [propsX.dealer, propsX.dealer_depart, propsX.contactPerson]
        .filter(Boolean)
        .join(' / ')

    return { line1: line1 || event.title || '', line2 }
}

function buildPendingEvent(dateStr) {
    return {
        id: PENDING_ID.value,
        title: pendingTitle(),
        start: dateStr,
        allDay: true,
        editable: true,
        classNames: ['shipping-pending'],
        backgroundColor: '#0f766e',
        borderColor: '#0f766e',
        textColor: '#fff',
        extendedProps: {
            orderID: props.orderId,
            SN: props.serialNumber,
            productName: props.productName,
            dealer: props.dealer,
            dealer_depart: props.previewRecord?.dealer_depart || '',
            contactPerson: props.contactPerson,
            pending: true,
        },
    }
}

function setSelectedDate(dateStr) {
    if (!dateStr || isPastDate(dateStr)) {
        error.value = '過去の日付は選択できません。'
        return
    }
    error.value = ''
    selectedDate.value = dateStr
}

function clearDetail() {
    selectedDetail.value = null
    selectedEventId.value = null
    detailError.value = ''
}

async function loadDetailForOrder(orderId, pending = false) {
    detailLoading.value = true
    detailError.value = ''
    selectedEventId.value = pending ? PENDING_ID.value : String(orderId)

    try {
        if (pending && props.previewRecord && String(props.previewRecord.orderID) === String(orderId)) {
            selectedDetail.value = { ...props.previewRecord }
            return
        }

        const result = await apiFetch(`${window.location.origin}${getApiBasePath()}/record/${orderId}`)
        if (!result?.response?.ok) {
            throw new Error(result?.data?.message || '案件詳細の取得に失敗しました。')
        }
        selectedDetail.value = result.data
    } catch (e) {
        selectedDetail.value = null
        detailError.value = e.message || '案件詳細の取得に失敗しました。'
    } finally {
        detailLoading.value = false
    }
}

async function persistShippingDate(orderId, dateStr) {
    const result = await apiFetch(`${window.location.origin}${getApiBasePath()}/${orderId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({
            shippingOut_requiredDate: dateStr,
        }),
    })

    if (!result) {
        throw new Error('更新に失敗しました。')
    }

    if (!result.response.ok) {
        throw new Error(result.data?.message || `更新に失敗しました。（HTTP ${result.response.status}）`)
    }

    return result.data
}

async function fetchEvents(info, successCallback, failureCallback) {
    error.value = ''
    try {
        const params = new URLSearchParams({
            start: info.startStr.slice(0, 10),
            end: info.endStr.slice(0, 10),
        })
        const result = await apiFetch(
            `${window.location.origin}${getApiBasePath()}/shipping-calendar/events?${params}`,
        )
        if (!result?.response?.ok) {
            throw new Error(result?.data?.message || '出荷予定の取得に失敗しました。')
        }

        capacity.value = Number(result.data.capacity) || SHIPPING_DAILY_CAPACITY
        dayCounts.value = result.data.counts || {}

        const events = (result.data.events || [])
            .filter((event) => props.mode !== 'complete' || String(event.id) !== String(props.orderId))
            .map((event) => ({
                ...event,
                editable: true,
                classNames: [
                    ...(event.classNames || []),
                    'shipping-event',
                    String(selectedEventId.value) === String(event.id) ? 'shipping-event-selected' : '',
                ].filter(Boolean),
            }))

        if (props.mode === 'complete' && props.orderId && selectedDate.value) {
            events.push(buildPendingEvent(selectedDate.value))
        }

        successCallback(events)
    } catch (e) {
        error.value = e.message || '出荷予定の取得に失敗しました。'
        failureCallback(e)
    }
}

function handleDateClick(arg) {
    if (props.mode !== 'complete') return
    const dateStr = toDateStr(arg.dateStr || arg.date)
    setSelectedDate(dateStr)
}

function handleEventClick(info) {
    const isPending = info.event.id === PENDING_ID.value || info.event.extendedProps?.pending
    const orderId = info.event.extendedProps?.orderID ?? info.event.id
    loadDetailForOrder(orderId, isPending)
}

function handleEventAllow(dropInfo) {
    const dateStr = toDateStr(dropInfo.start)
    if (!dateStr || isPastDate(dateStr)) return false
    return true
}

async function handleEventDrop(info) {
    if (dropSaving.value) {
        info.revert()
        return
    }

    const dateStr = toDateStr(info.event.start)
    if (!dateStr || isPastDate(dateStr)) {
        info.revert()
        error.value = '過去の日付には移動できません。'
        return
    }

    const isPending = info.event.id === PENDING_ID.value || info.event.extendedProps?.pending
    if (isPending) {
        selectedDate.value = dateStr
        error.value = ''
        return
    }

    const orderId = info.event.extendedProps?.orderID ?? info.event.id
    dropSaving.value = true
    error.value = ''
    try {
        await persistShippingDate(orderId, dateStr)
        const api = calendarRef.value?.getApi?.()
        api?.refetchEvents()
        if (String(selectedEventId.value) === String(orderId)) {
            await loadDetailForOrder(orderId, false)
        }
    } catch (e) {
        info.revert()
        if (!e.cancelled) {
            error.value = e.message || '日付の更新に失敗しました。'
        }
    } finally {
        dropSaving.value = false
    }
}

function dayCellClassNames(arg) {
    const dateStr = toDateStr(arg.date)
    const classes = []
    if (isPastDate(dateStr)) classes.push('shipping-day-past')
    if (arg.date.getDay() === 0 || arg.date.getDay() === 6) classes.push('shipping-day-weekend')
    if (selectedDate.value === dateStr) classes.push('shipping-day-selected')
    const count = displayCount(dateStr)
    if (count > capacity.value) {
        classes.push('shipping-day-over')
    } else if (count === capacity.value) {
        classes.push('shipping-day-full')
    }
    return classes
}

function dayCellContent(arg) {
    const dateStr = toDateStr(arg.date)
    const count = displayCount(dateStr)
    const over = count > capacity.value
    const full = count === capacity.value
    const badgeClass = over ? ' is-over' : (full ? ' is-full' : '')
    const badgeLabel = over
        ? `${count}/${capacity.value} 超過`
        : `${count}/${capacity.value}`
    return {
        html: `<div class="shipping-day-cell">`
            + `<span class="fc-daygrid-day-number">${arg.dayNumberText}</span>`
            + `<span class="shipping-capacity-badge${badgeClass}">${badgeLabel}</span>`
            + `</div>`,
    }
}

function eventContent(arg) {
    const { line1, line2 } = formatEventLines(arg.event)
    const viewType = arg.view?.type || ''
    const detailed = viewType === 'dayGridWeek' || viewType === 'dayGridDay'

    if (!detailed) {
        return {
            html: `<div class="shipping-event-chip" title="${escapeHtml(arg.event.title)}">${escapeHtml(line1)}${line2 ? ' / ' + escapeHtml(line2) : ''}</div>`,
        }
    }

    return {
        html: `<div class="shipping-event-bar" title="${escapeHtml(arg.event.title)}">`
            + `<div class="shipping-event-line1">${escapeHtml(line1)}</div>`
            + (line2 ? `<div class="shipping-event-line2">${escapeHtml(line2)}</div>` : '')
            + `</div>`,
    }
}

function handleDatesSet(info) {
    currentViewType.value = info.view?.type || 'dayGridMonth'
}

const calendarOptions = reactive({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'ja',
    height: '100%',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,dayGridWeek,dayGridDay',
    },
    buttonText: {
        today: '今日',
        month: '月',
        week: '週',
        day: '日',
    },
    views: {
        dayGridMonth: {
            dayMaxEvents: 4,
            eventDisplay: 'block',
        },
        dayGridWeek: {
            dayMaxEvents: false,
            eventDisplay: 'block',
        },
        dayGridDay: {
            dayMaxEvents: false,
            eventDisplay: 'block',
        },
    },
    editable: true,
    selectable: false,
    dayMaxEvents: true,
    eventDisplay: 'block',
    events: fetchEvents,
    dateClick: handleDateClick,
    eventClick: handleEventClick,
    eventAllow: handleEventAllow,
    eventDrop: handleEventDrop,
    datesSet: handleDatesSet,
    dayCellClassNames,
    dayCellContent,
    eventContent,
})

watch(selectedDate, () => {
    const api = calendarRef.value?.getApi?.()
    if (!api) return
    // changeView は同一 view でもイベントソース再取得を誘発し、refetch と二重検索になるため使わない
    api.refetchEvents()
})

function onCancel() {
    if (props.confirming) return
    emit('close')
}

function onConfirm() {
    if (!selectedDate.value || props.confirming) return

    error.value = ''
    emit('confirm', {
        shippingOut_requiredDate: selectedDate.value,
    })
}
</script>

<style scoped>
.shipping-dialog {
    display: flex;
    flex-direction: column;
    gap: 10px;
    height: 100%;
    min-height: 0;
}

.shipping-summary {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
    color: #334155;
    flex-shrink: 0;
}

.shipping-summary > div {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    align-items: baseline;
}

.selected-line strong {
    color: #0f766e;
    font-size: 15px;
}

.hint {
    color: #64748b;
    font-size: 12px;
}

.shipping-error {
    margin: 0;
    color: #b91c1c;
    font-size: 13px;
}

.shipping-split {
    flex: 1;
    min-height: 0;
}

.shipping-split-pane {
    min-height: 0;
    overflow: hidden;
}

.calendar-shell {
    height: 100%;
    min-height: 0;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
}

.detail-panel {
    height: 100%;
    min-height: 0;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.detail-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #1e293b;
    color: #fff;
    flex-shrink: 0;
}

.detail-panel-header h4 {
    margin: 0;
    font-size: 14px;
}

.detail-clear {
    border: 1px solid #94a3b8;
    background: #334155;
    color: #fff;
    border-radius: 4px;
    padding: 3px 8px;
    cursor: pointer;
    font-size: 12px;
}

.detail-empty {
    margin: 16px;
    color: #64748b;
    font-size: 13px;
}

.detail-scroll {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.detail-section {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 10px 12px;
}

.detail-section h5 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #0f172a;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 12px;
    margin: 0;
}

.detail-grid > div {
    display: grid;
    grid-template-columns: 88px 1fr;
    gap: 6px;
    align-items: start;
}

.detail-grid > div.span2 {
    grid-column: 1 / -1;
}

.detail-grid dt {
    margin: 0;
    color: #64748b;
    font-size: 12px;
}

.detail-grid dd {
    margin: 0;
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
    word-break: break-word;
}

.btn {
    padding: 8px 14px;
    border-radius: 4px;
    border: 1px solid #94a3b8;
    background: #fff;
    font-weight: 700;
    cursor: pointer;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #f8fafc;
}

.btn-primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

a.btn {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

@media (max-width: 1100px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<style>
/* 時間グリッド系ビューは使わないが、残存スタイル対策 */
.fc .fc-timegrid,
.fc .fc-timegrid-body,
.fc .fc-timegrid-slots,
.fc .fc-timegrid-cols {
    display: none !important;
}

.shipping-day-cell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 4px;
    padding: 2px 4px 0;
    box-sizing: border-box;
}

.shipping-capacity-badge {
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    padding: 1px 6px;
    border-radius: 999px;
    background: #e2e8f0;
}

.shipping-capacity-badge.is-full {
    background: #fed7aa;
    color: #9a3412;
}

.shipping-capacity-badge.is-over {
    background: #dc2626;
    color: #fff;
}

.fc .shipping-day-weekend {
    background: #f8fafc;
}

.fc .shipping-day-past {
    opacity: 0.55;
}

.fc .shipping-day-selected {
    outline: 2px solid #0f766e;
    outline-offset: -2px;
}

.fc .shipping-day-full {
    background: #ffedd5 !important;
}

.fc .shipping-day-full .fc-daygrid-day-number {
    color: #c2410c;
    font-weight: 800;
}

.fc .shipping-day-over {
    background: #fecaca !important;
}

.fc .shipping-day-over .fc-daygrid-day-frame {
    background: #fecaca;
}

.fc .shipping-day-over .fc-daygrid-day-number {
    color: #991b1b;
    font-weight: 800;
}

.fc .shipping-pending {
    font-weight: 700;
}

.fc .shipping-event {
    cursor: pointer;
}

.fc .shipping-event-selected {
    outline: 2px solid #f59e0b;
    outline-offset: 1px;
}

.fc .shipping-event-chip {
    padding: 1px 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
    line-height: 1.3;
}

.fc .shipping-event-bar {
    padding: 4px 6px;
    line-height: 1.35;
    white-space: normal;
    word-break: break-word;
}

.fc .shipping-event-line1 {
    font-size: 12px;
    font-weight: 700;
}

.fc .shipping-event-line2 {
    font-size: 11px;
    opacity: 0.95;
    margin-top: 2px;
}

.fc-dayGridWeek-view .fc-event,
.fc-dayGridDay-view .fc-event {
    margin-bottom: 4px;
}

.fc-dayGridWeek-view .fc-daygrid-event,
.fc-dayGridDay-view .fc-daygrid-event {
    white-space: normal !important;
}
</style>
