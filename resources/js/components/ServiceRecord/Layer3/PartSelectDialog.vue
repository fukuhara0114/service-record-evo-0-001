<template>
    <BaseDialog title="部品追加" large @close="$emit('close')">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>

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
            <button type="button" class="btn-secondary" :disabled="saving" @click="$emit('close')">
                キャンセル
            </button>
            <button type="button" class="btn-primary" :disabled="saving || !selectedItem" @click="save">
                {{ saving ? '追加中...' : '追加' }}
            </button>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>partID</th>
                        <th>部品名</th>
                        <th>説明</th>
                        <th>price_discounted</th>
                        <th>type</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in filteredItems"
                        :key="item.partID"
                        class="table-row"
                        :class="{ selected: selectedPartId === item.partID, disabled: isAlreadyAttached(item.partID) }"
                        @click="selectItem(item)"
                    >
                        <td>{{ item.partID }}</td>
                        <td>{{ item.partName || '—' }}</td>
                        <td>{{ item.description || '—' }}</td>
                        <td>{{ formatPrice(item.price_discounted) }}</td>
                        <td>{{ item.type || '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!filteredItems.length" class="empty-message">該当する部品がありません。</p>
        </div>
    </BaseDialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import BaseDialog from './BaseDialog.vue'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const page = usePage()
const searchQuery = ref('')
const selectedPartId = ref(null)
const saving = ref(false)
const error = ref('')

const attachedPartIds = computed(() => new Set((props.payload?.attachedPartIds ?? []).map(String)))

const items = computed(() => page.props.partsMaster ?? [])

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
            item?.type,
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

function formatPrice(value) {
    const num = Number(value)
    if (Number.isNaN(num)) return '—'
    return num.toLocaleString('ja-JP')
}

function getApiBasePath() {
    return window.location.pathname.replace(/\/administrator\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function save() {
    if (!selectedItem.value) {
        error.value = '部品を選択してください。'
        return
    }

    if (isAlreadyAttached(selectedItem.value.partID)) {
        error.value = 'この部品は既に追加されています。'
        return
    }

    saving.value = true
    error.value = ''

    const basePath = getApiBasePath()
    const url = `${window.location.origin}${basePath}/parts`

    try {
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                associatedID: props.record?.orderID,
                partID: selectedItem.value.partID,
            }),
        })

        if (!result) {
            return
        }

        const { response, data } = result

        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `追加に失敗しました。（HTTP ${response.status}）`)
        }

        emit('saved', data)
    } catch (e) {
        error.value = e.message || '追加に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.order-id {
    margin: 0 0 12px;
    color: #475569;
    font-size: 14px;
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
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
    color: #1e293b;
}

.data-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #e2e8f0;
    font-weight: 600;
    color: #334155;
}

.table-row {
    background: transparent;
    cursor: pointer;
}

.table-row.selected {
    background: #dbeafe;
}

.table-row.disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.table-row:hover:not(.disabled) {
    background: #eff6ff;
}

.table-row:last-child td {
    border-bottom: none;
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
