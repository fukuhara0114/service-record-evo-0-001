<template>
    <div class="sm-submit-dialog-host">
    <BaseDialog title="SM Submit" :show-close="!saving" @close="onClose">
        <div class="sm-submit-stack">
            <div class="field-block">
                <span class="field-label">orderID</span>
                <p class="order-id">{{ record?.orderID ?? '—' }}</p>
            </div>

            <div class="field-block">
                <span class="field-label">QUOTE</span>
                <div class="quote-row">
                    <input
                        v-model="quoteInput"
                        type="text"
                        class="quote-input"
                        :disabled="saving"
                    >
                    <button
                        type="button"
                        class="btn-secondary"
                        :disabled="saving"
                        @click="copyQuote"
                    >
                        コピー
                    </button>
                </div>
                <p v-if="copyMessage" class="copy-message">{{ copyMessage }}</p>
            </div>

            <button
                type="button"
                class="btn-complete"
                :disabled="saving"
                @click="onComplete"
            >
                {{ saving ? '処理中...' : '完了' }}
            </button>

            <section class="stocked-block">
                <h4>Stocked Parts（{{ stockedParts.length }}件）</h4>
                <p v-if="partsLoading" class="status-message">読み込み中...</p>
                <p v-else-if="partsError" class="error-message">{{ partsError }}</p>
                <div v-else-if="stockedParts.length" class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Part ID</th>
                                <th>部品名</th>
                                <th>説明</th>
                                <th>使用数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="part in stockedParts" :key="part.id">
                                <td>{{ part.partID }}</td>
                                <td>{{ part.stocked_part_master?.partName || '—' }}</td>
                                <td class="text-cell">{{ part.stocked_part_master?.description || '—' }}</td>
                                <td>{{ part.quantity ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="status-message">stocked Parts がありません。</p>
            </section>

            <p v-if="errorMessage" class="error-message">{{ errorMessage }}</p>
        </div>

        <template #footer>
            <button type="button" class="btn-secondary" :disabled="saving" @click="onClose">
                閉じる
            </button>
        </template>
    </BaseDialog>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import BaseDialog from './BaseDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { confirmOrderTypeOriginalMismatchForRecord } from '@/utils/confirmOrderTypeOriginalMismatch'
import { getServiceRecordBasePath } from '@/utils/serviceRecordApiBase'

const NEXT_STATUS_FROM_185 = 190

const props = defineProps({
    record: { type: Object, required: true },
})

const emit = defineEmits(['close', 'completed'])

const page = usePage()
const saving = ref(false)
const errorMessage = ref('')
const copyMessage = ref('')
let copyMessageTimer = null
const quoteInput = ref(String(props.record?.sm_quote ?? ''))
const stockedParts = ref([])
const partsLoading = ref(false)
const partsError = ref('')

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function recordApiUrl() {
    return `${window.location.origin}${getServiceRecordBasePath()}/${props.record.orderID}`
}

function attachmentsUrl() {
    return `${window.location.origin}${getServiceRecordBasePath()}/attachments/${props.record.orderID}`
}

function onClose() {
    if (saving.value) return
    emit('close')
}

function resolveStatusLabel(processId) {
    const id = String(processId ?? '')
    const row = page.props.statuses?.find(
        (status) => String(status.processID_new) === id,
    )
    return String(row?.status ?? '').trim() || `status=${id}`
}

function completeConfirmMessage(nextStatus) {
    const label = resolveStatusLabel(nextStatus)
    return `「${label}」に変更（status=${nextStatus}）しますか？`
}

function resolveCompleteNextStatus(currentStatus) {
    if (currentStatus === 185) return NEXT_STATUS_FROM_185
    return null
}

async function writeTextToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text)
        return
    }
    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.setAttribute('readonly', '')
    textarea.style.position = 'fixed'
    textarea.style.left = '-9999px'
    document.body.appendChild(textarea)
    textarea.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(textarea)
    if (!ok) throw new Error('クリップボードへのコピーに失敗しました。')
}

