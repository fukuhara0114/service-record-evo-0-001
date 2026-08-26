<template>
    <div class="invoice-form">
        <header class="invoice-topbar">
            <span class="invoice-badge">起伝</span>
            <span class="invoice-id-item">RMA#: {{ draftRecord?.RMA || record?.RMA || '—' }}</span>
            <span class="invoice-id-item">Loaner: {{ loanerLabel }}</span>
            <span class="invoice-id-item">受注#: {{ draftRecord?.orderNum || record?.orderNum || '—' }}</span>
            <span class="invoice-id-item">注文#: {{ draftRecord?.poNum || record?.poNum || '—' }}</span>
            <span class="invoice-id-item">Co#: {{ draftRecord?.coNum || record?.coNum || '—' }}</span>
        </header>

        <section class="invoice-toolbar">
            <label class="toolbar-field">
                <span>券生 Inv:</span>
                <input
                    type="text"
                    class="toolbar-input"
                    :value="draftRecord?.invNum ?? record?.invNum ?? ''"
                    :disabled="statusActionSaving"
                    @input="updateDraftValue('invNum', $event.target.value)"
                >
            </label>
            <label class="toolbar-field">
                <span>Mapics Inv:</span>
                <input
                    type="text"
                    class="toolbar-input"
                    :value="draftRecord?.mapics_inv ?? record?.mapics_inv ?? ''"
                    :disabled="statusActionSaving"
                    @input="updateDraftValue('mapics_inv', $event.target.value)"
                >
            </label>
            <button
                type="button"
                class="action-btn action-btn-mapics"
                :class="{ 'is-on': isMapics47On }"
                :disabled="statusActionSaving"
                @click="toggleMapics47"
            >
                Mapics47
            </button>
            <label class="toolbar-field">
                <span>出荷予定</span>
                <DateInputWithToday
                    class="toolbar-input toolbar-input-date"
                    :model-value="toDateInputValue(draftRecord?.shippingOut_requiredDate ?? record?.shippingOut_requiredDate)"
                    :disabled="statusActionSaving"
                    @update:model-value="updateDraftDateValue('shippingOut_requiredDate', $event)"
                />
            </label>
            <div class="invoice-toolbar-actions">
                <button type="button" class="action-btn action-btn-primary action-btn-wide" :disabled="statusActionSaving" @click="$emit('save')">
                    保存
                </button>
                <button
                    type="button"
                    class="action-btn action-btn-primary action-btn-wide"
                    :disabled="statusActionSaving"
                    @click="onComplete"
                >
                    {{ statusActionSaving ? '処理中...' : '完了' }}
                </button>
                <button
                    type="button"
                    class="action-btn action-btn-danger action-btn-wide"
                    :disabled="statusActionSaving"
                    @click="onRemand"
                >
                    差戻
                </button>
            </div>
        </section>
        <p v-if="actionMessage" class="action-message">{{ actionMessage }}</p>

        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <Splitpanes v-else class="default-theme invoice-splitpanes" @resized="onSplitResized">
            <Pane class="invoice-pane invoice-pane-left" :size="leftPaneSize" :min-size="28">
                <div class="left-scroll">
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

                    <section v-if="attachedLoaners.length" class="panel panel-loaner">
                        <div class="panel-header">
                            <h3>貸出機（{{ attachedLoaners.length }}件）</h3>
                        </div>
                        <table class="data-table loaner-table">
                            <thead>
                                <tr>
                                    <th>loanerID</th>
                                    <th>productName</th>
                                    <th>SN</th>
                                    <th>期間</th>
                                    <th class="col-amount">price</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="loaner in attachedLoaners" :key="loaner.orderID || loaner.attachedLoanerId">
                                    <td>{{ loaner.loanerID || '—' }}</td>
                                    <td>{{ loaner.productName || '—' }}</td>
                                    <td>{{ loaner.SN || '—' }}</td>
                                    <td>{{ loanerPeriod(loaner) }}</td>
                                    <td class="col-amount">{{ formatPrice(loaner.price ?? loaner.masterPrice) }}</td>
                                    <td>
                                        <a
                                            v-if="loaner.orderID"
                                            class="loaner-detail-link"
                                            :href="loanerHref(loaner.orderID)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >詳細</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="panel panel-info">
                        <div class="info-grid">
                            <div>
                                <h4>依頼者</h4>
                                <p>{{ draftRecord?.dealer || record?.dealer || '—' }}</p>
                                <p>{{ draftRecord?.dealer_depart || record?.dealer_depart || '—' }}</p>
                                <p>{{ draftRecord?.contactPerson || record?.contactPerson || '—' }}</p>
                                <p>〒 {{ draftRecord?.zipcode || record?.zipcode || '—' }}</p>
                                <p>{{ draftRecord?.address1 || record?.address1 || '—' }}</p>
                                <p>{{ draftRecord?.address2 || record?.address2 || '—' }}</p>
                                <p>Phone: {{ draftRecord?.phone || record?.phone || '—' }}</p>
                                <p>E-mail: {{ draftRecord?.email || record?.email || '—' }}</p>
                            </div>
                            <div>
                                <h4>E/U</h4>
                                <p>{{ draftRecord?.endUser || record?.endUser || '—' }}</p>
                                <p>{{ draftRecord?.endUser_depart || record?.endUser_depart || '—' }}</p>
                                <p>{{ draftRecord?.endUser_contactPerson || record?.endUser_contactPerson || '—' }}</p>
                                <p>〒 {{ draftRecord?.endUser_zipcode || record?.endUser_zipcode || '—' }}</p>
                                <p>{{ draftRecord?.endUser_address1 || record?.endUser_address1 || '—' }}</p>
                                <p>{{ draftRecord?.endUser_address2 || record?.endUser_address2 || '—' }}</p>
                                <p>Phone: {{ draftRecord?.endUser_phone || record?.endUser_phone || '—' }}</p>
                                <p>E-mail: {{ draftRecord?.endUser_email || record?.endUser_email || '—' }}</p>
                            </div>
                            <div>
                                <h4>納品先</h4>
                                <p>{{ draftRecord?.deliveryDestination_company || record?.deliveryDestination_company || '—' }}</p>
                                <p>{{ draftRecord?.deliveryDestination_depart || record?.deliveryDestination_depart || '—' }}</p>
                                <p>{{ draftRecord?.deliveryDestination_contactPerson || record?.deliveryDestination_contactPerson || '—' }}</p>
                                <p>〒 {{ draftRecord?.deliveryDestination_zipcode || record?.deliveryDestination_zipcode || '—' }}</p>
                                <p>{{ draftRecord?.deliveryDestination_address1 || record?.deliveryDestination_address1 || '—' }}</p>
                                <p>{{ draftRecord?.deliveryDestination_address2 || record?.deliveryDestination_address2 || '—' }}</p>
                                <p>Phone: {{ draftRecord?.deliveryDestination_phone || record?.deliveryDestination_phone || '—' }}</p>
                                <p>E-mail: {{ draftRecord?.deliveryDestination_email || record?.deliveryDestination_email || '—' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="panel panel-notes">
                        <div class="panel-header">
                            <h3>Notes（{{ sharedNotes.length }}件）</h3>
                            <div class="notes-actions">
                                <button type="button" class="action-btn action-btn-primary" @click="openNoteCreate">追加</button>
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
                                    class="action-btn action-btn-danger"
                                    :disabled="!canModifySelectedNote"
                                    @click="openNoteDelete"
                                >
                                    削除
                                </button>
                            </div>
                        </div>
                        <NotesTable
                            v-model:selected-id="selectedNoteId"
                            :notes="sharedNotes"
                            :record-order-id="record?.orderID ?? draftRecord?.orderID"
                            :current-user-name="authUserName"
                            @edit="openNoteEdit"
                        />
                    </section>
                </div>
            </Pane>

            <Pane class="invoice-pane invoice-pane-right" :size="rightPaneSize" :min-size="30">
                <div class="right-stack">
                    <section class="files-panel">
                        <div class="files-header">
                            <h3>
                                Files（書類 {{ sortedFiles.length }}件
                                ／ 撮影画像 {{ capturedImages.length }}件）
                            </h3>
                            <div class="files-actions">
                                <button
                                    type="button"
                                    class="action-btn action-btn-danger"
                                    :disabled="!selectedFileId"
                                    :title="selectedFileId ? '' : 'ファイルを選択してください'"
                                    @click="openFileDelete"
                                >
                                    削除
                                </button>
                                <button type="button" class="action-btn action-btn-primary" @click="openFileCreate">新規追加</button>
                            </div>
                        </div>
                        <div class="files-type-label">
                            <span class="type-badge type-badge-doc">書類ファイル</span>
                        </div>

                        <div
                            v-if="showFileDropzone"
                            class="file-dropzone"
                            :class="{
                                'file-dropzone-active': fileDropActive,
                                'file-dropzone-disabled': !canDropFiles || fileDropUploading,
                            }"
                            @dragenter.prevent="onFileDragEnter"
                            @dragover.prevent="onFileDragOver"
                            @dragleave.prevent="onFileDragLeave"
                            @drop.prevent="onFileDrop"
                            @click="openFileDropPicker"
                        >
                            <input
                                ref="fileDropInputEl"
                                type="file"
                                class="file-drop-input"
                                multiple
                                @change="onFileDropInputChange"
                            >
                            <div class="file-dropzone-top" @click.stop>
                                <p class="file-dropzone-title">
                                    {{ fileDropUploading ? `アップロード中...（${fileDropProgress}）` : 'ファイルをドロップ、またはクリックして選択' }}
                                </p>
                                <button
                                    type="button"
                                    class="action-btn file-dropzone-cancel"
                                    :disabled="fileDropUploading"
                                    @click="closeFileDropzone"
                                >
                                    閉じる
                                </button>
                            </div>
                            <p class="file-dropzone-help">
                                Explorer から任意ファイル（.eml / .msg / PDF / 画像など）を追加できます
                            </p>
                            <p v-if="fileDropError" class="file-dropzone-error" @click.stop>{{ fileDropError }}</p>
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

                            <AssociatedCapturedImages
                                :images="capturedImages"
                                @changed="emit('reload-attachments')"
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
import DateInputWithToday from '@/components/DateInputWithToday.vue'
import AssociatedCapturedImages from '@/components/ServiceRecord/AssociatedCapturedImages.vue'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import NotesTable from '@/components/ServiceRecord/NotesTable.vue'
import { apiFetch } from '@/utils/apiFetch'
import { findServiceMaster, resolveServiceWorkPrice, findPartMaster } from '@/utils/resolveServiceWorkPrice'
import { loanerDetailUrl } from '@/utils/serviceRecordPath'

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
    currentUserKanji: { type: String, default: '' },
})

