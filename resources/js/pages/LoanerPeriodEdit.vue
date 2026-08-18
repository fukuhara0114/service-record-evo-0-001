<template>
    <div class="period-page">
        <div class="page-header">
            <div class="header-title">
                <h1>貸出期間の編集</h1>
                <p class="subtitle">attachedloaners #{{ attached.id }} / orderID: {{ attached.associatedID }}</p>
            </div>
            <div class="header-message" aria-live="polite">
                <p v-if="error" class="global-error">{{ error }}</p>
                <p v-else-if="success" class="global-success">{{ success }}</p>
            </div>
            <div class="header-actions">
                <a :href="calendarUrl" class="btn btn-secondary">カレンダー</a>
                <a :href="adminUrl" class="btn btn-secondary">既存案件一覧</a>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="saving"
                    @click="save"
                >
                    {{ saving ? '保存中...' : '保存' }}
                </button>
                <CloseToHomeButton :href="homeUrl" />
            </div>
        </div>

        <div class="content-grid">
            <section class="info-card">
                <h2 class="card-title">貸出情報</h2>
                <div class="meta-grid-wrap">
                    <dl class="meta-grid meta-grid-row1">
                        <div>
                            <dt>productName</dt>
                            <dd>{{ attachedLocal.productName || '—' }}</dd>
                        </div>
                        <div>
                            <dt>item</dt>
                            <dd>{{ attachedLocal.item || '—' }}</dd>
                        </div>
                        <div>
                            <dt>SN</dt>
                            <dd>{{ attachedLocal.SN || '—' }}</dd>
                        </div>
                        <div>
                            <dt>manageNumber</dt>
                            <dd>{{ attachedLocal.manageNum || '—' }}</dd>
                        </div>
                        <div>
                            <dt>loanerID</dt>
                            <dd>{{ attachedLocal.loanerID || '—' }}</dd>
                        </div>
                    </dl>
                    <dl class="meta-grid meta-grid-row2">
                        <div>
                            <dt>order type</dt>
                            <dd>{{ attachedLocal.order_type || '—' }}</dd>
                        </div>
                        <div>
                            <dt>assignStatus</dt>
                            <dd>{{ attachedLocal.assignStatus || '—' }}</dd>
                        </div>
                        <div>
                            <dt>parentID</dt>
                            <dd>
                                <template v-if="attachedLocal.parentID">
                                    {{ attachedLocal.parentID }}
                                    <span v-if="parentLocal" class="parent-note">
                                        （{{ parentLocal.productName || '—' }} / {{ parentLocal.dealer || '—' }}）
                                    </span>
                                </template>
                                <template v-else>なし（service 案件待ち）</template>
                            </dd>
                        </div>
                        <div>
                            <dt>enduser SN</dt>
                            <dd>
                                <input
                                    v-model="form.enduser_SN"
                                    type="text"
                                    class="meta-input"
                                    placeholder="enduser SN"
                                >
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="loaner-stakeholder-stack">
                    <section class="stakeholder-card info-card-dealer">
                        <aside class="stakeholder-side">
                            <div class="stakeholder-label">dealer</div>
                            <button type="button" class="switch-btn" @click="swapStakeholders('dealer', 'endUser')">
                                switch E/U
                            </button>
                            <button type="button" class="switch-btn" @click="swapStakeholders('dealer', 'delivery')">
                                switch delivery
                            </button>
                        </aside>
                        <div class="stakeholder-body">
                            <div class="form-row row-dealer-top">
                                <button
                                    type="button"
                                    class="field-button field-button-pick"
                                    @click="openDealerSelect"
                                >
                                    dealer選択
                                </button>
                                <input
                                    v-model="form.dealer"
                                    type="text"
                                    class="w-dealer-name"
                                    placeholder="dealer"
                                >
                            </div>
                            <div class="form-row row-full">
                                <input v-model="form.dealer_depart" type="text" placeholder="dealer_depart">
                            </div>
                            <div class="form-row row-contact">
                                <input v-model="form.contactPerson" type="text" class="w-contact" placeholder="contactPerson">
                            </div>
                            <div class="form-row row-phone-email">
                                <input v-model="form.phone" type="text" class="w-phone" placeholder="Phone">
                                <input v-model="form.email" type="text" class="w-email" placeholder="EMail">
                            </div>
                            <div class="form-row row-zip">
                                <input
                                    v-model="form.zipcode"
                                    type="text"
                                    class="w-zip"
                                    inputmode="numeric"
                                    maxlength="8"
                                    placeholder="Zipcode"
                                    @input="onZipcodeInput('dealer')"
                                >
                            </div>
                            <div class="form-row row-address">
                                <input v-model="form.address1" type="text" class="w-address1" placeholder="address1">
                                <input v-model="form.address2" type="text" class="w-address2" placeholder="address2">
                            </div>
                        </div>
                    </section>

                    <section class="stakeholder-card info-card-delivery">
                        <aside class="stakeholder-side">
                            <div class="stakeholder-label">delivery</div>
                            <button type="button" class="switch-btn" @click="swapStakeholders('delivery', 'dealer')">
                                switch dealer
                            </button>
                            <button type="button" class="switch-btn" @click="swapStakeholders('delivery', 'endUser')">
                                switch E/U
                            </button>
                        </aside>
                        <div class="stakeholder-body">
                            <div class="form-row row-full">
                                <input v-model="form.deliveryDestination_company" type="text" placeholder="delivery">
                            </div>
                            <div class="form-row row-full">
                                <input v-model="form.deliveryDestination_depart" type="text" placeholder="delivery_depart">
                            </div>
                            <div class="form-row row-contact">
                                <input v-model="form.deliveryDestination_contactPerson" type="text" class="w-contact" placeholder="contactPerson">
                            </div>
                            <div class="form-row row-phone-email">
                                <input v-model="form.deliveryDestination_phone" type="text" class="w-phone" placeholder="Phone">
                                <input v-model="form.deliveryDestination_email" type="text" class="w-email" placeholder="EMail">
                            </div>
                            <div class="form-row row-zip">
                                <input
                                    v-model="form.deliveryDestination_zipcode"
                                    type="text"
                                    class="w-zip"
                                    inputmode="numeric"
                                    maxlength="8"
                                    placeholder="Zipcode"
                                    @input="onZipcodeInput('delivery')"
                                >
                            </div>
                            <div class="form-row row-address">
                                <input v-model="form.deliveryDestination_address1" type="text" class="w-address1" placeholder="address1">
                                <input v-model="form.deliveryDestination_address2" type="text" class="w-address2" placeholder="address2">
                            </div>
                        </div>
                    </section>

                    <section class="stakeholder-card info-card-enduser">
                        <aside class="stakeholder-side">
                            <div class="stakeholder-label">endUser</div>
                            <button type="button" class="switch-btn" @click="swapStakeholders('endUser', 'dealer')">
                                switch dealer
                            </button>
                            <button type="button" class="switch-btn" @click="swapStakeholders('endUser', 'delivery')">
                                switch delivery
                            </button>
                        </aside>
                        <div class="stakeholder-body">
                            <div class="form-row row-full">
                                <input v-model="form.endUser" type="text" placeholder="endUser">
                            </div>
                            <div class="form-row row-full">
                                <input v-model="form.endUser_depart" type="text" placeholder="endUser_depart">
                            </div>
                            <div class="form-row row-contact">
                                <input v-model="form.endUser_contactPerson" type="text" class="w-contact" placeholder="contactPerson">
                            </div>
                            <div class="form-row row-phone-email">
                                <input v-model="form.endUser_phone" type="text" class="w-phone" placeholder="Phone">
                                <input v-model="form.endUser_email" type="text" class="w-email" placeholder="EMail">
                            </div>
                            <div class="form-row row-zip">
                                <input
                                    v-model="form.endUser_zipcode"
                                    type="text"
                                    class="w-zip"
                                    inputmode="numeric"
                                    maxlength="8"
                                    placeholder="Zipcode"
                                    @input="onZipcodeInput('endUser')"
                                >
                            </div>
                            <div class="form-row row-address">
                                <input v-model="form.endUser_address1" type="text" class="w-address1" placeholder="address1">
                                <input v-model="form.endUser_address2" type="text" class="w-address2" placeholder="address2">
                            </div>
                        </div>
                    </section>
                </div>

                <section class="period-inline-card">
                    <h2 class="card-title">貸出期間 / status</h2>
                    <div class="period-dates-row">
                        <label v-if="dateFields.hasPlannedSent" class="field field-on-white">
                            <span>予定開始</span>
                            <input v-model="form.plannedSentDate" type="date">
                        </label>
                        <label v-if="dateFields.hasPlannedReturned" class="field field-on-white">
                            <span>予定終了</span>
                            <input v-model="form.plannedReturnedDate" type="date">
                        </label>
                    </div>
                    <div class="period-status-row">
                        <label v-if="attachedLocal.order_type === 'loaner'" class="field field-on-white">
                            <span>status（StatusLoaner）</span>
                            <select v-model="form.status">
                                <option value="">選択してください</option>
                                <option
                                    v-for="status in statuses"
                                    :key="status.processID_new"
                                    :value="String(status.processID_new)"
                                >
                                    {{ loanerStatusOptionLabel(status) }}
                                </option>
                            </select>
                        </label>
                        <p
                            v-else-if="attachedLocal.order_type === 'waiting_list'"
                            class="field-hint period-waiting-hint"
                        >
                            waiting_list（status なし）
                        </p>
                    </div>

                    <div v-if="attachedLocal.order_type === 'waiting_list'" class="schedule-box">
                        <h3 class="section-title">同機種の貸出終了予定</h3>
                        <p v-if="!productLoanSchedule" class="field-hint">終了予定情報を取得できませんでした。</p>
                        <template v-else>
                            <dl class="schedule-summary">
                                <div>
                                    <dt>最も早い終了予定</dt>
                                    <dd>{{ productLoanSchedule.earliestEndDate || '（該当なし）' }}</dd>
                                </div>
                                <div>
                                    <dt>最も遅い終了予定</dt>
                                    <dd>{{ productLoanSchedule.latestEndDate || '（該当なし）' }}</dd>
                                </div>
                                <div>
                                    <dt>推奨開始日（終了翌日）</dt>
                                    <dd>
                                        {{ productLoanSchedule.suggestedStartDate || '—' }}
                                        <button
                                            v-if="productLoanSchedule.suggestedStartDate"
                                            type="button"
                                            class="btn btn-secondary btn-xs"
                                            @click="applySuggestedPeriod"
                                        >
                                            この期間を反映
                                        </button>
                                    </dd>
                                </div>
                            </dl>

                            <div v-if="productLoanSchedule.items?.length" class="schedule-table-wrap">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>終了予定</th>
                                            <th>開始</th>
                                            <th>order_type</th>
                                            <th>loanerID</th>
                                            <th>SN</th>
                                            <th>dealer</th>
                                            <th>orderID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in productLoanSchedule.items"
                                            :key="`${item.attachedId}-${item.endDate}`"
                                        >
                                            <td>{{ item.endDate }}</td>
                                            <td>{{ item.startDate || '—' }}</td>
                                            <td>{{ item.order_type || '—' }}</td>
                                            <td>{{ item.loanerID || '—' }}</td>
                                            <td>{{ item.SN || '—' }}</td>
                                            <td>{{ item.dealer || '—' }}</td>
                                            <td>{{ item.associatedID || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="field-hint">現在以降の同機種貸出予定はありません。</p>
                        </template>
                    </div>
                </section>

                <section class="notes-panel">
                    <div class="notes-panel-heading">
                        <h2 class="card-title notes-title">
                            Notes（{{ sharedNotes.length }}件）
                            <span v-if="tbcNotesCount > 0" class="notes-tbc-count">要確認（{{ tbcNotesCount }}件）</span>
                        </h2>
                        <div class="notes-actions">
                            <button
                                type="button"
                                class="select-btn"
                                :disabled="!canModifySelectedNote"
                                :title="noteEditDeleteTitle"
                                @click="openNoteEdit"
                            >
                                編集
                            </button>
                            <button
                                type="button"
                                class="select-btn delete-btn"
                                :disabled="!canModifySelectedNote"
                                :title="noteEditDeleteTitle"
                                @click="openNoteDelete"
                            >
                                削除
                            </button>
                            <button type="button" class="select-btn add-btn" @click="openNoteCreate">
                                新規追加
                            </button>
                        </div>
                    </div>
                    <p v-if="noteError" class="inline-error">{{ noteError }}</p>
                    <div class="notes-shell">
                        <NotesTable
                            v-model:selected-id="selectedNoteId"
                            :notes="sharedNotes"
                            :record-order-id="attachedLocal.associatedID"
                            :show-confirm-status="true"
                            :current-user-name="authUserName"
                            @edit="openNoteEdit"
                        />
                    </div>
                </section>
            </section>

            <section class="info-card info-card-calendar">
                <div class="calendar-panel calendar-panel-solo">
                    <div class="calendar-panel-heading">
                        <h3 class="section-title calendar-title">予約カレンダー</h3>
                        <span class="calendar-help">予定を移動／左右端で期間変更</span>
                    </div>
                    <p v-if="calendarError" class="inline-error">{{ calendarError }}</p>
                    <p v-if="!calendarFilterReady" class="field-hint">
                        productName / loanerID が未設定のためカレンダーを表示できません。
                    </p>
                    <div v-else class="calendar-shell">
                        <FullCalendar ref="calendarRef" :options="calendarOptions" />
                    </div>
                </div>
            </section>
        </div>

        <IntakeMasterSelectDialog
            v-if="showDealerSelect"
            kind="dealer"
            :items="dealers"
            :initial-value="dealerInitialValue"
            :initial-search-query="form.dealer"
            @close="showDealerSelect = false"
            @selected="onDealerSelected"
        />

        <div v-if="showNoteDialog" class="confirm-overlay" @click.self="closeNoteDialog">
            <div class="confirm-panel note-edit-panel">
                <h3>{{ noteDialogMode === 'edit' ? 'Note 編集' : 'Note 新規追加' }}</h3>
                <p class="note-order-id">OrderID: {{ attachedLocal.associatedID }}</p>
                <label class="note-field">
                    内容
                    <textarea v-model="noteForm.note" rows="6"></textarea>
                </label>
                <label class="note-check">
                    <input v-model="noteForm.important" type="checkbox">
                    重要
                </label>
                <div class="confirm-toggles">
                    <button
                        type="button"
                        class="toggle-btn"
                        :class="{ on: noteForm.tbc }"
                        @click="toggleNoteTbc"
                    >
                        要
                    </button>
                    <button
                        v-if="noteForm.tbc"
                        type="button"
                        class="toggle-btn toggle-btn-done"
                        :class="{ on: noteForm.done }"
                        @click="noteForm.done = !noteForm.done"
                    >
                        済
                    </button>
                </div>
                <p v-if="noteDialogError" class="inline-error">{{ noteDialogError }}</p>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" :disabled="noteSaving" @click="closeNoteDialog">
                        キャンセル
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="noteSaving || !String(noteForm.note || '').trim()"
                        @click="saveNote"
                    >
                        {{ noteSaving ? '保存中...' : '保存' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="notePendingDelete" class="confirm-overlay" @click.self="notePendingDelete = null">
            <div class="confirm-panel">
                <h3>Note 削除</h3>
                <p>この Note を削除しますか？</p>
                <p class="note-delete-preview">{{ notePendingDelete.note }}</p>
                <p v-if="noteError" class="inline-error">{{ noteError }}</p>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" :disabled="noteDeleting" @click="notePendingDelete = null">
                        キャンセル
                    </button>
                    <button type="button" class="btn delete-btn" :disabled="noteDeleting" @click="deleteNote">
                        {{ noteDeleting ? '削除中...' : '削除' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import {
    handleMonthCellDoubleClickToDayView,
    ROLLING_MONTH_VIEW,
    fullCalendarDayCellClassNames,
    rollingMonthViewConfig,
} from '@/utils/fullCalendarCommon'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import IntakeMasterSelectDialog from '@/components/ServiceRecord/Intake/IntakeMasterSelectDialog.vue'
import NotesTable from '@/components/ServiceRecord/NotesTable.vue'
import { apiFetch } from '@/utils/apiFetch'
import { loanerStatusOptionLabel } from '@/utils/loanerStatusLabel'

const props = defineProps({
    attached: {
        type: Object,
        required: true,
    },
    parentRecord: {
        type: Object,
        default: null,
    },
    productLoanSchedule: {
        type: Object,
        default: null,
    },
    notes: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    dealersMaster: {
        type: Array,
        default: () => [],
    },
    dateFields: {
        type: Object,
        default: () => ({
            hasPlannedSent: false,
            hasPlannedReturned: false,
        }),
    },
})

const page = usePage()
const saving = ref(false)
const error = ref('')
const success = ref('')
const showDealerSelect = ref(false)

const attachedLocal = reactive({ ...props.attached })
const parentLocal = ref(props.parentRecord ? { ...props.parentRecord } : null)

const noteItems = ref([...(props.notes ?? [])])
const selectedNoteId = ref(null)
const showNoteDialog = ref(false)
const noteDialogMode = ref('create')
const editingNoteId = ref(null)
const noteSaving = ref(false)
const noteDialogError = ref('')
const noteForm = reactive({
    note: '',
    important: false,
    tbc: false,
    done: false,
})
const notePendingDelete = ref(null)
const noteDeleting = ref(false)
const noteError = ref('')

const calendarRef = ref(null)
const calendarError = ref('')

const STAKEHOLDER_FIELDS = {
    dealer: ['dealer', 'dealer_depart', 'contactPerson', 'phone', 'email', 'zipcode', 'address1', 'address2'],
    endUser: ['endUser', 'endUser_depart', 'endUser_contactPerson', 'endUser_phone', 'endUser_email', 'endUser_zipcode', 'endUser_address1', 'endUser_address2'],
    delivery: [
        'deliveryDestination_company',
        'deliveryDestination_depart',
        'deliveryDestination_contactPerson',
        'deliveryDestination_phone',
        'deliveryDestination_email',
        'deliveryDestination_zipcode',
        'deliveryDestination_address1',
        'deliveryDestination_address2',
    ],
}

const form = reactive({
    plannedSentDate: props.attached.plannedSentDate || '',
    plannedReturnedDate: props.attached.plannedReturnedDate || '',
    status: props.attached.status != null ? String(props.attached.status) : '',
    enduser_SN: props.attached.enduser_SN != null && props.attached.enduser_SN !== ''
        ? String(props.attached.enduser_SN)
        : '',
    dealer: props.attached.dealer || '',
    dealer_depart: props.attached.dealer_depart || '',
    contactPerson: props.attached.contactPerson || '',
    phone: props.attached.phone || '',
    email: props.attached.email || '',
    zipcode: props.attached.zipcode || '',
    address1: props.attached.address1 || '',
    address2: props.attached.address2 || '',
    endUser: props.attached.endUser || '',
    endUser_depart: props.attached.endUser_depart || '',
    endUser_contactPerson: props.attached.endUser_contactPerson || '',
    endUser_phone: props.attached.endUser_phone || '',
    endUser_email: props.attached.endUser_email || '',
    endUser_zipcode: props.attached.endUser_zipcode || '',
    endUser_address1: props.attached.endUser_address1 || '',
    endUser_address2: props.attached.endUser_address2 || '',
    deliveryDestination_company: props.attached.deliveryDestination_company || '',
    deliveryDestination_depart: props.attached.deliveryDestination_depart || '',
    deliveryDestination_contactPerson: props.attached.deliveryDestination_contactPerson || '',
    deliveryDestination_phone: props.attached.deliveryDestination_phone || '',
    deliveryDestination_email: props.attached.deliveryDestination_email || '',
    deliveryDestination_zipcode: props.attached.deliveryDestination_zipcode || '',
    deliveryDestination_address1: props.attached.deliveryDestination_address1 || '',
    deliveryDestination_address2: props.attached.deliveryDestination_address2 || '',
})

const zipLookupTimers = {
    dealer: null,
    endUser: null,
    delivery: null,
}

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const adminUrl = computed(() => {
    const orderType = attachedLocal.order_type === 'waiting_list' ? 'waiting_list' : 'loaner'
    return `${page.props.appBaseUrl}/servicerecord/administrator?orderType=${encodeURIComponent(orderType)}`
})
const calendarUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/loaner/calendar`)
const statuses = computed(() => props.statuses ?? [])
const dealers = computed(() => props.dealersMaster ?? [])
const dealerInitialValue = computed(() => {
    const matched = dealers.value.find(item => item.dealerName === form.dealer)
    return matched?.id ?? null
})

const authUserName = computed(() => String(page.props.auth?.user?.kanji_name ?? '').trim())
const sharedNotes = computed(() =>
    noteItems.value.filter(note => !(note?.personal === true || note?.personal === 1 || note?.personal === '1')),
)
const tbcNotesCount = computed(() =>
    sharedNotes.value.filter((note) => {
        const tbc = note?.tbc === true || note?.tbc === 1 || note?.tbc === '1'
        const done = note?.done === true || note?.done === 1 || note?.done === '1'
        return tbc && !done
    }).length,
)
const selectedNote = computed(() =>
    sharedNotes.value.find(note => Number(note.id) === Number(selectedNoteId.value)) || null,
)
const canModifySelectedNote = computed(() => !!selectedNote.value && isNoteOwner(selectedNote.value))
const noteEditDeleteTitle = computed(() => {
    if (!selectedNoteId.value) return 'Note を選択してください'
    if (!selectedNote.value) return 'Note を選択してください'
    if (!isNoteOwner(selectedNote.value)) {
        return `自分が書いた Note のみ編集・削除できます（ログイン: ${authUserName.value || '不明'} / 記入者: ${selectedNote.value.whoWrote || '不明'}）`
    }
    return ''
})

const calendarFilterReady = computed(() => {
    const productName = String(attachedLocal.productName ?? '').trim()
    const loanerId = attachedLocal.loanerID
    return productName !== '' || (loanerId != null && loanerId !== '')
})

const calendarOptions = {
    plugins: [dayGridPlugin, listPlugin, interactionPlugin],
    initialView: ROLLING_MONTH_VIEW,
    locale: 'ja',
    firstDay: 0,
    height: '100%',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: `${ROLLING_MONTH_VIEW},listMonth`,
    },
    buttonText: { today: '今日', list: 'リスト' },
    views: {
        [ROLLING_MONTH_VIEW]: {
            ...rollingMonthViewConfig,
        },
    },
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    dayMaxEvents: true,
    dayCellClassNames: fullCalendarDayCellClassNames,
    dateClick: handleMonthCellDoubleClickToDayView,
    events: fetchCalendarEvents,
    eventDrop: updateEventPeriod,
    eventResize: updateEventPeriod,
    eventClick(info) {
        if (!info.event.id || String(info.event.id) === String(props.attached.id)) return
        const orderId = info.event.extendedProps?.associatedID || info.event.id
        window.location.href = `${page.props.appBaseUrl}/servicerecord/loaner/detail/${orderId}`
    },
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function isTruthyFlag(value) {
    return value === true || value === 1 || value === '1'
}

function toggleNoteTbc() {
    noteForm.tbc = !noteForm.tbc
    if (!noteForm.tbc) noteForm.done = false
}

function isNoteOwner(note) {
    if (!note) return false
    const who = String(note.whoWrote ?? '').trim()
    if (!who) return false
    if (note.is_mine === true || note.is_mine === 1 || note.is_mine === '1') return true
    return authUserName.value !== '' && authUserName.value === who
}

function openNoteCreate() {
    noteDialogMode.value = 'create'
    editingNoteId.value = null
    noteForm.note = ''
    noteForm.important = false
    noteForm.tbc = false
    noteForm.done = false
    noteDialogError.value = ''
    showNoteDialog.value = true
}

function openNoteEdit() {
    const note = selectedNote.value
    if (!note) return
    if (!isNoteOwner(note)) {
        window.alert(`自分が書いた Note のみ編集できます。\nログイン: ${authUserName.value || '不明'}\n記入者: ${note.whoWrote || '不明'}`)
        return
    }
    noteDialogMode.value = 'edit'
    editingNoteId.value = note.id
    noteForm.note = note.note ?? ''
    noteForm.important = !!note.important
    noteForm.tbc = isTruthyFlag(note.tbc)
    noteForm.done = noteForm.tbc && isTruthyFlag(note.done)
    noteDialogError.value = ''
    showNoteDialog.value = true
}

function closeNoteDialog() {
    if (noteSaving.value) return
    showNoteDialog.value = false
    noteDialogError.value = ''
}

async function saveNote() {
    const text = String(noteForm.note ?? '').trim()
    if (!text) {
        noteDialogError.value = '内容を入力してください。'
        return
    }

    noteSaving.value = true
    noteDialogError.value = ''
    try {
        const isEdit = noteDialogMode.value === 'edit'
        const url = isEdit
            ? `${page.props.appBaseUrl}/servicerecord/notes/${editingNoteId.value}`
            : `${page.props.appBaseUrl}/servicerecord/notes`
        const tbc = noteForm.tbc ? true : null
        const done = noteForm.tbc && noteForm.done ? true : null
        const body = isEdit
            ? { note: text, important: !!noteForm.important, tbc, done }
            : {
                associatedID: attachedLocal.associatedID,
                note: text,
                important: !!noteForm.important,
                personal: false,
                tbc,
                done,
            }
        const result = await apiFetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        })
        if (!result) throw new Error('Note の保存に失敗しました。')
        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `Note の保存に失敗しました。（HTTP ${response.status}）`)
        }

        const saved = data.note
        if (isEdit) {
            noteItems.value = noteItems.value.map(note =>
                Number(note.id) === Number(saved.id) ? saved : note,
            )
        } else {
            noteItems.value = [saved, ...noteItems.value]
            selectedNoteId.value = saved.id
        }
        showNoteDialog.value = false
        success.value = data.message || 'Note を保存しました。'
    } catch (e) {
        noteDialogError.value = e.message || 'Note の保存に失敗しました。'
    } finally {
        noteSaving.value = false
    }
}

function openNoteDelete() {
    const note = selectedNote.value
    if (!note) return
    if (!isNoteOwner(note)) {
        window.alert(`自分が書いた Note のみ削除できます。\nログイン: ${authUserName.value || '不明'}\n記入者: ${note.whoWrote || '不明'}`)
        return
    }
    noteError.value = ''
    notePendingDelete.value = note
}

async function deleteNote() {
    if (!notePendingDelete.value) return
    noteDeleting.value = true
    noteError.value = ''
    try {
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/notes/${notePendingDelete.value.id}`,
            {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            },
        )
        if (!result) throw new Error('Note の削除に失敗しました。')
        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `Note の削除に失敗しました。（HTTP ${response.status}）`)
        }
        const deletedId = notePendingDelete.value.id
        noteItems.value = noteItems.value.filter(note => Number(note.id) !== Number(deletedId))
        if (Number(selectedNoteId.value) === Number(deletedId)) selectedNoteId.value = null
        notePendingDelete.value = null
        success.value = data.message || 'Note を削除しました。'
    } catch (e) {
        noteError.value = e.message || 'Note の削除に失敗しました。'
    } finally {
        noteDeleting.value = false
    }
}

async function fetchCalendarEvents(info, successCallback, failureCallback) {
    calendarError.value = ''
    try {
        const params = new URLSearchParams({
            start: info.startStr.slice(0, 10),
            end: info.endStr.slice(0, 10),
        })
        const productName = String(attachedLocal.productName ?? '').trim()
        if (productName) {
            params.set('productName', productName)
        } else if (attachedLocal.loanerID != null && attachedLocal.loanerID !== '') {
            params.set('loanerID', String(attachedLocal.loanerID))
        }
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/calendar/events?${params.toString()}`,
        )
        if (!result) return successCallback([])
        const { response, data } = result
        if (!response.ok) throw new Error(data.message || `カレンダー取得に失敗しました。（HTTP ${response.status}）`)
        successCallback(data.events ?? [])
    } catch (e) {
        calendarError.value = e.message || 'カレンダー取得に失敗しました。'
        failureCallback(e)
    }
}

function toLocalYmd(value) {
    if (!value) return null
    if (typeof value === 'string') return value.slice(0, 10)
    const year = value.getFullYear()
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

function addLocalDays(ymd, amount) {
    if (!ymd) return null
    const [year, month, day] = ymd.split('-').map(Number)
    const date = new Date(year, month - 1, day, 12)
    date.setDate(date.getDate() + amount)
    return toLocalYmd(date)
}

function eventDates(event) {
    const start = toLocalYmd(event.startStr || event.start)
    const exclusiveEnd = toLocalYmd(event.endStr || event.end)
    return start ? { start, end: exclusiveEnd ? addLocalDays(exclusiveEnd, -1) : start } : null
}

async function updateEventPeriod(changeInfo) {
    const dates = eventDates(changeInfo.event)
    if (!dates || dates.end < dates.start) {
        changeInfo.revert()
        calendarError.value = '移動後の期間が不正です。'
        return
    }

    const ext = changeInfo.event.extendedProps ?? {}
    const payload = {
        sentDate: props.dateFields.hasPlannedSent ? (ext.sentDate || null) : dates.start,
        returnedDate: props.dateFields.hasPlannedReturned ? (ext.returnedDate || null) : dates.end,
        comment: ext.comment || null,
    }
    if (props.dateFields.hasPlannedSent) payload.plannedSentDate = dates.start
    if (props.dateFields.hasPlannedReturned) payload.plannedReturnedDate = dates.end

    calendarError.value = ''
    try {
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/period/${changeInfo.event.id}`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify(payload),
            },
        )
        if (!result) {
            changeInfo.revert()
            return
        }
        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `期間更新に失敗しました。（HTTP ${response.status}）`)
        }
        changeInfo.event.setExtendedProp('plannedSentDate', dates.start)
        changeInfo.event.setExtendedProp('plannedReturnedDate', dates.end)
        if (String(changeInfo.event.id) === String(props.attached.id)) {
            form.plannedSentDate = dates.start
            form.plannedReturnedDate = dates.end
            if (data.attached) {
                form.plannedSentDate = data.attached.plannedSentDate || dates.start
                form.plannedReturnedDate = data.attached.plannedReturnedDate || dates.end
            }
        }
        success.value = `貸出期間を更新しました。（${dates.start} 〜 ${dates.end}）`
    } catch (e) {
        changeInfo.revert()
        calendarError.value = e.message || '期間更新に失敗しました。'
    }
}

