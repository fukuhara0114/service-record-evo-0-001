<template>
    <div class="logistics-detail">
        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <Splitpanes v-else class="default-theme logistics-splitpanes" @resized="syncPaneSizes">
            <Pane class="logistics-pane logistics-pane-files" :size="leftPaneSize" :min-size="22">
                <section class="panel panel-files">
                <div class="panel-header">
                    <h3>
                        Files（書類 {{ sortedFiles.length }}件
                        ／ 撮影画像 {{ capturedImages.length }}件）
                    </h3>
                    <div class="panel-actions">
                        <button
                            type="button"
                            class="action-btn"
                            :disabled="!selectedFileId"
                            @click="openFileDelete"
                        >
                            削除
                        </button>
                        <button type="button" class="action-btn" @click="openFileCreate">新規追加</button>
                    </div>
                </div>
                <div class="panel-body files-body">
                    <div class="captured-images-panel">
                            <button
                                type="button"
                                class="captured-toggle"
                                :class="{ 'has-images': capturedImages.length > 0 }"
                                @click="capturedImagesOpen = !capturedImagesOpen"
                            >
                                <span>撮影画像（{{ capturedImages.length }}件）</span>
                                <span class="captured-toggle-icon">{{ capturedImagesOpen ? '▲' : '▼' }}</span>
                            </button>
                        <div v-show="capturedImagesOpen" class="captured-images-body">
                            <AssociatedCapturedImages
                                :images="capturedImages"
                                @changed="emit('reload-attachments')"
                            />
                            <p v-if="!capturedImages.length" class="empty-message">撮影画像がありません。</p>
                        </div>
                    </div>

                    <div class="files-list-wrap">
                        <AttachedFileItem
                            v-for="(file, index) in sortedFiles"
                            :key="file.id"
                            :file="file"
                            :order-id="record?.orderID"
                            :selected="selectedFileId === file.id"
                            :can-move-up="index > 0"
                            :can-move-down="index < sortedFiles.length - 1"
                            :sorting="fileSortSaving"
                            @select="selectedFileId = file.id"
                            @move="(direction) => moveFile(file.id, direction)"
                            @sort-num-change="(sortNum) => updateFileSortNum(file.id, sortNum)"
                        />
                        <p v-if="!sortedFiles.length" class="empty-message">書類ファイルがありません。</p>
                    </div>
                </div>
            </section>
            </Pane>

            <Pane class="logistics-pane logistics-pane-right" :size="rightPaneSize" :min-size="28">
                <div class="right-column">
                <div class="action-bar">
                    <button
                        type="button"
                        class="workflow-btn"
                        :disabled="statusActionSaving"
                        @click="onComplete"
                    >
                        {{ statusActionSaving ? '処理中...' : '出荷完了' }}
                    </button>
                    <button
                        type="button"
                        class="workflow-btn"
                        :disabled="statusActionSaving"
                        @click="onRemand"
                    >
                        差戻
                    </button>
                </div>
                <p v-if="actionMessage" class="action-message">{{ actionMessage }}</p>

                <section class="panel panel-summary">
                    <div class="panel-body summary-row">
                        <span class="summary-item">{{ fieldValue('dealer') || 'dealer' }}</span>
                        <span class="summary-item">{{ fieldValue('productName') || 'ProductName' }}</span>
                        <span class="summary-item">SN:{{ fieldValue('SN') || 'xxxxxxx' }}</span>
                    </div>
                </section>

                <section class="panel panel-delivery">
                    <div class="panel-header">
                        <h3>発送先</h3>
                    </div>
                    <div class="panel-body delivery-form">
                        <label class="field field-zip">
                            <span>郵便番号</span>
                            <input
                                type="text"
                                placeholder="zipcode"
                                :value="fieldValue('deliveryDestination_zipcode')"
                                @input="updateDraftValue('deliveryDestination_zipcode', $event.target.value)"
                            >
                        </label>
                        <div class="address-row">
                            <label class="field field-address1">
                                <input
                                    type="text"
                                    placeholder="address1"
                                    :value="fieldValue('deliveryDestination_address1')"
                                    @input="updateDraftValue('deliveryDestination_address1', $event.target.value)"
                                >
                            </label>
                            <label class="field field-address2">
                                <input
                                    type="text"
                                    placeholder="address2"
                                    :value="fieldValue('deliveryDestination_address2')"
                                    @input="updateDraftValue('deliveryDestination_address2', $event.target.value)"
                                >
                            </label>
                        </div>
                        <label class="field field-company">
                            <input
                                type="text"
                                placeholder="deliveryCompany"
                                :value="fieldValue('deliveryDestination_company')"
                                @input="updateDraftValue('deliveryDestination_company', $event.target.value)"
                            >
                        </label>
                        <label class="field field-full">
                            <input
                                type="text"
                                placeholder="deliveryCompany_depart"
                                :value="fieldValue('deliveryDestination_depart')"
                                @input="updateDraftValue('deliveryDestination_depart', $event.target.value)"
                            >
                        </label>
                        <label class="field field-contact">
                            <input
                                type="text"
                                placeholder="deliveryCompany_contactPerson"
                                :value="fieldValue('deliveryDestination_contactPerson')"
                                @input="updateDraftValue('deliveryDestination_contactPerson', $event.target.value)"
                            >
                        </label>
                        <label class="field field-phone">
                            <input
                                type="text"
                                placeholder="deliveryCompany_Phone"
                                :value="fieldValue('deliveryDestination_phone')"
                                @input="updateDraftValue('deliveryDestination_phone', $event.target.value)"
                            >
                        </label>
                    </div>
                </section>

                <section class="panel panel-price">
                    <table class="price-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="col-amount">Price</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <span
                                        v-if="isLoanerRecord"
                                        class="loaner-case-badge"
                                    >貸出機案件</span>
                                    <template v-else>{{ returnCodeLabel }}</template>
                                </td>
                                <td class="col-amount">{{ formatPrice(workPrice) }}</td>
                                <td>作業内容</td>
                            </tr>
                            <tr>
                                <td>a2la{{ isA2laOn ? '' : '（OFF）' }}</td>
                                <td class="col-amount">{{ formatPrice(a2laPrice) }}</td>
                                <td>添付書類</td>
                            </tr>
                            <tr v-if="loanerPrice > 0">
                                <td>貸出機</td>
                                <td class="col-amount">{{ formatPrice(loanerPrice) }}</td>
                                <td>{{ loanerLabel }}</td>
                            </tr>
                            <tr v-for="part in parts" :key="part.id ?? part.partID">
                                <td>アクセサリ</td>
                                <td class="col-amount">{{ formatPrice(partVersionPrice(part)) }}</td>
                                <td>{{ partDisplayName(part) }}</td>
                            </tr>
                            <tr class="row-summary">
                                <td>小計</td>
                                <td class="col-amount">{{ formatPrice(subtotal) }}</td>
                                <td />
                            </tr>
                            <tr class="row-summary">
                                <td>調整</td>
                                <td class="col-amount">{{ formatSignedAmount(adjustmentAmount) }}</td>
                                <td />
                            </tr>
                            <tr class="row-total">
                                <td>計</td>
                                <td class="col-amount">{{ formatPrice(grandTotal) }}</td>
                                <td />
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="panel panel-notes">
                    <div class="panel-header">
                        <h3>Notes（{{ sharedNotes.length }}件）</h3>
                        <div class="panel-actions">
                            <button type="button" class="action-btn" @click="openNoteCreate">新規追加</button>
                            <button
                                type="button"
                                class="action-btn"
                                :disabled="!canModifySelectedNote"
                                @click="openNoteEdit"
                            >
                                編集
                            </button>
                            <button
                                type="button"
                                class="action-btn"
                                :disabled="!canModifySelectedNote"
                                @click="openNoteDelete"
                            >
                                削除
                            </button>
                        </div>
                    </div>
                    <div class="panel-body notes-body">
                        <NotesTable
                            v-model:selected-id="selectedNoteId"
                            :notes="sharedNotes"
                            :record-order-id="record?.orderID ?? draftRecord?.orderID"
                            :current-user-name="currentUserName"
                            empty-message="Notes"
                            @edit="openNoteEdit"
                        />
                    </div>
                </section>
                </div>
            </Pane>
        </Splitpanes>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import AssociatedCapturedImages from '@/components/ServiceRecord/AssociatedCapturedImages.vue'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import NotesTable from '@/components/ServiceRecord/NotesTable.vue'