const emit = defineEmits(['open-dialog', 'files-updated', 'reload-attachments', 'save', 'workflow-done'])

const INVOICE_STATUS_MPPICS_FINAL = 385
const INVOICE_COMPLETE_STATUS_LOGISTICS = 350
const INVOICE_COMPLETE_STATUS_SHIPPED = 400
const INVOICE_COMPLETE_STATUS_LOANER_LENDING = 388

function resolveInvoiceCompleteStatus(currentStatus, orderType) {
    const status = Number(currentStatus)
    const type = String(orderType ?? 'service').trim().toLowerCase()
    if (status === INVOICE_STATUS_MPPICS_FINAL) {
        return type === 'loaner'
            ? INVOICE_COMPLETE_STATUS_LOANER_LENDING
            : INVOICE_COMPLETE_STATUS_SHIPPED
    }
    return INVOICE_COMPLETE_STATUS_LOGISTICS
}

const page = usePage()

/** 受注日あり: その日の版 / 未定: 最新版（空文字は未定扱い） */
const priceAsOfDate = computed(() => {
    const raw = props.draftRecord?.orderDate || props.record?.orderDate || null
    if (raw == null || raw === '') return null
    const match = String(raw).match(/(\d{4}-\d{2}-\d{2})/)
    return match ? match[1] : String(raw)
})
const leftPaneSize = ref(40)
const rightPaneSize = ref(60)
const selectedFileId = ref(null)
const selectedNoteId = ref(null)
const actionMessage = ref('')
const statusActionSaving = ref(false)
const fileSortSaving = ref(false)
const fileDropInputEl = ref(null)
const showFileDropzone = ref(false)
const fileDropActive = ref(false)
const fileDropUploading = ref(false)
const fileDropError = ref('')
const fileDropProgress = ref('')
const fileDragDepth = ref(0)

