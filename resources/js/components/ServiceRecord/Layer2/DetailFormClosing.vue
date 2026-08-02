<template>
    <div class="closing-form">
        <header class="closing-topbar">
            <span class="closing-meta">{{ draftRecord?.productName || record?.productName || '—' }}</span>
            <span class="closing-meta">{{ draftRecord?.dealer || record?.dealer || '—' }}</span>
            <span class="closing-meta">{{ returnCodeLabel }}</span>
        </header>

        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <Splitpanes v-else class="default-theme closing-splitpanes" @resized="onSplitResized">
            <Pane class="closing-pane closing-pane-left" :size="leftPaneSize" :min-size="28">
                <div class="left-scroll">
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

                    <section class="panel">
                        <table class="price-table">
                            <thead>
                                <tr>
                                    <th>項目</th>
                                    <th class="col-amount">金額</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>作業内容（{{ returnCodeLabel }}）</td>
                                    <td class="col-amount">{{ formatPrice(workPrice) }}</td>
                                </tr>
                                <tr>
                                    <td>a2la{{ isA2laOn ? '' : '（OFF）' }}</td>
                                    <td class="col-amount">{{ formatPrice(a2laPrice) }}</td>
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

                    <section class="panel panel-info">
                        <h3>Dealer / E/U / Delivery</h3>
                        <div class="info-grid">
                            <div>
                                <h4>Dealer</h4>
                                <p>{{ draftRecord?.dealer || record?.dealer || '—' }}</p>
                                <p>{{ draftRecord?.dealer_depart || record?.dealer_depart || '—' }}</p>
                                <p>{{ draftRecord?.contactPerson || record?.contactPerson || '—' }}</p>
                                <p>{{ draftRecord?.email || record?.email || '—' }}</p>
                                <p>{{ draftRecord?.phone || record?.phone || '—' }}</p>
                            </div>
                            <div>
                                <h4>E/U</h4>
                                <p>{{ draftRecord?.endUser || record?.endUser || '—' }}</p>
                                <p>{{ draftRecord?.endUser_depart || record?.endUser_depart || '—' }}</p>
                                <p>{{ draftRecord?.endUser_contactPerson || record?.endUser_contactPerson || '—' }}</p>
                                <p>{{ draftRecord?.endUser_email || record?.endUser_email || '—' }}</p>
                                <p>{{ draftRecord?.endUser_phone || record?.endUser_phone || '—' }}</p>
                            </div>
                            <div>
                                <h4>Delivery</h4>
                                <p>{{ draftRecord?.deliveryDestination_company || record?.deliveryDestination_company || '—' }}</p>
                                <p>{{ draftRecord?.deliveryDestination_depart || record?.deliveryDestination_depart || '—' }}</p>
                                <p>{{ draftRecord?.deliveryDestination_contactPerson || record?.deliveryDestination_contactPerson || '—' }}</p>
                                <p>{{ draftRecord?.deliveryDestination_email || record?.deliveryDestination_email || '—' }}</p>
                                <p>{{ draftRecord?.deliveryDestination_phone || record?.deliveryDestination_phone || '—' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="panel panel-notes">
                        <div class="panel-header">
                            <h3>Notes（{{ sharedNotes.length }}件）</h3>
                            <button type="button" class="action-btn action-btn-primary" @click="openNoteCreate">新規追加</button>
                        </div>
                        <div v-if="sharedNotes.length" class="notes-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>日時</th>
                                        <th>記入者</th>
                                        <th>内容</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="note in sharedNotes" :key="note.id">
                                        <td class="col-date">{{ formatDate(note.whenWrote) }}</td>
                                        <td class="col-author">{{ note.whoWrote || '—' }}</td>
                                        <td class="text-cell" v-html="linkifyNote(note.note)" />
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="empty-message">Notes がありません。</p>
                    </section>
                </div>
            </Pane>

            <Pane class="closing-pane closing-pane-right" :size="rightPaneSize" :min-size="30">
                <div class="right-stack">
                    <section class="files-panel">
                        <div class="files-header">
                            <h3>Files（{{ sortedFiles.length }}件）</h3>
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

                        <div v-if="sortedFiles.length" class="files-list-wrap">
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
                        </div>
                        <p v-else class="empty-message">Files がありません。</p>
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
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import ShippingOutDateDialog from '@/components/ServiceRecord/Layer3/ShippingOutDateDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { linkifyText } from '@/utils/linkifyText'

const props = defineProps({
    record: Object,
    draftRecord: Object,
    notes: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    parts: { type: Array, default: () => [] },
    attachmentsLoading: { type: Boolean, default: false },
    attachmentsError: { type: String, default: '' },
})

const emit = defineEmits(['open-dialog', 'files-updated', 'reload-attachments', 'save', 'workflow-done'])

const page = usePage()
const leftPaneSize = ref(48)
const rightPaneSize = ref(52)
const selectedFileId = ref(null)
const actionComment = ref('')
const actionMessage = ref('')
const statusActionSaving = ref(false)
const showShippingDialog = ref(false)
const fileSortSaving = ref(false)
const fileDropInputEl = ref(null)
const showFileDropzone = ref(false)
const fileDropActive = ref(false)
const fileDropUploading = ref(false)
const fileDropError = ref('')
const fileDropProgress = ref('')
const fileDragDepth = ref(0)

const sharedNotes = computed(() =>
    (props.notes ?? []).filter(note => !(note?.personal === true || note?.personal === 1 || note?.personal === '1')),
)

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

const workPrice = computed(() => {
    const value = Number(props.draftRecord?.price ?? props.record?.price ?? 0)
    return Number.isFinite(value) ? value : 0
})

const a2laPrice = computed(() => {
    if (!isA2laOn.value) return 0
    const serviceID = props.draftRecord?.serviceID ?? props.record?.serviceID
    const master = (page.props.servicesMaster ?? []).find(item => String(item.serviceID) === String(serviceID))
    const value = Number(master?.price_a2la ?? 0)
    return Number.isFinite(value) ? value : 0
})

const partsPriceTotal = computed(() =>
    (props.parts ?? []).reduce((sum, part) => {
        const value = Number(part.part_master?.price_discounted)
        return sum + (Number.isNaN(value) ? 0 : value)
    }, 0),
)

const adjustmentAmount = computed(() => {
    const value = Number(props.draftRecord?.discount_service ?? props.record?.discount_service ?? 0)
    return Number.isFinite(value) ? value : 0
})

const subtotal = computed(() => workPrice.value + a2laPrice.value + partsPriceTotal.value)
const grandTotal = computed(() => subtotal.value - adjustmentAmount.value)

function formatPrice(value) {
    const num = Number(value)
    if (!Number.isFinite(num)) return '—'
    return new Intl.NumberFormat('ja-JP').format(num)
}

function formatSignedAmount(value) {
    const num = Number(value)
    if (!Number.isFinite(num) || num === 0) return '0'
    const abs = formatPrice(Math.abs(num))
    return num > 0 ? `-${abs}` : `+${abs}`
}

function formatDate(value) {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)
    const pad = (n) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function linkifyNote(value) {
    const html = linkifyText(value)
    return html || '—'
}

function getApiBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer)\/?$/, '')
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

watch(
    () => props.record?.orderID,
    () => {
        actionComment.value = ''
        actionMessage.value = ''
        selectedFileId.value = null
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

.price-table th,
.price-table td,
.data-table th,
.data-table td {
    border-bottom: 1px solid #cbd5e1;
    padding: 7px 8px;
    text-align: left;
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

.info-grid h4 {
    margin: 0 0 6px;
    font-size: 12px;
    color: #334155;
}

.info-grid p {
    margin: 0 0 2px;
    font-size: 12px;
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

@media (max-width: 960px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
