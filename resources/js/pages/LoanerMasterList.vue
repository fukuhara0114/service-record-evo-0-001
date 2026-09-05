<template>
    <div class="loaner-master-page">
        <header class="page-header">
            <div class="header-title">
                <h1>LoanerMaster 一覧</h1>
                <p class="subtitle">loanermaster008 全カラム（{{ totalCount }}件）</p>
            </div>
            <div class="scope-bar">
                <button
                    v-for="item in scopeButtons"
                    :key="item.id"
                    type="button"
                    class="scope-btn"
                    :class="{ active: currentScope === item.id }"
                    :disabled="loading"
                    @click="setScope(item.id)"
                >
                    {{ item.label }}
                </button>
                <div class="quick-filter">
                    <label class="quick-filter-label" for="loanerMasterQuickFilter">Quick Filter</label>
                    <input
                        id="loanerMasterQuickFilter"
                        v-model="quickFilter"
                        type="text"
                        class="quick-filter-input"
                        placeholder="スペース区切りで複数検索"
                        :disabled="loading"
                    >
                    <span class="quick-filter-count" aria-live="polite">{{ filteredRows.length }}件</span>
                </div>
            </div>
            <div class="header-actions">
                <form class="search-form" @submit.prevent="runSearch">
                    <input
                        v-model="searchInput"
                        type="search"
                        class="search-input"
                        placeholder="loanerID / 製品名 / SN など"
                        :disabled="loading"
                    >
                    <button type="submit" class="search-btn" :disabled="loading">検索</button>
                    <button
                        type="button"
                        class="clear-btn"
                        :disabled="loading"
                        @click="clearSearch"
                    >
                        クリア
                    </button>
                </form>
                <CloseToHomeButton :href="homeUrl" />
            </div>
        </header>

        <section class="list-card">
            <div v-if="masters?.links?.length" class="pager">
                <template v-for="link in masters.links" :key="`${link.label}-${link.url}`">
                    <button
                        v-if="link.url"
                        type="button"
                        class="page-link"
                        :class="{ active: link.active }"
                        :disabled="loading"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="page-link disabled"
                        v-html="link.label"
                    />
                </template>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th v-for="column in displayColumns" :key="column">
                                <button
                                    type="button"
                                    class="sort-button"
                                    :disabled="loading"
                                    @click="toggleSort(column)"
                                >
                                    {{ columnLabel(column) }}{{ sortIndicator(column) }}
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredRows.length === 0">
                            <td :colspan="displayColumns.length || 1" class="empty">データがありません。</td>
                        </tr>
                        <tr
                            v-for="row in filteredRows"
                            :key="row.id"
                            class="data-row"
                            :class="{ selected: Number(selectedId) === Number(row.id) }"
                            @click="selectRow(row)"
                            @dblclick="onRowDoubleClick(row)"
                        >
                            <td v-for="column in displayColumns" :key="`${row.id}-${column}`">
                                <span
                                    v-if="column === LENDING_PARENT_STATUS_COLUMN"
                                    :class="{ 'lending-elapsed-red': isLendingElapsedText(lendingParentCell(row)) }"
                                >{{ lendingParentCell(row) }}</span>
                                <select
                                    v-else-if="column === statusColumn"
                                    class="status-select"
                                    :value="statusSelectValue(row[column])"
                                    :disabled="savingStatus"
                                    @click.stop
                                    @change="changeCurrentStatus(row, $event.target.value)"
                                >
                                    <option
                                        v-if="!hasStatusOption(row[column])"
                                        :value="statusSelectValue(row[column])"
                                    >
                                        {{ displayCell(row[column]) }}
                                    </option>
                                    <option
                                        v-for="opt in uniqueStatusOptions"
                                        :key="opt.id"
                                        :value="opt.id"
                                    >
                                        {{ opt.label }} ({{ opt.id }})
                                    </option>
                                </select>
                                <template v-else>
                                    {{ displayCell(row[column]) }}
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            v-if="detailChoiceOpen"
            class="dialog-overlay"
            @click.self="closeDetailChoice"
        >
            <div
                class="dialog-panel detail-choice-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="detail-choice-title"
            >
                <header class="detail-choice-header">
                    <h3 id="detail-choice-title">詳細を開く</h3>
                    <button
                        type="button"
                        class="detail-choice-close"
                        aria-label="閉じる"
                        @click="closeDetailChoice"
                    >
                        X
                    </button>
                </header>
                <p v-if="detailChoiceKindLabel" class="detail-choice-kind">{{ detailChoiceKindLabel }}</p>
                <p v-if="detailChoiceError" class="detail-choice-error">{{ detailChoiceError }}</p>
                <div class="detail-choice-actions">
                    <button type="button" class="detail-choice-btn" @click="openParentDetail">
                        親案件詳細
                    </button>
                    <button type="button" class="detail-choice-btn" @click="openLoanerDetail">
                        loaner詳細
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="editDialogOpen"
            class="dialog-overlay"
            @click.self="closeEditDialog"
        >
            <div
                class="dialog-panel edit-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="edit-dialog-title"
            >
                <header class="detail-choice-header">
                    <h3 id="edit-dialog-title">LoanerMaster 詳細</h3>
                    <div class="edit-dialog-header-actions">
                        <button
                            type="button"
                            class="edit-save-btn"
                            :disabled="editSaving"
                            @click="saveEditDialog"
                        >
                            {{ editSaving ? '保存中...' : '保存' }}
                        </button>
                        <button
                            type="button"
                            class="detail-choice-close"
                            aria-label="閉じる"
                            :disabled="editSaving"
                            @click="closeEditDialog"
                        >
                            X
                        </button>
                    </div>
                </header>
                <p v-if="editError" class="detail-choice-error">{{ editError }}</p>
                <div class="edit-dialog-body">
                    <label
                        v-for="column in editColumns"
                        :key="column"
                        class="edit-field"
                        :class="{ 'edit-field--full': isFullWidthColumn(column) }"
                    >
                        <span class="edit-label">{{ columnLabel(column) }}</span>
                        <select
                            v-if="column === statusColumn"
                            v-model="editForm[column]"
                            class="edit-input"
                            :disabled="editSaving"
                        >
                            <option value="">—</option>
                            <option
                                v-for="opt in uniqueStatusOptions"
                                :key="opt.id"
                                :value="String(opt.id)"
                            >
                                {{ opt.label }} ({{ opt.id }})
                            </option>
                        </select>
                        <input
                            v-else-if="isDateColumn(column)"
                            v-model="editForm[column]"
                            type="date"
                            class="edit-input"
                            :disabled="editSaving || isReadonlyColumn(column)"
                        >
                        <input
                            v-else-if="isNumberColumn(column)"
                            v-model="editForm[column]"
                            type="number"
                            class="edit-input"
                            :disabled="editSaving || isReadonlyColumn(column)"
                        >
                        <input
                            v-else
                            v-model="editForm[column]"
                            type="text"
                            class="edit-input"
                            :disabled="editSaving || isReadonlyColumn(column)"
                        >
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import { loanerDetailUrl, serviceRecordUrl } from '@/utils/serviceRecordPath'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    masters: {
        type: Object,
        required: true,
    },
    sort: {
        type: String,
        default: 'groupName',
    },
    direction: {
        type: String,
        default: 'asc',
    },
    statusColumn: {
        type: String,
        default: 'currentStatus',
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    scope: {
        type: String,
        default: 'all',
    },
    q: {
        type: String,
        default: '',
    },
})