function updateCalendarSize() {
    calendarRef.value?.getApi?.().updateSize()
}

function readStakeholder(kind) {
    return Object.fromEntries(STAKEHOLDER_FIELDS[kind].map(key => [key, form[key]]))
}

function writeStakeholder(kind, values) {
    STAKEHOLDER_FIELDS[kind].forEach((key) => {
        form[key] = values[key] ?? ''
    })
}

function swapStakeholders(left, right) {
    const leftValues = readStakeholder(left)
    const rightValues = readStakeholder(right)
    writeStakeholder(left, mapStakeholderValues(right, left, rightValues))
    writeStakeholder(right, mapStakeholderValues(left, right, leftValues))
}

function mapStakeholderValues(fromKind, toKind, values) {
    const fromKeys = STAKEHOLDER_FIELDS[fromKind]
    const toKeys = STAKEHOLDER_FIELDS[toKind]
    const mapped = {}
    toKeys.forEach((toKey, index) => {
        mapped[toKey] = values[fromKeys[index]] ?? ''
    })
    return mapped
}

function openDealerSelect() {
    showDealerSelect.value = true
}

function onDealerSelected(result) {
    form.dealer = result.dealer ?? ''
    form.dealer_depart = result.dealer_depart ?? ''
    form.contactPerson = result.contactPerson ?? ''
    form.email = result.email ?? ''
    form.phone = result.phone ?? ''
    form.zipcode = result.zipcode ?? form.zipcode
    form.address1 = result.address1 ?? form.address1
    form.address2 = result.address2 ?? form.address2
    showDealerSelect.value = false
}

