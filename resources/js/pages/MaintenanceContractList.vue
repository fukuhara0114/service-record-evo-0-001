<template>
    <div class="contract-page">
        <header class="page-header">
            <div>
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
                <div class="search-grid">
                    <label class="search-field">
                        <span>dealer</span>
                        <input v-model="searchForm.dealer" type="text" placeholder="dealer">
                    </label>
                    <label class="search-field">
                        <span>instrumentName</span>
                        <input v-model="searchForm.instrumentName" type="text" placeholder="instrumentName">
                    </label>
                    <label class="search-field">
                        <span>SN</span>
                        <input v-model="searchForm.SN" type="text" placeholder="SN">
                    </label>
                    <label class="search-field">
                        <span>endUser</span>
                        <input v-model="searchForm.endUser" type="text" placeholder="endUser">
                    </label>
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
                        <button type="button" class="btn btn-secondary" :disabled="searching" @click="clearSearch">
                            クリア
                        </button>
                    </div>
                </div>
            </form>

            <div v-if="contracts?.links?.length" class="pager">
                <template v-for="link in contracts.links" :key="`${link.label}-${link.url}`">
                    <button
                        v-if="link.url"
                        type="button"
                        class="page-link"
                        :class="{ active: link.active }"
                        :disabled="searching"
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
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="12" class="empty">該当する契約はありません。</td>
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
                            <td>
                                <div class="type-name">{{ row.contractTypeName || '—' }}</div>
                                <div v-if="row.contractTypeDescription" class="type-desc">
                                    {{ row.contractTypeDescription }}
                                </div>
                            </td>
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
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'

const props = defineProps({
    contracts: {
        type: Object,
        required: true,
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
            scope: 'active',
        }),
    },
})

const page = usePage()
const searching = ref(false)
const selectedId = ref(null)
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const rows = computed(() => props.contracts?.data ?? [])
const totalCount = computed(() => props.contracts?.total ?? rows.value.length)
const listUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/maintenance-contracts`)

const searchForm = reactive({
    dealer: props.filters?.dealer ?? '',
    endUser: props.filters?.endUser ?? '',
    instrumentName: props.filters?.instrumentName ?? '',
    SN: props.filters?.SN ?? '',
    scope: props.filters?.scope === 'all' ? 'all' : 'active',
})

const isActiveScope = computed(() => searchForm.scope !== 'all')

watch(
    () => props.filters,
    (next) => {
        searchForm.dealer = next?.dealer ?? ''
        searchForm.endUser = next?.endUser ?? ''
        searchForm.instrumentName = next?.instrumentName ?? ''
        searchForm.SN = next?.SN ?? ''
        searchForm.scope = next?.scope === 'all' ? 'all' : 'active'
    },
    { deep: true },
)

function buildQuery(extra = {}) {
    const query = { ...extra }
    if (searchForm.dealer.trim()) query.dealer = searchForm.dealer.trim()
    if (searchForm.endUser.trim()) query.endUser = searchForm.endUser.trim()
    if (searchForm.instrumentName.trim()) query.instrumentName = searchForm.instrumentName.trim()
    if (searchForm.SN.trim()) query.SN = searchForm.SN.trim()
    query.scope = searchForm.scope === 'all' ? 'all' : 'active'
    return query
}

function runQuery(url, query = {}) {
    searching.value = true
    router.get(url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
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

function clearSearch() {
    searchForm.dealer = ''
    searchForm.endUser = ''
    searchForm.instrumentName = ''
    searchForm.SN = ''
    // scope（有効/全件）は維持
    runQuery(listUrl.value, buildQuery())
}

function goToPage(url) {
    if (!url || searching.value) return
    searching.value = true
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            searching.value = false
        },
    })
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
</script>

<style scoped>
.contract-page {
    min-height: 100vh;
    padding: 12px 16px 24px;
    background: #e2e8f0;
    box-sizing: border-box;
    color: #1e293b;
    font-weight: 700;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 12px;
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
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.search-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: end;
    justify-content: flex-start;
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

.search-side {
    display: flex;
    gap: 8px;
    align-items: center;
    flex: 0 0 auto;
    width: auto;
    max-width: none;
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

.table-wrap {
    overflow: auto;
    max-height: calc(100vh - 260px);
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1100px;
}

th,
td {
    border-bottom: 1px solid #e2e8f0;
    padding: 8px 10px;
    text-align: left;
    vertical-align: top;
}

th {
    position: sticky;
    top: 0;
    background: #f1f5f9;
    color: #475569;
    z-index: 1;
    white-space: nowrap;
}

.empty {
    text-align: center;
    color: #64748b;
    padding: 24px;
}

.data-row {
    cursor: pointer;
}

.data-row:hover td {
    background: #f8fafc;
}

.data-row.selected td {
    background: #dbeafe;
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

.type-name {
    color: #0f172a;
}

.type-desc {
    margin-top: 2px;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
    justify-content: flex-end;
}

.page-link {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 2px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #334155;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.page-link.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.page-link.disabled,
.page-link:disabled {
    opacity: 0.5;
    cursor: default;
}
</style>