const LENDING_PARENT_STATUS_COLUMN = 'lending_parent_status'
const ASSOCIATED_ID_COLUMN = 'associatedID'
const PARENT_COMPLETE_STATUS = 400
const PARENT_NOT_FOUND_MESSAGE = '親案件が見つかりません'
const LOANER_NOT_FOUND_MESSAGE = 'Loaner案件がみつかりません'

const page = usePage()
const loading = ref(false)
const savingStatus = ref(false)
const selectedId = ref(null)
const searchInput = ref(props.q || '')
const quickFilter = ref('')
const detailChoiceOpen = ref(false)
const detailChoiceRow = ref(null)
const detailChoiceError = ref('')
const editDialogOpen = ref(false)
const editSaving = ref(false)
const editError = ref('')
const editForm = reactive({})
const DATE_COLUMNS = new Set(['certificatedDate', 'sentDate', 'returnedDate'])
const NUMBER_COLUMNS = new Set(['loanerID', 'price', 'associatedID', 'inventory', 'book'])
const READONLY_COLUMNS = new Set(['id'])
const FULL_WIDTH_COLUMNS = new Set(['note1', 'note2', 'note3'])
const HIDDEN_EDIT_COLUMNS = new Set([
    'lastEditPerson',
    'lastEditDate',
    'validDateMin',
    'validDateMax',
    'groupName',
])
watch(() => props.q, (value) => {
    searchInput.value = value || ''
})
const editColumns = computed(() =>
    (props.columns ?? []).filter((column) => !HIDDEN_EDIT_COLUMNS.has(column)),
)
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const rows = computed(() => props.masters?.data ?? [])
const totalCount = computed(() => props.masters?.total ?? rows.value.length)
const currentSort = computed(() => props.sort || 'groupName')
const currentDirection = computed(() => props.direction === 'desc' ? 'desc' : 'asc')
const currentScope = computed(() => props.scope || 'all')
const currentSearch = computed(() => props.q || '')
const listUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/loaner/master`)
const displayColumns = computed(() => {
    const cols = props.columns ?? []
    if (currentScope.value === 'lending') {
        const rest = cols.filter((column) => column !== ASSOCIATED_ID_COLUMN)
        return [LENDING_PARENT_STATUS_COLUMN, ASSOCIATED_ID_COLUMN, ...rest]
    }
    return cols
})
const filteredRows = computed(() => {
    const queries = String(quickFilter.value || '')
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter((q) => q.length > 0)

    if (queries.length === 0) {
        return rows.value
    }

    return rows.value.filter((row) => {
        const rowText = displayColumns.value
            .map((column) => {
                if (column === LENDING_PARENT_STATUS_COLUMN) {
                    return lendingParentCell(row)
                }
                const value = row?.[column]
                if (value == null || value === '') return ''
                if (column === props.statusColumn) {
                    const opt = uniqueStatusOptions.value.find((o) => String(o.id) === String(value))
                    return opt ? `${opt.label} ${opt.id}` : String(value)
                }
                return String(value)
            })
            .join(' ')
            .toLowerCase()

        return queries.every((q) => rowText.includes(q))
    })
})

function columnLabel(column) {
    if (column === LENDING_PARENT_STATUS_COLUMN) return '親案件状況'
    if (column === ASSOCIATED_ID_COLUMN && currentScope.value === 'lending') return '親案件ID'
    return column
}
const scopeButtons = [
    { id: 'all', label: '全件' },
    { id: 'stock', label: '在庫' },
    { id: 'non_stock', label: '非在庫' },
    { id: 'reserved', label: '確保済み' },
    { id: 'lending', label: '貸出中' },
    { id: 'returning', label: '返却処理中' },
    { id: 'other', label: 'その他' },
]
const uniqueStatusOptions = computed(() => {
    const seen = new Set()
    return (props.statusOptions ?? []).filter((opt) => {
        const id = String(opt?.id ?? '')
        if (seen.has(id)) return false
        seen.add(id)
        return true
    })
})

function displayCell(value) {
    if (value == null || value === '') return '—'
    return String(value)
}

function toYmd(value) {
    if (value == null || value === '') return null
    const raw = String(value).slice(0, 10)
    return /^\d{4}-\d{2}-\d{2}$/.test(raw) ? raw : null
}

function tokyoTodayYmd() {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date())
}

/** today − shippingOut（出荷日から何日経ったか） */
function elapsedDaysFromShippingOut(shippingOut) {
    const ymd = toYmd(shippingOut)
    if (!ymd) return null
    const today = tokyoTodayYmd()
    if (ymd > today) return null
    const [y1, m1, d1] = ymd.split('-').map(Number)
    const [y2, m2, d2] = today.split('-').map(Number)
    const diff = Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / 86400000)
    return Number.isFinite(diff) ? diff : null
}

/** 貸出中スコープ先頭カラム: 親 status < 400 → 作業中 / >=400 → 出荷完了後xx日経過 */
function lendingParentCell(row) {
    const status = row?.parentStatus
    if (status == null || status === '') return '—'
    const statusNum = Number(status)
    if (!Number.isFinite(statusNum)) return '—'
    if (statusNum < PARENT_COMPLETE_STATUS) return '作業中'

    const days = elapsedDaysFromShippingOut(row?.parentShippingOut)
    if (days == null) return '—'
    return `出荷完了後${days}日経過`
}

function isLendingElapsedText(text) {
    return String(text ?? '').startsWith('出荷完了後')
}

function statusSelectValue(value) {
    return value == null ? '' : String(value)
}

function hasStatusOption(value) {
    const id = statusSelectValue(value)
    return uniqueStatusOptions.value.some(opt => String(opt.id) === id)
}

function changeCurrentStatus(row, value) {
    if (!row?.id || savingStatus.value) return

    savingStatus.value = true
    router.put(`${page.props.appBaseUrl}/servicerecord/loaner/master/${row.id}/current-status`, {
        currentStatus: value === '' ? null : value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            savingStatus.value = false
        },
    })
}

function sortIndicator(column) {
    if (currentSort.value !== column) return ''
    return currentDirection.value === 'desc' ? ' ▼' : ' ▲'
}

function listQuery(extra = {}) {
    const query = {
        sort: currentSort.value,
        direction: currentDirection.value,
        scope: currentScope.value,
        ...extra,
    }

    const q = Object.prototype.hasOwnProperty.call(extra, 'q')
        ? String(extra.q ?? '').trim()
        : currentSearch.value.trim()

    if (q !== '') {
        query.q = q
    } else {
        delete query.q
    }

    return query
}

function runListQuery(query) {
    loading.value = true
    router.get(listUrl.value, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            loading.value = false
        },
    })
}

function setScope(scope) {
    if (!scope || loading.value) return
    runListQuery(listQuery({ scope }))
}

function runSearch() {
    if (loading.value) return
    runListQuery(listQuery({ q: searchInput.value }))
}

function clearSearch() {
    if (loading.value) return
    searchInput.value = ''
    quickFilter.value = ''
    runListQuery(listQuery({ q: '', scope: 'all' }))
}

function selectRow(row) {
    selectedId.value = row?.id ?? null
}

function onRowDoubleClick(row) {
    selectRow(row)
    if (currentScope.value === 'lending') {
        detailChoiceRow.value = row
        detailChoiceError.value = ''
        detailChoiceOpen.value = true
        return
    }
    openEditDialog(row)
}

function isDateColumn(column) {
    return DATE_COLUMNS.has(column)
}

function isNumberColumn(column) {
    return NUMBER_COLUMNS.has(column)
}

function isReadonlyColumn(column) {
    return READONLY_COLUMNS.has(column)
}

function isFullWidthColumn(column) {
    return FULL_WIDTH_COLUMNS.has(column)
}

function toEditDateValue(value) {
    if (value == null || value === '') return ''
    return String(value).slice(0, 10)
}

function openEditDialog(row) {
    if (!row) return
    editError.value = ''
    for (const key of Object.keys(editForm)) {
        delete editForm[key]
    }
    for (const column of editColumns.value) {
        const value = row[column]
        if (isDateColumn(column)) {
            editForm[column] = toEditDateValue(value)
        } else if (column === props.statusColumn || isNumberColumn(column)) {
            editForm[column] = value == null || value === '' ? '' : String(value)
        } else {
            editForm[column] = value == null ? '' : String(value)
        }
    }
    editDialogOpen.value = true
}

function closeEditDialog() {
    if (editSaving.value) return
    editDialogOpen.value = false
    editError.value = ''
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function nullableEditValue(value) {
    if (value === '' || value === undefined) return null
    return value
}

async function saveEditDialog() {
    const id = editForm.id
    if (!id) {
        editError.value = '更新対象の id がありません。'
        return
    }

    editSaving.value = true
    editError.value = ''
    try {
        const body = {}
        for (const column of editColumns.value) {
            if (column === 'id') continue
            let value = nullableEditValue(editForm[column])
            if (
                (column === props.statusColumn || NUMBER_COLUMNS.has(column))
                && value != null
                && value !== ''
            ) {
                const num = Number(value)
                value = Number.isFinite(num) ? num : value
            }
            body[column] = value
        }

        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/master/${id}`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(body),
            },
        )
        if (!result) throw new Error('保存に失敗しました。')
        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        editDialogOpen.value = false
        loading.value = true
        router.reload({
            only: ['masters'],
            preserveScroll: true,
            onFinish: () => {
                loading.value = false
            },
        })
    } catch (e) {
        editError.value = e.message || '保存に失敗しました。'
    } finally {
        editSaving.value = false
    }
}