import { apiFetch } from '@/utils/apiFetch'
import { tokyoTodayYmd } from '@/utils/businessDays'
import { findServiceMaster, resolveRecordWorkPriceFromMasters, findPartMaster, normalizePriceAsOfDate, applyPartMasterAsOf, resolveLoanerMasterLinePrice, resolveLinkedLoanerPriceAsOfDate } from '@/utils/resolveServiceWorkPrice'

/** Logistics 完了時の status（一覧の 350 から外れる値） */
const LOGISTICS_COMPLETE_STATUS = 385
const LOGISTICS_COMPLETE_LABEL = '貸出機出荷完了＿最終処理(Mappics)'

const props = defineProps({
    record: Object,
    draftRecord: Object,
    notes: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    capturedImages: { type: Array, default: () => [] },
    parts: { type: Array, default: () => [] },
    loaners: { type: Array, default: () => [] },
    attachmentsLoading: { type: Boolean, default: false },
    attachmentsError: { type: String, default: '' },
})

const emit = defineEmits(['open-dialog', 'files-updated', 'reload-attachments', 'workflow-done', 'save'])

const page = usePage()
const leftPaneSize = ref(48)
const rightPaneSize = ref(52)
const selectedFileId = ref(null)
const selectedNoteId = ref(null)
const capturedImagesOpen = ref(false)
const fileSortSaving = ref(false)
const statusActionSaving = ref(false)
const actionMessage = ref('')

