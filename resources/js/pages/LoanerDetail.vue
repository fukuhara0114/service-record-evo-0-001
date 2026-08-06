<template>
    <div class="loaner-detail-page">
        <header class="page-header">
            <strong>OrderID: {{ record.orderID }}</strong>
            <div class="header-actions">
                <span v-if="success" class="save-message success">{{ success }}</span>
                <span v-if="error" class="save-message error">{{ error }}</span>
                <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
                    {{ saving ? '保存中...' : '保存' }}
                </button>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="closePage">閉じる</button>
            </div>
        </header>

        <Splitpanes class="default-theme outer-splitpanes" @resized="onPanesResized">
            <Pane :size="leftPaneSize" :min-size="32" class="main-pane">
                <div class="left-pane">
                    <section class="panel loaner-panel">
                        <div class="panel-heading">
                            <h2>貸出情報</h2>
                            <button type="button" class="select-btn" @click="activeSelectKind = 'loanerUnit'">
                                貸出機を選択
                            </button>
                        </div>
                        <div class="compact-grid loaner-grid">
                            <label><span>親OrderID</span><input v-model="form.parentID" type="number"></label>
                            <label class="span-2">
                                <span>製品名</span>
                                <button type="button" class="master-value" @click="activeSelectKind = 'loanerUnit'">
                                    {{ form.productName || '選択してください' }}
                                </button>
                            </label>
                            <label><span>loanerID</span><input :value="form.loanerID" type="text" readonly></label>
                            <label><span>品目</span><input :value="selectedUnit?.item || loanerMaster?.item || ''" type="text" readonly></label>
                            <label><span>S/N</span><input v-model="form.SN" type="text"></label>
                            <label><span>管理番号</span><input :value="selectedUnit?.manageNum || loanerMaster?.manageNum || ''" type="text" readonly></label>
                            <label><span>グループ</span><input :value="selectedUnit?.groupName || loanerMaster?.groupName || ''" type="text" readonly></label>
                            <label><span>案件種別</span><input :value="record.order_type || ''" type="text" readonly></label>
                            <label v-if="record.order_type === 'loaner'">
                                <span>status</span>
                                <select v-model="form.status">
                                    <option value="">選択してください</option>
                                    <option v-for="status in statuses" :key="status.processID" :value="String(status.processID)">
                                        {{ status.status }} ({{ status.processID }})
                                    </option>
                                </select>
                            </label>
                            <label><span>割当状態</span><input v-model="form.assignStatus" type="text"></label>
                            <label class="span-2"><span>コメント</span><input v-model="form.comment" type="text"></label>
                        </div>
                    </section>

                    <div class="people-row">
                        <section class="panel person-panel">
                            <div class="panel-heading">
                                <h2>依頼者</h2>
                                <button type="button" class="select-btn" @click="activeSelectKind = 'dealer'">マスター選択</button>
                            </div>
                            <div class="compact-grid">
                                <label><span>会社名</span><input v-model="form.dealer" type="text"></label>
                                <label><span>部署名</span><input v-model="form.dealer_depart" type="text"></label>
                                <label><span>担当者</span><input v-model="form.contactPerson" type="text"></label>
                                <label><span>電話</span><input v-model="form.phone" type="text"></label>
                                <label><span>E-mail</span><input v-model="form.email" type="text"></label>
                                <label><span>FAX</span><input v-model="form.fax" type="text"></label>
                                <label><span>郵便番号</span><input v-model="form.zipcode" type="text"></label>
                                <label><span>住所1</span><input v-model="form.address1" type="text"></label>
                                <label class="span-2"><span>住所2</span><input v-model="form.address2" type="text"></label>
                            </div>
                        </section>

                        <section class="panel person-panel">
                            <div class="panel-heading">
                                <h2>発送先</h2>
                            </div>
                            <div class="compact-grid">
                                <label><span>会社名</span><input v-model="form.deliveryDestination_company" type="text"></label>
                                <label><span>部署名</span><input v-model="form.deliveryDestination_depart" type="text"></label>
                                <label><span>担当者</span><input v-model="form.deliveryDestination_contactPerson" type="text"></label>
                                <label><span>電話</span><input v-model="form.deliveryDestination_phone" type="text"></label>
                                <label><span>E-mail</span><input v-model="form.deliveryDestination_email" type="text"></label>
                                <label><span>郵便番号</span><input v-model="form.deliveryDestination_zipcode" type="text"></label>
                                <label><span>住所1</span><input v-model="form.deliveryDestination_address1" type="text"></label>
                                <label class="span-2"><span>住所2</span><input v-model="form.deliveryDestination_address2" type="text"></label>
                            </div>
                        </section>
                    </div>

                    <section class="panel period-panel">
                        <h2>貸出期間</h2>
                        <label><span>予定開始</span><input v-model="form.plannedSentDate" type="date"></label>
                        <span>〜</span>
                        <label><span>予定終了</span><input v-model="form.plannedReturnedDate" type="date"></label>
                        <label><span>実開始</span><input v-model="form.sentDate" type="date"></label>
                        <span>〜</span>
                        <label><span>実終了</span><input v-model="form.returnedDate" type="date"></label>
                    </section>

                    <section class="panel calendar-panel">
                        <div class="panel-heading">
                            <h2>カレンダー</h2>
                            <span class="calendar-help">予定を移動／左右端で期間変更</span>
                        </div>
                        <p v-if="calendarError" class="calendar-error">{{ calendarError }}</p>
                        <div class="calendar-shell">
                            <FullCalendar ref="calendarRef" :options="calendarOptions" />
                        </div>
                    </section>
                </div>
            </Pane>

            <Pane :size="rightPaneSize" :min-size="32" class="main-pane">
                <section class="panel files-panel">
                    <div class="panel-heading">
                        <h2>Files（{{ fileItems.length }}件）</h2>
                        <div class="file-actions">
                            <button
                                type="button"
                                class="select-btn delete-btn"
                                :disabled="!selectedFileId || fileBusy"
                                @click="requestFileDelete"
                            >
                                削除
                            </button>
                            <button
                                type="button"
                                class="select-btn add-btn"
                                :disabled="fileBusy"
                                @click="openFilePicker"
                            >
                                {{ uploading ? '追加中...' : 'ファイル追加' }}
                            </button>
                            <input
                                ref="fileInput"
                                type="file"
                                class="file-input"
                                multiple
                                @change="onFileInputChange"
                            >
                        </div>
                    </div>
                    <p v-if="fileError" class="file-error">{{ fileError }}</p>
                    <div
                        class="file-dropzone"
                        :class="{ active: fileDropActive, disabled: fileBusy }"
                        @dragenter.prevent="onFileDragEnter"
                        @dragover.prevent="onFileDragOver"
                        @dragleave.prevent="onFileDragLeave"
                        @drop.prevent="onFileDrop"
                        @click="openFilePicker"
                    >
                        <strong>{{ uploading ? `アップロード中 ${uploadProgress}` : 'ファイルをドロップ、またはクリックして選択' }}</strong>
                        <span>PDF・画像・メール・その他のファイル（1ファイル10MBまで）</span>
                    </div>
                    <div v-if="fileItems.length" class="files-list">
                        <AttachedFileItem
                            v-for="file in fileItems"
                            :key="file.id"
                            :file="file"
                            :order-id="record.orderID"
                            :file-base-url="`${page.props.appBaseUrl}/servicerecord/files`"
                            :selected="selectedFileId === file.id"
                            :can-move-up="false"
                            :can-move-down="false"
                            :sorting="false"
                            @select="selectedFileId = file.id"
                        />
                    </div>
                    <p v-else class="empty">関連ファイルはありません。</p>
                </section>
            </Pane>
        </Splitpanes>

        <IntakeMasterSelectDialog
            v-if="activeSelectKind"
            :kind="activeSelectKind"
            :items="activeSelectItems"
            :initial-value="activeSelectInitialValue"
            @close="activeSelectKind = null"
            @selected="onMasterSelected"
        />

        <div v-if="filePendingDelete" class="confirm-overlay" @click.self="filePendingDelete = null">
            <div class="confirm-panel">
                <h3>ファイル削除</h3>
                <p>「{{ filePendingDelete.documentName || '名称なし' }}」を削除しますか？</p>
                <p v-if="fileError" class="file-error">{{ fileError }}</p>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" :disabled="deleting" @click="filePendingDelete = null">キャンセル</button>
                    <button type="button" class="btn delete-btn" :disabled="deleting" @click="deleteFile">
                        {{ deleting ? '削除中...' : '削除' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import IntakeMasterSelectDialog from '@/components/ServiceRecord/Intake/IntakeMasterSelectDialog.vue'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    attached: { type: Object, required: true },
    record: { type: Object, required: true },
    loanerMaster: { type: Object, default: null },
    files: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    dealersMaster: { type: Array, default: () => [] },
    loanerUnits: { type: Array, default: () => [] },
    dateFields: { type: Object, required: true },
})