function applySuggestedPeriod() {
    if (!props.productLoanSchedule?.suggestedStartDate) return
    form.plannedSentDate = props.productLoanSchedule.suggestedStartDate
    form.plannedReturnedDate = props.productLoanSchedule.suggestedEndDate || ''
}

async function fetchAddressByZipcode(zipcode) {
    const digits = String(zipcode ?? '').replace(/\D/g, '')
    if (digits.length !== 7) return null

    const response = await fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${digits}`)
    if (!response.ok) {
        throw new Error('郵便番号の検索に失敗しました。')
    }

    const data = await response.json()
    const result = data?.results?.[0]
    if (!result) return null

    return {
        address1: result.address1 || '',
        address2: `${result.address2 || ''}${result.address3 || ''}`,
    }
}

function onZipcodeInput(kind) {
    if (zipLookupTimers[kind]) {
        clearTimeout(zipLookupTimers[kind])
    }

    zipLookupTimers[kind] = setTimeout(async () => {
        try {
            const zipcode = kind === 'dealer'
                ? form.zipcode
                : kind === 'endUser'
                    ? form.endUser_zipcode
                    : form.deliveryDestination_zipcode
            const address = await fetchAddressByZipcode(zipcode)
            if (!address) return
            if (kind === 'dealer') {
                form.address1 = address.address1
                form.address2 = address.address2
            } else if (kind === 'endUser') {
                form.endUser_address1 = address.address1
                form.endUser_address2 = address.address2
            } else {
                form.deliveryDestination_address1 = address.address1
                form.deliveryDestination_address2 = address.address2
            }
        } catch (e) {
            error.value = e.message || '郵便番号の検索に失敗しました。'
        }
    }, 350)
}

async function save() {
    error.value = ''
    success.value = ''

    if (
        form.plannedSentDate
        && form.plannedReturnedDate
        && form.plannedReturnedDate < form.plannedSentDate
    ) {
        error.value = 'plannedReturnedDate は plannedSentDate 以降にしてください。'
        return
    }

    saving.value = true

    try {
        const body = {
            dealer: form.dealer || null,
            dealer_depart: form.dealer_depart || null,
            contactPerson: form.contactPerson || null,
            phone: form.phone || null,
            email: form.email || null,
            zipcode: form.zipcode || null,
            address1: form.address1 || null,
            address2: form.address2 || null,
            endUser: form.endUser || null,
            endUser_depart: form.endUser_depart || null,
            endUser_contactPerson: form.endUser_contactPerson || null,
            endUser_phone: form.endUser_phone || null,
            endUser_email: form.endUser_email || null,
            endUser_zipcode: form.endUser_zipcode || null,
            endUser_address1: form.endUser_address1 || null,
            endUser_address2: form.endUser_address2 || null,
            deliveryDestination_company: form.deliveryDestination_company || null,
            deliveryDestination_depart: form.deliveryDestination_depart || null,
            deliveryDestination_contactPerson: form.deliveryDestination_contactPerson || null,
            deliveryDestination_phone: form.deliveryDestination_phone || null,
            deliveryDestination_email: form.deliveryDestination_email || null,
            deliveryDestination_zipcode: form.deliveryDestination_zipcode || null,
            deliveryDestination_address1: form.deliveryDestination_address1 || null,
            deliveryDestination_address2: form.deliveryDestination_address2 || null,
            enduser_SN: form.enduser_SN === '' || form.enduser_SN == null
                ? null
                : String(form.enduser_SN).trim(),
        }
        if (props.dateFields.hasPlannedSent) {
            body.plannedSentDate = form.plannedSentDate || null
        }
        if (props.dateFields.hasPlannedReturned) {
            body.plannedReturnedDate = form.plannedReturnedDate || null
        }
        if (attachedLocal.order_type === 'loaner') {
            body.status = form.status === '' ? null : Number(form.status)
        }

        const url = `${page.props.appBaseUrl}/servicerecord/loaner/period/${props.attached.id}`
        const result = await apiFetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        })

        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        success.value = data.message || '貸出期間を更新しました。'
        if (data.attached) {
            form.plannedSentDate = data.attached.plannedSentDate || ''
            form.plannedReturnedDate = data.attached.plannedReturnedDate || ''
            if (Object.prototype.hasOwnProperty.call(data.attached, 'enduser_SN')) {
                form.enduser_SN = data.attached.enduser_SN || ''
                attachedLocal.enduser_SN = data.attached.enduser_SN
            }
            if (Object.prototype.hasOwnProperty.call(data.attached, 'status')) {
                form.status = data.attached.status != null ? String(data.attached.status) : ''
                attachedLocal.status = data.attached.status
            }
            if (data.record) {
                form.dealer = data.record.dealer || ''
                form.dealer_depart = data.record.dealer_depart || ''
                form.contactPerson = data.record.contactPerson || ''
                form.phone = data.record.phone || ''
                form.email = data.record.email || ''
                form.zipcode = data.record.zipcode || ''
                form.address1 = data.record.address1 || ''
                form.address2 = data.record.address2 || ''
                form.endUser = data.record.endUser || ''
                form.endUser_depart = data.record.endUser_depart || ''
                form.endUser_contactPerson = data.record.endUser_contactPerson || ''
                form.endUser_phone = data.record.endUser_phone || ''
                form.endUser_email = data.record.endUser_email || ''
                form.endUser_zipcode = data.record.endUser_zipcode || ''
                form.endUser_address1 = data.record.endUser_address1 || ''
                form.endUser_address2 = data.record.endUser_address2 || ''
                form.deliveryDestination_company = data.record.deliveryDestination_company || ''
                form.deliveryDestination_depart = data.record.deliveryDestination_depart || ''
                form.deliveryDestination_contactPerson = data.record.deliveryDestination_contactPerson || ''
                form.deliveryDestination_phone = data.record.deliveryDestination_phone || ''
                form.deliveryDestination_email = data.record.deliveryDestination_email || ''
                form.deliveryDestination_zipcode = data.record.deliveryDestination_zipcode || ''
                form.deliveryDestination_address1 = data.record.deliveryDestination_address1 || ''
                form.deliveryDestination_address2 = data.record.deliveryDestination_address2 || ''
            }
        }
        nextTick(() => calendarRef.value?.getApi?.().refetchEvents())
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    window.addEventListener('resize', updateCalendarSize)
    nextTick(updateCalendarSize)
})
onBeforeUnmount(() => window.removeEventListener('resize', updateCalendarSize))
</script>

<style scoped>
.period-page {
    min-height: 100vh;
    height: 100vh;
    padding: 12px 16px 16px;
    background: #888888;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
    flex: 0 0 auto;
    min-height: 52px;
}

.header-title {
    flex: 0 0 auto;
}

.page-header h1 {
    margin: 0 0 4px;
    font-size: 22px;
    color: #1e293b;
}

.subtitle {
    margin: 0;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
}

.header-message {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    align-items: center;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: nowrap;
    flex: 0 0 auto;
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

.btn-primary {
    background: #2563eb;
}

.btn-secondary {
    background: #64748b;
}

.btn-xs {
    padding: 4px 8px;
    font-size: 11px;
    margin-left: 8px;
}

.btn-primary:disabled,
.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.content-grid {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) minmax(320px, 1.2fr);
    gap: 12px;
    align-items: stretch;
    flex: 1 1 auto;
    min-height: 0;
}

.info-card {
    background: #cccccc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 14px;
}

.info-card-calendar {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    padding: 10px;
}

.content-grid > .info-card:first-child {
    min-height: 0;
    overflow: auto;
}

.period-inline-card {
    margin-top: 12px;
    padding: 12px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #bbbbbb;
}

.period-inline-card > .card-title {
    margin-bottom: 8px;
}

.period-dates-row {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    gap: 16px;
    align-items: center;
}

.period-dates-row .field {
    flex: 1 1 0;
    min-width: 0;
    flex-direction: row;
    align-items: center;
    gap: 8px;
}

.period-dates-row .field span {
    flex: 0 0 auto;
    white-space: nowrap;
}

.period-dates-row .field input {
    flex: 1 1 auto;
    min-width: 0;
}

.period-status-row {
    margin-top: 10px;
}

.period-waiting-hint {
    margin: 0;
}

.card-title {
    margin: 0 0 10px;
    font-size: 15px;
    color: #0f172a;
}

.meta-grid-wrap {
    margin: 0 0 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.meta-grid {
    margin: 0;
    display: grid;
    gap: 8px;
}

.meta-grid-row1 {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.meta-grid-row2 {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.meta-grid > div {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 6px 8px;
    background: #f1f5f9;
    border-radius: 4px;
    font-size: 13px;
    min-width: 0;
}

.meta-grid-row2 > div {
    flex-direction: row;
    align-items: center;
    gap: 8px;
    padding: 4px 8px;
}

.meta-grid-row2 dt {
    flex: 0 0 auto;
    white-space: nowrap;
}

.meta-grid-row2 dd {
    flex: 1 1 auto;
    min-width: 0;
}

.meta-input {
    width: 100%;
    box-sizing: border-box;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    background: #fff;
}

.meta-grid dt {
    margin: 0;
    color: #64748b;
    font-weight: 700;
}

.meta-grid dd {
    margin: 0;
    color: #1e293b;
    font-weight: 700;
    word-break: break-word;
}

.parent-note {
    color: #64748b;
    font-weight: 600;
}

.loaner-stakeholder-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.stakeholder-card {
    display: grid;
    grid-template-columns: 88px minmax(0, 1fr);
    gap: 8px;
    border: 1px solid #000;
    border-radius: 6px;
    padding: 8px;
    background: #aaaaaa;
}

.stakeholder-side {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.stakeholder-label {
    padding: 6px 8px;
    background: #334155;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
    border-radius: 4px;
}

.switch-btn {
    border: 1px solid #475569;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 6px;
    border-radius: 4px;
    cursor: pointer;
}

.stakeholder-body {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.form-row {
    display: grid;
    gap: 6px;
}

.row-full {
    grid-template-columns: 1fr;
}

.row-dealer-top {
    grid-template-columns: 80px minmax(0, 1fr);
}

.row-contact {
    grid-template-columns: minmax(140px, 1fr);
}

.row-phone-email {
    grid-template-columns: minmax(120px, 0.8fr) minmax(0, 1.4fr);
}

.row-zip {
    grid-template-columns: 120px;
}

.row-address {
    grid-template-columns: minmax(120px, 0.8fr) minmax(0, 1.4fr);
}

.field-button,
.stakeholder-body input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #64748b;
    border-radius: 4px;
    padding: 6px 8px;
    font-size: 12px;
    font-weight: 700;
    background: #fff;
}

.field-button-pick {
    width: 80px;
    max-width: 80px;
    min-width: 80px;
    padding-left: 4px;
    padding-right: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    background: #e2e8f0;
}

.form-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.field input,
.field select,
.field textarea {
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
}

.field-on-white input,
.field-on-white select {
    background: #fff;
}

.field-hint {
    margin: 0;
    color: #64748b;
    font-size: 12px;
}

.section-title {
    margin: 0 0 8px;
    font-size: 13px;
    color: #92400e;
}

.schedule-box {
    margin: 4px 0 8px;
    padding: 12px;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    background: #fffbeb;
}

.schedule-summary {
    margin: 0 0 10px;
    display: grid;
    gap: 8px;
}

.schedule-summary div {
    display: grid;
    grid-template-columns: 160px 1fr;
    gap: 8px;
    align-items: center;
    font-size: 13px;
}

.schedule-summary dt {
    margin: 0;
    color: #92400e;
}

.schedule-summary dd {
    margin: 0;
    color: #1e293b;
    font-weight: 700;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

.schedule-table-wrap {
    overflow: auto;
    border: 1px solid #fde68a;
    border-radius: 4px;
    background: #fff;
}

.schedule-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.schedule-table th,
.schedule-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #fef3c7;
    text-align: left;
}

.schedule-table th {
    background: #fef3c7;
    color: #92400e;
}

.global-error,
.global-success {
    margin: 0;
    padding: 8px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
    width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.global-error {
    background: #fee2e2;
    color: #b91c1c;
}

.global-success {
    background: #dcfce7;
    color: #166534;
}

.notes-panel {
    margin-top: 12px;
    padding: 10px;
    border: 1px solid #86efac;
    border-radius: 6px;
    background: #aaaaaa;
}

.notes-panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.notes-title {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.notes-tbc-count {
    font-size: 12px;
    color: #dc2626;
}

.notes-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.select-btn {
    min-height: 28px;
    padding: 4px 10px;
    border: 1px solid #94a3b8;
    border-radius: 3px;
    background: #fff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.select-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.select-btn.add-btn {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.select-btn.delete-btn,
.btn.delete-btn {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}

.notes-shell {
    min-height: 180px;
    max-height: 320px;
    overflow: auto;
    background: #fff;
    border: 1px solid #bbf7d0;
    border-radius: 4px;
}

.calendar-panel {
    margin-top: 0;
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.calendar-panel-solo {
    height: 100%;
}

.calendar-panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
    flex-wrap: wrap;
    flex: 0 0 auto;
}

.calendar-title {
    margin: 0;
    color: #9f1239;
}

.calendar-help {
    color: #64748b;
    font-size: 11px;
}

.calendar-shell {
    flex: 1 1 auto;
    min-height: 240px;
    height: auto;
    overflow: hidden;
    background: #fff;
    border: 1px solid #fecaca;
    border-radius: 4px;
    padding: 4px;
}

.inline-error {
    margin: 0 0 6px;
    color: #b91c1c;
    font-size: 12px;
}

.confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 80;
    padding: 16px;
}

.confirm-panel {
    width: min(480px, 100%);
    background: #fff;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 16px;
}

.confirm-panel h3 {
    margin: 0 0 8px;
    font-size: 16px;
}

.note-delete-preview {
    margin: 8px 0;
    padding: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 13px;
}

.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
}

.note-order-id {
    margin: 0 0 10px;
    color: #64748b;
    font-size: 13px;
}

.note-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.note-field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    padding: 8px;
    font-size: 13px;
    font-weight: 700;
    resize: vertical;
}

.note-check {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 10px 0 8px;
    font-size: 13px;
    font-weight: 700;
}

.confirm-toggles {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.toggle-btn {
    min-width: 40px;
    min-height: 28px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #475569;
    font-weight: 700;
    cursor: pointer;
}

.toggle-btn.on {
    background: #fef3c7;
    border-color: #f59e0b;
    color: #92400e;
}

.toggle-btn-done.on {
    background: #dcfce7;
    border-color: #16a34a;
    color: #166534;
}

@media (max-width: 960px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}
</style>