const currentUserName = computed(() => page.props.authUser?.kanji_name || '')

const sortedFiles = computed(() => {
    const list = [...(props.files ?? [])]
    list.sort((a, b) => {
        const aNull = a?.sortNum == null
        const bNull = b?.sortNum == null
        if (aNull && bNull) return Number(a?.id ?? 0) - Number(b?.id ?? 0)
        if (aNull) return 1
        if (bNull) return -1
        if (Number(a.sortNum) !== Number(b.sortNum)) return Number(a.sortNum) - Number(b.sortNum)
        return Number(a?.id ?? 0) - Number(b?.id ?? 0)
    })
    return list
})

const sharedNotes = computed(() =>
    (props.notes ?? []).filter(note => !isPersonalNote(note)),
)

const selectedNote = computed(() =>
    sharedNotes.value.find(note => Number(note.id) === Number(selectedNoteId.value)),
)

const canModifySelectedNote = computed(() => {
    const note = selectedNote.value
    if (!note) return false
    return String(note.whoWrote || '') === String(currentUserName.value || '')
})

watch(() => props.files, (files) => {
    if (selectedFileId.value && !files.some(f => Number(f.id) === Number(selectedFileId.value))) {
        selectedFileId.value = null
    }
}, { immediate: true })

watch(() => props.notes, () => {
    if (
        selectedNoteId.value != null
        && !sharedNotes.value.some(n => Number(n.id) === Number(selectedNoteId.value))
    ) {
        selectedNoteId.value = null
    }
})

function isPersonalNote(note) {
    return note?.personal === true || note?.personal === 1 || note?.personal === '1'
}

function syncPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

function fieldValue(field) {
    const draft = props.draftRecord?.[field]
    if (draft !== undefined && draft !== null && draft !== '') return String(draft)
    const value = props.record?.[field]
    if (value !== undefined && value !== null && value !== '') return String(value)
    return ''
}

/** 価格版は servicerecord.orderDate のみ */
const priceAsOfDate = computed(() =>
    normalizePriceAsOfDate(props.draftRecord?.orderDate ?? props.record?.orderDate),
)

const isLoanerRecord = computed(() => {
    const orderType = props.draftRecord?.order_type ?? props.record?.order_type
    return orderType === 'loaner'
})

