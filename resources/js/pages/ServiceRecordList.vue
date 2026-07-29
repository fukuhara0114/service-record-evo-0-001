<template>
    <div class="list-page-container">
        <!-- 第1階層: 検索窓 -->
        <div class="fixed-header-zone">
            <div class="order-type-filters">
                <button
                    type="button"
                    class="order-type-btn"
                    :class="{ active: orderTypeFilter === 'service' }"
                    @click="orderTypeFilter = 'service'"
                >
                    service
                </button>
                <button
                    type="button"
                    class="order-type-btn"
                    :class="{ active: orderTypeFilter === 'loaner' }"
                    @click="orderTypeFilter = 'loaner'"
                >
                    loaner
                </button>
                <button
                    type="button"
                    class="order-type-btn"
                    :class="{ active: orderTypeFilter === 'waiting_list' }"
                    @click="orderTypeFilter = 'waiting_list'"
                >
                    waiting
                </button>
            </div>
            <div class="search-area">
                <label for="customSearchInput">Quick Filer:</label>
                <input
                    type="text"
                    id="customSearchInput"
                    v-model="searchQuery"
                    placeholder="複数キーワードはスペース区切り（例: sony 修理）"
                >
                <button type="button" @click="clearSearch">Clear</button>
            </div>
            <div class="home-link-area">
                <a :href="homeUrl">Home</a>
            </div>
        </div>

        <!-- 第1階層: テーブル -->
        <div class="scrollable-table-zone">
            <table id="myLargeTable">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">OrderID</th>
                        <th>受領日</th>
                        <th>ステータス</th>
                        <th>RMA#</th>
                        <th>製品名</th>
                        <th>S/N</th>
                        <th>作業内容</th>
                        <th>担当者</th>
                        <th>販売店</th>
                        <th>部署</th>
                        <th>担当者</th>
                        <th>Email</th>
                        <th>Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in filteredRecords"
                        :key="r.orderID"
                        class="table-row"
                        :class="{ 'active-row': selectedOrderId === r.orderID }"
                        @click="selectedOrderId = r.orderID"
                        @dblclick="openSecondLayer(r)"
                    >
                        <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                        <td>{{ r.receivedDate }}</td>
                        <td>{{ statusLabel(r) }}</td>
                        <td>{{ r.RMA }}</td>
                        <td>{{ r.productName }}</td>
                        <td>{{ r.SN }}</td>
                        <td>{{ r.return_code_master?.description || '' }}</td>
                        <td>{{ r.labor_master?.laborName || '' }}</td>
                        <td>{{ r.dealer }}</td>
                        <td>{{ r.dealer_depart }}</td>
                        <td>{{ r.contactPerson }}</td>
                        <td>{{ r.email }}</td>
                        <td>{{ r.phone }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 第2階層: 詳細 A/B/C -->
        <p v-if="detailLoading" class="global-loading">詳細データを読み込み中...</p>
        <p v-if="detailOpenError" class="global-error">{{ detailOpenError }}</p>

        <DetailShell
            v-if="isDetailOpen"
            :record="activeRecord"
            :draft-record="draftRecord"
            :notes="activeNotes"
            :files="activeFiles"
            :parts="activeParts"
            :attachments-loading="attachmentsLoading"
            :attachments-error="attachmentsError"
            :saving-record="isSavingRecord"
            :save-error="saveError"
            :layout="detailLayout"
            @close="closeDetail"
            @switch-layout="switchDetailLayout"
            @open-dialog="openDialog"
            @save="saveRecord"
        />

        <!-- 第3階層: 入力・確認ダイアログ -->
        <InputDialogA
            v-if="activeDialog === 'A'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
        <InputDialogB
            v-if="activeDialog === 'B'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
        <InputDialogC
            v-if="activeDialog === 'C'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
        <ConfirmDialogD
            v-if="activeDialog === 'D'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
        <NoteEditDialog
            v-if="activeDialog === 'NOTE'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
        <FileUploadDialog
            v-if="activeDialog === 'FILE'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
        <ServiceMasterSelectDialog
            v-if="activeDialog === 'MASTER_SELECT'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
        <PartSelectDialog
            v-if="activeDialog === 'PART'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { redirectToLogin } from '@/utils/auth'
import { apiFetch } from '@/utils/apiFetch'
import DetailShell from '@/components/ServiceRecord/Layer2/DetailShell.vue'
import InputDialogA from '@/components/ServiceRecord/Layer3/InputDialogA.vue'
import InputDialogB from '@/components/ServiceRecord/Layer3/InputDialogB.vue'
import InputDialogC from '@/components/ServiceRecord/Layer3/InputDialogC.vue'
import ConfirmDialogD from '@/components/ServiceRecord/Layer3/ConfirmDialogD.vue'
import NoteEditDialog from '@/components/ServiceRecord/Layer3/NoteEditDialog.vue'
import FileUploadDialog from '@/components/ServiceRecord/Layer3/FileUploadDialog.vue'
import ServiceMasterSelectDialog from '@/components/ServiceRecord/Layer3/ServiceMasterSelectDialog.vue'
import PartSelectDialog from '@/components/ServiceRecord/Layer3/PartSelectDialog.vue'

const props = defineProps({
    initialRecords: Array,
    statuses: Array,
    statusesLoaner: Array,
    returnCodes: Array,
    labors: Array,
    mode: String,
})

const page = usePage()

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)

