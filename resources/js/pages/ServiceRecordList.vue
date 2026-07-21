<template>
    <div class="list-page-container">
        <!-- 🏆 【第1階層】一覧リストの検索窓（以前作ったスタイルとクリアボタン付き） -->
        <div class="fixed-header-zone" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; box-sizing: border-box; background: white; border-bottom: 2px solid #3b82f6;">
            <div style="flex: 1;"></div>
            <div style="flex: 1; display: flex; justify-content: center; align-items: center; gap: 8px;">
                <label for="customSearchInput" style="font-weight: bold; font-size: 14px; white-space: nowrap;">Quick Filer:</label>
                <input type="text" id="customSearchInput" v-model="searchQuery" placeholder="キーワードを入力してください..." style="width: 400px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                <button type="button" @click="clearSearch" style="padding: 6px 16px; background-color: #6b7280; color: white; border: none; border-radius: 4px; font-size: 14px; font-weight: bold; cursor: pointer;">Clear</button>
            </div>
            <div style="flex: 1; display: flex; justify-content: flex-end;">
                <a href="/home" style="padding: 8px 16px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none;">Home</a>
            </div>
        </div>

        <!-- 🏆 【第1階層】テーブルエリア（内側スクロール・以前作ったスタイルをVue用に最適化） -->
        <div class="scrollable-table-zone" style="margin-top: 80px; padding: 0 20px; overflow-auto;">
            <table id="myLargeTable" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f3f4f6;">
                        <th style="width: 80px; text-align: center;">OrderID</th>
                        <th>受領日</th>
                        <th>ステータス</th>
                        <th>RMA#</th>
                        <th>製品名</th>
                        <th>S/N</th>
                        <th>販売店</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- 💡 filteredRecords（検索で絞り込まれた配列）をVueの v-for ループで回します -->
                    <!-- シングルクリックで選択色変更、ダブルクリックで第2階層を起動 -->
                    <tr v-for="r in filteredRecords" 
                        :key="r.orderID"
                        class="table-row"
                        :class="{ 'active-row': selectedOrderId === r.orderID }"
                        @click="selectedOrderId = r.orderID"
                        @dblclick="openSecondLayer(r)"
                        style="cursor: pointer;">
                        
                        <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                        <td>{{ r.receivedDate }}</td>
                        <!-- 💡 前回のデバッグ通り、小文字の r.status_master.status で100%文字が出ます -->
                        <td>{{ r.status_master?.status || '' }}</td>
                        <td>{{ r.RMA }}</td>
                        <td>{{ r.productName }}</td>
                        <td>{{ r.SN }}</td>
                        <td>{{ r.dealer }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ========================================================================= -->
        <!-- 🚀 以前作った「詳細（第2階層）」や「入力（第3階層）」のコンポーネントをここに並列配置します -->
        <!-- ========================================================================= -->
        <!-- <DetailModalParent v-if="isDetailOpen" :record="activeRecord" @close="isDetailOpen = false" /> -->
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'

// 🚀 【最重要】コントローラーの「Inertia::render」の第2引数で定義したデータをここで受信します
const props = defineProps({
    initialRecords: Array,
    statuses: Array,
    returnCodes: Array,
    labors: Array,
    mode: String
})

// 状態管理用の変数（リアクティブデータ）
const searchQuery = ref('')
const selectedOrderId = ref(null)
const isDetailOpen = ref(false)
const activeRecord = ref(null)

// 🚀 【検索機能】Vueのcomputedを使って、文字が打ち込まれたらテーブルを自動でリアルタイムに絞り込む（前回のJSロジックの完全Vue化）
const filteredRecords = computed(() => {
    if (!searchQuery.value) return props.initialRecords
    
    const query = searchQuery.value.toLowerCase().trim()
    return props.initialRecords.filter(r => {
        return (
            r.orderID?.toString().includes(query) ||
            r.productName?.toLowerCase().includes(query) ||
            r.SN?.toLowerCase().includes(query) ||
            r.dealer?.toLowerCase().includes(query) ||
            r.status_master?.status?.toLowerCase().includes(query)
        )
    })
})

// 🚀 クイックフィルターをクリアする関数
function clearSearch() {
    searchQuery.value = ''
    document.getElementById('customSearchInput')?.focus()
}

// 🚀 【第2階層の起動】ダブルクリックされたら、その行のデータを変数にセットして詳細を開くフラグを立てる
function openSecondLayer(record) {
    activeRecord.value = record
    isDetailOpen.value = true
    console.log("第2階層（詳細ダイアログ）を開きます。対象データ:", record)
}
</script>

<style scoped>
/* 以前定義したHoverや選択時のCSSをそのままここに記述します */
.table-row:hover td {
    color: white !important;
    background-color: #1751c4 !important;
    white-space: normal !important;
    word-break: break-all !important;
}
.active-row td {
    color: white !important;
    background-color: #2563eb !important;
}
#myLargeTable td, #myLargeTable th {
    border: 1px solid #333333;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