const returnCodeLabel = computed(() => {
    const master = props.record?.return_code_master?.description
    if (master) return master
    const id = props.draftRecord?.returnCode ?? props.record?.returnCode
    const found = (page.props.returnCodes ?? []).find((item) => String(item.id) === String(id))
    return found?.description || '修理'
})

const isA2laOn = computed(() => {
    const value = props.draftRecord?.a2la ?? props.record?.a2la
    return value === 1 || value === '1' || value === true
})

const selectedServiceMaster = computed(() => findServiceMaster(page.props.servicesMaster, {
    productName: props.draftRecord?.productName ?? props.record?.productName,
    entityID: props.draftRecord?.entityID ?? props.record?.entityID,
    serviceID: props.draftRecord?.serviceID ?? props.record?.serviceID,
}, priceAsOfDate.value))

const workPrice = computed(() => {
    const noCharge = props.draftRecord?.loaner_no_charge ?? props.record?.loaner_no_charge
    if (isLoanerRecord.value && (noCharge === 1 || noCharge === '1' || noCharge === true)) return 0
    return resolveRecordWorkPriceFromMasters({
        orderType: props.draftRecord?.order_type ?? props.record?.order_type,
        returnCode: props.draftRecord?.returnCode ?? props.record?.returnCode,
        serviceMaster: selectedServiceMaster.value,
        loanerID: props.draftRecord?.loanerID ?? props.record?.loanerID,
        loanerPriceVersions: props.draftRecord?.priceVersions ?? props.record?.priceVersions ?? [],
        asOfDate: priceAsOfDate.value,
    })
})

const a2laPrice = computed(() => {
    if (!isA2laOn.value) return 0
    const value = Number(selectedServiceMaster.value?.price_a2la ?? 0)
    return Number.isFinite(value) ? value : 0
})

function resolvedPartMaster(part) {
    return findPartMaster(page.props.partsMaster, part.partID, priceAsOfDate.value)
}

function partVersionPrice(part) {
    const raw = resolvedPartMaster(part)?.price_discounted
    const value = Number(raw)
    return Number.isFinite(value) ? value : 0
}

function partDisplayName(part) {
    return resolvedPartMaster(part)?.partName || part.partID || '—'
}

const currentReturnCode = computed(() => {
    const value = props.draftRecord?.returnCode ?? props.record?.returnCode
    const num = Number(value)
    return Number.isFinite(num) ? num : null
})

function applyLinePricesForAsOf() {
    const asOf = priceAsOfDate.value
    const parentOrderDate = props.draftRecord?.orderDate ?? props.record?.orderDate
    const partsMaster = page.props.partsMaster ?? []
    for (const part of props.parts ?? []) {
        applyPartMasterAsOf(part, partsMaster, asOf)
    }
    const returnCode = currentReturnCode.value
    for (const loaner of props.loaners ?? []) {
        const loanerAsOf = resolveLinkedLoanerPriceAsOfDate(loaner, parentOrderDate)
        const amount = resolveLoanerMasterLinePrice(loaner, returnCode, loanerAsOf)
        loaner.masterPrice = amount
    }
}

watch(
    [priceAsOfDate, currentReturnCode, () => props.parts, () => props.loaners],
    applyLinePricesForAsOf,
    { immediate: true },
)

const partsPriceTotal = computed(() =>
    (props.parts ?? []).reduce((sum, part) => sum + partVersionPrice(part), 0),
)

const loanerLabel = computed(() => {
    const first = (props.loaners ?? [])[0]
    if (!first) return '—'
    return first.loanerID || first.productName || first.SN || first.orderID || '—'
})

const loanerPrice = computed(() => {
    // 親 service 案件では紐づく貸出機は請求しない（貸出機案件側で請求）
    if (!isLoanerRecord.value) return 0
    const noCharge = props.draftRecord?.loaner_no_charge ?? props.record?.loaner_no_charge
    if (noCharge === 1 || noCharge === '1' || noCharge === true) return 0
    const parentOrderDate = props.draftRecord?.orderDate ?? props.record?.orderDate
    return (props.loaners ?? []).reduce((sum, loaner) => {
        const asOf = resolveLinkedLoanerPriceAsOfDate(loaner, parentOrderDate)
        const value = Number(resolveLoanerMasterLinePrice(loaner, currentReturnCode.value, asOf))
        return sum + (Number.isFinite(value) ? value : 0)
    }, 0)
})