const detailChoiceKindLabel = computed(() => {
    if (detailChoiceRow.value?.associatedCaseKind === 'legacy') return ''
    return ''
})

function closeDetailChoice() {
    detailChoiceOpen.value = false
    detailChoiceRow.value = null
    detailChoiceError.value = ''
}

function associatedOrderId(row) {
    const associatedId = Number(row?.associatedID)
    return Number.isFinite(associatedId) && associatedId > 0 ? associatedId : null
}

function openAdministratorDetail(orderId) {
    const url = new URL(serviceRecordUrl('administrator'))
    url.searchParams.set('openOrderID', String(orderId))
    window.open(url.href, '_blank', 'noopener,noreferrer')
    closeDetailChoice()
}

function openParentDetail() {
    const orderId = associatedOrderId(detailChoiceRow.value)
    if (orderId == null) {
        detailChoiceError.value = PARENT_NOT_FOUND_MESSAGE
        return
    }
    openAdministratorDetail(orderId)
}

function openLoanerDetail() {
    const row = detailChoiceRow.value
    // 旧Loaner案件: 常に associatedID の service 案件を開く
    if (row?.associatedCaseKind === 'legacy') {
        const orderId = associatedOrderId(row)
        if (orderId == null) {
            detailChoiceError.value = LOANER_NOT_FOUND_MESSAGE
            return
        }
        openAdministratorDetail(orderId)
        return
    }

    const loanerOrderId = Number(row?.loanerOrderID)
    if (!Number.isFinite(loanerOrderId) || loanerOrderId <= 0) {
        detailChoiceError.value = LOANER_NOT_FOUND_MESSAGE
        return
    }
    window.open(loanerDetailUrl(loanerOrderId), '_blank', 'noopener,noreferrer')
    closeDetailChoice()
}

