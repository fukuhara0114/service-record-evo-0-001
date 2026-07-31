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
                            <span>orderID: {{ record.orderID }}</span>
                            <span v-if="purpose === 'loaner'">type: {{ record.order_type || '—' }}</span>
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
                        <h4>案件詳細</h4>
                    </div>
                    <div v-if="selectedRecord" class="detail-grid">
                        <div><span>orderID</span><strong>{{ selectedRecord.orderID || '—' }}</strong></div>
                        <div><span>order_type</span><strong>{{ selectedRecord.order_type || '—' }}</strong></div>
                        <div><span>productName</span><strong>{{ selectedRecord.productName || '—' }}</strong></div>
                        <div><span>SN</span><strong>{{ selectedRecord.SN || '—' }}</strong></div>
                        <div><span>dealer</span><strong>{{ selectedRecord.dealer || '—' }}</strong></div>
                        <div><span>dealer_depart</span><strong>{{ selectedRecord.dealer_depart || '—' }}</strong></div>
                        <div><span>contactPerson</span><strong>{{ selectedRecord.contactPerson || '—' }}</strong></div>
                        <div v-if="purpose === 'loaner'">
                            <span>status</span>
                            <strong>{{ selectedRecord.status_master_loaner?.status || selectedRecord.status || '—' }}</strong>
                        </div>
                        <div v-if="purpose === 'loaner'">
                            <span>parentID</span>
                            <strong>{{ selectedRecord.parentID || 'なし' }}</strong>
                        </div>
                        <div v-if="purpose !== 'loaner'">
                            <span>returnCode</span>
                            <strong>{{ selectedRecord.return_code_master?.description || '—' }}</strong>
                        </div>
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
                            <input v-model="linkForm.receivedDate" type="date">
                        </label>
                        <label class="field">
                            <span>status</span>
                            <select v-model="linkForm.status">
                                <option
                                    v-for="status in statuses"
                                    :key="status.processID"
                                    :value="String(status.processID)"
                                >
                                    {{ status.status }} ({{ status.processID }})
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

const selectedOrderId = ref(null)
const showLinkConfirm = ref(false)
const linkForm = reactive({
    receivedDate: '',
    status: '',
})

const dialogTitle = computed(() => {
    if (props.purpose === 'parent') return '親案件の検索'
    if (props.purpose === 'loaner') return 'loaner案件検索'
    if (props.purpose === 'file') return 'service案件検索'
    return '既存案件検索'
})

const dialogHint = computed(() => {
    if (props.purpose === 'loaner') {
        return '選択した loaner に対し、新規 service を作成して parentID を設定します（productName / SN / dealer / contactPerson 必須）'
    }
    if (props.purpose === 'file') {
        return '選択した既存 service 案件へ、このファイルをアタッチします'
    }
    if (props.purpose === 'parent') {
        return 'order_type=service を productName / SN / dealer / contactPerson で検索'
    }
    return 'productName / SN / dealer / contactPerson で検索します'
})

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

watch(selectedRecord, () => {
    showLinkConfirm.value = false
})

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

.detail-grid {
    padding: 16px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    overflow: auto;
    flex: 1;
}

.detail-grid div {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f8fafc;
}

.detail-grid span {
    font-size: 12px;
    color: #64748b;
}

.detail-grid strong {
    font-size: 13px;
    color: #1e293b;
    word-break: break-word;
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