const adjustmentAmount = computed(() => {
    const value = Number(props.draftRecord?.discount_service ?? props.record?.discount_service ?? 0)
    return Number.isFinite(value) ? value : 0
})

const subtotal = computed(() => workPrice.value + a2laPrice.value + partsPriceTotal.value + loanerPrice.value)
const grandTotal = computed(() => subtotal.value + adjustmentAmount.value)

function formatPrice(value) {
    const num = Number(value)
    if (!Number.isFinite(num)) return '—'
    return new Intl.NumberFormat('ja-JP').format(num)
}

function formatSignedAmount(value) {
    const num = Number(value)
    if (!Number.isFinite(num) || num === 0) return '0'
    const abs = formatPrice(Math.abs(num))
    return num > 0 ? `+${abs}` : `-${abs}`
}

function updateDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function getBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
}

function getRecordApiUrl() {
    return `${window.location.origin}${getBasePath()}/${props.record?.orderID}`
}

function getFilesApiBase() {
    return `${window.location.origin}${getBasePath()}/files`
}

async function updateRecord(payload) {
    if (!props.record?.orderID) {
        throw new Error('案件が選択されていません。')
    }

    const result = await apiFetch(getRecordApiUrl(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
    })

    if (!result?.response?.ok) {
        throw new Error(result?.data?.message || `更新に失敗しました。（HTTP ${result?.response?.status ?? ''}）`)
    }

    Object.assign(props.record, payload)
    if (props.draftRecord) Object.assign(props.draftRecord, payload)
    return result.data
}

function deliveryPayload() {
    return {
        deliveryDestination_company: fieldValue('deliveryDestination_company') || null,
        deliveryDestination_depart: fieldValue('deliveryDestination_depart') || null,
        deliveryDestination_contactPerson: fieldValue('deliveryDestination_contactPerson') || null,
        deliveryDestination_phone: fieldValue('deliveryDestination_phone') || null,
        deliveryDestination_email: fieldValue('deliveryDestination_email') || null,
        deliveryDestination_zipcode: fieldValue('deliveryDestination_zipcode') || null,
        deliveryDestination_address1: fieldValue('deliveryDestination_address1') || null,
        deliveryDestination_address2: fieldValue('deliveryDestination_address2') || null,
    }
}

async function onComplete() {
    if (statusActionSaving.value) return
    const orderType = props.record?.order_type ?? props.draftRecord?.order_type ?? ''
    const row = orderType === 'loaner'
        ? (page.props.statusesLoaner ?? []).find(item => Number(item.processID_new) === LOGISTICS_COMPLETE_STATUS)
        : (page.props.statuses ?? []).find(item => Number(item.processID_new) === LOGISTICS_COMPLETE_STATUS)
    const label = String(row?.status_new ?? row?.status ?? LOGISTICS_COMPLETE_LABEL).trim()
        || LOGISTICS_COMPLETE_LABEL
    if (!window.confirm(`「${label}」に変更（status=${LOGISTICS_COMPLETE_STATUS}）しますか？`)) return

    statusActionSaving.value = true
    actionMessage.value = ''
    try {
        await updateRecord({
            ...deliveryPayload(),
            status: LOGISTICS_COMPLETE_STATUS,
            shippingOut_requiredDate: tokyoTodayYmd(),
        })
        emit('workflow-done', { action: 'complete', status: LOGISTICS_COMPLETE_STATUS })
    } catch (e) {
        actionMessage.value = e.message || '完了処理に失敗しました。'
    } finally {
        statusActionSaving.value = false
    }
}

function onRemand() {
    if (statusActionSaving.value) return
    emit('open-dialog', 'NOTE', {
        mode: 'create',
        personal: false,
        remand: true,
    })
}