onMounted(() => {
    if (!page.props.authUser) {
        redirectToLogin()
    }

    const params = new URLSearchParams(window.location.search)
    const initialQuery = params.get('q')?.trim()
    if (initialQuery) {
        searchQuery.value = initialQuery
    }
})

// --- 第1階層 ---
const searchQuery = ref('')
const orderTypeFilter = ref('service')
const selectedOrderId = ref(null)

const filteredRecords = computed(() => {
    let records = props.initialRecords ?? []

    records = records.filter((r) => matchesOrderTypeFilter(r, orderTypeFilter.value))

    if (!searchQuery.value) return records

    const queries = searchQuery.value
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter(q => q.length > 0)

    if (queries.length === 0) return records

    return records.filter(r => {
        const rowText = [
            r.orderID?.toString(),
            r.receivedDate,
            statusLabel(r),
            r.RMA,
            r.productName,
            r.SN,
            r.return_code_master?.description,
            r.labor_master?.laborName,
            r.dealer,
            r.dealer_depart,
            r.contactPerson,
            r.email,
            r.phone,
            r.order_type,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return queries.every(q => rowText.includes(q))
    })
})

function matchesOrderTypeFilter(record, filter) {
    const orderType = record?.order_type ?? null

    if (filter === 'service') {
        return orderType === 'service' || orderType == null || orderType === ''
    }
    if (filter === 'loaner') {
        return orderType === 'loaner'
    }
    if (filter === 'waiting_list') {
        return orderType === 'waiting_list'
    }
    return true
}

function statusLabel(record) {
    if (record?.order_type === 'waiting_list') {
        return ''
    }
    if (record?.order_type === 'loaner') {
        return record.status_master_loaner?.status || ''
    }
    return record.status_master?.status || ''
}

function clearSearch() {
    searchQuery.value = ''
    document.getElementById('customSearchInput')?.focus()
}

// --- 第2階層 ---
const isDetailOpen = ref(false)
const activeRecord = ref(null)
const draftRecord = ref(null)
const detailLayout = ref('A')
const activeNotes = ref([])
const activeFiles = ref([])
const activeParts = ref([])
const attachmentsLoading = ref(false)
const attachmentsError = ref('')
const isSavingRecord = ref(false)
const saveError = ref('')
const detailLoading = ref(false)
const detailOpenError = ref('')

function getBasePath() {
    return window.location.pathname.replace(/\/administrator\/?$/, '')
}

function applyAttachmentData(data) {
    if (!data) {
        attachmentsError.value = '添付データが見つかりません。'
        activeNotes.value = []
        activeFiles.value = []
        activeParts.value = []
        return
    }

    if (data.error) {
        attachmentsError.value = data.error
        activeNotes.value = []
        activeFiles.value = []
        activeParts.value = []
        return
    }

    attachmentsError.value = ''
    activeNotes.value = data.notes ?? []
    activeFiles.value = data.files ?? []
    activeParts.value = data.parts ?? []
}

function loadAttachments(orderID) {
    return new Promise((resolve) => {
        attachmentsLoading.value = true
        attachmentsError.value = ''
        activeNotes.value = []
        activeFiles.value = []
        activeParts.value = []

        router.get(
            window.location.pathname,
            { loadOrderID: orderID },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['attachmentData'],
                onSuccess: (page) => {
                    applyAttachmentData(page.props.attachmentData)
                    resolve()
                },
                onError: () => {
                    attachmentsError.value = '添付データの取得に失敗しました。'
                    resolve()
                },
                onFinish: () => {
                    attachmentsLoading.value = false
                },
            },
        )
    })
}

