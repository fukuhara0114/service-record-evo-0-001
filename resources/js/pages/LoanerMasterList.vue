<template>
    <div class="loaner-master-page">
        <header class="page-header">
            <div>
                <h1>LoanerMaster 一覧</h1>
                <p class="subtitle">loanermaster008 全カラム（{{ totalCount }}件）</p>
            </div>
            <div class="header-actions">
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
                            <th v-for="column in columns" :key="column">{{ column }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td :colspan="columns.length || 1" class="empty">データがありません。</td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="data-row"
                            :class="{ selected: Number(selectedId) === Number(row.id) }"
                            @click="selectRow(row)"
                        >
                            <td v-for="column in columns" :key="`${row.id}-${column}`">
                                {{ displayCell(row[column]) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'

const props = defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    masters: {
        type: Object,
        required: true,
    },
})

const page = usePage()
const loading = ref(false)
const selectedId = ref(null)
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const rows = computed(() => props.masters?.data ?? [])
const totalCount = computed(() => props.masters?.total ?? rows.value.length)

function displayCell(value) {
    if (value == null || value === '') return '—'
    return String(value)
}

function selectRow(row) {
    selectedId.value = row?.id ?? null
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
    max-height: calc(100vh - 180px);
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

th,
td {
    border: 1px solid #e2e8f0;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
    white-space: nowrap;
}

th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f8fafc;
    color: #334155;
}

.data-row {
    cursor: pointer;
}

.data-row:hover {
    background: #f8fafc;
}

.data-row.selected {
    background: #dbeafe;
}

.empty {
    text-align: center;
    color: #64748b;
    padding: 24px;
}
</style>