function openFileCreate() {
    emit('open-dialog', 'FILE', { mode: 'create' })
}

function openFileDelete() {
    const file = sortedFiles.value.find(item => Number(item.id) === Number(selectedFileId.value))
    if (!file) return
    emit('open-dialog', 'D', { action: 'delete-file', file, fileId: file.id })
}

function openNoteCreate() {
    emit('open-dialog', 'NOTE', { mode: 'create', personal: false })
}

function openNoteEdit() {
    const note = selectedNote.value
    if (!note || !canModifySelectedNote.value) return
    emit('open-dialog', 'NOTE', { mode: 'edit', note, personal: false })
}

function openNoteDelete() {
    const note = selectedNote.value
    if (!note || !canModifySelectedNote.value) return
    emit('open-dialog', 'D', { action: 'delete-note', note, noteId: note.id })
}

async function moveFile(fileId, direction) {
    if (fileSortSaving.value) return
    const list = [...sortedFiles.value]
    const index = list.findIndex(file => Number(file.id) === Number(fileId))
    if (index < 0) return
    const swapIndex = direction === 'up' ? index - 1 : index + 1
    if (swapIndex < 0 || swapIndex >= list.length) return

    const current = list[index]
    const target = list[swapIndex]
    const currentSort = current.sortNum ?? (index + 1) * 10
    const targetSort = target.sortNum ?? (swapIndex + 1) * 10

    fileSortSaving.value = true
    try {
        await Promise.all([
            patchFileSort(current.id, targetSort),
            patchFileSort(target.id, currentSort),
        ])
        const nextFiles = (props.files ?? []).map((file) => {
            if (Number(file.id) === Number(current.id)) return { ...file, sortNum: targetSort }
            if (Number(file.id) === Number(target.id)) return { ...file, sortNum: currentSort }
            return file
        })
        emit('files-updated', nextFiles)
    } catch (e) {
        window.alert(e.message || '表示順の変更に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

async function updateFileSortNum(fileId, sortNum) {
    if (fileSortSaving.value) return
    fileSortSaving.value = true
    try {
        await patchFileSort(fileId, sortNum)
        const nextFiles = (props.files ?? []).map(file => (
            Number(file.id) === Number(fileId) ? { ...file, sortNum } : file
        ))
        emit('files-updated', nextFiles)
    } catch (e) {
        window.alert(e.message || '表示順の変更に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

async function patchFileSort(fileId, sortNum) {
    const result = await apiFetch(`${getFilesApiBase()}/${fileId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ sortNum }),
    })
    if (!result?.response?.ok) {
        throw new Error(result?.data?.message || '表示順の更新に失敗しました。')
    }
}
</script>

<style scoped>
.logistics-detail {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #bbbbbb;
    padding: 10px;
    box-sizing: border-box;
}

.status-message {
    margin: 16px;
    color: #334155;
}

.status-message.error {
    color: #b91c1c;
}

.logistics-splitpanes {
    flex: 1;
    min-height: 0;
    height: 100%;
    overflow: hidden;
}

.logistics-splitpanes :deep(.splitpanes__pane) {
    min-height: 0;
    overflow: hidden;
}

.logistics-pane {
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.panel-files {
    width: 100%;
    height: 100%;
    min-height: 0;
}

.right-column {
    flex: 1 1 0%;
    width: 100%;
    min-height: 0;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-x: hidden;
    overflow-y: auto;
}

.right-column > .action-bar,
.right-column > .action-message,
.right-column > .panel-summary,
.right-column > .panel-delivery,
.right-column > .panel-price,
.right-column > .panel-notes {
    flex: 0 0 auto;
}

.right-column > .panel-notes {
    min-height: 160px;
}

.panel-price {
    padding: 10px 12px;
    background: #fff;
}

.panel {
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    border: 2px solid #0f172a;
    border-radius: 8px;
    background: #eff6ff;
    overflow: hidden;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-bottom: 1px solid #94a3b8;
    background: #dbeafe;
    flex: 0 0 auto;
}

.panel-header h3 {
    margin: 0;
    font-size: 15px;
    color: #0f172a;
}

.panel-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.panel-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    padding: 8px;
    background: #fff;
}

.files-body,
.notes-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.files-body {
    overflow: hidden;
}

.captured-images-panel {
    flex: 0 0 auto;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    overflow: hidden;
}

.captured-toggle {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 6px 10px;
    border: none;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.captured-toggle:hover {
    background: #cbd5e1;
}

.captured-toggle.has-images {
    background: #86efac;
    color: #14532d;
}

.captured-toggle.has-images:hover {
    background: #4ade80;
}

.captured-toggle.has-images .captured-toggle-icon {
    color: #166534;
}

.captured-toggle-icon {
    font-size: 11px;
    color: #475569;
}

.captured-images-body {
    max-height: 200px;
    overflow: auto;
    padding: 8px;
}

.files-list-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.action-bar {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    flex: 0 0 auto;
}

.workflow-btn {
    min-height: 48px;
    border: 2px solid #0f172a;
    border-radius: 0;
    background: #fff;
    color: #0f172a;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
}

.workflow-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.action-message {
    margin: 0;
    color: #b91c1c;
    font-size: 13px;
}

.action-btn {
    padding: 4px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #0f172a;
    font-size: 12px;
    cursor: pointer;
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.panel-summary .panel-body {
    background: #eff6ff;
    padding: 12px 14px;
}

.summary-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
    gap: 20px;
}

.summary-item {
    min-width: 0;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.delivery-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #eff6ff;
}

.address-row {
    display: grid;
    grid-template-columns: minmax(100px, 0.45fr) minmax(0, 1.55fr);
    gap: 8px;
}

.field {
    display: block;
    min-width: 0;
}

.field-zip,
.field-contact,
.field-phone {
    width: min(240px, 100%);
}

.field-company,
.field-full {
    width: 100%;
}

.field input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #0f172a;
    border-radius: 2px;
    box-sizing: border-box;
    background: #fff;
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
}

.field input::placeholder {
    color: #94a3b8;
    font-weight: 500;
}

.price-table {
    width: 100%;
    table-layout: auto;
    border-collapse: collapse;
    font-size: 16px;
    font-weight: 700;
}

.price-table th,
.price-table td {
    border-bottom: 1px solid #cbd5e1;
    padding: 7px 8px;
    padding-right: 50px;
    text-align: left;
    white-space: nowrap;
}

.price-table th:not(:first-child),
.price-table td:not(:first-child) {
    padding-left: 0;
}

.price-table th:first-child,
.price-table td:first-child,
.price-table .col-amount {
    width: 1%;
    text-align: left !important;
}

.price-table th:last-child,
.price-table td:last-child {
    width: 99%;
    padding-right: 8px;
    white-space: normal;
}

.price-table .row-summary td {
    background: #f8fafc;
    font-weight: 700;
}

.price-table .row-total td {
    background: #e2e8f0;
    font-weight: 800;
}

.loaner-case-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 4px;
    background: #dc2626;
    color: #fff;
    font-size: inherit;
    font-weight: 700;
    line-height: 1.4;
    white-space: nowrap;
}

.notes-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 120px;
    font-size: 18px;
    font-weight: 700;
    color: #64748b;
}

.table-wrap {
    min-height: 0;
    overflow: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
    vertical-align: top;
}

.data-table th {
    position: sticky;
    top: 0;
    background: #e2e8f0;
    z-index: 1;
}

.notes-table th,
.notes-table td {
    font-weight: 700;
}

.table-row {
    cursor: pointer;
}

.table-row.active-row {
    background: #dbeafe;
}

.table-row.important-row {
    background: #fef3c7;
}

.col-date {
    width: 120px;
    white-space: nowrap;
}

.col-author {
    width: 90px;
}

.text-cell {
    word-break: break-word;
}

.empty-message {
    margin: 0;
    padding: 12px;
    color: #64748b;
    font-size: 13px;
}

:deep(.splitpanes__splitter) {
    background: #cbd5e1;
    min-width: 8px;
}

:deep(.splitpanes__splitter:hover) {
    background: #94a3b8;
}
</style>