const page = usePage()
const saving = ref(false)
const error = ref('')
const success = ref('')
const calendarError = ref('')
const calendarRef = ref(null)
const fileInput = ref(null)
const fileItems = ref([...props.files])
const selectedFileId = ref(props.files[0]?.id ?? null)
const uploading = ref(false)
const deleting = ref(false)
const fileError = ref('')
const uploadProgress = ref('')
const fileDropActive = ref(false)
const fileDragDepth = ref(0)
const filePendingDelete = ref(null)
const activeSelectKind = ref(null)
const leftPaneSize = ref(49)
const rightPaneSize = ref(51)
const fileBusy = computed(() => uploading.value || deleting.value)

const stringValue = value => value == null ? '' : String(value)
const form = reactive({
    parentID: stringValue(props.record.parentID),
    status: stringValue(props.record.status),
    productName: stringValue(props.record.productName ?? props.loanerMaster?.productName),
    SN: stringValue(props.record.SN ?? props.loanerMaster?.SN),
    loanerID: props.attached.loanerID ?? props.record.loanerID ?? null,
    assignStatus: stringValue(props.attached.assignStatus),
    comment: stringValue(props.attached.comment),
    sentDate: stringValue(props.attached.sentDate),
    returnedDate: stringValue(props.attached.returnedDate),
    plannedSentDate: stringValue(props.attached.plannedSentDate ?? props.attached.sentDate),
    plannedReturnedDate: stringValue(props.attached.plannedReturnedDate ?? props.attached.returnedDate),
    dealer: stringValue(props.record.dealer),
    dealer_depart: stringValue(props.record.dealer_depart),
    contactPerson: stringValue(props.record.contactPerson),
    email: stringValue(props.record.email),
    phone: stringValue(props.record.phone),
    fax: stringValue(props.record.fax),
    zipcode: stringValue(props.record.zipcode),
    address1: stringValue(props.record.address1),
    address2: stringValue(props.record.address2),
    deliveryDestination_company: stringValue(props.record.deliveryDestination_company),
    deliveryDestination_depart: stringValue(props.record.deliveryDestination_depart),
    deliveryDestination_contactPerson: stringValue(props.record.deliveryDestination_contactPerson),
    deliveryDestination_email: stringValue(props.record.deliveryDestination_email),
    deliveryDestination_phone: stringValue(props.record.deliveryDestination_phone),
    deliveryDestination_zipcode: stringValue(props.record.deliveryDestination_zipcode),
    deliveryDestination_address1: stringValue(props.record.deliveryDestination_address1),
    deliveryDestination_address2: stringValue(props.record.deliveryDestination_address2),
})