async function copyQuote() {
    const text = String(quoteInput.value ?? '').trim()
    if (copyMessageTimer) clearTimeout(copyMessageTimer)
    if (!text) {
        copyMessage.value = 'QUOTE が空です'
        copyMessageTimer = setTimeout(() => {
            copyMessage.value = ''
        }, 2000)
        return
    }
    try {
        await writeTextToClipboard(text)
        copyMessage.value = `コピーしました: ${text}`
    } catch {
        copyMessage.value = 'コピーに失敗しました'
    }
    copyMessageTimer = setTimeout(() => {
        copyMessage.value = ''
    }, 2000)
}

async function loadStockedParts() {
    if (!props.record?.orderID) {
        partsError.value = '案件が選択されていません。'
        return
    }
    partsLoading.value = true
    partsError.value = ''
    try {
        const result = await apiFetch(attachmentsUrl())
        if (!result?.response?.ok) {
            throw new Error(result?.data?.message || 'Stocked Parts の取得に失敗しました。')
        }
        stockedParts.value = result.data?.stockedParts ?? []
    } catch (e) {
        partsError.value = e.message || 'Stocked Parts の取得に失敗しました。'
        stockedParts.value = []
    } finally {
        partsLoading.value = false
    }
}

async function onComplete() {
    if (saving.value) return

    if (!props.record?.orderID) {
        errorMessage.value = '案件が選択されていません。'
        return
    }

    const currentStatus = Number(props.record?.status)
    const nextStatus = resolveCompleteNextStatus(currentStatus)
    if (nextStatus == null) {
        errorMessage.value = 'この status では完了操作できません。'
        return
    }

    if (!window.confirm(completeConfirmMessage(nextStatus))) return

    if (!confirmOrderTypeOriginalMismatchForRecord(props.record)) return

    saving.value = true
    errorMessage.value = ''
    try {
        const payload = {
            status: nextStatus,
            sm_quote: String(quoteInput.value ?? '').trim(),
        }
        const result = await apiFetch(recordApiUrl(), {
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
        emit('completed', { status: nextStatus })
    } catch (e) {
        errorMessage.value = e.message || '完了処理に失敗しました。'
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    loadStockedParts()
})
</script>

<style scoped>
.sm-submit-dialog-host :deep(.dialog-overlay) {
    align-items: stretch;
    justify-content: center;
}

.sm-submit-dialog-host :deep(.dialog-panel) {
    height: 100%;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    border-radius: 0;
}

.sm-submit-dialog-host :deep(.dialog-header),
.sm-submit-dialog-host :deep(.dialog-footer) {
    flex: 0 0 auto;
}

.sm-submit-dialog-host :deep(.dialog-body) {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sm-submit-stack {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.field-block {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 0 0 auto;
}

.field-label {
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}

.order-id {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

.quote-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quote-input {
    flex: 1 1 auto;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 14px;
}

.copy-message,
.status-message {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.btn-complete {
    flex: 0 0 auto;
    width: 100%;
    padding: 10px 16px;
    border: 1px solid #15803d;
    border-radius: 4px;
    background: #15803d;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}

.btn-complete:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.stocked-block {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.stocked-block h4 {
    margin: 0 0 8px;
    font-size: 14px;
    color: #1e40af;
    flex: 0 0 auto;
}

.table-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.data-table th,
.data-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

.data-table tbody tr:nth-child(even) {
    background: #f1f5f9;
}

.data-table tbody tr:nth-child(odd) {
    background: #fff;
}

.data-table tbody tr:nth-child(6n) td {
    border-bottom: 3px solid #334155;
}

.data-table th {
    background: #f8fafc;
    color: #334155;
}

.text-cell {
    overflow-wrap: anywhere;
}

.error-message {
    margin: 0;
    color: #b91c1c;
    font-size: 14px;
    flex: 0 0 auto;
}

.btn-secondary {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    color: white;
    background: #6b7280;
    white-space: nowrap;
}

.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