const authUserName = computed(() => {
    const fromProp = String(props.currentUserKanji ?? '').trim()
    if (fromProp) return fromProp
    return String(page.props.authUser?.kanji_name ?? '').trim()
})

const sharedNotes = computed(() =>
    (props.notes ?? []).filter(note => !(note?.personal === true || note?.personal === 1 || note?.personal === '1')),
)

const selectedNote = computed(() => sharedNotes.value.find(n => Number(n.id) === Number(selectedNoteId.value)))

function isNoteOwner(note) {
    if (!note) return false

    const who = String(note.whoWrote ?? '').trim()
    if (!who) return false

    if (note.is_mine === true || note.is_mine === 1 || note.is_mine === '1') {
        return true
    }

    const me = authUserName.value
    return me !== '' && me === who
}

const canModifySelectedNote = computed(() => !!selectedNote.value && isNoteOwner(selectedNote.value))

function compareFilesBySortNum(a, b) {
    const aNull = a?.sortNum == null
    const bNull = b?.sortNum == null
    if (aNull && bNull) return Number(a?.id ?? 0) - Number(b?.id ?? 0)
    if (aNull) return 1
    if (bNull) return -1
    if (Number(a.sortNum) !== Number(b.sortNum)) {
        return Number(a.sortNum) - Number(b.sortNum)
    }
    return Number(a?.id ?? 0) - Number(b?.id ?? 0)
}

