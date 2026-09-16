<template>
    <div class="stocked-part-select-host">
    <BaseDialog title="stocked Parts 選択" large @close="$emit('close')">
        <div class="select-dialog-layout">
            <div class="order-row">
                <p class="order-id">OrderID: {{ record?.orderID }}</p>
                <button
                    type="button"
                    class="btn-add-master"
                    :disabled="creatingMaster"
                    @click="openCreateMasterDialog"
                >
                    新規Parts追加
                </button>
            </div>
            <p class="help-text">部品を選択したあと、数量入力へ進みます。</p>

            <label class="search-field">
                検索
                <input
                    v-model="searchQuery"
                    type="text"
                    class="search-input"
                    placeholder="partID / partName / description で検索"
                >
            </label>

            <p v-if="error" class="error-message">{{ error }}</p>

            <div class="dialog-actions">
                <button type="button" class="btn-secondary" @click="$emit('close')">キャンセル</button>
                <button type="button" class="btn-primary" :disabled="!selectedItem" @click="goNext">
                    数量入力へ
                </button>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>partID</th>
                            <th>部品名</th>
                            <th>説明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in filteredItems"
                            :key="item.partID"
                            class="table-row"
                            :class="{ selected: selectedPartId === item.partID, disabled: isAlreadyAttached(item.partID) }"
                            @click="selectItem(item)"
                            @dblclick="onRowDblClick(item)"
                        >
                            <td>{{ item.partID }}</td>
                            <td>{{ item.partName || '—' }}</td>
                            <td>{{ item.description || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!filteredItems.length" class="empty-message">該当する部品がありません。</p>
            </div>
        </div>
    </BaseDialog>

        <div
            v-if="createMasterOpen"
            class="create-master-overlay"
            @click.self="closeCreateMasterDialog"
        >
            <div
                class="create-master-panel"
                role="dialog"
                aria-modal="true"
                aria-labelledby="create-master-title"
            >
                <header class="create-master-header">
                    <h3 id="create-master-title">新規Parts追加</h3>
                    <button
                        type="button"
                        class="create-master-close"
                        aria-label="閉じる"
                        :disabled="creatingMaster"
                        @click="closeCreateMasterDialog"
                    >
                        ×
                    </button>
                </header>
                <div class="create-master-body">
                    <p class="create-master-help">stockedpartmaster に新しい部品を追加します。</p>
                    <label class="create-field">
                        <span>部品名</span>
                        <input
                            v-model="createForm.partName"
                            type="text"
                            class="create-input"
                            :disabled="creatingMaster"
                            placeholder="partName"
                        >
                    </label>
                    <label class="create-field">
                        <span>説明</span>
                        <textarea
                            v-model="createForm.description"
                            class="create-input create-textarea"
                            :disabled="creatingMaster"
                            placeholder="description"
                            rows="3"
                        />
                    </label>
                    <p v-if="createError" class="error-message">{{ createError }}</p>
                </div>
                <footer class="create-master-footer">
                    <button
                        type="button"
                        class="btn-secondary"
                        :disabled="creatingMaster"
                        @click="closeCreateMasterDialog"
                    >
                        キャンセル
                    </button>
                    <button
                        type="button"
                        class="btn-primary"
                        :disabled="creatingMaster"
                        @click="saveNewMaster"
                    >
                        {{ creatingMaster ? '追加中...' : '追加' }}
                    </button>
                </footer>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import BaseDialog from './BaseDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { getServiceRecordBasePath } from '@/utils/serviceRecordApiBase'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'selected'])

const page = usePage()
const searchQuery = ref('')
const selectedPartId = ref(null)
const error = ref('')
const extraMasters = ref([])
const createMasterOpen = ref(false)
const creatingMaster = ref(false)
const createError = ref('')
const createForm = reactive({
    partName: '',
    description: '',
})

const attachedPartIds = computed(() => new Set((props.payload?.attachedPartIds ?? []).map(String)))
const items = computed(() => {
    const extras = extraMasters.value
    const extraIds = new Set(extras.map((item) => String(item.partID)))
    const rest = (page.props.stockedPartsMaster ?? []).filter(
        (item) => !extraIds.has(String(item.partID)),
    )
    return [...extras, ...rest]
})

const filteredItems = computed(() => {
    const tokens = searchQuery.value
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter(Boolean)

    if (tokens.length === 0) return items.value

    return items.value.filter((item) => {
        const text = [
            item?.partID,
            item?.partName,
            item?.description,
        ]
            .filter(value => value != null && value !== '')
            .join(' ')
            .toLowerCase()

        return tokens.every(token => text.includes(token))
    })
})

const selectedItem = computed(() =>
    items.value.find(item => String(item.partID) === String(selectedPartId.value)),
)

function isAlreadyAttached(partId) {
    return attachedPartIds.value.has(String(partId))
}

function selectItem(item) {
    if (isAlreadyAttached(item.partID)) {
        error.value = 'この部品は既に追加されています。'
        return
    }

    error.value = ''
    selectedPartId.value = item.partID
}

function goNext() {
    if (!selectedItem.value) {
        error.value = '部品を選択してください。'
        return
    }
    if (isAlreadyAttached(selectedItem.value.partID)) {
        error.value = 'この部品は既に追加されています。'
        return
    }

    emit('selected', {
        mode: 'create',
        partID: selectedItem.value.partID,
        partName: selectedItem.value.partName,
        description: selectedItem.value.description,
    })
}

function onRowDblClick(item) {
    selectItem(item)
    if (String(selectedPartId.value) !== String(item?.partID)) return
    goNext()
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function resetCreateForm() {
    createForm.partName = ''
    createForm.description = ''
    createError.value = ''
}

function openCreateMasterDialog() {
    if (creatingMaster.value) return
    resetCreateForm()
    createMasterOpen.value = true
}

function closeCreateMasterDialog() {
    if (creatingMaster.value) return
    createMasterOpen.value = false
    createError.value = ''
}

async function saveNewMaster() {
    const partName = String(createForm.partName ?? '').trim()
    if (!partName) {
        createError.value = '部品名を入力してください。'
        return
    }

    const payload = {
        partName,
        description: String(createForm.description ?? '').trim() || null,
    }

    creatingMaster.value = true
    createError.value = ''
    try {
        const result = await apiFetch(
            `${window.location.origin}${getServiceRecordBasePath()}/stocked-parts-master`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            },
        )
        if (!result) throw new Error('追加に失敗しました。')
        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `追加に失敗しました。（HTTP ${response.status}）`)
        }
        const created = data?.master
        if (!created?.partID) throw new Error('追加後の partID を取得できませんでした。')

        extraMasters.value = [
            created,
            ...extraMasters.value.filter((item) => String(item.partID) !== String(created.partID)),
        ]
        selectedPartId.value = created.partID
        error.value = ''
        createMasterOpen.value = false
        resetCreateForm()
    } catch (e) {
        createError.value = e.message || '追加に失敗しました。'
    } finally {
        creatingMaster.value = false
    }
}
</script>