function toggleSort(column) {
    if (!column || loading.value) return

    const nextDirection = currentSort.value === column && currentDirection.value === 'asc'
        ? 'desc'
        : 'asc'

    runListQuery(listQuery({
        sort: column,
        direction: nextDirection,
    }))
}

function goToPage(url) {
    if (!url || loading.value) return
    loading.value = true
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            loading.value = false
        },
    })
}
</script>

<style scoped>
.loaner-master-page {
    zoom: 1.1;
    width: 100%;
    min-height: calc(100vh / 1.1);
    padding: 12px 16px 24px;
    background: #e2e8f0;
    box-sizing: border-box;
    color: #1e293b;
    font-weight: 700;
    transform-origin: top left;
}

.scope-bar {
    display: flex;
    flex-wrap: nowrap;
    justify-content: center;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.page-header {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) auto minmax(180px, 1fr);
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.header-title {
    min-width: 0;
}

.header-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex-wrap: wrap;
}

.quick-filter {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
    margin-left: 50px;
    min-width: 160px;
    max-width: 360px;
}

.quick-filter-label {
    flex: 0 0 auto;
    white-space: nowrap;
    font-size: 12px;
    color: #334155;
}

.quick-filter-input {
    flex: 1 1 auto;
    min-width: 120px;
    padding: 7px 10px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    color: #1e293b;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
}

