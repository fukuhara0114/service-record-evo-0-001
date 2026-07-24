<template>
    <div class="list-page-container">
        <!-- 第1階層: 検索窓 -->
        <div class="fixed-header-zone">
            <div style="flex: 1;"></div>
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
                <a href="/home">Home</a>
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
                        <td>{{ r.status_master?.status || '' }}</td>
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
        <p v-if="attachmentsLoading" class="global-loading">添付データを読み込み中...</p>

        <DetailShell
            v-if="isDetailOpen"
            :record="activeRecord"
            :notes="activeNotes"
            :files="activeFiles"
            :parts="activeParts"
            :attachments-loading="attachmentsLoading"
            :attachments-error="attachmentsError"
            :layout="detailLayout"
            @close="closeDetail"
            @switch-layout="switchDetailLayout"
            @open-dialog="openDialog"
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
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { redirectToLogin } from '@/utils/auth'
import DetailShell from '@/components/ServiceRecord/Layer2/DetailShell.vue'
import InputDialogA from '@/components/ServiceRecord/Layer3/InputDialogA.vue'
import InputDialogB from '@/components/ServiceRecord/Layer3/InputDialogB.vue'
import InputDialogC from '@/components/ServiceRecord/Layer3/InputDialogC.vue'
import ConfirmDialogD from '@/components/ServiceRecord/Layer3/ConfirmDialogD.vue'
import NoteEditDialog from '@/components/ServiceRecord/Layer3/NoteEditDialog.vue'
import FileUploadDialog from '@/components/ServiceRecord/Layer3/FileUploadDialog.vue'

const props = defineProps({
    initialRecords: Array,
    statuses: Array,
    returnCodes: Array,
    labors: Array,
    mode: String,
})

const page = usePage()

onMounted(() => {
    if (!page.props.authUser) {
        redirectToLogin()
    }
})

// --- 第1階層 ---
const searchQuery = ref('')
const selectedOrderId = ref(null)

const filteredRecords = computed(() => {
    if (!searchQuery.value) return props.initialRecords

    const queries = searchQuery.value
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter(q => q.length > 0)

    if (queries.length === 0) return props.initialRecords

    return props.initialRecords.filter(r => {
        const rowText = [
            r.orderID?.toString(),
            r.receivedDate,
            r.status_master?.status,
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
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return queries.every(q => rowText.includes(q))
    })
})

function clearSearch() {
    searchQuery.value = ''
    document.getElementById('customSearchInput')?.focus()
}

// --- 第2階層 ---
const isDetailOpen = ref(false)
const activeRecord = ref(null)
const detailLayout = ref('A')
const activeNotes = ref([])
const activeFiles = ref([])
const activeParts = ref([])
const attachmentsLoading = ref(false)
const attachmentsError = ref('')

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

async function openSecondLayer(record) {
    if (!record?.orderID) {
        console.error('orderID が取得できません', record)
        return
    }

    activeRecord.value = record
    detailLayout.value = 'A'
    closeDialog()

    await loadAttachments(record.orderID)
    isDetailOpen.value = true
}

function switchDetailLayout(layout) {
    detailLayout.value = layout
}

function closeDetail() {
    isDetailOpen.value = false
    activeRecord.value = null
    activeNotes.value = []
    activeFiles.value = []
    activeParts.value = []
    attachmentsLoading.value = false
    attachmentsError.value = ''
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
    if (result && activeRecord.value) {
        Object.assign(activeRecord.value, result)
    }

    if (activeRecord.value?.orderID) {
        await loadAttachments(activeRecord.value.orderID)
    }

    closeDialog()
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
</style>