const selectedUnit = computed(() =>
    props.loanerUnits.find(unit => String(unit.loanerID) === String(form.loanerID)) ?? null,
)
const activeSelectItems = computed(() =>
    activeSelectKind.value === 'dealer' ? props.dealersMaster : props.loanerUnits,
)
const activeSelectInitialValue = computed(() => {
    if (activeSelectKind.value === 'loanerUnit') return form.loanerID
    return props.dealersMaster.find(item => item.dealerName === form.dealer)?.id ?? null
})

const calendarOptions = {
    plugins: [dayGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'ja',
    height: '100%',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
    buttonText: { today: '今日', month: '月', list: 'リスト' },
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    dayMaxEvents: true,
    events: fetchCalendarEvents,
    eventDrop: updateEventPeriod,
    eventResize: updateEventPeriod,
    eventClick(info) {
        if (!info.event.id || String(info.event.id) === String(props.attached.id)) return
        window.location.href = `${page.props.appBaseUrl}/servicerecord/loaner/detail/${info.event.id}`
    },
}

function nullable(value) {
    return value === '' || value === undefined ? null : value
}

function numericNullable(value) {
    return value === '' || value === null || value === undefined ? null : Number(value)
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function validationError(data, fallback) {
    return data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || fallback)
}

function onMasterSelected(result) {
    if (activeSelectKind.value === 'loanerUnit') {
        form.loanerID = result.loanerID ?? null
        form.productName = result.productName ?? ''
        form.SN = result.SN ?? ''
    } else if (activeSelectKind.value === 'dealer') {
        ;['dealer', 'dealer_depart', 'contactPerson', 'email', 'phone', 'fax', 'zipcode', 'address1', 'address2']
            .forEach((field) => {
                form[field] = result[field] ?? ''
            })
    }
    activeSelectKind.value = null
}

function filesApiBase() {
    return `${page.props.appBaseUrl}/servicerecord/files`
}

function openFilePicker() {
    if (fileBusy.value) return
    fileError.value = ''
    fileInput.value?.click()
}

function onFileInputChange(event) {
    const selected = [...(event.target.files ?? [])]
    event.target.value = ''
    if (selected.length) uploadFiles(selected)
}

function onFileDragEnter(event) {
    if (fileBusy.value || ![...(event.dataTransfer?.types ?? [])].includes('Files')) return
    fileDragDepth.value += 1
    fileDropActive.value = true
}

function onFileDragOver(event) {
    if (fileBusy.value) return
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy'
    fileDropActive.value = true
}

function onFileDragLeave() {
    fileDragDepth.value = Math.max(0, fileDragDepth.value - 1)
    if (fileDragDepth.value === 0) fileDropActive.value = false
}

function onFileDrop(event) {
    fileDragDepth.value = 0
    fileDropActive.value = false
    if (fileBusy.value) return
    const dropped = [...(event.dataTransfer?.files ?? [])]
    if (!dropped.length) {
        fileError.value = 'ドロップされた内容からファイルを取得できませんでした。'
        return
    }
    uploadFiles(dropped)
}

function guessDocumentType(file) {
    const name = String(file?.name || '').toLowerCase()
    const type = String(file?.type || '').toLowerCase()
    if (name.endsWith('.eml') || name.endsWith('.msg') || type.includes('message')) return 'メール'
    if (type === 'application/pdf' || name.endsWith('.pdf')) return 'PDF'
    if (type.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|tiff?)$/i.test(name)) return '画像'
    return '添付ファイル'
}