.quick-filter-input:disabled {
    opacity: 0.7;
}

.quick-filter-count {
    flex: 0 0 auto;
    white-space: nowrap;
    font-size: 12px;
    color: #475569;
}

.search-form {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.search-input {
    width: min(220px, 36vw);
    min-width: 120px;
    padding: 7px 10px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    color: #1e293b;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
}

.search-input:disabled {
    opacity: 0.7;
}

.search-btn {
    padding: 7px 12px;
    border: 1px solid #2563eb;
    border-radius: 6px;
    background: #2563eb;
    color: #fff;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.search-btn:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.clear-btn {
    padding: 7px 12px;
    border: 1px solid #64748b;
    border-radius: 6px;
    background: #64748b;
    color: #fff;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.clear-btn:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.scope-btn {
    min-width: 96px;
    padding: 8px 14px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.scope-btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.scope-btn:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.page-header h1 {
    margin: 0 0 4px;
    font-size: 22px;
}

.subtitle {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.list-card {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}

.page-link {
    min-width: 34px;
    padding: 4px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
    color: #334155;
    font-size: 12px;
    cursor: pointer;
}

.page-link.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.page-link.disabled {
    opacity: 0.5;
    cursor: default;
}

.table-wrap {
    overflow: auto;
    max-height: calc((100vh / 1.1) - 180px);
    border: 1px solid #333333;
    border-radius: 6px;
}

table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    background: #f0f0f0;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

th,
td {
    border: 1px solid #333333;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 700;
}

th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #2f63cc;
    color: #fff;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
}

td {
    background: #f5f5f5;
}

.sort-button {
    width: 100%;
    padding: 0;
    border: none;
    background: transparent;
    color: inherit;
    font: inherit;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
}

.sort-button:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.data-row {
    cursor: pointer;
}

.data-row.selected td {
    color: #1e293b !important;
    background-color: #cab7e1 !important;
}

.status-select {
    min-width: 160px;
    max-width: 240px;
    padding: 2px 4px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #111827;
    font: inherit;
    font-weight: 700;
}

.data-row.selected .status-select {
    color: #111827;
}

.empty {
    text-align: center;
    color: #64748b;
    padding: 24px;
}

.lending-elapsed-red {
    color: #dc2626;
    font-weight: 800;
}

.dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 200;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 16px;
}

.dialog-panel {
    width: min(420px, calc(100vw - 32px));
    background: #f8fafc;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.28);
    box-sizing: border-box;
}

.detail-choice-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid #cbd5e1;
    background: #1e293b;
    color: #fff;
    border-radius: 8px 8px 0 0;
}

