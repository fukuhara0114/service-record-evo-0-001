<template>
    <div class="dialog-overlay" @click.self="$emit('close')">
        <div class="dialog-panel">
            <div class="dialog-header">
                <h3>{{ title }}</h3>
                <button type="button" class="close-btn" @click="$emit('close')">×</button>
            </div>

            <div class="dialog-body">
                <div class="search-toolbar">
                    <label class="search-field">
                        検索
                        <input
                            ref="searchInput"
                            v-model="searchQuery"
                            type="text"
                            class="search-input"
                            :class="{ 'ime-latin': usesLatinSearch }"
                            :lang="usesLatinSearch ? 'en' : 'ja'"
                            :inputmode="usesLatinSearch ? 'latin' : 'text'"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            :placeholder="searchPlaceholder"
                            @compositionstart="onCompositionStart"
                            @compositionend="onCompositionEnd"
                            @input="onSearchInput"
                        >
                    </label>
                    <div class="dialog-actions">
                        <button type="button" class="btn-secondary" @click="$emit('close')">キャンセル</button>
                        <button type="button" class="btn-primary" :disabled="!selectedItem" @click="confirm">選択</button>
                    </div>
                </div>

                <p v-if="error" class="error-message">{{ error }}</p>

                <div v-if="kind === 'dealer'" class="preview-box">
                    <div class="preview-grid">
                        <div><span>dealerName</span><strong>{{ selectedItem?.dealerName || '—' }}</strong></div>
                        <div><span>depart</span><strong>{{ selectedItem?.depart || '—' }}</strong></div>
                        <div><span>contactPerson</span><strong>{{ selectedItem?.contactPerson || '—' }}</strong></div>
                        <div><span>email</span><strong>{{ selectedItem?.email || '—' }}</strong></div>
                        <div><span>phone</span><strong>{{ selectedItem?.phone || '—' }}</strong></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th v-for="column in columns" :key="column.key">{{ column.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in filteredItems"
                                :key="itemKey(item)"
                                class="table-row"
                                :class="{ selected: String(selectedValue) === String(itemValue(item)) }"
                                @click="selectedValue = itemValue(item)"
                                @dblclick="confirm"
                            >
                                <td v-for="column in columns" :key="column.key">
                                    {{ column.getter(item) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!filteredItems.length" class="empty-message">該当する項目がありません。</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps({
    kind: {
        type: String,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
    initialValue: {
        type: [String, Number],
        default: null,
    },
    initialSearchQuery: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['close', 'selected'])

const searchQuery = ref('')
const selectedValue = ref(null)
const error = ref('')
const searchInput = ref(null)
const isComposing = ref(false)

const configs = {
    serviceMaster: {
        title: '製品名選択',
        searchPlaceholder: '半角英数で検索（productName / entityID / id）',
        columns: [
            { key: 'id', label: 'id', getter: item => item?.id ?? '—' },
            { key: 'productName', label: 'productName', getter: item => item?.productName ?? '—' },
            { key: 'entityID', label: 'entityID', getter: item => item?.entityID ?? '—' },
        ],
        // serviceID はマスタ上で重複し得るため、一意キーは id を使う
        valueGetter: item => item?.id,
        searchFields: item => [item?.id, item?.serviceID, item?.productName, item?.entityID],
        buildResult: item => ({
            serviceID: item?.serviceID,
            productName: String(item?.productName ?? item?.entityID ?? item?.serviceID ?? ''),
            entityID: item?.entityID ?? null,
        }),
    },
    dealer: {
        title: '依頼者選択',
        searchPlaceholder: 'dealerName / depart / contactPerson / email / phone で検索',
        columns: [
            { key: 'id', label: 'id', getter: item => item?.id ?? '—' },
            { key: 'dealerName', label: 'dealerName', getter: item => item?.dealerName ?? '—' },
            { key: 'depart', label: 'depart', getter: item => item?.depart ?? '—' },
            { key: 'contactPerson', label: 'contactPerson', getter: item => item?.contactPerson ?? '—' },
            { key: 'email', label: 'email', getter: item => item?.email ?? '—' },
            { key: 'phone', label: 'phone', getter: item => item?.phone ?? '—' },
        ],
        valueGetter: item => item?.id,
        searchFields: item => [
            item?.id,
            item?.dealerName,
            item?.depart,
            item?.contactPerson,
            item?.email,
            item?.phone,
        ],
        buildResult: item => ({
            dealer: item?.dealerName ?? '',
            dealer_depart: item?.depart ?? '',
            contactPerson: item?.contactPerson ?? '',
            email: item?.email ?? '',
            phone: item?.phone ?? '',
            fax: item?.fax ?? '',
            zipcode: item?.zipcode ?? item?.zip ?? '',
            address1: item?.address1 ?? '',
            address2: item?.address2 ?? '',
        }),
    },
    loanerProduct: {
        title: '貸出機種選択',
        searchPlaceholder: '半角英数で検索（productName / item）',
        columns: [
            { key: 'item', label: 'item', getter: item => item?.item ?? '—' },
            { key: 'productName', label: 'productName', getter: item => item?.productName ?? '—' },
            { key: 'availableCount', label: '在庫', getter: item => item?.availableCount ?? 0 },
            { key: 'totalCount', label: '台数', getter: item => item?.totalCount ?? 0 },
            { key: 'order_type', label: '判定', getter: item => item?.order_type ?? '—' },
        ],
        valueGetter: item => item?.productName,
        searchFields: item => [item?.productName, item?.item],
        buildResult: item => ({
            productName: String(item?.productName ?? ''),
            item: item?.item ?? '',
        }),
    },
    loanerUnit: {
        title: '貸出機選択',
        searchPlaceholder: '半角英数で検索（productName / item / SN / loanerID）',
        columns: [
            { key: 'productName', label: 'productName', getter: item => item?.productName ?? '—' },
            { key: 'item', label: 'item', getter: item => item?.item ?? '—' },
            { key: 'loanerID', label: 'loanerID', getter: item => item?.loanerID ?? '—' },
            { key: 'SN', label: 'SN', getter: item => item?.SN ?? '—' },
            { key: 'manageNum', label: '管理番号', getter: item => item?.manageNum ?? '—' },
            { key: 'groupName', label: 'グループ', getter: item => item?.groupName ?? '—' },
        ],
        valueGetter: item => item?.loanerID,
        searchFields: item => [
            item?.productName,
            item?.item,
            item?.loanerID,
            item?.SN,
            item?.manageNum,
            item?.groupName,
        ],
        buildResult: item => ({
            loanerID: item?.loanerID ?? null,
            productName: item?.productName ?? '',
            item: item?.item ?? '',
            SN: item?.SN ?? '',
            manageNum: item?.manageNum ?? '',
            groupName: item?.groupName ?? '',
        }),
    },
}

const config = computed(() => configs[props.kind] ?? configs.serviceMaster)
const usesLatinSearch = computed(() =>
    ['serviceMaster', 'loanerProduct', 'loanerUnit'].includes(props.kind),
)
const title = computed(() => config.value.title)
const searchPlaceholder = computed(() => config.value.searchPlaceholder)
const columns = computed(() => config.value.columns)

const filteredItems = computed(() => {
    const baseItems = props.kind === 'loanerProduct'
        ? (props.items ?? []).filter((item) => {
            const text = String(item?.item ?? '')
            return !text.includes('使用不可') && !text.includes('サービス終了')
        })
        : (props.items ?? [])

    const tokens = searchQuery.value
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter(Boolean)

    const filtered = tokens.length === 0
        ? [...baseItems]
        : baseItems.filter((item) => {
            const text = config.value.searchFields(item)
                .filter(value => value != null && value !== '')
                .join(' ')
                .toLowerCase()
            return tokens.every(token => text.includes(token))
        })

    if (props.kind === 'loanerProduct') {
        filtered.sort((a, b) => compareLoanerItemSort(a?.item, b?.item)
            || String(a?.productName ?? '').localeCompare(String(b?.productName ?? ''), 'en', { numeric: true }))
    }

    return filtered
})

const selectedItem = computed(() =>
    props.items.find(item => String(itemValue(item)) === String(selectedValue.value)),
)

watch(
    () => [props.kind, props.initialValue, props.initialSearchQuery, props.items],
    () => {
        const seed = String(props.initialSearchQuery ?? '').trim()
        searchQuery.value = usesLatinSearch.value ? toHalfWidthAlnum(seed) : seed
        error.value = ''
        selectedValue.value = props.initialValue ?? null
    },
    { immediate: true },
)

onMounted(async () => {
    await nextTick()
    searchInput.value?.focus()
    if (searchQuery.value) {
        searchInput.value?.select?.()
    }
})

function toHalfWidthAlnum(value) {
    return String(value ?? '')
        .replace(/[！-～]/g, char => String.fromCharCode(char.charCodeAt(0) - 0xFEE0))
        .replace(/　/g, ' ')
}

/** 【簿外】を除き、英数字だけで比較するソートキー */
function loanerItemSortKey(value) {
    return String(value ?? '')
        .replace(/【簿外】/g, '')
        .replace(/[^0-9A-Za-z]+/g, '')
        .toLowerCase()
}

function compareLoanerItemSort(a, b) {
    return loanerItemSortKey(a).localeCompare(loanerItemSortKey(b), 'en', {
        numeric: true,
        sensitivity: 'base',
    })
}

function onCompositionStart() {
    isComposing.value = true
}

function onCompositionEnd(event) {
    isComposing.value = false
    if (!usesLatinSearch.value) return
    searchQuery.value = toHalfWidthAlnum(event.target.value)
}

function onSearchInput(event) {
    if (!usesLatinSearch.value || isComposing.value) return
    const next = toHalfWidthAlnum(event.target.value)
    if (next !== searchQuery.value) {
        searchQuery.value = next
    }
}

function itemValue(item) {
    return config.value.valueGetter(item)
}

function itemKey(item) {
    return String(itemValue(item) ?? JSON.stringify(item))
}

function confirm() {
    if (!selectedItem.value) {
        error.value = '項目を選択してください。'
        return
    }

    emit('selected', config.value.buildResult(selectedItem.value))
}
</script>

<style scoped>
.dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    z-index: 300;
    display: flex;
    justify-content: center;
    align-items: stretch;
    padding: 12px;
    box-sizing: border-box;
}

.dialog-panel {
    width: min(96vw, 1400px);
    height: calc(100vh - 24px);
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #1e293b;
    color: #fff;
}

.dialog-header h3 {
    margin: 0;
    font-size: 16px;
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
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    overflow: hidden;
}

.search-toolbar {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.search-field {
    display: block;
    flex: 1 1 auto;
    min-width: 0;
    font-weight: 700;
    font-size: 14px;
}

.search-input {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 10px 12px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    font-size: 14px;
}

.ime-latin {
    ime-mode: disabled;
}

.dialog-actions {
    display: flex;
    flex: 0 0 auto;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}

.preview-box {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    padding: 10px 12px;
    min-height: 72px;
    box-sizing: border-box;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px 12px;
    font-size: 13px;
}

.preview-grid span {
    display: block;
    color: #64748b;
    margin-bottom: 2px;
}

.preview-grid strong {
    color: #1e293b;
    font-weight: 600;
}

.table-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
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
    font-weight: 700;
    color: #334155;
}

.table-row {
    cursor: pointer;
}

.table-row:hover {
    background: #eff6ff;
}

.table-row.selected {
    background: #dbeafe;
}

.error-message {
    margin: 0;
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
    color: #fff;
}

.btn-primary {
    background: #2563eb;
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #6b7280;
}
</style>