function nextFileSortNum() {
    const values = fileItems.value
        .map(file => Number(file.sortNum))
        .filter(value => Number.isFinite(value))
    return values.length ? Math.max(...values) + 10 : 10
}

async function uploadSingleFile(file, sortNum) {
    const body = new FormData()
    body.append('associatedID', String(props.record.orderID))
    body.append('file', file)
    body.append('documentName', file.name || 'untitled')
    body.append('documentType', guessDocumentType(file))
    body.append('sortNum', String(sortNum))

    const result = await apiFetch(filesApiBase(), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        body,
    })
    if (!result) throw new Error(`${file.name || 'ファイル'}のアップロードに失敗しました。`)
    const { response, data } = result
    if (!response.ok) {
        throw new Error(validationError(
            data,
            `${file.name || 'ファイル'}のアップロードに失敗しました。（HTTP ${response.status}）`,
        ))
    }
    return data.file
}

async function uploadFiles(files) {
    const list = files.filter(file => file && file.size >= 0)
    if (!list.length) return

    uploading.value = true
    fileError.value = ''
    success.value = ''
    let sortNum = nextFileSortNum()
    let lastAdded = null

    try {
        for (let index = 0; index < list.length; index += 1) {
            uploadProgress.value = `${index + 1}/${list.length}: ${list[index].name || 'untitled'}`
            const added = await uploadSingleFile(list[index], sortNum)
            fileItems.value.push(added)
            lastAdded = added
            sortNum += 10
        }
        if (lastAdded) selectedFileId.value = lastAdded.id
        success.value = `${list.length}件のファイルを追加しました。`
    } catch (e) {
        fileError.value = e.message || 'ファイルのアップロードに失敗しました。'
    } finally {
        uploading.value = false
        uploadProgress.value = ''
        fileDropActive.value = false
        fileDragDepth.value = 0
    }
}