.detail-choice-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}

.detail-choice-kind {
    margin: 12px 14px 0;
    color: #0f172a;
    font-size: 15px;
    font-weight: 800;
    text-align: center;
}

.detail-choice-close {
    min-width: 36px;
    min-height: 34px;
    border: none;
    border-radius: 4px;
    background: #475569;
    color: #fff;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
}

.detail-choice-error {
    margin: 12px 14px 0;
    color: #b91c1c;
    font-size: 13px;
}

.detail-choice-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px 14px;
}

.detail-choice-btn {
    min-height: 42px;
    padding: 10px 14px;
    border: none;
    border-radius: 6px;
    background: #4a4a4a;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-align: center;
}

.detail-choice-btn:hover {
    background: #2563eb;
}

.edit-dialog {
    width: 50vw;
    max-width: 50vw;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.edit-dialog-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.edit-save-btn {
    min-height: 34px;
    padding: 6px 14px;
    border: none;
    border-radius: 4px;
    background: #2563eb;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.edit-save-btn:disabled {
    opacity: 0.6;
    cursor: wait;
}

.edit-dialog-body {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px;
    overflow: auto;
}

.edit-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 12px;
    min-width: 0;
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
}

.edit-label {
    flex: 0 0 140px;
    width: 140px;
}

.edit-input {
    width: 400px;
    max-width: 400px;
    box-sizing: border-box;
    min-height: 34px;
    padding: 6px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    font: inherit;
    font-weight: 700;
    color: #0f172a;
}

.edit-field--full {
    width: 100%;
}

.edit-field--full .edit-input {
    flex: 1 1 auto;
    width: auto;
    max-width: none;
    min-width: 0;
}

.edit-input:disabled {
    background: #e2e8f0;
    color: #64748b;
}
</style>