<style scoped>
.order-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 8px;
}

.order-id {
    margin: 0;
    color: #475569;
    font-size: 14px;
}

.btn-add-master {
    flex: 0 0 auto;
    min-width: 140px;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    background: #2563eb;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.btn-add-master:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.create-master-overlay {
    position: fixed;
    inset: 0;
    z-index: 230;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 16px;
    background: rgba(0, 0, 0, 0.45);
}

.create-master-panel {
    width: min(480px, calc(100vw - 32px));
    background: #fff;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.create-master-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    background: #1e293b;
    color: #fff;
}

.create-master-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}

.create-master-close {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 4px;
    background: #475569;
    color: #fff;
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
}

.create-master-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px 14px;
}

.create-master-help {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.create-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

.create-input {
    width: 100%;
    box-sizing: border-box;
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font: inherit;
    font-weight: 600;
}

.create-textarea {
    resize: vertical;
    min-height: 72px;
}

.create-master-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 0 14px 14px;
}

.help-text {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 13px;
}

.search-field {
    display: block;
    margin-bottom: 12px;
    font-weight: bold;
    font-size: 14px;
}

.search-input {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
}

.table-wrap {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    overflow: auto;
}

.dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin: 8px 0 12px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 10px 12px;
    border: 1px solid #333333;
    text-align: left;
    font-size: 13px;
    color: #1e293b;
}

.data-table th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #2f63cc;
    color: #fff;
    font-weight: 700;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
}

.data-table td {
    background: #f5f5f5;
}

.table-row {
    cursor: pointer;
}

.table-row.selected td {
    color: #1e293b !important;
    background-color: #cab7e1 !important;
}

.table-row.disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.table-row:hover:not(.disabled) {
    background: #eff6ff;
}

.error-message {
    margin: 0 0 12px;
    color: #b91c1c;
    font-size: 14px;
}

.empty-message {
    margin: 0;
    padding: 16px;
    color: #64748b;
}

.btn-primary,
.btn-secondary {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}
</style>