function requestFileDelete() {
    fileError.value = ''
    filePendingDelete.value = fileItems.value.find(file => file.id === selectedFileId.value) ?? null
}

async function deleteFile() {
    const target = filePendingDelete.value
    if (!target || deleting.value) return

    deleting.value = true
    fileError.value = ''
    success.value = ''
    try {
        const result = await apiFetch(`${filesApiBase()}/${target.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        })
        if (!result) return
        const { response, data } = result
        if (!response.ok) {
            throw new Error(validationError(data, `ファイル削除に失敗しました。（HTTP ${response.status}）`))
        }
        fileItems.value = fileItems.value.filter(file => file.id !== target.id)
        selectedFileId.value = fileItems.value[0]?.id ?? null
        filePendingDelete.value = null
        success.value = data.message || 'ファイルを削除しました。'
    } catch (e) {
        fileError.value = e.message || 'ファイル削除に失敗しました。'
    } finally {
        deleting.value = false
    }
}

async function save() {
    error.value = ''
    success.value = ''
    if (form.plannedSentDate && form.plannedReturnedDate && form.plannedReturnedDate < form.plannedSentDate) {
        error.value = '予定終了日は予定開始日以降にしてください。'
        return
    }
    if (form.sentDate && form.returnedDate && form.returnedDate < form.sentDate) {
        error.value = '実終了日は実開始日以降にしてください。'
        return
    }

    const payload = { ...form }
    payload.parentID = numericNullable(form.parentID)
    payload.loanerID = numericNullable(form.loanerID)
    payload.status = numericNullable(form.status)
    Object.keys(payload).forEach((key) => {
        if (typeof payload[key] === 'string') payload[key] = nullable(payload[key])
    })
    if (!props.dateFields.hasPlannedSent) delete payload.plannedSentDate
    if (!props.dateFields.hasPlannedReturned) delete payload.plannedReturnedDate

    saving.value = true
    try {
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/detail/${props.attached.id}`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify(payload),
            },
        )
        if (!result) return
        const { response, data } = result
        if (!response.ok) throw new Error(validationError(data, `保存に失敗しました。（HTTP ${response.status}）`))
        syncCurrentDates(data.attached)
        success.value = data.message || '貸出詳細を保存しました。'
        calendarRef.value?.getApi?.().refetchEvents()
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}

function syncCurrentDates(attached) {
    if (!attached) return
    form.sentDate = attached.sentDate || ''
    form.returnedDate = attached.returnedDate || ''
    form.plannedSentDate = attached.plannedSentDate || attached.sentDate || ''
    form.plannedReturnedDate = attached.plannedReturnedDate || attached.returnedDate || ''
    form.assignStatus = attached.assignStatus || ''
    form.comment = attached.comment || ''
}