async function fetchRecord(orderID) {
    const url = `${window.location.origin}${getBasePath()}/record/${orderID}`
    const result = await apiFetch(url)

    if (!result) {
        throw new Error('詳細データの取得に失敗しました。')
    }

    const { response, data } = result
    if (!response.ok) {
        throw new Error(data?.message || `詳細データの取得に失敗しました。（HTTP ${response.status}）`)
    }

    return data
}

async function openSecondLayer(record) {
    if (!record?.orderID) {
        console.error('orderID が取得できません', record)
        return
    }

    detailOpenError.value = ''
    attachmentsError.value = ''
    activeRecord.value = record
    draftRecord.value = { ...record }
    detailLayout.value = 'A'
    closeDialog()
    isDetailOpen.value = true
    detailLoading.value = true

    try {
        const fullRecord = await fetchRecord(record.orderID)
        activeRecord.value = fullRecord
        draftRecord.value = { ...fullRecord }
    } catch (e) {
        detailOpenError.value = `${e.message || '詳細データの取得に失敗しました。'}（一覧の情報のみ表示しています）`
    } finally {
        detailLoading.value = false
    }

    await loadAttachments(record.orderID)
}

function switchDetailLayout(layout) {
    detailLayout.value = layout
}

function closeDetail() {
    isDetailOpen.value = false
    activeRecord.value = null
    draftRecord.value = null
    activeNotes.value = []
    activeFiles.value = []
    activeParts.value = []
    attachmentsLoading.value = false
    attachmentsError.value = ''
    detailLoading.value = false
    detailOpenError.value = ''
    saveError.value = ''
    isSavingRecord.value = false
    closeDialog()
}

// --- 第3階層 ---
const activeDialog = ref(null)
const dialogPayload = ref(null)

function openDialog(type, payload = null) {
    activeDialog.value = type
    dialogPayload.value = payload
}

function closeDialog() {
    activeDialog.value = null
    dialogPayload.value = null
}

async function onDialogSaved(result) {
    if (activeDialog.value === 'MASTER_SELECT' && result && draftRecord.value) {
        Object.assign(draftRecord.value, result)
        closeDialog()
        return
    }

    if (result && activeRecord.value) {
        Object.assign(activeRecord.value, result)
    }

    if (activeRecord.value?.orderID) {
        await loadAttachments(activeRecord.value.orderID)
    }

    closeDialog()
}

