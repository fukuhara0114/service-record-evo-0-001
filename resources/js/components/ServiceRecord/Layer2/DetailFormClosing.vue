<template>
    <div class="closing-form">
        <header class="closing-topbar">
            <span class="closing-meta">{{ draftRecord?.productName || record?.productName || '—' }}</span>
            <span class="closing-meta">SN : {{ draftRecord?.SN || record?.SN || '—' }}</span>
            <span class="closing-meta">{{ draftRecord?.dealer || record?.dealer || '—' }}</span>
            <span class="closing-meta">{{ returnCodeLabel }}</span>
        </header>

        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <Splitpanes v-else class="default-theme closing-splitpanes" @resized="onSplitResized">
            <Pane class="closing-pane closing-pane-left" :size="leftPaneSize" :min-size="28">
                <div class="left-column">
                    <section class="id-bar">
                        <span>RMA: {{ draftRecord?.RMA || record?.RMA || '—' }}</span>
                        <span>Quote: {{ draftRecord?.quoteNum || draftRecord?.sm_quote || record?.quoteNum || record?.sm_quote || '—' }}</span>
                        <span>受注番号: {{ draftRecord?.orderNum || record?.orderNum || '—' }}</span>
                        <span>注文番号: {{ draftRecord?.poNum || record?.poNum || '—' }}</span>
                    </section>

                    <div class="action-row">
                        <input
                            v-model="actionComment"
                            type="text"
                            class="action-input action-input-conum"
                            placeholder="Co Num"
                            :disabled="statusActionSaving"
                        >
                        <button type="button" class="action-btn action-btn-wide" :disabled="statusActionSaving" @click="$emit('save')">
                            保存
                        </button>
                        <button type="button" class="action-btn" :disabled="statusActionSaving" @click="showGalleryDialog = true">
                            Gallery
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
                    <p v-if="actionMessage" class="action-message">{{ actionMessage }}</p>

                    <Splitpanes
                        class="default-theme left-inner-splitpanes"
                        horizontal
                        @resized="onLeftVerticalResized"
                    >
                        <Pane class="left-top-pane" :size="leftTopPaneSize" :min-size="22">
                            <Splitpanes
                                class="default-theme price-info-splitpanes"
                                @resized="onPriceInfoResized"
                            >
                                <Pane class="price-pane" :size="pricePaneSize" :min-size="20">
                                    <section class="panel panel-price">
                                        <table class="price-table">
                                            <thead>
                                                <tr>
                                                    <th>項目</th>
                                                    <th class="col-amount">金額</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>
                                                        <span
                                                            v-if="isLoanerRecord"
                                                            class="loaner-case-badge"
                                                        >貸出機案件</span>
                                                        <template v-else>作業内容（{{ returnCodeLabel }}）</template>
                                                    </td>
                                                    <td class="col-amount">{{ formatPrice(workPrice) }}</td>
                                                </tr>
                                                <tr>
                                                    <td>a2la{{ isA2laOn ? '' : '（OFF）' }}</td>
                                                    <td class="col-amount">{{ formatPrice(a2laPrice) }}</td>
                                                </tr>
                                                <tr v-if="loanerPrice > 0">
                                                    <td>貸出機</td>
                                                    <td class="col-amount">{{ formatPrice(loanerPrice) }}</td>
                                                </tr>
                                                <tr>
                                                    <td>Parts</td>
                                                    <td class="col-amount">{{ formatPrice(partsPriceTotal) }}</td>
                                                </tr>
                                                <tr class="row-summary">
                                                    <td>小計</td>
                                                    <td class="col-amount">{{ formatPrice(subtotal) }}</td>
                                                </tr>
                                                <tr class="row-summary">
                                                    <td>価格調整</td>
                                                    <td class="col-amount">{{ formatSignedAmount(adjustmentAmount) }}</td>
                                                </tr>
                                                <tr class="row-total">
                                                    <td>合計</td>
                                                    <td class="col-amount">{{ formatPrice(grandTotal) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </section>
                                </Pane>
                                <Pane class="info-pane" :size="infoPaneSize" :min-size="30">
                                    <section class="panel panel-info">
                                        <div class="info-stack">
                                            <div class="info-block">
                                                <h4>Dealer</h4>
                                                <p>{{ draftRecord?.dealer || record?.dealer || '—' }}</p>
                                                <p>{{ draftRecord?.dealer_depart || record?.dealer_depart || '—' }}</p>
                                                <p>{{ formatAddress(draftRecord?.address1 ?? record?.address1, draftRecord?.address2 ?? record?.address2) }}</p>
                                                <p>{{ draftRecord?.contactPerson || record?.contactPerson || '—' }}</p>
                                                <p>{{ draftRecord?.email || record?.email || '—' }}</p>
                                                <p>{{ draftRecord?.phone || record?.phone || '—' }}</p>
                                            </div>
                                            <div class="info-block">
                                                <h4>E/U</h4>
                                                <p>{{ draftRecord?.endUser || record?.endUser || '—' }}</p>
                                                <p>{{ draftRecord?.endUser_depart || record?.endUser_depart || '—' }}</p>
                                                <p>{{ formatAddress(draftRecord?.endUser_address1 ?? record?.endUser_address1, draftRecord?.endUser_address2 ?? record?.endUser_address2) }}</p>
                                                <p>{{ draftRecord?.endUser_contactPerson || record?.endUser_contactPerson || '—' }}</p>
                                                <p>{{ draftRecord?.endUser_email || record?.endUser_email || '—' }}</p>
                                                <p>{{ draftRecord?.endUser_phone || record?.endUser_phone || '—' }}</p>
                                            </div>
                                            <div class="info-block">
                                                <h4>Delivery</h4>
                                                <p>{{ draftRecord?.deliveryDestination_company || record?.deliveryDestination_company || '—' }}</p>
                                                <p>{{ draftRecord?.deliveryDestination_depart || record?.deliveryDestination_depart || '—' }}</p>
                                                <p>{{ formatAddress(draftRecord?.deliveryDestination_address1 ?? record?.deliveryDestination_address1, draftRecord?.deliveryDestination_address2 ?? record?.deliveryDestination_address2) }}</p>
                                                <p>{{ draftRecord?.deliveryDestination_contactPerson || record?.deliveryDestination_contactPerson || '—' }}</p>
                                                <p>{{ draftRecord?.deliveryDestination_email || record?.deliveryDestination_email || '—' }}</p>
                                                <p>{{ draftRecord?.deliveryDestination_phone || record?.deliveryDestination_phone || '—' }}</p>
                                            </div>
                                        </div>
                                    </section>
                                </Pane>
                            </Splitpanes>
                        </Pane>

                        <Pane class="left-notes-pane" :size="leftNotesPaneSize" :min-size="20">
                            <section class="panel panel-notes notes-card">
                                <div class="section-header">
                                    <h3>Notes（{{ sharedNotes.length }}件）</h3>
                                    <div class="section-actions">
                                        <button type="button" class="action-btn" :disabled="!selectedNoteId" :title="noteEditDeleteTitle" @click="openNoteEdit">編集</button>
                                        <button type="button" class="action-btn action-btn-danger" :disabled="!selectedNoteId" :title="noteEditDeleteTitle" @click="openNoteDelete">削除</button>
                                        <button type="button" class="action-btn" @click="openEmailNoteLink">メール紐づけ</button>
                                        <button type="button" class="action-btn action-btn-primary" @click="openNoteCreate">新規追加</button>
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
                        </Pane>
                    </Splitpanes>
                </div>
            </Pane>

            <Pane class="closing-pane closing-pane-right" :size="rightPaneSize" :min-size="30">
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
                        </div>
                    </section>
                </div>
            </Pane>
        </Splitpanes>

        <ShippingOutDateDialog
            v-if="showShippingDialog"
            :order-id="record?.orderID"
            :product-name="draftRecord?.productName || record?.productName || ''"
            :serial-number="draftRecord?.SN || record?.SN || ''"
            :dealer="draftRecord?.dealer || record?.dealer || ''"
            :contact-person="draftRecord?.contactPerson || record?.contactPerson || ''"
            :preview-record="draftRecord || record"
            :confirming="statusActionSaving"
            @close="showShippingDialog = false"
            @confirm="onShippingConfirm"
        />

        <CapturedImageGalleryDialog
            v-if="showGalleryDialog"
            title="Gallery"
            :associatedID="galleryAssociatedId"
            :associated-id="galleryAssociatedId"
            @close="showGalleryDialog = false"
            @associated="emit('reload-attachments')"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import AssociatedCapturedImages from '@/components/ServiceRecord/AssociatedCapturedImages.vue'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import ShippingOutDateDialog from '@/components/ServiceRecord/Layer3/ShippingOutDateDialog.vue'
import CapturedImageGalleryDialog from '@/components/ServiceRecord/CapturedImageGalleryDialog.vue'
import NotesTable from '@/components/ServiceRecord/NotesTable.vue'
import { apiFetch } from '@/utils/apiFetch'
import { findServiceMaster, resolveServiceWorkPrice, findPartMaster, normalizePriceAsOfDate, applyPartMasterAsOf, resolveLoanerLinePrice } from '@/utils/resolveServiceWorkPrice'

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

const emit = defineEmits(['open-dialog', 'files-updated', 'reload-attachments', 'save', 'workflow-done'])

const page = usePage()
const leftPaneSize = ref(48)
const rightPaneSize = ref(52)
const leftTopPaneSize = ref(55)
const leftNotesPaneSize = ref(45)
const pricePaneSize = ref(40)
const infoPaneSize = ref(60)
const selectedFileId = ref(null)
const selectedNoteId = ref(null)
const capturedImagesOpen = ref(false)
const actionComment = ref('')
const actionMessage = ref('')
const statusActionSaving = ref(false)
const showShippingDialog = ref(false)
const showGalleryDialog = ref(false)
const galleryAssociatedId = computed(() => props.record?.orderID ?? null)
const fileSortSaving = ref(false)
const fileDropInputEl = ref(null)
const showFileDropzone = ref(false)
const fileDropActive = ref(false)
const fileDropUploading = ref(false)
const fileDropError = ref('')
const fileDropProgress = ref('')
const fileDragDepth = ref(0)

const authUserName = computed(() => String(page.props.authUser?.kanji_name ?? '').trim())

const sharedNotes = computed(() =>
    (props.notes ?? []).filter(note => !(note?.personal === true || note?.personal === 1 || note?.personal === '1')),
)

const selectedNote = computed(() =>
    sharedNotes.value.find(n => Number(n.id) === Number(selectedNoteId.value)) || null,
)

function isNoteOwner(note) {
    if (!note) return false
    const who = String(note.whoWrote ?? '').trim()
    if (!who) return false
    if (note.is_mine === true || note.is_mine === 1 || note.is_mine === '1') {
        return true
    }
    return authUserName.value !== '' && authUserName.value === who
}

const noteEditDeleteTitle = computed(() => {
    if (!selectedNoteId.value) return 'Note を選択してください'
    if (!selectedNote.value) return 'Note を選択してください'
    if (!isNoteOwner(selectedNote.value)) {
        return `自分が書いた Note のみ編集・削除できます（ログイン: ${authUserName.value || '不明'} / 記入者: ${selectedNote.value.whoWrote || '不明'}）`
    }
    return ''
})

function formatAddress(address1, address2) {
    const parts = [address1, address2]
        .map(value => String(value ?? '').trim())
        .filter(Boolean)
    return parts.length ? parts.join(' ') : '—'
}

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

const selectedFile = computed(() => props.files.find(f => f.id === selectedFileId.value))

const canDropFiles = computed(() => Boolean(props.record?.orderID))

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

/** 受注日（2001年以降）: その日の版 / 未定・2000年以前: 最新版 */
const priceAsOfDate = computed(() => {
    if (props.draftRecord) {
        return normalizePriceAsOfDate(props.draftRecord.orderDate)
    }
    return normalizePriceAsOfDate(props.record?.orderDate)
})

const selectedServiceMaster = computed(() => {
    return findServiceMaster(page.props.servicesMaster, {
        productName: props.draftRecord?.productName ?? props.record?.productName,
        entityID: props.draftRecord?.entityID ?? props.record?.entityID,
        serviceID: props.draftRecord?.serviceID ?? props.record?.serviceID,
    }, priceAsOfDate.value)
})

const isLoanerRecord = computed(() => {
    const orderType = props.draftRecord?.order_type ?? props.record?.order_type
    return orderType === 'loaner'
})

const workPrice = computed(() => {
    const returnCode = props.draftRecord?.returnCode ?? props.record?.returnCode
    const resolved = resolveServiceWorkPrice(selectedServiceMaster.value, returnCode)
    if (Number.isFinite(resolved) && resolved !== 0) return resolved
    const stored = Number(props.draftRecord?.price ?? props.record?.price)
    return Number.isFinite(stored) ? stored : 0
})

const a2laPrice = computed(() => {
    if (!isA2laOn.value) return 0
    const value = Number(selectedServiceMaster.value?.price_a2la ?? 0)
    return Number.isFinite(value) ? value : 0
})

watch(workPrice, (value) => {
    if (!props.draftRecord) return
    if (isLoanerRecord.value) return
    props.draftRecord.price = value
}, { immediate: true })

const partsPriceTotal = computed(() =>
    (props.parts ?? []).reduce((sum, part) => {
        const versioned = findPartMaster(page.props.partsMaster, part.partID, priceAsOfDate.value)
            ?? part.part_master
            ?? part.partMaster
        const value = Number(versioned?.price_discounted)
        return sum + (Number.isFinite(value) ? value : 0)
    }, 0),
)

watch(
    [priceAsOfDate, () => props.parts],
    () => {
        const asOf = priceAsOfDate.value
        const partsMaster = page.props.partsMaster ?? []
        for (const part of props.parts ?? []) {
            applyPartMasterAsOf(part, partsMaster, asOf)
        }
    },
    { immediate: true },
)

const currentReturnCode = computed(() => {
    const value = props.draftRecord?.returnCode ?? props.record?.returnCode
    const num = Number(value)
    return Number.isFinite(num) ? num : null
})

const loanerPrice = computed(() => {
    const noCharge = props.draftRecord?.loaner_no_charge ?? props.record?.loaner_no_charge
    if (noCharge === 1 || noCharge === '1' || noCharge === true) return 0
    return (props.loaners ?? []).reduce((sum, loaner) => {
        const value = Number(resolveLoanerLinePrice(loaner, currentReturnCode.value, priceAsOfDate.value))
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

function onSplitResized({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

function onLeftVerticalResized({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftTopPaneSize.value = panes[0].size
    leftNotesPaneSize.value = panes[1].size
}

function onPriceInfoResized({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    pricePaneSize.value = panes[0].size
    infoPaneSize.value = panes[1].size
}

function openNoteCreate() {
    emit('open-dialog', 'NOTE', { mode: 'create', personal: false })
}

function openEmailNoteLink() {
    emit('open-dialog', 'EMAIL_NOTE_LINK')
}

function openNoteEdit() {
    const note = selectedNote.value
    if (!note) return
    if (!isNoteOwner(note)) {
        window.alert(`自分が書いた Note のみ編集できます。\nログイン: ${authUserName.value || '不明'}\n記入者: ${note.whoWrote || '不明'}`)
        return
    }
    emit('open-dialog', 'NOTE', { mode: 'edit', note })
}

function openNoteDelete() {
    const note = selectedNote.value
    if (!note) return
    if (!isNoteOwner(note)) {
        window.alert(`自分が書いた Note のみ削除できます。\nログイン: ${authUserName.value || '不明'}\n記入者: ${note.whoWrote || '不明'}`)
        return
    }
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

function onComplete() {
    if (statusActionSaving.value) return
    actionMessage.value = ''
    showShippingDialog.value = true
}

async function onShippingConfirm({ shippingOut_requiredDate }) {
    if (statusActionSaving.value) return

    statusActionSaving.value = true
    actionMessage.value = ''
    try {
        await updateRecordFields({
            status: 300,
            shippingOut_requiredDate,
        })
        showShippingDialog.value = false
        emit('workflow-done', {
            action: 'complete',
            status: 300,
            shippingOut_requiredDate,
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
    })
}

watch(() => props.files, (newFiles) => {
    if (selectedFileId.value && !newFiles.some(f => f.id === selectedFileId.value)) {
        selectedFileId.value = null
    }
})

watch(() => props.notes, () => {
    if (
        selectedNoteId.value != null
        && !sharedNotes.value.some(n => Number(n.id) === Number(selectedNoteId.value))
    ) {
        selectedNoteId.value = null
    }
})

watch(
    () => props.record?.orderID,
    () => {
        actionComment.value = ''
        actionMessage.value = ''
        selectedFileId.value = null
        selectedNoteId.value = null
        closeFileDropzone()
    },
)
</script>

<style scoped>
.closing-form {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #f1f5f9;
}

.closing-topbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 18px;
    align-items: center;
    padding: 10px 14px;
    background: #e2e8f0;
    border-bottom: 1px solid #94a3b8;
    font-size: 18px;
    color: #1e293b;
    flex-shrink: 0;
}

.closing-meta {
    font-weight: 700;
}

.closing-splitpanes {
    flex: 1;
    min-height: 0;
}

.closing-pane {
    min-height: 0;
    overflow: hidden;
}

.left-column {
    height: 100%;
    min-height: 0;
    overflow: hidden;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-sizing: border-box;
}

.left-inner-splitpanes,
.price-info-splitpanes {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
}

.left-top-pane,
.left-notes-pane,
.price-pane,
.info-pane {
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    display: flex;
}

.left-top-pane > .splitpanes,
.price-pane > .panel,
.info-pane > .panel,
.left-notes-pane > .panel {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
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
    flex: 0 0 auto;
}

.panel {
    background: #fff;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-sizing: border-box;
    width: 100%;
    height: 100%;
}

.panel-price {
    padding: 8px;
}

.panel-info {
    background: #dbeafe;
    overflow: auto;
}

.panel-notes,
.notes-card {
    min-height: 0;
    border-color: #cbd5e1;
    border-radius: 8px;
    padding: 12px 14px;
    background: #fff;
}

.notes-card .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
    flex: 0 0 auto;
}

.notes-card .section-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
}

.notes-card .section-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.notes-card .action-btn {
    padding: 4px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    font-size: 13px;
    font-weight: 400;
    cursor: pointer;
    white-space: nowrap;
}

.notes-card .action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.notes-card .action-btn-primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.notes-card .action-btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}

.notes-card .attachment-table-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
}

.notes-card .data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.notes-card .data-table th,
.notes-card .data-table td {
    border: 1px solid #94a3b8;
    padding: 4px 6px;
    text-align: left;
    vertical-align: top;
}

.notes-card .data-table thead th {
    background: #e2e8f0;
    font-weight: 700;
}

.notes-card .table-row {
    cursor: pointer;
}

.notes-card .table-row:hover td {
    background: #dbeafe;
}

.notes-card .active-row td {
    color: #fff !important;
    background: #7e25eb !important;
}

.notes-card .table-row.active-row:hover td {
    background: #7e25eb !important;
}

.notes-card .important-row td {
    background: #fef3c7;
}

.notes-card .text-cell {
    white-space: pre-wrap;
    word-break: break-word;
}

.notes-card :deep(.note-autolink) {
    color: #1d4ed8;
    text-decoration: underline;
    word-break: break-all;
}

.notes-card :deep(.active-row .note-autolink) {
    color: #fff;
}

.notes-card .notes-table {
    table-layout: fixed;
}

.notes-card .notes-table th,
.notes-card .notes-table td {
    font-weight: 700;
}

.notes-card .notes-table .col-note-date,
.notes-card .notes-table .col-note-author {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    box-sizing: border-box;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notes-card .notes-table .col-note-body {
    width: auto;
}

.notes-card .empty-message {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.price-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 15px;
    font-weight: 700;
    table-layout: fixed;
}

.price-table th,
.price-table td {
    border-bottom: 1px solid #cbd5e1;
    padding: 6px 8px;
    text-align: left;
    word-break: break-word;
    font-weight: 700;
}

.price-table th {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
}

.col-amount {
    text-align: right !important;
    white-space: nowrap;
    width: 96px;
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

.row-summary td {
    background: #f8fafc;
    font-weight: 800;
}

.row-total td {
    background: #e2e8f0;
    font-weight: 800;
}

.info-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
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

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.data-table th,
.data-table td {
    border-bottom: 1px solid #cbd5e1;
    padding: 7px 8px;
    text-align: left;
}

.info-block h4 {
    margin: 0 0 6px;
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
}

.info-block p {
    margin: 0 0 3px;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
    line-height: 1.35;
}

.panel-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.notes-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
}

.table-row {
    cursor: pointer;
}

.table-row:hover td {
    background: #dbeafe;
}

.active-row td {
    color: #fff !important;
    background: #7e25eb !important;
}

.table-row.active-row:hover td {
    background: #7e25eb !important;
}

.important-row td {
    background: #fef3c7;
}

:deep(.note-autolink) {
    color: #1d4ed8;
    text-decoration: underline;
    word-break: break-all;
}

:deep(.active-row .note-autolink) {
    color: #fff;
}

.notes-table {
    table-layout: fixed;
}

.notes-table th,
.notes-table td {
    font-weight: 700;
}

.notes-table .col-note-date,
.notes-table .col-note-author {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    box-sizing: border-box;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notes-table .col-note-body {
    width: auto;
}

.col-date,
.col-author {
    white-space: nowrap;
    width: 96px;
}

.captured-images-panel {
    flex: 0 0 auto;
    margin: 0 0 8px;
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
</style>