async function fetchCalendarEvents(info, successCallback, failureCallback) {
    calendarError.value = ''
    try {
        const params = new URLSearchParams({
            start: info.startStr.slice(0, 10),
            end: info.endStr.slice(0, 10),
        })
        if (form.loanerID) params.set('loanerID', form.loanerID)
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
        if (!response.ok) throw new Error(validationError(data, `期間更新に失敗しました。（HTTP ${response.status}）`))
        changeInfo.event.setExtendedProp('plannedSentDate', dates.start)
        changeInfo.event.setExtendedProp('plannedReturnedDate', dates.end)
        if (String(changeInfo.event.id) === String(props.attached.id)) syncCurrentDates(data.attached)
        success.value = `貸出期間を更新しました。（${dates.start} 〜 ${dates.end}）`
    } catch (e) {
        changeInfo.revert()
        calendarError.value = e.message || '期間更新に失敗しました。'
    }
}

function onPanesResized({ panes } = {}) {
    if (panes?.length >= 2) {
        leftPaneSize.value = panes[0].size
        rightPaneSize.value = panes[1].size
    }
    nextTick(() => calendarRef.value?.getApi?.().updateSize())
}

function safeReturnUrl() {
    const raw = new URLSearchParams(window.location.search).get('returnUrl')
    if (!raw) return null
    try {
        const url = new URL(raw, window.location.origin)
        return url.origin === window.location.origin ? url.href : null
    } catch {
        return null
    }
}

function closePage() {
    if (window.opener && !window.opener.closed) {
        window.close()
        return
    }
    const returnUrl = safeReturnUrl()
    if (returnUrl) return void (window.location.href = returnUrl)
    if (props.record.parentID) {
        window.location.href = `${page.props.appBaseUrl}/servicerecords/detail/${props.record.parentID}`
        return
    }
    window.location.href = `${page.props.appBaseUrl}/servicerecord/administrator`
}

function updateCalendarSize() {
    calendarRef.value?.getApi?.().updateSize()
}

onMounted(() => window.addEventListener('resize', updateCalendarSize))
onBeforeUnmount(() => window.removeEventListener('resize', updateCalendarSize))
</script>

<style scoped>
.loaner-detail-page,
.loaner-detail-page * {
    box-sizing: border-box;
}

.loaner-detail-page {
    width: 100%;
    max-width: 100vw;
    height: 100vh;
    height: 100dvh;
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    overflow: hidden;
    background: #e2e8f0;
    color: #1e293b;
    font-size: 12px;
}

.page-header {
    min-height: 36px;
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    background: #dbeafe;
}