const sortedFiles = computed(() =>
    [...(props.files ?? [])].sort(compareFilesBySortNum),
)

const canDropFiles = computed(() => Boolean(props.record?.orderID))

const isLoanerRecord = computed(() => {
    const orderType = props.draftRecord?.order_type ?? props.record?.order_type
    return orderType === 'loaner'
})

const returnCodeLabel = computed(() => {
    const id = props.draftRecord?.returnCode ?? props.record?.returnCode
    const master = props.record?.return_code_master
    if (master?.description) return master.description
    const found = (page.props.returnCodes ?? []).find(item => String(item.id) === String(id))
    return found?.description || (id != null && id !== '' ? String(id) : '—')
})

const isA2laOn = computed(() => {
    const value = props.draftRecord?.a2la ?? props.record?.a2la
    return value === 1 || value === '1' || value === true
})

const isMapics47On = computed(() => {
    const value = props.draftRecord?.mapics47 ?? props.record?.mapics47
    return value === 1 || value === '1' || value === true
})

const selectedServiceMaster = computed(() => {
    return findServiceMaster(page.props.servicesMaster, {
        productName: props.draftRecord?.productName ?? props.record?.productName,
        entityID: props.draftRecord?.entityID ?? props.record?.entityID,
        serviceID: props.draftRecord?.serviceID ?? props.record?.serviceID,
    }, props.draftRecord?.orderDate ?? props.record?.orderDate ?? null)
})

const workPrice = computed(() => {
    const returnCode = props.draftRecord?.returnCode ?? props.record?.returnCode
    return resolveServiceWorkPrice(selectedServiceMaster.value, returnCode)
})

const a2laPrice = computed(() => {
    if (!isA2laOn.value) return 0
    const value = Number(selectedServiceMaster.value?.price_a2la ?? 0)
    return Number.isFinite(value) ? value : 0
})

watch(workPrice, (value) => {
    if (!props.draftRecord) return
    props.draftRecord.price = value
}, { immediate: true })

function resolvedPartMaster(part) {
    return findPartMaster(page.props.partsMaster, part.partID, priceAsOfDate.value)
        ?? part.part_master
        ?? part.partMaster
        ?? null
}

function partVersionPrice(part) {
    const raw = resolvedPartMaster(part)?.price_discounted
    const value = Number(raw)
    return Number.isFinite(value) ? value : 0
}

function partDisplayName(part) {
    return resolvedPartMaster(part)?.partName || part.partID || '—'
}

const partsPriceTotal = computed(() =>
    (props.parts ?? []).reduce((sum, part) => sum + partVersionPrice(part), 0),
)

const loanerLabel = computed(() => {
    const first = (props.loaners ?? [])[0]
    if (!first) return props.draftRecord?.loanerID || props.record?.loanerID || '—'
    return first.loanerID || first.productName || first.SN || first.orderID || '—'
})

const attachedLoaners = computed(() =>
    (props.loaners ?? []).filter((loaner) => loaner?.attachedLoanerId),
)

function formatLoanerDate(value) {
    if (!value) return '—'
    return String(value).slice(0, 10)
}

function loanerPeriod(loaner) {
    if (!loaner?.plannedSentDate && !loaner?.plannedReturnedDate) return '—'
    return `${formatLoanerDate(loaner.plannedSentDate)} 〜 ${formatLoanerDate(loaner.plannedReturnedDate)}`
}

function loanerHref(orderId) {
    const returnUrl = typeof window !== 'undefined' ? window.location.href : ''
    return loanerDetailUrl(orderId, returnUrl ? { returnUrl } : {})
}

