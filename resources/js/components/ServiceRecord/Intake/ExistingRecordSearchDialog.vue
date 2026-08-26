<template>
    <div
        :class="inline ? 'inline-root' : 'dialog-overlay dialog-overlay-blocking'"
        @click.self="!inline && $emit('close')"
    >
        <div :class="inline ? 'inline-panel' : 'dialog-panel'">
            <div v-if="!inline" class="dialog-header">
                <div>
                    <h3>{{ dialogTitle }}</h3>
                    <p>{{ querySummary || '検索キーワードなし' }}</p>
                </div>
                <button type="button" class="close-btn" @click="$emit('close')">×</button>
            </div>
            <div v-else class="inline-toolbar">
                <div>
                    <p class="inline-summary">{{ querySummary || '検索キーワードなし' }}</p>
                    <p class="inline-hint">{{ dialogHint }}</p>
                </div>
                <button
                    type="button"
                    class="btn-secondary"
                    :disabled="searching"
                    @click="$emit('search')"
                >
                    {{ searching ? '検索中...' : '再検索' }}
                </button>
            </div>

            <div class="dialog-body">
                <div class="result-pane">
                    <div class="pane-header">
                        <h4>検索結果（{{ records.length }}件）</h4>
                    </div>
                    <div class="result-list">
                        <button
                            v-for="record in records"
                            :key="record.orderID"
                            type="button"
                            class="result-item"
                            :class="{ active: selectedOrderId === record.orderID }"
                            @click="selectedOrderId = record.orderID"
                        >
                            <strong>{{ record.productName || '—' }}</strong>
                            <span class="result-order-status-row">
                                <span>orderID: {{ record.orderID }}</span>
                                <span class="result-status">{{ formatRecordStatus(record) }}</span>
                            </span>
                            <span v-if="purpose === 'loaner'">type: {{ record.order_type || '—' }}</span>
                            <span v-if="purpose === 'loaner'">item: {{ record.item || '—' }}</span>
                            <span>S/N: {{ record.SN || '—' }}</span>
                            <span>Dealer: {{ record.dealer || '—' }}</span>
                            <span>Contact: {{ record.contactPerson || '—' }}</span>
                        </button>
                        <p v-if="!records.length" class="empty-message">
                            {{ hasSearched ? '該当案件はありません。' : '「再検索」で検索してください。' }}
                        </p>
                    </div>
                </div>

                <div class="detail-pane">
                    <div class="pane-header">
                        <h4>Notes / Files</h4>
                    </div>
                    <div v-if="selectedRecord" class="detail-scroll">
                        <p v-if="attachmentsLoading" class="attachment-status">Notes / Files を読み込み中...</p>
                        <p v-else-if="attachmentsError" class="attachment-status error">{{ attachmentsError }}</p>

                        <template v-else>
                            <section class="attachment-section">
                                <div class="attachment-section-header">
                                    <h5>Notes（{{ sharedNotes.length }}件）</h5>
                                </div>
                                <div class="notes-host">
                                    <NotesTable
                                        v-model:selected-id="selectedNoteId"
                                        :notes="sharedNotes"
                                        :record-order-id="selectedRecord.orderID"
                                        :date-column-width="134"
                                        :author-column-width="66"
                                        :table-font-size="12"
                                        :allow-edit="false"
                                    />
                                </div>
                            </section>

                            <section class="attachment-section attachment-section-files">
                                <div class="attachment-section-header">
                                    <h5>Files（書類 {{ sortedDetailFiles.length }}件）</h5>
                                </div>
                                <div class="files-list-wrap">
                                    <AttachedFileItem
                                        v-for="(file, index) in sortedDetailFiles"
                                        :key="file.id"
                                        :file="file"
                                        :order-id="selectedRecord.orderID"
                                        :file-base-url="filesBaseUrl"
                                        :selected="selectedFileId === file.id"
                                        :can-move-up="false"
                                        :can-move-down="false"
                                        :sorting="false"
                                        @select="selectedFileId = file.id"
                                    />
                                    <p v-if="!sortedDetailFiles.length" class="empty-message">書類ファイルがありません。</p>
                                </div>
                            </section>
                        </template>
                    </div>
                    <p v-else class="empty-message">左の一覧から案件を選択してください。</p>
                    <div class="detail-actions">
                        <button
                            v-if="purpose === 'parent'"
                            type="button"
                            class="btn-primary"
                            :disabled="!selectedRecord"
                            @click="confirmParentSelect"
                        >
                            この案件を親として選択
                        </button>
                        <button
                            v-else-if="purpose === 'loaner'"
                            type="button"
                            class="btn-primary"
                            :disabled="!selectedRecord || Boolean(selectedRecord.parentID)"
                            @click="confirmLoanerSelect"
                        >
                            {{ selectedRecord?.parentID ? '既に紐づき済み' : '新規作成して紐づけ対象に追加' }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="btn-primary"
                            :disabled="!selectedRecord"
                            @click="openLinkConfirm"
                        >
                            選択した案件にPDFをアタッチ
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showLinkConfirm" class="confirm-overlay" @click.self="showLinkConfirm = false">
            <div class="confirm-panel">
                <div class="confirm-header">
                    <h3>紐づけ確認</h3>
                    <button type="button" class="close-btn" @click="showLinkConfirm = false">×</button>
                </div>
                <div class="confirm-body">
                    <p class="confirm-message">
                        選択した案件にファイルを紐づけます。必要なら receivedDate / status も変更できます。
                    </p>
                    <div class="confirm-fields">
                        <label class="field">
                            <span>productName</span>
                            <input type="text" :value="selectedRecord?.productName || ''" readonly>
                        </label>
                        <label class="field">
                            <span>SN</span>
                            <input type="text" :value="selectedRecord?.SN || ''" readonly>
                        </label>
                        <label class="field">
                            <span>receivedDate</span>
                            <DateInputWithToday v-model="linkForm.receivedDate" />
                        </label>
                        <label class="field">
                            <span>status</span>
                            <select v-model="linkForm.status">
                                <option
                                    v-for="status in statuses"
                                    :key="status.processID_new"
                                    :value="String(status.processID_new)"
                                >
                                    {{ statusMasterOptionLabel(status) }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn-secondary" @click="showLinkConfirm = false">キャンセル</button>
                    <button type="button" class="btn-primary" @click="confirmLink">紐づけ実行</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import NotesTable from '@/components/ServiceRecord/NotesTable.vue'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import DateInputWithToday from '@/components/DateInputWithToday.vue'
import { apiFetch } from '@/utils/apiFetch'
import { loanerStatusLabel, statusMasterOptionLabel } from '@/utils/loanerStatusLabel'

const props = defineProps({
    records: {
        type: Array,
        default: () => [],
    },
    querySummary: {
        type: String,
        default: '',
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    inline: {
        type: Boolean,
        default: false,
    },
    searching: {
        type: Boolean,
        default: false,
    },
    hasSearched: {
        type: Boolean,
        default: false,
    },
    /** 'file' = PDF紐づけ / 'parent' = 貸出の親選択 / 'loaner' = 貸出案件選択 */
    purpose: {
        type: String,
        default: 'file',
    },
})

const emit = defineEmits(['close', 'link-selected', 'parent-selected', 'loaner-selected', 'search'])

const page = usePage()
const selectedOrderId = ref(null)
const showLinkConfirm = ref(false)
const linkForm = reactive({
    receivedDate: '',
    status: '',
})

const attachmentsLoading = ref(false)
const attachmentsError = ref('')
const detailNotes = ref([])
const detailFiles = ref([])
const selectedNoteId = ref(null)
const selectedFileId = ref(null)
let attachmentsRequestSeq = 0

const dialogTitle = computed(() => {
    if (props.purpose === 'parent') return '親案件の検索'
    if (props.purpose === 'loaner') return 'loaner案件検索'
    if (props.purpose === 'file') return 'service案件検索'
    return '既存案件検索'
})

const dialogHint = computed(() => {
    if (props.purpose === 'loaner') {
        return '検索: productName→item / dealer→dealer（部分一致）。選択した loaner に新規 service を作成して parentID を設定します'
    }
    if (props.purpose === 'file') {
        return '検索: productName / SN / dealer / contactPerson の各項目が対応カラムに含まれる案件（部分一致・AND）'
    }
    if (props.purpose === 'parent') {
        return 'order_type=service を productName / SN / dealer / contactPerson で検索'
    }
    return 'productName / SN / dealer / contactPerson で検索します'
})

function formatRecordStatus(record) {
    if (!record) return '—'
    const id = record.status
    const label = record.status_label
        ?? record.statusMaster?.status
        ?? record.status_master?.status
        ?? loanerStatusLabel(record.statusMasterLoaner)
        ?? loanerStatusLabel(record.status_master_loaner)
        ?? loanerStatusLabel(props.statuses?.find(s => String(s.processID_new) === String(id)))
        ?? null
    if (label != null && label !== '' && id != null && id !== '') {
        return `${label} (${id})`
    }
    if (label != null && label !== '') return String(label)
    if (id != null && id !== '') return String(id)
    return '—'
}

watch(
    () => props.records,
    (records) => {
        selectedOrderId.value = records[0]?.orderID ?? null
        showLinkConfirm.value = false
    },
    { immediate: true },
)

const selectedRecord = computed(() =>
    (props.records ?? []).find(record => String(record.orderID) === String(selectedOrderId.value)),
)

const sharedNotes = computed(() =>
    (detailNotes.value ?? []).filter(note => !(
        note?.personal === true || note?.personal === 1 || note?.personal === '1'
    )),
)

const sortedDetailFiles = computed(() =>
    [...(detailFiles.value ?? [])].sort((a, b) => {
        const aSort = Number(a?.sortNum ?? Number.MAX_SAFE_INTEGER)
        const bSort = Number(b?.sortNum ?? Number.MAX_SAFE_INTEGER)
        if (aSort !== bSort) return aSort - bSort
        return Number(a?.id ?? 0) - Number(b?.id ?? 0)
    }),
)

const filesBaseUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/files`)

watch(selectedRecord, (record) => {
    showLinkConfirm.value = false
    selectedNoteId.value = null
    selectedFileId.value = null
    loadAttachments(record?.orderID)
})

async function loadAttachments(orderID) {
    const requestSeq = ++attachmentsRequestSeq
    detailNotes.value = []
    detailFiles.value = []
    attachmentsError.value = ''

    if (!orderID) {
        attachmentsLoading.value = false
        return
    }

    attachmentsLoading.value = true
    try {
        const url = `${page.props.appBaseUrl}/servicerecord/attachments/${orderID}`
        const result = await apiFetch(url)
        if (requestSeq !== attachmentsRequestSeq) return
        if (!result?.response?.ok) {
            throw new Error(result?.data?.message || '添付データの取得に失敗しました。')
        }
        detailNotes.value = result.data?.notes ?? []
        detailFiles.value = result.data?.files ?? []
        selectedFileId.value = detailFiles.value[0]?.id ?? null
    } catch (e) {
        if (requestSeq !== attachmentsRequestSeq) return
        attachmentsError.value = e.message || '添付データの取得に失敗しました。'
    } finally {
        if (requestSeq === attachmentsRequestSeq) {
            attachmentsLoading.value = false
        }
    }
}

function toDateInputValue(value) {
    if (!value) return ''
    const match = String(value).match(/^(\d{4}-\d{2}-\d{2})/)
    return match ? match[1] : ''
}

function openLinkConfirm() {
    if (!selectedRecord.value) return
    linkForm.receivedDate = toDateInputValue(selectedRecord.value.receivedDate)
    linkForm.status = selectedRecord.value.status != null ? String(selectedRecord.value.status) : ''
    showLinkConfirm.value = true
}

function confirmParentSelect() {
    if (!selectedRecord.value) return
    emit('parent-selected', {
        record: selectedRecord.value,
    })
}

function confirmLoanerSelect() {
    if (!selectedRecord.value || selectedRecord.value.parentID) return
    emit('loaner-selected', {
        record: selectedRecord.value,
    })
}

function confirmLink() {
    if (!selectedRecord.value) return
    emit('link-selected', {
        record: selectedRecord.value,
        receivedDate: linkForm.receivedDate || null,
        status: linkForm.status === '' ? null : Number(linkForm.status),
    })
    showLinkConfirm.value = false
}
</script>

<style scoped>
.dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.28);
    z-index: 320;
    display: flex;
    justify-content: flex-end;
    align-items: stretch;
    padding: 12px;
    box-sizing: border-box;
    pointer-events: none;
}

.dialog-overlay-blocking {
    pointer-events: auto;
}

.dialog-panel,
.confirm-overlay,
.inline-root {
    pointer-events: auto;
}

.dialog-panel {
    width: min(52vw, 920px);
    height: calc(100vh - 24px);
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.dialog-panel > .dialog-body {
    margin: 12px;
    flex: 1;
}

.inline-root {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.inline-panel {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
}

.inline-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 0 0 12px;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.inline-summary {
    margin: 0 0 4px;
    font-size: 13px;
    color: #1e293b;
    font-weight: 700;
}

.inline-hint {
    margin: 0;
    font-size: 12px;
    color: #64748b;
}

.dialog-header,
.confirm-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 12px 16px;
    background: #1e293b;
    color: #fff;
}

.dialog-header h3,
.confirm-header h3 {
    margin: 0 0 4px;
    font-size: 16px;
}

.dialog-header p {
    margin: 0;
    font-size: 12px;
    color: #cbd5e1;
}

.close-btn {
    background: none;
    border: none;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
}

.dialog-body {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: minmax(200px, 38%) minmax(240px, 62%);
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
}

.result-pane,
.detail-pane {
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #fff;
}

.result-pane {
    border-right: 1px solid #e2e8f0;
}

.pane-header {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
}

.pane-header h4 {
    margin: 0;
    font-size: 14px;
    color: #1e293b;
}

.result-list {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.result-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    text-align: left;
    cursor: pointer;
    color: #334155;
}

.result-item.active {
    border-color: #2563eb;
    background: #dbeafe;
}

.result-item strong {
    color: #1e293b;
}

.result-order-status-row {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
}

.result-status {
    margin-left: 50px;
}

.detail-scroll {
    flex: 1;
    min-height: 0;
    overflow: auto;
    display: flex;
    flex-direction: column;
}

.attachment-section {
    padding: 12px 16px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 140px;
}

.attachment-section + .attachment-section {
    border-top: 1px solid #e2e8f0;
}

.attachment-section-files {
    flex: 1;
    min-height: 220px;
}

.attachment-section-header h5 {
    margin: 0;
    font-size: 13px;
    color: #1e293b;
}

.attachment-status {
    margin: 0;
    padding: 16px;
    font-size: 12px;
    color: #64748b;
}

.attachment-status.error {
    color: #b91c1c;
}

.notes-host {
    min-height: 120px;
    max-height: 220px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #fff;
}

.files-list-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.empty-message {
    margin: 0;
    padding: 16px;
    color: #64748b;
}

.detail-actions,
.confirm-actions {
    padding: 12px 16px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 340;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 16px;
}

.confirm-panel {
    width: min(520px, 96vw);
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.confirm-body {
    padding: 16px;
}

.confirm-message {
    margin: 0 0 14px;
    color: #334155;
    font-size: 14px;
}

.confirm-fields {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
    color: #475569;
}

.field input,
.field select {
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
}

.field select option {
    color: #1e293b;
    background-color: #fff;
}

.field input[readonly] {
    background: #f8fafc;
}

.btn-primary,
.btn-secondary {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    color: #fff;
}

.btn-primary {
    background: #2563eb;
}

.btn-secondary {
    background: #64748b;
}

.btn-primary:disabled,
.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