async function saveRecord() {
    if (!activeRecord.value?.orderID || !draftRecord.value) {
        return
    }

    isSavingRecord.value = true
    saveError.value = ''

    const url = `${window.location.origin}${getBasePath()}/${activeRecord.value.orderID}`

    try {
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                serviceID: draftRecord.value.serviceID,
                productName: draftRecord.value.productName,
                entityID: draftRecord.value.entityID,
                status: draftRecord.value.status,
                returnCode: draftRecord.value.returnCode,
                laborID: draftRecord.value.laborID,
                receivedDate: draftRecord.value.receivedDate,
                SN: draftRecord.value.SN,
                RMA: draftRecord.value.RMA,
                sm_workorder: draftRecord.value.sm_workorder,
                sm_quote: draftRecord.value.sm_quote,
                coNum: draftRecord.value.coNum,
                dealer: draftRecord.value.dealer,
                dealer_depart: draftRecord.value.dealer_depart,
                contactPerson: draftRecord.value.contactPerson,
                email: draftRecord.value.email,
                phone: draftRecord.value.phone,
                receiptNumber: draftRecord.value.receiptNumber,
                quoteDate: draftRecord.value.quoteDate,
                quoteNum: draftRecord.value.quoteNum,
                poNum: draftRecord.value.poNum,
                orderDate: draftRecord.value.orderDate,
                orderNum: draftRecord.value.orderNum,
                invNum: draftRecord.value.invNum,
                shippedDate: draftRecord.value.shippedDate,
                productType: draftRecord.value.productType,
                price: draftRecord.value.price,
                discountRate: draftRecord.value.discountRate,
                a2la: draftRecord.value.a2la,
                sentOut: draftRecord.value.sentOut,
                shipTo: draftRecord.value.shipTo,
                rmaNumOverSea: draftRecord.value.rmaNumOverSea,
                preData: draftRecord.value.preData,
                postData: draftRecord.value.postData,
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
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        Object.assign(activeRecord.value, draftRecord.value)
        if (activeRecord.value.order_type === 'loaner') {
            activeRecord.value.status_master_loaner = page.props.statusesLoaner?.find(
                status => String(status.processID) === String(draftRecord.value.status),
            ) ?? null
            activeRecord.value.status_master = null
        } else if (activeRecord.value.order_type === 'waiting_list') {
            activeRecord.value.status_master = null
            activeRecord.value.status_master_loaner = null
        } else {
            activeRecord.value.status_master = page.props.statuses?.find(
                status => String(status.processID) === String(draftRecord.value.status),
            ) ?? null
        }
        activeRecord.value.return_code_master = page.props.returnCodes?.find(code => String(code.id) === String(draftRecord.value.returnCode)) ?? null
        activeRecord.value.labor_master = page.props.labors?.find(labor => String(labor.laborID) === String(draftRecord.value.laborID)) ?? null
        activeRecord.value.serviceMaster = page.props.servicesMaster?.find(service => String(service.serviceID) === String(draftRecord.value.serviceID)) ?? null
    } catch (e) {
        saveError.value = e.message || '保存に失敗しました。'
    } finally {
        isSavingRecord.value = false
    }
}
</script>

<style scoped>
.list-page-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow: hidden;
    background: #e2e8f0;
    position: relative;
}

.fixed-header-zone {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    box-sizing: border-box;
    background: #dbdbdb;
    border-bottom: 2px solid #3b82f6;
    z-index: 20;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.order-type-filters {
    display: flex;
    gap: 6px;
    flex: 1;
    justify-content: flex-start;
}

.order-type-btn {
    padding: 6px 12px;
    border: 1px solid #64748b;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.order-type-btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.search-area {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
}

.search-area label {
    font-weight: bold;
    font-size: 14px;
    white-space: nowrap;
}

.search-area input {
    width: 400px;
    padding: 6px 12px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 14px;
    background-color: #ffffff;
    color: #111827;
}

.search-area button {
    padding: 6px 16px;
    background-color: #6b7280;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

.home-link-area {
    flex: 1;
    display: flex;
    justify-content: flex-end;
}

.home-link-area a {
    padding: 8px 16px;
    background: #2563eb;
    color: white;
    border-radius: 6px;
    text-decoration: none;
}

.scrollable-table-zone {
    flex: 1;
    min-height: 0;
    padding-left: 10px;
    padding-right: 10px;
    overflow: auto;
    background: #e2e8f0;
}

#myLargeTable {
    width: 100%;
    border-collapse: collapse;
    background: #d8d8d8;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

#myLargeTable thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #2f63cc;
    color: white;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
}

#myLargeTable td,
#myLargeTable th {
    border: 1px solid #333333;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 12px;
    font-weight: bold;
}

.table-row {
    cursor: pointer;
}

.active-row td {
    color: rgb(255, 255, 255) !important;
    background-color: #7e25eb !important;
}

.global-loading {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.35);
    color: white;
    font-size: 18px;
    font-weight: bold;
    z-index: 90;
}

.global-error {
    position: fixed;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    max-width: min(90vw, 720px);
    margin: 0;
    padding: 10px 16px;
    background: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    color: #b91c1c;
    font-size: 14px;
    z-index: 95;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}
</style>