.page-header strong { font-size: 14px; }
.header-actions { min-width: 0; display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
.save-message { max-width: min(42vw, 520px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.save-message.success { color: #166534; }
.save-message.error { color: #b91c1c; }

.btn,
.select-btn {
    min-height: 28px;
    padding: 4px 14px;
    border: 1px solid transparent;
    border-radius: 3px;
    cursor: pointer;
    white-space: nowrap;
}
.btn:disabled { opacity: .6; cursor: wait; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-secondary { background: #64748b; color: #fff; }
.select-btn { padding: 2px 8px; border-color: #94a3b8; background: #fff; color: #334155; font-size: 11px; }

.outer-splitpanes { flex: 1; min-width: 0; min-height: 0; overflow: hidden; }
.main-pane { min-width: 0; min-height: 0; padding: 0 5px; overflow: hidden; }
.left-pane { width: 100%; height: 100%; min-width: 0; min-height: 0; display: flex; flex-direction: column; gap: 5px; overflow-x: hidden; overflow-y: auto; padding-right: 3px; }
.panel { min-width: 0; min-height: 0; border: 1px solid #94a3b8; background: #fff; padding: 7px; overflow: visible; }
.panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 5px; }
.panel h2 { margin: 0; font-size: 13px; }
.panel h3 { margin: 7px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #cbd5e1; font-size: 11px; color: #475569; }

.loaner-panel { flex: 0 0 auto; }
.people-row { flex: 0 0 auto; min-width: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px; }
.person-panel { height: max-content; }
.period-panel { flex: 0 0 auto; min-height: 45px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.period-panel h2 { margin-right: 5px; white-space: nowrap; }
.period-panel label { display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.period-panel input { width: 116px; }
.calendar-panel { flex: 1 0 330px; min-height: 330px; display: flex; flex-direction: column; overflow: hidden; }
.calendar-shell { flex: 1; min-height: 0; overflow: hidden; }
.calendar-help, .file-help { color: #64748b; font-size: 10px; }
.calendar-error { margin: 0 0 3px; color: #b91c1c; font-size: 11px; }

.compact-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 6px; }
.loaner-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.compact-grid label { min-width: 0; display: grid; grid-template-columns: 62px minmax(0, 1fr); align-items: center; gap: 4px; }
.compact-grid label > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #475569; }
.compact-grid input,
.compact-grid select,
.master-value,
.period-panel input {
    width: 100%;
    min-width: 0;
    height: 25px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}
.compact-grid input[readonly] { background: #f1f5f9; color: #64748b; }
.compact-grid .span-2 { grid-column: span 2; }
.master-value { cursor: pointer; text-align: left; }
.files-panel { width: 100%; height: 100%; display: flex; flex-direction: column; overflow: hidden; }
.file-actions { display: flex; align-items: center; gap: 5px; }
.file-input { display: none; }
.add-btn { border-color: #2563eb; background: #2563eb; color: #fff; }
.delete-btn { border-color: #dc2626; background: #dc2626; color: #fff; }
.select-btn:disabled { opacity: .55; cursor: not-allowed; }
.file-dropzone {
    flex: 0 0 auto;
    margin-bottom: 7px;
    padding: 13px 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    border: 2px dashed #94a3b8;
    border-radius: 5px;
    background: #f8fafc;
    color: #475569;
    text-align: center;
    cursor: pointer;
}
.file-dropzone strong { color: #1e293b; }
.file-dropzone span { font-size: 10px; }
.file-dropzone.active { border-color: #2563eb; background: #dbeafe; }
.file-dropzone.disabled { opacity: .6; cursor: wait; }
.file-error { margin: 0 0 6px; color: #b91c1c; font-size: 11px; }
.files-list { flex: 1; min-height: 0; overflow: auto; padding-right: 3px; }
.empty { margin: 12px; color: #64748b; }

.confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 400;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, .5);
}
.confirm-panel { width: min(420px, 100%); padding: 16px; border-radius: 7px; background: #fff; box-shadow: 0 12px 32px rgba(15, 23, 42, .25); }
.confirm-panel h3 { margin: 0 0 10px; font-size: 15px; }
.confirm-panel p { overflow-wrap: anywhere; }
.confirm-actions { display: flex; justify-content: flex-end; gap: 7px; margin-top: 14px; }

:deep(.splitpanes__splitter) { width: 7px !important; border-left: 1px solid #64748b; border-right: 1px solid #64748b; background: #cbd5e1 !important; }
:deep(.splitpanes__splitter:hover) { background: #60a5fa !important; }
:deep(.fc) { font-size: 10px; }
:deep(.fc .fc-toolbar) { margin-bottom: 3px; gap: 3px; }
:deep(.fc .fc-toolbar-title) { font-size: 13px; }
:deep(.fc .fc-button) { padding: 2px 5px; font-size: 10px; }
:deep(.fc .fc-daygrid-day-number) { padding: 1px 3px; }
:deep(.fc .fc-event) { cursor: move; }
:deep(.file-item) { margin-bottom: 7px; }

@media (max-width: 1050px) {
    .loaner-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .compact-grid { grid-template-columns: 1fr; }
    .compact-grid .span-2 { grid-column: auto; }
}

@media (max-height: 760px) {
    .calendar-panel { flex-basis: 300px; min-height: 300px; }
}

@media (max-width: 720px) {
    .loaner-detail-page { padding: 3px; }
    .main-pane { padding: 0 2px; }
    .header-actions { gap: 3px; }
    .btn { padding-inline: 8px; }
    .save-message { display: none; }
    .people-row { grid-template-columns: 1fr; }
    .period-panel h2, .period-panel label span { display: none; }
}
</style>

<style>
html,
body,
#app {
    max-width: 100vw;
    overflow: hidden;
}
</style>