const loanerPrice = computed(() => {
    const noCharge = props.draftRecord?.loaner_no_charge ?? props.record?.loaner_no_charge
    if (noCharge === 1 || noCharge === '1' || noCharge === true) return 0
    return (props.loaners ?? []).reduce((sum, loaner) => {
        const value = Number(loaner?.price ?? loaner?.loaner_master?.price ?? 0)
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

function toDateInputValue(value) {
    if (!value) return ''
    const normalized = String(value).trim().replace(' ', 'T')
    const date = new Date(normalized)
    if (Number.isNaN(date.getTime())) {
        const match = String(value).match(/^(\d{4}-\d{2}-\d{2})/)
        return match ? match[1] : ''
    }
    const pad = (n) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function updateDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value
}

function updateDraftDateValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value || null
}

function toggleMapics47() {
    if (!props.draftRecord) return
    props.draftRecord.mapics47 = isMapics47On.value ? 0 : 1
}

function getApiBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function getRecordApiUrl() {
    return `${window.location.origin}${getApiBasePath()}/${props.record.orderID}`
}

function getFilesApiBase() {
    return `${window.location.origin}${getApiBasePath()}/files`
}

function onSplitResized(panes) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

function openNoteCreate() {
    emit('open-dialog', 'NOTE', { mode: 'create', personal: false })
}

function openNoteEdit() {
    const note = selectedNote.value
    if (!note || !isNoteOwner(note)) return
    emit('open-dialog', 'NOTE', { mode: 'edit', note })
}

function openNoteDelete() {
    const note = selectedNote.value
    if (!note || !isNoteOwner(note)) return
    emit('open-dialog', 'D', { action: 'delete-note', note, noteId: note.id })
}

async function persistFileSortNum(fileId, sortNum) {
    const result = await apiFetch(`${getFilesApiBase()}/${fileId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ sortNum }),
    })

    if (!result) {
        throw new Error('順序の更新に失敗しました。')
    }

    const { response, data } = result
    if (!response.ok) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : null
        throw new Error(validationMessage || data.message || `順序の更新に失敗しました。（HTTP ${response.status}）`)
    }

    return data.file
}

async function updateFileSortNum(fileId, sortNum) {
    if (fileSortSaving.value) return

    fileSortSaving.value = true
    try {
        const updated = await persistFileSortNum(fileId, sortNum)
        const nextFiles = (props.files ?? []).map((file) => (
            String(file.id) === String(fileId)
                ? { ...file, ...updated, sortNum: updated?.sortNum ?? sortNum }
                : file
        ))
        emit('files-updated', nextFiles.sort(compareFilesBySortNum))
    } catch (e) {
        window.alert(e.message || '順序の更新に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

async function moveFile(fileId, direction) {
    if (fileSortSaving.value) return

    const list = [...sortedFiles.value]
    const index = list.findIndex(file => String(file.id) === String(fileId))
    if (index < 0) return

    const swapIndex = direction === 'up' ? index - 1 : index + 1
    if (swapIndex < 0 || swapIndex >= list.length) return

    ;[list[index], list[swapIndex]] = [list[swapIndex], list[index]]

    const updates = list.map((file, idx) => ({
        id: file.id,
        sortNum: (idx + 1) * 10,
    }))

    fileSortSaving.value = true
    try {
        const results = await Promise.all(
            updates.map(item => persistFileSortNum(item.id, item.sortNum)),
        )
        const byId = new Map(results.map(file => [String(file.id), file]))
        const nextFiles = (props.files ?? []).map((file) => {
            const updated = byId.get(String(file.id))
            return updated ? { ...file, ...updated } : file
        })
        emit('files-updated', nextFiles.sort(compareFilesBySortNum))
    } catch (e) {
        window.alert(e.message || '表示順の変更に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

function openFileCreate() {
    if (!canDropFiles.value) {
        window.alert('案件が選択されていません。')
        return
    }
    showFileDropzone.value = true
    fileDropError.value = ''
    fileDropActive.value = false
    fileDragDepth.value = 0
}

function closeFileDropzone() {
    if (fileDropUploading.value) return
    showFileDropzone.value = false
    fileDropActive.value = false
    fileDropError.value = ''
    fileDragDepth.value = 0
}

function openFileDelete() {
    const file = selectedFile.value
    if (!file) return
    emit('open-dialog', 'D', { action: 'delete-file', file, fileId: file.id })
}

function guessDocumentType(file) {
    const name = String(file?.name || '').toLowerCase()
    const type = String(file?.type || '').toLowerCase()
    if (name.endsWith('.eml') || name.endsWith('.msg') || type.includes('message') || type.includes('ms-outlook')) {
        return 'メール'
    }
    if (type === 'application/pdf' || name.endsWith('.pdf')) {
        return 'PDF'
    }
    if (type.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|tiff?)$/i.test(name)) {
        return '画像'
    }
    return '添付ファイル'
}

function nextSortNum() {
    const nums = (props.files ?? [])
        .map(file => Number(file.sortNum))
        .filter(num => Number.isFinite(num))
    if (!nums.length) return 10
    return Math.max(...nums) + 10
}

function onFileDragEnter(event) {
    if (!canDropFiles.value || fileDropUploading.value) return
    if (![...event.dataTransfer?.types ?? []].includes('Files')) return
    fileDragDepth.value += 1
    fileDropActive.value = true
}

function onFileDragOver(event) {
    if (!canDropFiles.value || fileDropUploading.value) return
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy'
    }
    fileDropActive.value = true
}

function onFileDragLeave() {
    fileDragDepth.value = Math.max(0, fileDragDepth.value - 1)
    if (fileDragDepth.value === 0) {
        fileDropActive.value = false
    }
}

function onFileDrop(event) {
    fileDragDepth.value = 0
    fileDropActive.value = false
    if (!canDropFiles.value || fileDropUploading.value) return
    const files = [...(event.dataTransfer?.files ?? [])]
    if (!files.length) {
        fileDropError.value = 'ドロップされた内容からファイルを取得できませんでした。Explorer に保存したファイルをドロップしてください。'
        return
    }
    uploadDroppedFiles(files)
}

function openFileDropPicker() {
    if (!canDropFiles.value || fileDropUploading.value) return
    fileDropInputEl.value?.click()
}

function onFileDropInputChange(event) {
    const files = [...(event.target.files ?? [])]
    event.target.value = ''
    if (!files.length) return
    uploadDroppedFiles(files)
}

async function uploadSingleDroppedFile(file, sortNum) {
    const formData = new FormData()
    formData.append('associatedID', props.record.orderID)
    formData.append('file', file)
    formData.append('documentName', file.name || 'untitled')
    formData.append('documentType', guessDocumentType(file))
    formData.append('sortNum', String(sortNum))

    const result = await apiFetch(getFilesApiBase(), {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: formData,
    })

    if (!result) {
        throw new Error(`${file.name || 'ファイル'} のアップロードに失敗しました。`)
    }

    const { response, data } = result
    if (!response.ok) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : null
        throw new Error(
            validationMessage
            || data.message
            || `${file.name || 'ファイル'} のアップロードに失敗しました。（HTTP ${response.status}）`,
        )
    }

    return data.file
}

async function uploadDroppedFiles(files) {
    if (!canDropFiles.value) {
        fileDropError.value = '案件が選択されていません。'
        return
    }

    const list = files.filter(file => file && file.size >= 0)
    if (!list.length) {
        fileDropError.value = 'アップロード可能なファイルがありません。'
        return
    }

    fileDropUploading.value = true
    fileDropError.value = ''
    let startSort = nextSortNum()

    try {
        for (let i = 0; i < list.length; i += 1) {
            const file = list[i]
            fileDropProgress.value = `${i + 1}/${list.length}: ${file.name || 'untitled'}`
            await uploadSingleDroppedFile(file, startSort)
            startSort += 10
        }
        emit('reload-attachments')
        showFileDropzone.value = false
        fileDropActive.value = false
        fileDragDepth.value = 0
    } catch (e) {
        fileDropError.value = e.message || 'アップロードに失敗しました。'
        emit('reload-attachments')
    } finally {
        fileDropUploading.value = false
        fileDropProgress.value = ''
    }
}

async function updateRecordFields(payload) {
    if (!props.record?.orderID) {
        throw new Error('案件が選択されていません。')
    }

    const result = await apiFetch(getRecordApiUrl(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify(payload),
    })

    if (!result) {
        throw new Error('更新に失敗しました。')
    }

    if (!result.response.ok) {
        throw new Error(result.data?.message || `更新に失敗しました。（HTTP ${result.response.status}）`)
    }

    const record = result.data?.record
    if (record) {
        if (props.draftRecord) Object.assign(props.draftRecord, record)
        if (props.record) Object.assign(props.record, record)
    } else {
        if (payload.status != null) {
            if (props.draftRecord) props.draftRecord.status = payload.status
            if (props.record) props.record.status = payload.status
        }
        if (payload.shippingOut_requiredDate != null) {
            if (props.draftRecord) props.draftRecord.shippingOut_requiredDate = payload.shippingOut_requiredDate
            if (props.record) props.record.shippingOut_requiredDate = payload.shippingOut_requiredDate
        }
    }

    return result.data
}

async function onComplete() {
    if (statusActionSaving.value || !props.draftRecord) return

    const currentStatus = props.draftRecord?.status ?? props.record?.status
    const orderType = props.draftRecord?.order_type ?? props.record?.order_type ?? 'service'
    const nextStatus = resolveInvoiceCompleteStatus(currentStatus, orderType)

    statusActionSaving.value = true
    actionMessage.value = ''
    try {
        await updateRecordFields({
            status: nextStatus,
            invNum: props.draftRecord.invNum,
            mapics_inv: props.draftRecord.mapics_inv,
            mapics47: props.draftRecord.mapics47,
            shippingOut_requiredDate: props.draftRecord.shippingOut_requiredDate,
            coNum: props.draftRecord.coNum,
            price: props.draftRecord.price,
            discount_service: props.draftRecord.discount_service,
        })
        emit('workflow-done', {
            action: 'complete',
            status: nextStatus,
        })
    } catch (e) {
        if (!e.cancelled) {
            actionMessage.value = e.message || '完了処理に失敗しました。'
        }
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
        remandStatus: 201,
    })
}

watch(() => props.files, (newFiles) => {
    if (selectedFileId.value && !newFiles.some(f => f.id === selectedFileId.value)) {
        selectedFileId.value = null
    }
})

watch(() => props.notes, () => {
    if (selectedNoteId.value && !sharedNotes.value.some(n => n.id === selectedNoteId.value)) {
        selectedNoteId.value = null
    }
})

watch(
    () => props.record?.orderID,
    () => {
        actionMessage.value = ''
        selectedFileId.value = null
        selectedNoteId.value = null
        closeFileDropzone()
    },
)
</script>

<style scoped>
.invoice-form {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #f1f5f9;
}

.invoice-topbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 18px;
    align-items: center;
    padding: 10px 14px;
    background: #e2e8f0;
    border-bottom: 1px solid #94a3b8;
    font-size: 16px;
    color: #1e293b;
    flex-shrink: 0;
}

.invoice-badge {
    padding: 2px 10px;
    border-radius: 4px;
    background: #1e40af;
    color: #fff;
    font-weight: 700;
    font-size: 13px;
}

.invoice-meta {
    font-weight: 700;
}

.invoice-id-item {
    font-size: 19px;
    font-weight: 700;
    color: #1e3a8a;
}

.invoice-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 12px;
    padding: 8px 14px;
    background: #fff;
    border-bottom: 1px solid #94a3b8;
    flex-shrink: 0;
}

.toolbar-field {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.toolbar-input {
    width: 120px;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-weight: 700;
    box-sizing: border-box;
}

.toolbar-input-date {
    width: 100%;
    min-width: 0;
}

.toolbar-field .date-input-with-today {
    width: 168px;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}

.invoice-toolbar-actions {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: space-evenly;
    margin-left: auto;
    flex: 0 0 50%;
    width: 50%;
    max-width: 50%;
    box-sizing: border-box;
}

.action-btn-mapics {
    background: #fff;
    color: #0f172a;
    border: 1px solid #94a3b8;
}

.action-btn-mapics:hover {
    background: #f8fafc;
    color: #0f172a;
}

.action-btn-mapics.is-on {
    background: #16a34a;
    color: #fff;
    border-color: #16a34a;
}

.action-btn-mapics.is-on:hover {
    background: #15803d;
    color: #fff;
}

.invoice-splitpanes {
    flex: 1;
    min-height: 0;
}

.invoice-pane {
    min-height: 0;
    overflow: hidden;
}

.notes-actions {
    display: flex;
    gap: 6px;
}

.active-note-row {
    background: #dbeafe;
}

.action-message {
    margin: 0;
    padding: 6px 14px;
    color: #b91c1c;
    font-size: 13px;
    flex-shrink: 0;
}

.left-scroll {
    height: 100%;
    overflow: auto;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-sizing: border-box;
}

.id-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    padding: 10px 12px;
    background: #bfdbfe;
    border: 1px solid #60a5fa;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #1e3a8a;
}

.panel {
    background: #fff;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    padding: 10px 12px;
}

.panel-info {
    background: #dbeafe;
}

.panel-loaner {
    background: #ecfdf5;
    border-color: #6ee7b7;
}

.loaner-detail-link {
    color: #1d4ed8;
    font-weight: 700;
    text-decoration: underline;
    white-space: nowrap;
}

.panel h3,
.panel-header h3,
.files-header h3 {
    margin: 0;
    font-size: 14px;
    color: #1e293b;
}

.panel-header,
.files-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.price-table,
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.panel-price .price-table {
    width: 100%;
    table-layout: auto;
    font-size: 16px;
    font-weight: 700;
}

.panel-price .price-table th,
.panel-price .price-table td {
    padding-left: 8px;
    padding-right: 50px;
    white-space: nowrap;
}

.panel-price .price-table th:not(:first-child),
.panel-price .price-table td:not(:first-child) {
    padding-left: 0;
}

.panel-price .price-table th:first-child,
.panel-price .price-table td:first-child,
.panel-price .price-table .col-amount {
    width: 1%;
    text-align: left !important;
}

.panel-price .price-table th:last-child,
.panel-price .price-table td:last-child {
    width: 99%;
    padding-right: 8px;
    white-space: normal;
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

.price-table th,
.price-table td,
.data-table th,
.data-table td {
    border-bottom: 1px solid #cbd5e1;
    padding: 7px 8px;
    text-align: left;
}

.notes-table th,
.notes-table td {
    font-weight: 700;
}

.col-amount {
    text-align: right !important;
    white-space: nowrap;
    width: 120px;
}

.row-summary td {
    background: #f8fafc;
    font-weight: 700;
}

.row-total td {
    background: #e2e8f0;
    font-weight: 800;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.panel-info .info-grid h4 {
    margin: 0 0 6px;
    font-size: 15px;
    font-weight: 700;
    color: #334155;
}

.panel-info .info-grid p {
    margin: 0 0 2px;
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    word-break: break-word;
}

.notes-wrap {
    max-height: 220px;
    overflow: auto;
}

.col-date,
.col-author {
    white-space: nowrap;
    width: 96px;
}

.text-cell {
    white-space: pre-wrap;
    word-break: break-word;
}

:deep(.note-autolink) {
    color: #1d4ed8;
    text-decoration: underline;
}

.right-stack {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 0;
    box-sizing: border-box;
}

.action-row {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-shrink: 0;
}

.action-input {
    flex: 1;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 13px;
}

.action-input-conum {
    flex: 0 0 150px;
    width: 150px;
    max-width: 150px;
}

.action-btn {
    padding: 7px 12px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.action-btn-wide {
    min-width: 9em;
    padding: 7px 36px;
}

.action-btn-primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.action-btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.action-message {
    margin: 0;
    color: #b91c1c;
    font-size: 12px;
}

.files-panel {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #dbeafe;
    border: 1px solid #60a5fa;
    border-radius: 0;
    padding: 10px;
    box-sizing: border-box;
    height: 100%;
    overflow: hidden;
}

.files-actions {
    display: flex;
    gap: 6px;
}

.file-dropzone {
    position: relative;
    flex: 0 0 auto;
    margin-bottom: 10px;
    padding: 14px 12px;
    border: 2px dashed #94a3b8;
    border-radius: 8px;
    background: #f8fafc;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.file-dropzone:hover {
    border-color: #2563eb;
    background: #eff6ff;
}

.file-dropzone-active {
    border-color: #2563eb;
    background: #dbeafe;
}

.file-dropzone-disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.file-drop-input {
    display: none;
}

.file-dropzone-title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
}

.file-dropzone-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.file-dropzone-cancel {
    flex: 0 0 auto;
}

.file-dropzone-help {
    margin: 0;
    font-size: 12px;
    color: #64748b;
}

.file-dropzone-error {
    margin: 8px 0 0;
    font-size: 12px;
    color: #b91c1c;
}

.files-list-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.files-type-label {
    margin: 0 0 8px;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.type-badge-doc {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #93c5fd;
}

.status-message {
    margin: 12px;
    color: #475569;
}

.status-message.error {
    color: #b91c1c;
}

.empty-message {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

@media (max-width: 960px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
