<template>
    <BaseDialog :title="config.title" large @close="$emit('close')">
        <label class="search-field">
            検索
            <input
                v-model="searchQuery"
                type="text"
                class="search-input"
                :placeholder="config.searchPlaceholder"
            >
        </label>

        <p v-if="error" class="error-message">{{ error }}</p>

        <div class="dialog-actions">
            <button type="button" class="btn-secondary" @click="$emit('close')">
                キャンセル
            </button>
            <button type="button" class="btn-primary" :disabled="!selectedItem" @click="save">
                選択
            </button>
        </div>

        <div v-if="kind === 'dealer'" class="dealer-preview">
            <h4>選択中の依頼者情報</h4>
            <div class="dealer-preview-grid">
                <label v-for="field in dealerPreviewFields" :key="field.key" class="dealer-preview-field">
                    <span>{{ field.label }}</span>
                    <input type="text" :value="selectedItem ? (selectedItem[field.key] ?? '') : ''" readonly>
                </label>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th v-for="column in config.columns" :key="column.label">
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in visibleItems"
                        :key="itemKey(item)"
                        class="table-row"
                        :class="{ selected: selectedValue === itemValue(item) }"
                        @click="selectedValue = itemValue(item)"
                        @dblclick="save"
                    >
                        <td v-for="column in config.columns" :key="column.label">
                            {{ column.getter(item) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!filteredItems.length" class="empty-message">該当する項目がありません。</p>
            <p v-else-if="filteredItems.length > visibleItems.length" class="empty-message">
                {{ filteredItems.length }}件中 {{ visibleItems.length }}件を表示中。検索で絞り込むか「さらに表示」を押してください。
                <button type="button" class="btn-secondary more-btn" @click="showMore">さらに表示</button>
            </p>
        </div>
    </BaseDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import BaseDialog from './BaseDialog.vue'
import { latestMastersByKey } from '@/utils/resolveServiceWorkPrice'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const page = usePage()
const searchQuery = ref('')
const selectedValue = ref(null)
const error = ref('')
const displayLimit = ref(150)
const DISPLAY_STEP = 150
const latestCache = new Map()

function toText(value) {
    return value == null ? '' : String(value)
}

function cachedLatest(rows, key) {
    const list = Array.isArray(rows) ? rows : []
    const cacheKey = `${key}:${list.length}:${list[0]?.id ?? ''}:${list[list.length - 1]?.id ?? ''}`
    if (latestCache.has(cacheKey)) return latestCache.get(cacheKey)
    const result = latestMastersByKey(list, key)
    latestCache.clear()
    latestCache.set(cacheKey, result)
    return result
}

const configs = {
    serviceMaster: {
        title: '製品名選択',
        searchPlaceholder: 'productName / entityID で検索',
        items: propsValue => cachedLatest(propsValue.servicesMaster ?? [], 'serviceID'),
        columns: [
            { label: 'id', getter: item => item?.id ?? '—' },
            { label: 'productName', getter: item => item?.productName ?? '—' },
            { label: 'entityID', getter: item => item?.entityID ?? '—' },
        ],
        // 最新版のみ表示。行キーは surrogate id
        valueGetter: item => item?.id,
        initialValue: () => null,
        searchFields: item => [item?.id, item?.serviceID, item?.productName, item?.entityID],
        buildResult: item => ({
            serviceID: item?.serviceID,
            productName: String(item?.productName ?? item?.entityID ?? item?.serviceID ?? ''),
            entityID: item?.entityID ?? null,
        }),
    },
    status: {
        title: 'ステータス選択',
        searchPlaceholder: 'status で検索',
        items: propsValue => propsValue.statuses ?? [],
        columns: [
            { label: 'processID_new', getter: item => item?.processID_new ?? '—' },
            { label: 'status', getter: item => item?.status ?? '—' },
        ],
        valueGetter: item => item?.processID_new,
        initialValue: payload => payload?.status ?? props.record?.status ?? null,
        searchFields: item => [item?.processID_new, item?.status],
        buildResult: item => ({
            status: item?.processID_new,
        }),
    },
    returnCode: {
        title: '作業内容選択',
        searchPlaceholder: 'description で検索',
        items: propsValue => propsValue.returnCodes ?? [],
        columns: [
            { label: 'id', getter: item => item?.id ?? '—' },
            { label: 'description', getter: item => item?.description ?? '—' },
        ],
        valueGetter: item => item?.id,
        initialValue: payload => payload?.returnCode ?? props.record?.returnCode ?? null,
        searchFields: item => [item?.id, item?.description],
        buildResult: item => ({
            returnCode: item?.id,
        }),
    },
    labor: {
        title: '作業担当選択',
        searchPlaceholder: 'laborName で検索',
        items: propsValue => propsValue.labors ?? [],
        columns: [
            { label: 'laborID', getter: item => item?.laborID ?? '—' },
            { label: 'laborName', getter: item => item?.laborName ?? '—' },
        ],
        valueGetter: item => item?.laborID,
        initialValue: payload => payload?.laborID ?? props.record?.laborID ?? null,
        searchFields: item => [item?.laborID, item?.laborName],
        buildResult: item => ({
            laborID: item?.laborID,
        }),
    },
    dealer: {
        title: '依頼者選択',
        searchPlaceholder: 'dealer で検索',
        items: propsValue => propsValue.dealersMaster ?? [],
        columns: [
            { label: 'id', getter: item => item?.id ?? '—' },
            { label: 'dealerName', getter: item => item?.dealerName ?? '—' },
            { label: 'depart', getter: item => item?.depart ?? '—' },
            { label: 'contactPerson', getter: item => item?.contactPerson ?? '—' },
            { label: 'email', getter: item => item?.email ?? '—' },
            { label: 'phone', getter: item => item?.phone ?? '—' },
        ],
        valueGetter: item => item?.id,
        initialValue: payload => payload?.dealer ?? props.record?.dealer ?? null,
        searchFields: item => [item?.id, item?.dealerName, item?.depart, item?.contactPerson, item?.email, item?.phone, item?.dealer, item?.name, item?.companyName, item?.title, item?.note],
        buildResult: item => ({
            dealer: item?.dealerName ?? item?.dealer ?? item?.name ?? item?.companyName ?? String(item?.id ?? ''),
            dealer_depart: item?.depart ?? '',
            contactPerson: item?.contactPerson ?? '',
            email: item?.email ?? '',
            phone: item?.phone ?? '',
        }),
    },
    incident: {
        title: 'Incidents 選択',
        searchPlaceholder: 'incidentNum / companyName / depart / customerNum で検索',
        items: propsValue => propsValue.incidentsMaster ?? [],
        columns: [
            { label: 'incidentNum', getter: item => item?.incidentNum ?? '—' },
            { label: 'companyName', getter: item => item?.companyName ?? '—' },
            { label: 'depart', getter: item => item?.depart ?? '—' },
            { label: 'customerNum', getter: item => item?.customerNum ?? '—' },
        ],
        valueGetter: item => item?.id,
        initialValue: payload => payload?.incident ?? props.record?.incident ?? null,
        searchFields: item => [item?.id, item?.incidentNum, item?.companyName, item?.depart, item?.customerNum],
        buildResult: item => ({
            incident: item?.incidentNum != null && item?.incidentNum !== '' ? Number(item.incidentNum) : null,
        }),
    },
}

const kind = computed(() => {
    const value = props.payload?.kind ?? 'serviceMaster'
    return configs[value] ? value : 'serviceMaster'
})

const config = computed(() => configs[kind.value])
const items = computed(() => config.value.items(page.props))

const filteredItems = computed(() => {
    const tokens = searchQuery.value
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter(Boolean)

    if (tokens.length === 0) return items.value

    return items.value.filter((item) => {
        const text = config.value.searchFields(item)
            .map(toText)
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
        return tokens.every(token => text.includes(token))
    })
})

const visibleItems = computed(() => filteredItems.value.slice(0, displayLimit.value))

function showMore() {
    displayLimit.value += DISPLAY_STEP
}

const selectedItem = computed(() =>
    items.value.find(item => String(itemValue(item)) === String(selectedValue.value)),
)

watch(
    [kind, () => props.payload?.kind, () => props.payload?.dealer, () => props.payload?.productName, () => props.payload?.entityID, () => props.payload?.incident, () => props.payload?.searchQuery, () => props.record?.orderID],
    () => {
        searchQuery.value = String(props.payload?.searchQuery ?? '').trim()
        error.value = ''
        displayLimit.value = DISPLAY_STEP
        if (kind.value === 'dealer') {
            const desiredDealer = props.payload?.dealer ?? props.record?.dealer ?? ''
            const matchedDealer = items.value.find(item =>
                String(item?.id ?? '') === String(desiredDealer)
                || String(item?.dealerName ?? '') === String(desiredDealer)
                || String(item?.dealer ?? '') === String(desiredDealer)
                || String(item?.name ?? '') === String(desiredDealer)
                || String(item?.companyName ?? '') === String(desiredDealer),
            )
            selectedValue.value = matchedDealer?.id ?? null
            return
        }

        if (kind.value === 'serviceMaster') {
            const desiredName = props.payload?.productName ?? props.record?.productName ?? ''
            const desiredEntity = props.payload?.entityID ?? props.record?.entityID ?? ''
            const matched = items.value.find(item =>
                (desiredName !== '' && String(item?.productName ?? '') === String(desiredName))
                || (desiredEntity !== '' && String(item?.entityID ?? '') === String(desiredEntity)),
            )
            selectedValue.value = matched?.id ?? null
            return
        }

        if (kind.value === 'incident') {
            const desiredIncident = props.payload?.incident ?? props.record?.incident ?? ''
            const matched = items.value.find(item =>
                String(item?.incidentNum ?? '') === String(desiredIncident)
                || String(item?.id ?? '') === String(desiredIncident),
            )
            selectedValue.value = matched?.id ?? null
            return
        }

        selectedValue.value = config.value.initialValue(props.payload)
    },
    { immediate: true },
)

function itemValue(item) {
    return config.value.valueGetter(item)
}

function itemKey(item) {
    return String(itemValue(item) ?? JSON.stringify(item))
}

const dealerPreviewFields = [
    { key: 'id', label: 'id' },
    { key: 'dealerName', label: 'dealerName' },
    { key: 'depart', label: 'depart' },
    { key: 'contactPerson', label: 'contactPerson' },
    { key: 'email', label: 'email' },
    { key: 'phone', label: 'phone' },
]

function save() {
    if (!selectedItem.value) {
        error.value = '項目を選択してください。'
        return
    }

    emit('saved', config.value.buildResult(selectedItem.value))
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

.table-row:hover {
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

.dealer-preview {
    margin-top: 16px;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
}

.dealer-preview h4 {
    margin: 0 0 12px;
    color: #334155;
    font-size: 14px;
}

.dealer-preview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 12px;
}

.dealer-preview-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
    color: #475569;
}

.dealer-preview-field input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    background: white;
    color: #1e293b;
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

.more-btn {
    margin-left: 8px;
    padding: 4px 10px;
    font-size: 12px;
}
</style>
