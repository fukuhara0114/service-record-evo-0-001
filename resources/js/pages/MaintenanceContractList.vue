<template>
    <div class="contract-page">
        <header class="page-header">
            <div class="header-title-row">
                <h1>Maintenance Contract 一覧</h1>
                <p class="subtitle">
                    <template v-if="isActiveScope">
                        有効: expireDate が {{ filterDate }} 以降（{{ totalCount }}件）
                    </template>
                    <template v-else>
                        全件表示（{{ totalCount }}件）
                    </template>
                </p>
            </div>
            <div class="header-actions">
                <CloseToHomeButton :href="homeUrl" />
            </div>
        </header>

        <section class="list-card">
            <form class="search-bar" @submit.prevent="search">
                <div class="search-row-top">
                    <div class="search-fields">
                        <div class="search-field">
                            <input v-model="searchForm.dealer" type="text" placeholder="dealer" aria-label="dealer">
                        </div>
                        <div class="search-field">
                            <input v-model="searchForm.instrumentName" type="text" placeholder="instrumentName" aria-label="instrumentName">
                        </div>
                        <div class="search-field">
                            <input v-model="searchForm.SN" type="text" placeholder="SN" aria-label="SN">
                        </div>
                        <div class="search-field">
                            <input v-model="searchForm.endUser" type="text" placeholder="endUser" aria-label="endUser">
                        </div>
                    </div>
                    <div class="quick-filter">
                        <input
                            id="contractQuickFilter"
                            v-model="quickFilter"
                            type="text"
                            class="quick-filter-input"
                            placeholder="Quick Filter : 複数キーワードはスペース区切り"
                            aria-label="Quick Filter"
                            @keydown.enter.prevent
                        >
                        <button type="button" class="btn btn-secondary" @click="clearQuickFilter">
                            clear
                        </button>
                    </div>
                </div>
                <div class="search-row-bottom">
                    <div class="search-range">
                        <span class="range-label">有効期限（expireDate）</span>
                        <div class="range-inputs">
                            <DateInputWithToday v-model="searchForm.expireDateFrom" aria-label="expireDate From" />
                            <span class="range-sep">〜</span>
                            <DateInputWithToday v-model="searchForm.expireDateTo" aria-label="expireDate To" />
                        </div>
                    </div>
                    <div class="search-range">
                        <span class="range-label">認証期限（certificationExpireDate）</span>
                        <div class="range-inputs">
                            <DateInputWithToday v-model="searchForm.certificationExpireDateFrom" aria-label="certificationExpireDate From" />
                            <span class="range-sep">〜</span>
                            <DateInputWithToday v-model="searchForm.certificationExpireDateTo" aria-label="certificationExpireDate To" />
                        </div>
                    </div>
                    <div class="search-side">
                        <button
                            type="button"
                            class="btn scope-toggle"
                            :class="{ active: isActiveScope, all: !isActiveScope }"
                            :disabled="searching"
                            @click="toggleScope"
                        >
                            {{ isActiveScope ? '有効' : '全件' }}
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="searching">
                            {{ searching ? '検索中...' : '検索' }}
                        </button>
                        <button
                            type="button"
                            class="btn notice-toggle"
                            :class="{ active: isNoticeFilter }"
                            :disabled="searching"
                            @click="toggleNoticeFilter"
                        >
                            要案内
                        </button>
                        <button type="button" class="btn btn-secondary" :disabled="searching" @click="clearSearch">
                            クリア
                        </button>
                    </div>
                    <div class="excel-export-slot">
                        <button
                            type="button"
                            class="btn btn-excel"
                            :disabled="searching || excelExporting"
                            @click="downloadExcel"
                        >
                            {{ excelExporting ? '出力中...' : 'Excel出力' }}
                        </button>
                        <p v-if="excelMessage" class="excel-message">{{ excelMessage }}</p>
                    </div>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>契約種別</th>
                            <th>dealer</th>
                            <th>endUser</th>
                            <th>instrumentName</th>
                            <th>SN</th>
                            <th>開始</th>
                            <th>契約終了</th>
                            <th>認証期限</th>
                            <th>status</th>
                            <th>amount</th>
                            <th>RefNumber</th>
                            <th>案内日</th>
                            <th>更新受注日</th>
                            <th>description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="15" class="empty">該当する契約はありません。</td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="data-row"
                            :class="{ selected: Number(selectedId) === Number(row.id) }"
                            @click="selectRow(row)"
                            @dblclick="openDetail(row)"
                        >
                            <td>{{ row.id }}</td>
                            <td>{{ row.contractTypeName || '—' }}</td>
                            <td>{{ row.dealer || '—' }}</td>
                            <td>{{ row.endUser || '—' }}</td>
                            <td>{{ row.instrumentName || '—' }}</td>
                            <td>{{ row.SN || '—' }}</td>
                            <td class="nowrap">{{ row.startDate || '—' }}</td>
                            <td class="nowrap">{{ row.expireDate || '—' }}</td>
                            <td class="nowrap expire">{{ row.certificationExpireDate || '—' }}</td>
                            <td>{{ row.status || '—' }}</td>
                            <td class="num">{{ formatAmount(row.amount) }}</td>
                            <td>{{ row.RefNumber || '—' }}</td>
                            <td class="nowrap">{{ row.informedDate || '—' }}</td>
                            <td class="nowrap">{{ row.renewedDate || '—' }}</td>
                            <td class="desc">{{ row.description || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import ExcelJS from 'exceljs'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import DateInputWithToday from '@/components/DateInputWithToday.vue'
import { applySensitivityLabel } from '@/utils/applySensitivityLabel'

const props = defineProps({
    contracts: {
        type: Array,
        default: () => [],
    },
    filterDate: {
        type: String,
        default: '',
    },
    filters: {
        type: Object,
        default: () => ({
            dealer: '',
            endUser: '',
            instrumentName: '',
            SN: '',
            expireDateFrom: '',
            expireDateTo: '',
            certificationExpireDateFrom: '',
            certificationExpireDateTo: '',
            scope: 'active',
            notice: false,
        }),
    },
})

const page = usePage()
const searching = ref(false)
const selectedId = ref(null)
const quickFilter = ref('')
const excelExporting = ref(false)
const excelMessage = ref('')

const EXCEL_COLUMNS = [
    { header: 'ID', key: 'id', width: 12 },
    { header: '契約種別', key: 'contractTypeName', width: 22 },
    { header: 'dealer', key: 'dealer', width: 28 },
    { header: 'endUser', key: 'endUser', width: 28 },
    { header: 'instrumentName', key: 'instrumentName', width: 22 },
    { header: 'SN', key: 'SN', width: 18 },
    { header: '開始', key: 'startDate', width: 14 },
    { header: '契約終了', key: 'expireDate', width: 14 },
    { header: '認証期限', key: 'certificationExpireDate', width: 14 },
    { header: 'status', key: 'status', width: 12 },
    { header: 'amount', key: 'amount', width: 14 },
    { header: 'RefNumber', key: 'RefNumber', width: 16 },
    { header: '案内日', key: 'informedDate', width: 14 },
    { header: '更新受注日', key: 'renewedDate', width: 14 },
    { header: 'description', key: 'description', width: 40 },
]
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const listUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/maintenance-contracts`)

const searchForm = reactive({
    dealer: props.filters?.dealer ?? '',
    endUser: props.filters?.endUser ?? '',
    instrumentName: props.filters?.instrumentName ?? '',
    SN: props.filters?.SN ?? '',
    expireDateFrom: props.filters?.expireDateFrom ?? '',
    expireDateTo: props.filters?.expireDateTo ?? '',
    certificationExpireDateFrom: props.filters?.certificationExpireDateFrom ?? '',
    certificationExpireDateTo: props.filters?.certificationExpireDateTo ?? '',
    scope: props.filters?.scope === 'all' ? 'all' : 'active',
    notice: Boolean(props.filters?.notice),
})

const isActiveScope = computed(() => searchForm.scope !== 'all')
const isNoticeFilter = computed(() => Boolean(searchForm.notice))
const rows = computed(() => {
    const all = props.contracts ?? []
    const scoped = isNoticeFilter.value ? all.filter(isNoticeTarget) : all
    const queries = String(quickFilter.value || '')
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter((q) => q.length > 0)
    if (queries.length === 0) return scoped
    return scoped.filter((row) => {
        const rowText = rowSearchText(row)
        return queries.every((q) => rowText.includes(q))
    })
})
const totalCount = computed(() => rows.value.length)

watch(
    () => props.filters,
    (next) => {
        searchForm.dealer = next?.dealer ?? ''
        searchForm.endUser = next?.endUser ?? ''
        searchForm.instrumentName = next?.instrumentName ?? ''
        searchForm.SN = next?.SN ?? ''
        searchForm.expireDateFrom = next?.expireDateFrom ?? ''
        searchForm.expireDateTo = next?.expireDateTo ?? ''
        searchForm.certificationExpireDateFrom = next?.certificationExpireDateFrom ?? ''
        searchForm.certificationExpireDateTo = next?.certificationExpireDateTo ?? ''
        searchForm.scope = next?.scope === 'all' ? 'all' : 'active'
        searchForm.notice = Boolean(next?.notice)
    },
    { deep: true },
)

function buildQuery(extra = {}) {
    const query = { ...extra }
    if (searchForm.dealer.trim()) query.dealer = searchForm.dealer.trim()
    if (searchForm.endUser.trim()) query.endUser = searchForm.endUser.trim()
    if (searchForm.instrumentName.trim()) query.instrumentName = searchForm.instrumentName.trim()
    if (searchForm.SN.trim()) query.SN = searchForm.SN.trim()
    if (searchForm.expireDateFrom) query.expireDateFrom = searchForm.expireDateFrom
    if (searchForm.expireDateTo) query.expireDateTo = searchForm.expireDateTo
    if (searchForm.certificationExpireDateFrom) {
        query.certificationExpireDateFrom = searchForm.certificationExpireDateFrom
    }
    if (searchForm.certificationExpireDateTo) {
        query.certificationExpireDateTo = searchForm.certificationExpireDateTo
    }
    query.scope = searchForm.scope === 'all' ? 'all' : 'active'
    if (searchForm.notice) query.notice = '1'
    return query
}

function runQuery(url, query = {}) {
    searching.value = true
    router.get(url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['contracts', 'filters', 'filterDate'],
        onFinish: () => {
            searching.value = false
        },
    })
}

function search() {
    runQuery(listUrl.value, buildQuery())
}

function toggleScope() {
    searchForm.scope = searchForm.scope === 'all' ? 'active' : 'all'
    runQuery(listUrl.value, buildQuery())
}

function rowSearchText(row) {
    return [
        row?.id,
        row?.contractType,
        row?.contractTypeName,
        row?.contractTypeDescription,
        row?.dealer,
        row?.branch,
        row?.contact,
        row?.phone,
        row?.email,
        row?.address,
        row?.endUser,
        row?.endUser_depart,
        row?.endUser_contact,
        row?.endUser_phone,
        row?.endUser_email,
        row?.endUser_address,
        row?.instrumentName,
        row?.SN,
        row?.startDate,
        row?.expireDate,
        row?.certificationExpireDate,
        row?.renewalInformation,
        row?.informedDate,
        row?.renewedDate,
        row?.informed,
        row?.status,
        row?.amount,
        row?.RefNumber,
        row?.description,
    ]
        .map((value) => (value == null || value === '' ? '' : String(value)))
        .join(' ')
        .toLowerCase()
}

function clearSearch() {
    searchForm.dealer = ''
    searchForm.endUser = ''
    searchForm.instrumentName = ''
    searchForm.SN = ''
    searchForm.expireDateFrom = ''
    searchForm.expireDateTo = ''
    searchForm.certificationExpireDateFrom = ''
    searchForm.certificationExpireDateTo = ''
    searchForm.notice = false
    // scope（有効/全件）は維持
    runQuery(listUrl.value, buildQuery())
}

function clearQuickFilter() {
    quickFilter.value = ''
    document.getElementById('contractQuickFilter')?.focus()
}

function toggleNoticeFilter() {
    searchForm.notice = !searchForm.notice
    runQuery(listUrl.value, buildQuery())
}

function todayYmd() {
    if (props.filterDate) return String(props.filterDate).slice(0, 10)
    const d = new Date()
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function ymdOrNull(value) {
    if (value == null || value === '') return null
    const raw = String(value).trim().slice(0, 10)
    if (!raw || raw.startsWith('0000-00-00')) return null
    if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) return null
    const year = Number(raw.slice(0, 4))
    if (!Number.isFinite(year) || year < 1901) return null
    return raw
}

function isNoticeTarget(row) {
    const planned = ymdOrNull(row?.renewalInformation)
    if (!planned || planned > todayYmd()) return false
    if (ymdOrNull(row?.renewedDate)) return false
    if (row?.informed != null && row.informed !== '' && Number(row.informed) < 0) return false
    return true
}

function selectRow(row) {
    selectedId.value = row?.id ?? null
}

function openDetail(row) {
    if (!row?.id) return
    selectedId.value = row.id
    window.location.href = `${page.props.appBaseUrl}/servicerecord/maintenance-contracts/${row.id}`
}

function formatAmount(value) {
    if (value == null || value === '') return '—'
    const num = Number(value)
    if (!Number.isFinite(num)) return String(value)
    return num.toLocaleString('ja-JP')
}

function excelCellValue(row, key) {
    if (key === 'amount') {
        if (row?.amount == null || row.amount === '') return null
        const num = Number(row.amount)
        return Number.isFinite(num) ? num : String(row.amount)
    }
    const value = row?.[key]
    if (value == null) return ''
    return String(value)
}

function excelFileName() {
    const ymd = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date()).replace(/-/g, '')
    return `MaintenanceContract_${ymd}.xlsx`
}

async function downloadExcel() {
    const exportRows = rows.value
    excelMessage.value = ''
    if (!exportRows.length) {
        excelMessage.value = '出力する行がありません。'
        return
    }

    excelExporting.value = true
    try {
        const workbook = new ExcelJS.Workbook()
        workbook.creator = 'ServiceRecord'
        workbook.created = new Date()

        const sheet = workbook.addWorksheet('Maintenance Contract', {
            views: [{ state: 'frozen', ySplit: 1 }],
        })
        sheet.columns = EXCEL_COLUMNS.map((column) => ({
            header: column.header,
            key: column.key,
            width: column.width,
        }))

        exportRows.forEach((row) => {
            const values = {}
            EXCEL_COLUMNS.forEach((column) => {
                values[column.key] = excelCellValue(row, column.key)
            })
            sheet.addRow(values)
        })

        const headerRow = sheet.getRow(1)
        headerRow.font = { name: 'Arial', size: 11, bold: true, color: { argb: 'FFFFFFFF' } }
        headerRow.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true }
        headerRow.height = 22
        EXCEL_COLUMNS.forEach((_, index) => {
            const cell = headerRow.getCell(index + 1)
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: 'FF2F63CC' },
            }
        })

        const amountColumn = EXCEL_COLUMNS.findIndex((column) => column.key === 'amount') + 1
        for (let rowIndex = 2; rowIndex <= sheet.rowCount; rowIndex += 1) {
            const cell = sheet.getRow(rowIndex).getCell(amountColumn)
            if (typeof cell.value === 'number') {
                cell.numFmt = '#,##0.##'
                cell.alignment = { horizontal: 'right' }
            }
            sheet.getRow(rowIndex).getCell(EXCEL_COLUMNS.length).alignment = { wrapText: true, vertical: 'top' }
        }

        let xlsxBuffer = await workbook.xlsx.writeBuffer()
        const msip = page.props.msip || {}
        const labelId = String(msip.publicLabelId || '').trim()
        const siteId = String(msip.siteId || '').trim()
        if (labelId && siteId) {
            xlsxBuffer = await applySensitivityLabel(xlsxBuffer, {
                labelId,
                siteId,
                method: 'Privileged',
                contentBits: 0,
            })
        }

        const blob = new Blob(
            [xlsxBuffer],
            { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
        )
        const url = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.download = excelFileName()
        document.body.appendChild(anchor)
        anchor.click()
        anchor.remove()
        URL.revokeObjectURL(url)
    } catch (e) {
        excelMessage.value = e.message || 'Excel出力に失敗しました。'
    } finally {
        excelExporting.value = false
    }
}
</script>

<style scoped>
.contract-page {
    zoom: 1.1;
    --page-zoom: 1.1;
    width: 100%;
    min-height: calc(100vh / 1.1);
    padding: 12px 16px 24px;
    background: #e2e8f0;
    box-sizing: border-box;
    color: #1e293b;
    font-weight: 700;
    transform-origin: top left;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}

.header-title-row {
    display: flex;
    align-items: center;
    min-width: 0;
}

.page-header h1 {
    margin: 0;
    font-size: 22px;
    flex: 0 0 auto;
}

.subtitle {
    margin: 0 0 0 200px;
    color: #64748b;
    font-size: 13px;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.list-card {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
}

.search-bar {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.search-row-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.search-fields,
.search-row-bottom {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: end;
    justify-content: flex-start;
}

.search-fields {
    flex: 0 1 auto;
}

.search-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    box-sizing: border-box;
    flex: 0 0 200px;
    width: 200px;
    max-width: 200px;
    min-width: 200px;
    font-size: 12px;
    color: #475569;
}

.quick-filter {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex: 1 1 auto;
    min-width: 220px;
}

.quick-filter-input {
    flex: 1 1 auto;
    width: 100%;
    max-width: 360px;
    box-sizing: border-box;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    padding: 7px 8px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

.search-field input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    padding: 7px 8px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

.search-range {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 0 0 auto;
    font-size: 12px;
    color: #475569;
}

.range-label {
    font-weight: 700;
}

.range-inputs {
    display: flex;
    align-items: center;
    gap: 6px;
}

.range-inputs input {
    width: 140px;
    box-sizing: border-box;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    padding: 7px 8px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

.range-sep {
    color: #64748b;
}

.search-side {
    display: flex;
    gap: 8px;
    align-items: center;
    flex: 0 0 auto;
    width: auto;
    max-width: none;
}

.excel-export-slot {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    margin-left: auto;
    align-self: flex-end;
}

.btn-excel {
    background: #107c41;
    white-space: nowrap;
}

.excel-message {
    margin: 0;
    font-size: 12px;
    color: #b91c1c;
}

.btn {
    min-height: 34px;
    padding: 6px 14px;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    color: #fff;
}

.btn:disabled {
    opacity: 0.6;
    cursor: wait;
}

.btn-primary {
    background: #2563eb;
}

.btn-secondary {
    background: #64748b;
}

.scope-toggle {
    min-width: 72px;
    border: 1px solid #15803d;
    background: #dcfce7;
    color: #166534;
}

.scope-toggle.all {
    border-color: #475569;
    background: #e2e8f0;
    color: #334155;
}

.notice-toggle {
    min-width: 72px;
    border: 1px solid #b45309;
    background: #ffedd5;
    color: #9a3412;
}

.notice-toggle.active {
    background: #f97316;
    border-color: #ea580c;
    color: #fff;
}

.table-wrap {
    overflow: auto;
    max-height: calc((100vh / 1.1) - 150px);
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1100px;
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

.empty {
    text-align: center;
    color: #64748b;
    padding: 24px;
}

.data-row {
    cursor: pointer;
}

.data-row.selected td {
    color: #1e293b !important;
    background-color: #cab7e1 !important;
}

.nowrap {
    white-space: nowrap;
}

.expire {
    color: #b45309;
}

.num {
    text-align: right;
    white-space: nowrap;
}

.desc {
    white-space: normal;
    min-width: 160px;
    max-width: 280px;
    word-break: break-word;
}
</style>
