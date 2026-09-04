<template>
    <div class="list-page-container">
        <!-- 🏆 【第1階層】一覧リストの検索窓（固定） -->
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

        <!-- 🏆 【第1階層】テーブルエリア（body だけスクロール） -->
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
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    initialRecords: Array,
    statuses: Array,
    returnCodes: Array,
    labors: Array,
    mode: String
})

const searchQuery = ref('')
const selectedOrderId = ref(null)
const isDetailOpen = ref(false)
const activeRecord = ref(null)

const filteredRecords = computed(() => {
    if (!searchQuery.value) return props.initialRecords
    // 空白で分割して空文字を除去
    const queries = searchQuery.value
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter(q => q.length > 0)
    if (queries.length === 0) return props.initialRecords
    return props.initialRecords.filter(r => {
        // 1行分の文字列をまとめる
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
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
        // すべてのキーワードが含まれている行だけ残す
        return queries.every(q => rowText.includes(q))
    })
})

function clearSearch() {
    searchQuery.value = ''
    document.getElementById('customSearchInput')?.focus()
}

function openSecondLayer(record) {
    activeRecord.value = record
    isDetailOpen.value = true
    console.log("第2階層（詳細ダイアログ）を開きます。対象データ:", record)
}
</script>

<style scoped>
.list-page-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow: hidden;
    background: #e2e8f0; /* 白 → 少し暗いグレー */
}

.fixed-header-zone {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    box-sizing: border-box;
    background: #dbdbdb; /* ヘッダーは白のまま */
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
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
   background-color: #ffffff;
    color: #111827;
    border: 1px solid #94a3b8;
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
    padding-left: 10px; /* 背景が見える余白 */
    padding-right: 10px; /* 背景が見える余白 */
    overflow: auto;
    background: #e2e8f0; /* スクロールエリアも同じ背景色 */
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

/* .table-row:hover td {
    color: white !important;
    background-color: #1751c4 !important;
    white-space: normal !important;
    word-break: break-all !important;
} */

.active-row td {
    color: #1e293b !important;
    background-color: #cab7e1 !important;
}
</style>