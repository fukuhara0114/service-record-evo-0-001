<template>
    <div class="list-page-container">
        <!-- 第1階層: 検索窓 -->
        <div class="fixed-header-zone">
            <div v-if="!isRestrictedListMode" class="order-type-filters">
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
                    :class="{ active: orderTypeFilter === 'closing' }"
                    @click="orderTypeFilter = 'closing'"
                >
                    closing
                </button>
                <button
                    type="button"
                    class="order-type-btn"
                    :class="{ active: orderTypeFilter === 'invoice' }"
                    @click="orderTypeFilter = 'invoice'"
                >
                    invoice
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
                <button
                    type="button"
                    class="order-type-btn"
                    :class="{ active: orderTypeFilter === 'abroad' }"
                    @click="orderTypeFilter = 'abroad'"
                >
                    abroad
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
            <div v-if="isBoardMode" class="logistics-view-controls">
                <button
                    type="button"
                    class="view-mode-btn"
                    :class="{ active: logisticsViewMode === 'list' }"
                    @click="logisticsViewMode = 'list'"
                >
                    一覧のみ
                </button>
                <button
                    type="button"
                    class="view-mode-btn"
                    :class="{ active: logisticsViewMode === 'both' }"
                    @click="logisticsViewMode = 'both'"
                >
                    一覧+カレンダー
                </button>
                <button
                    type="button"
                    class="view-mode-btn"
                    :class="{ active: logisticsViewMode === 'calendar' }"
                    @click="logisticsViewMode = 'calendar'"
                >
                    カレンダーのみ
                </button>
                <button
                    type="button"
                    class="view-mode-btn swap-btn"
                    :disabled="logisticsViewMode !== 'both'"
                    @click="logisticsCalendarOnLeft = !logisticsCalendarOnLeft"
                >
                    左右入替
                </button>
            </div>
            <div class="home-link-area">
                <span v-if="mode === 'engineer'" class="mode-badge">Engineer</span>
                <span v-else-if="mode === 'logistics'" class="mode-badge">Logistics (status=350)</span>
                <span v-else-if="mode === 'shippingPrep'" class="mode-badge">出荷準備 (status=300,385)</span>
                <a v-if="!isRestrictedListMode" :href="shippingCalendarUrl" class="calendar-link">出荷カレンダー</a>
                <a v-if="!isRestrictedListMode" :href="priceRevisionUrl" class="calendar-link">価格改定</a>
                <CloseToHomeButton :href="homeUrl" />
            </div>
        </div>

        <!-- 第1階層: テーブル / Logistics・出荷準備はカレンダー併用 -->
        <template v-if="isBoardMode">
            <div v-if="logisticsViewMode === 'list'" class="scrollable-table-zone">
                <table id="myLargeTable">
                    <thead>
                        <tr>
                            <th style="width: 80px; text-align: center;">OrderID</th>
                            <th>予定出荷日</th>
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
                            <td
                                style="text-align: center; font-weight: bold;"
                                :class="orderIdUnderlineClass(r)"
                            >{{ r.orderID }}</td>
                            <td>{{ formatListDate(r.shippingOut_requiredDate) }}</td>
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
            <div v-else-if="logisticsViewMode === 'calendar'" class="logistics-calendar-zone">
                <ShippingOutDateDialog
                    ref="logisticsCalendarRef"
                    mode="browse"
                    plain
                    go-to-today
                    initial-view="dayGridDay"
                    hide-detail-pane
                    :editable="false"
                    :show-footer="false"
                    :status-filter="boardStatusFilter"
                    :status-by-order-id="boardStatusByOrderId"
                    @select-order="onLogisticsCalendarSelect"
                />
            </div>
            <Splitpanes
                v-else
                class="default-theme logistics-split"
                @resized="onLogisticsSplitResized"
            >
                <Pane
                    v-for="panel in logisticsPanels"
                    :key="panel"
                    class="logistics-split-pane"
                    :size="50"
                    :min-size="24"
                >
                    <div v-if="panel === 'list'" class="scrollable-table-zone logistics-pane-body">
                        <table id="myLargeTable">
                            <thead>
                                <tr>
                                    <th style="width: 80px; text-align: center;">OrderID</th>
                                    <th>予定出荷日</th>
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
                                    <td
                                        style="text-align: center; font-weight: bold;"
                                        :class="orderIdUnderlineClass(r)"
                                    >{{ r.orderID }}</td>
                                    <td>{{ formatListDate(r.shippingOut_requiredDate) }}</td>
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
                    <div v-else class="logistics-calendar-zone logistics-pane-body">
                        <ShippingOutDateDialog
                            ref="logisticsCalendarRef"
                            mode="browse"
                            plain
                            go-to-today
                            :show-footer="false"
                            hide-detail-pane
                            :editable="false"
                            :status-filter="boardStatusFilter"
                            :status-by-order-id="boardStatusByOrderId"
                            @select-order="onLogisticsCalendarSelect"
                        />
                    </div>
                </Pane>
            </Splitpanes>
        </template>
        <div v-else class="scrollable-table-zone">
            <div v-if="orderTypeFilter === 'abroad'" class="abroad-toolbar">
                <button
                    type="button"
                    class="abroad-excel-btn"
                    :disabled="abroadSelectedCount === 0"
                    @click="openAbroadExcelPreview"
                >
                    Create Excel File{{ abroadSelectedCount > 0 ? ` (${abroadSelectedCount})` : '' }}
                </button>
                <span v-if="abroadExcelMessage" class="abroad-excel-message">{{ abroadExcelMessage }}</span>
            </div>
            <table id="myLargeTable">
                <thead>
                    <tr v-if="orderTypeFilter === 'abroad'">
                        <th style="width: 80px; text-align: center;">OrderID</th>
                        <th style="width: 44px; text-align: center;">
                            <input
                                type="checkbox"
                                :checked="abroadAllVisibleSelected"
                                :indeterminate.prop="abroadSomeVisibleSelected && !abroadAllVisibleSelected"
                                title="表示中を全選択"
                                @click.stop
                                @change="toggleAbroadSelectAll($event)"
                            >
                        </th>
                        <th>受領日</th>
                        <th>ステータス</th>
                        <th>製品名</th>
                        <th>S/N</th>
                        <th>作業内容</th>
                        <th>販売店</th>
                        <th>部署</th>
                    </tr>
                    <tr v-else>
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
                        <template v-if="orderTypeFilter === 'abroad'">
                            <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                            <td style="text-align: center;" @click.stop @dblclick.stop>
                                <input
                                    type="checkbox"
                                    :checked="isAbroadSelected(r.orderID)"
                                    @change="toggleAbroadSelect(r.orderID, $event)"
                                >
                            </td>
                            <td>{{ formatListDate(r.receivedDate) }}</td>
                            <td>{{ statusLabel(r) }}</td>
                            <td>{{ r.productName }}</td>
                            <td>{{ r.SN }}</td>
                            <td>{{ r.return_code_master?.description || '' }}</td>
                            <td>{{ r.dealer }}</td>
                            <td>{{ r.dealer_depart }}</td>
                        </template>
                        <template v-else>
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
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="abroadExcelPreviewOpen"
            class="abroad-preview-overlay"
            @click.self="closeAbroadExcelPreview"
        >
            <div class="abroad-preview-panel abroad-preview-panel-wide" role="dialog" aria-modal="true" aria-labelledby="abroad-preview-title">
                <header class="abroad-preview-header">
                    <div>
                        <h2 id="abroad-preview-title">Excel プレビュー / 編集</h2>
                        <p>表と添付画像を確認・編集してからファイルを作成します（{{ abroadExcelPreviewRows.length }} 行 / 画像 {{ abroadAttachedImages.length }} 件）</p>
                    </div>
                    <button type="button" class="abroad-preview-close" aria-label="閉じる" @click="closeAbroadExcelPreview">×</button>
                </header>
                <div class="abroad-preview-body abroad-preview-body-split">
                    <section class="abroad-preview-section">
                        <div class="abroad-preview-section-head">
                            <h3>Excel 内容</h3>
                            <button type="button" class="abroad-preview-btn abroad-preview-btn-small" @click="addAbroadExcelRow">行を追加</button>
                        </div>
                        <div class="abroad-preview-table-wrap">
                            <table class="abroad-preview-table">
                                <thead>
                                    <tr>
                                        <th v-for="header in abroadExcelHeaders" :key="header">{{ header }}</th>
                                        <th style="width: 72px;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, rowIndex) in abroadExcelPreviewRows" :key="`row-${rowIndex}`">
                                        <td v-for="(_cell, cellIndex) in row" :key="cellIndex">
                                            <input
                                                v-model="abroadExcelPreviewRows[rowIndex][cellIndex]"
                                                type="text"
                                                class="abroad-cell-input"
                                            >
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                class="abroad-preview-btn abroad-preview-btn-small abroad-preview-btn-danger"
                                                @click="removeAbroadExcelRow(rowIndex)"
                                            >
                                                削除
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="abroad-preview-section">
                        <div class="abroad-preview-section-head">
                            <h3>添付画像レイアウト</h3>
                            <button type="button" class="abroad-preview-btn abroad-preview-btn-small" @click="abroadGalleryPickerOpen = true">
                                Gallery から選択
                            </button>
                        </div>
                        <p v-if="!abroadAttachedImages.length" class="abroad-preview-empty">
                            まだ画像がありません。「Gallery から選択」で複数追加できます。
                        </p>
                        <div v-else class="abroad-image-grid">
                            <div
                                v-for="(image, imageIndex) in abroadAttachedImages"
                                :key="image.id"
                                class="abroad-image-card"
                            >
                                <img :src="image.thumbnail_url || image.image_url" :alt="image.title || image.file_name">
                                <div class="abroad-image-meta">
                                    <strong>{{ image.title || image.file_name }}</strong>
                                    <span>{{ image.captured_by || '—' }}</span>
                                </div>
                                <div class="abroad-image-actions">
                                    <button type="button" class="abroad-preview-btn abroad-preview-btn-small" :disabled="imageIndex === 0" @click="moveAbroadImage(imageIndex, -1)">↑</button>
                                    <button type="button" class="abroad-preview-btn abroad-preview-btn-small" :disabled="imageIndex === abroadAttachedImages.length - 1" @click="moveAbroadImage(imageIndex, 1)">↓</button>
                                    <button type="button" class="abroad-preview-btn abroad-preview-btn-small abroad-preview-btn-danger" @click="removeAbroadImage(imageIndex)">外す</button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                <footer class="abroad-preview-footer">
                    <span v-if="abroadExcelCreating" class="abroad-excel-message">作成中...</span>
                    <button type="button" class="abroad-preview-btn" :disabled="abroadExcelCreating" @click="closeAbroadExcelPreview">キャンセル</button>
                    <button
                        type="button"
                        class="abroad-preview-btn abroad-preview-btn-primary"
                        :disabled="abroadExcelCreating || !abroadExcelPreviewRows.length"
                        @click="downloadAbroadExcelFile"
                    >
                        Excel File を作成
                    </button>
                </footer>
            </div>
        </div>

        <div
            v-if="abroadGalleryPickerOpen"
            class="abroad-preview-overlay abroad-gallery-overlay"
            @click.self="abroadGalleryPickerOpen = false"
        >
            <div class="abroad-gallery-panel" role="dialog" aria-modal="true" aria-label="Gallery から画像を選択">
                <header class="abroad-preview-header">
                    <div>
                        <h2>Gallery から画像を選択</h2>
                        <p>複数選択して「選択した画像を使う」を押してください</p>
                    </div>
                    <button type="button" class="abroad-preview-close" aria-label="閉じる" @click="abroadGalleryPickerOpen = false">×</button>
                </header>
                <div class="abroad-gallery-body">
                    <CapturedImageGallery
                        selection-only
                        @confirm-selection="onAbroadGalleryConfirm"
                    />
                </div>
            </div>
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
            :captured-images="activeCapturedImages"
            :parts="activeParts"
            :stocked-parts="activeStockedParts"
            :loaners="activeLoaners"
            :attachments-loading="attachmentsLoading"
            :attachments-error="attachmentsError"
            :saving-record="isSavingRecord"
            :save-error="saveError"
            :layout="detailLayout"
            :mode="mode"
            :current-user-kanji="currentUserKanji"
            @close="closeDetail"
            @switch-layout="switchDetailLayout"
            @open-dialog="openDialog"
            @save="saveRecord"
            @files-updated="onFilesUpdated"
            @reload-attachments="onReloadAttachments"
            @workflow-done="onEngineerWorkflowDone"
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
        <StockedPartSelectDialog
            v-if="activeDialog === 'STOCKED_PART'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @selected="onStockedPartSelected"
        />
        <StockedPartQuantityDialog
            v-if="activeDialog === 'STOCKED_PART_QTY'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
        <UnregisteredEmailNoteLinkDialog
            v-if="activeDialog === 'EMAIL_NOTE_LINK'"
            :record="activeRecord"
            :payload="dialogPayload"
            @close="closeDialog"
            @saved="onDialogSaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import ExcelJS from 'exceljs'
import { redirectToLogin } from '@/utils/auth'
import { apiFetch } from '@/utils/apiFetch'
import { findServiceMaster } from '@/utils/resolveServiceWorkPrice'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import CapturedImageGallery from '@/components/ServiceRecord/CapturedImageGallery.vue'
import DetailShell from '@/components/ServiceRecord/Layer2/DetailShell.vue'
import InputDialogA from '@/components/ServiceRecord/Layer3/InputDialogA.vue'
import InputDialogB from '@/components/ServiceRecord/Layer3/InputDialogB.vue'
import InputDialogC from '@/components/ServiceRecord/Layer3/InputDialogC.vue'
import ConfirmDialogD from '@/components/ServiceRecord/Layer3/ConfirmDialogD.vue'
import NoteEditDialog from '@/components/ServiceRecord/Layer3/NoteEditDialog.vue'
import FileUploadDialog from '@/components/ServiceRecord/Layer3/FileUploadDialog.vue'
import ServiceMasterSelectDialog from '@/components/ServiceRecord/Layer3/ServiceMasterSelectDialog.vue'
import PartSelectDialog from '@/components/ServiceRecord/Layer3/PartSelectDialog.vue'
import StockedPartSelectDialog from '@/components/ServiceRecord/Layer3/StockedPartSelectDialog.vue'
import StockedPartQuantityDialog from '@/components/ServiceRecord/Layer3/StockedPartQuantityDialog.vue'
import UnregisteredEmailNoteLinkDialog from '@/components/ServiceRecord/Layer3/UnregisteredEmailNoteLinkDialog.vue'
import ShippingOutDateDialog from '@/components/ServiceRecord/Layer3/ShippingOutDateDialog.vue'

const props = defineProps({
    initialRecords: Array,
    statuses: Array,
    statusesLoaner: Array,
    returnCodes: Array,
    labors: Array,
    mode: String,
})

const page = usePage()

const isBoardMode = computed(() => props.mode === 'logistics' || props.mode === 'shippingPrep')
const isRestrictedListMode = computed(() =>
    props.mode === 'engineer' || props.mode === 'logistics' || props.mode === 'shippingPrep',
)
const boardStatusFilter = computed(() => (
    props.mode === 'shippingPrep' ? '300,385' : '300,350'
))

const currentUserKanji = computed(() => {
    const fromPage = String(page.props.authUser?.kanji_name ?? '').trim()
    if (fromPage) return fromPage
    if (typeof document !== 'undefined') {
        return String(document.querySelector('meta[name="auth-kanji-name"]')?.content ?? '').trim()
    }
    return ''
})
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const shippingCalendarUrl = computed(() => {
    const base = getBasePath()
    return `${window.location.origin}${base}/shipping-calendar`
})
const priceRevisionUrl = computed(() => {
    const base = getBasePath()
    return `${window.location.origin}${base}/master-price-revision`
})

onMounted(() => {
    if (!page.props.authUser) {
        redirectToLogin()
    }

    const params = new URLSearchParams(window.location.search)
    const initialQuery = params.get('q')?.trim()
    if (initialQuery) {
        searchQuery.value = initialQuery
    }

    if (isBoardMode.value) {
        startLogisticsAutoRefresh()
    }
})

onUnmounted(() => {
    stopLogisticsAutoRefresh()
})

// --- 第1階層 ---
const searchQuery = ref('')
const orderTypeFilter = ref('service')
const selectedOrderId = ref(null)
const abroadSelectedIds = ref(new Set())
const abroadExcelMessage = ref('')
const abroadExcelPreviewOpen = ref(false)
const abroadGalleryPickerOpen = ref(false)
const abroadExcelPreviewRows = ref([])
const abroadAttachedImages = ref([])
const abroadExcelCreating = ref(false)
const abroadExcelHeaders = ['OrderID', '受領日', 'ステータス', '製品名', 'S/N', '作業内容', '販売店', '部署']
// Home→Logistics / 出荷準備 遷移時は「カレンダーのみ / 日 / 今日」を初期表示
const logisticsViewMode = ref(isBoardMode.value ? 'calendar' : 'both') // list | both | calendar
const logisticsCalendarOnLeft = ref(false)
const logisticsCalendarRef = ref(null)
const logisticsAutoRefreshTimer = ref(null)
const logisticsAutoRefreshing = ref(false)
const LOGISTICS_AUTO_REFRESH_MS = 60 * 1000

const logisticsPanels = computed(() => (
    logisticsCalendarOnLeft.value ? ['calendar', 'list'] : ['list', 'calendar']
))

/** カレンダー色分けは一覧の orderID→status を優先する */
const boardStatusByOrderId = computed(() => {
    const map = {}
    const fallback = props.mode === 'logistics' ? 350 : 0
    for (const record of props.initialRecords ?? []) {
        if (record?.orderID == null) continue
        const status = Number(record.status)
        const resolved = Number.isFinite(status) && status > 0 ? status : fallback
        if (!resolved) continue
        map[String(record.orderID)] = resolved
        map[Number(record.orderID)] = resolved
    }
    return map
})

function orderIdUnderlineClass(record) {
    if (props.mode !== 'shippingPrep') return ''
    const status = Number(record?.status)
    if (status === 300) return 'order-id-status-300'
    if (status === 385) return 'order-id-status-385'
    return ''
}

function onLogisticsSplitResized() {
    nextTick(() => {
        window.dispatchEvent(new Event('resize'))
    })
}

watch([logisticsViewMode, logisticsCalendarOnLeft], () => {
    nextTick(() => {
        window.dispatchEvent(new Event('resize'))
    })
})

async function onLogisticsCalendarSelect({ orderId, pending }) {
    if (pending || !orderId) return
    selectedOrderId.value = Number(orderId) || orderId
    const record = (props.initialRecords ?? []).find(
        (item) => String(item.orderID) === String(orderId),
    )
    if (record) {
        await openSecondLayer(record)
        return
    }
    try {
        const fullRecord = await fetchRecord(orderId)
        await openSecondLayer(fullRecord)
    } catch (e) {
        detailOpenError.value = e.message || '案件詳細の取得に失敗しました。'
    }
}

const filteredRecords = computed(() => {
    let records = props.initialRecords ?? []

    if (props.mode === 'engineer') {
        records = records.filter((r) => {
            const orderType = r?.order_type ?? 'service'
            return orderType === 'service' || orderType === 'loaner'
        })
    } else if (!isBoardMode.value) {
        records = records.filter((r) => matchesOrderTypeFilter(r, orderTypeFilter.value))
    }

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
            formatListDate(r.shippingOut_requiredDate),
            r.shippingOut_requiredDate,
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

const abroadSelectedCount = computed(() => abroadSelectedIds.value.size)
const abroadAllVisibleSelected = computed(() => {
    const rows = filteredRecords.value
    if (!rows.length) return false
    return rows.every((r) => abroadSelectedIds.value.has(r.orderID))
})
const abroadSomeVisibleSelected = computed(() => {
    const rows = filteredRecords.value
    if (!rows.length) return false
    return rows.some((r) => abroadSelectedIds.value.has(r.orderID))
})

function isAbroadSelected(orderID) {
    return abroadSelectedIds.value.has(orderID)
}

function toggleAbroadSelect(orderID, event) {
    const next = new Set(abroadSelectedIds.value)
    if (event.target.checked) {
        next.add(orderID)
    } else {
        next.delete(orderID)
    }
    abroadSelectedIds.value = next
}

function toggleAbroadSelectAll(event) {
    const next = new Set(abroadSelectedIds.value)
    if (event.target.checked) {
        for (const r of filteredRecords.value) {
            next.add(r.orderID)
        }
    } else {
        for (const r of filteredRecords.value) {
            next.delete(r.orderID)
        }
    }
    abroadSelectedIds.value = next
}

function clearAbroadSelection() {
    abroadSelectedIds.value = new Set()
    abroadExcelMessage.value = ''
    closeAbroadExcelPreview()
}

function buildAbroadExcelRows() {
    return filteredRecords.value
        .filter((r) => abroadSelectedIds.value.has(r.orderID))
        .map((r) => [
            String(r.orderID ?? ''),
            String(formatListDate(r.receivedDate) || r.receivedDate || ''),
            String(statusLabel(r) || ''),
            String(r.productName || ''),
            String(r.SN || ''),
            String(r.return_code_master?.description || ''),
            String(r.dealer || ''),
            String(r.dealer_depart || ''),
        ])
}

function openAbroadExcelPreview() {
    abroadExcelMessage.value = ''
    const rows = buildAbroadExcelRows()
    if (!rows.length) {
        abroadExcelMessage.value = '行を選択してください。'
        return
    }
    abroadExcelPreviewRows.value = rows.map((row) => [...row])
    abroadAttachedImages.value = []
    abroadGalleryPickerOpen.value = false
    abroadExcelPreviewOpen.value = true
}

function closeAbroadExcelPreview() {
    abroadExcelPreviewOpen.value = false
    abroadGalleryPickerOpen.value = false
    abroadExcelPreviewRows.value = []
    abroadAttachedImages.value = []
    abroadExcelCreating.value = false
}

function addAbroadExcelRow() {
    abroadExcelPreviewRows.value.push(abroadExcelHeaders.map(() => ''))
}

function removeAbroadExcelRow(index) {
    abroadExcelPreviewRows.value.splice(index, 1)
}

function onAbroadGalleryConfirm(images) {
    const incoming = Array.isArray(images) ? images : []
    const next = [...abroadAttachedImages.value]
    for (const image of incoming) {
        if (!image?.id) continue
        if (next.some((item) => item.id === image.id)) continue
        next.push({
            id: image.id,
            file_name: image.file_name,
            title: image.title,
            image_url: image.image_url,
            thumbnail_url: image.thumbnail_url,
            captured_by: image.captured_by,
            captured_at: image.captured_at,
        })
    }
    abroadAttachedImages.value = next
    abroadGalleryPickerOpen.value = false
}

function removeAbroadImage(index) {
    abroadAttachedImages.value.splice(index, 1)
}

function moveAbroadImage(index, delta) {
    const target = index + delta
    if (target < 0 || target >= abroadAttachedImages.value.length) return
    const next = [...abroadAttachedImages.value]
    const [item] = next.splice(index, 1)
    next.splice(target, 0, item)
    abroadAttachedImages.value = next
}

async function fetchImageBlob(url) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'image/*,application/octet-stream',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    if (!response.ok) {
        throw new Error(`画像の取得に失敗しました。（HTTP ${response.status}）`)
    }
    return response.blob()
}

function resolveExcelImageExtension(fileName, mimeType) {
    const name = String(fileName || '').toLowerCase()
    if (name.endsWith('.png') || mimeType === 'image/png') return 'png'
    if (name.endsWith('.gif') || mimeType === 'image/gif') return 'gif'
    return 'jpeg'
}

async function downloadAbroadExcelFile() {
    const rows = abroadExcelPreviewRows.value
    if (!rows.length) {
        abroadExcelMessage.value = '行を選択してください。'
        return
    }

    abroadExcelCreating.value = true
    abroadExcelMessage.value = ''

    try {
        const workbook = new ExcelJS.Workbook()
        workbook.creator = 'ServiceRecord'
        workbook.created = new Date()

        const dataSheet = workbook.addWorksheet('Data', {
            views: [{ state: 'frozen', ySplit: 1 }],
        })
        dataSheet.addRow([...abroadExcelHeaders])
        rows.forEach((row) => {
            dataSheet.addRow(row.map((cell) => (cell == null ? '' : String(cell))))
        })

        const headerRow = dataSheet.getRow(1)
        headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' } }
        headerRow.fill = {
            type: 'pattern',
            pattern: 'solid',
            fgColor: { argb: 'FF0F766E' },
        }
        abroadExcelHeaders.forEach((_, index) => {
            const column = dataSheet.getColumn(index + 1)
            column.width = Math.max(12, String(abroadExcelHeaders[index]).length + 4)
        })
        dataSheet.columns.forEach((column) => {
            let max = column.width || 12
            column.eachCell({ includeEmpty: true }, (cell) => {
                const len = String(cell.value ?? '').length
                if (len + 2 > max) max = Math.min(40, len + 2)
            })
            column.width = max
        })

        // リストの下に画像のみを水平配置（ラベル・ファイル名・日付は付けない）
        if (abroadAttachedImages.value.length) {
            const imageHeightPx = 220
            const imageWidthPx = 280
            const colSpanPerImage = 3.2
            // exceljs の tl は 0-based。データ最終行(1-based)=rows.length+1 の次行
            const imageTopRow = rows.length + 1
            let imageIndex = 0

            for (let i = 0; i < abroadAttachedImages.value.length; i += 1) {
                const image = abroadAttachedImages.value[i]
                const sourceUrl = image.image_url || image.thumbnail_url
                if (!sourceUrl) continue

                const blob = await fetchImageBlob(sourceUrl)
                const buffer = await blob.arrayBuffer()
                const extension = resolveExcelImageExtension(image.file_name, blob.type)
                const imageId = workbook.addImage({
                    buffer,
                    extension,
                })
                dataSheet.addImage(imageId, {
                    tl: { col: imageIndex * colSpanPerImage, row: imageTopRow },
                    ext: { width: imageWidthPx, height: imageHeightPx },
                })
                imageIndex += 1
            }
        }

        const stamp = new Date().toISOString().slice(0, 19).replace(/[-:T]/g, '')
        const xlsxBuffer = await workbook.xlsx.writeBuffer()
        const blob = new Blob(
            [xlsxBuffer],
            { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
        )
        const url = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.download = `abroad_${stamp}.xlsx`
        document.body.appendChild(anchor)
        anchor.click()
        anchor.remove()
        URL.revokeObjectURL(url)

        abroadExcelMessage.value = `${rows.length} 行` +
            (abroadAttachedImages.value.length ? ` + 画像 ${abroadAttachedImages.value.length} 件` : '') +
            ' を 1 つの Excel ファイル（.xlsx）で出力しました。'
        closeAbroadExcelPreview()
    } catch (e) {
        abroadExcelMessage.value = e.message || 'ファイル作成に失敗しました。'
    } finally {
        abroadExcelCreating.value = false
    }
}

watch(orderTypeFilter, (value) => {
    if (value !== 'abroad') {
        clearAbroadSelection()
    }
})

function matchesOrderTypeFilter(record, filter) {
    const orderType = record?.order_type ?? null
    const status = Number(record?.status)

    if (filter === 'service') {
        return orderType === 'service' || orderType == null || orderType === ''
    }
    if (filter === 'closing') {
        const isServiceOrLoaner = orderType === 'service'
            || orderType === 'loaner'
            || orderType == null
            || orderType === ''
        return isServiceOrLoaner && status === 200
    }
    if (filter === 'invoice') {
        return Number.isFinite(status) && status >= 300 && status <= 385
    }
    if (filter === 'loaner') {
        return orderType === 'loaner'
    }
    if (filter === 'waiting_list') {
        return orderType === 'waiting_list'
    }
    if (filter === 'abroad') {
        return Number(record?.rmaNumOverSea) === 123
            || String(record?.rmaNumOverSea ?? '').trim() === '123'
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

function formatListDate(value) {
    if (value == null || value === '') return ''
    if (typeof value === 'string') return value.slice(0, 10)
    if (value instanceof Date) {
        const pad = (n) => String(n).padStart(2, '0')
        return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`
    }
    return String(value).slice(0, 10)
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
const activeCapturedImages = ref([])
const activeParts = ref([])
const activeStockedParts = ref([])
const activeLoaners = ref([])
const attachmentsLoading = ref(false)
const attachmentsError = ref('')
const isSavingRecord = ref(false)
const saveError = ref('')
const detailLoading = ref(false)
const detailOpenError = ref('')

function getBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
}

function annotateNotesOwnership(notes) {
    const me = currentUserKanji.value
    return (Array.isArray(notes) ? notes : []).map((note) => {
        const who = String(note?.whoWrote ?? '').trim()
        const isMine = note?.is_mine === true || note?.is_mine === 1 || note?.is_mine === '1'
            || (me !== '' && who !== '' && me === who)
        return {
            ...note,
            is_mine: isMine,
        }
    })
}

function applyAttachmentData(data) {
    if (!data) {
        attachmentsError.value = '添付データが見つかりません。'
        activeNotes.value = []
        activeFiles.value = []
        activeCapturedImages.value = []
        activeParts.value = []
        activeStockedParts.value = []
        activeLoaners.value = []
        return
    }

    if (data.error) {
        attachmentsError.value = data.error
        activeNotes.value = []
        activeFiles.value = []
        activeCapturedImages.value = []
        activeParts.value = []
        activeStockedParts.value = []
        activeLoaners.value = []
        return
    }

    attachmentsError.value = ''
    activeNotes.value = annotateNotesOwnership(data.notes ?? [])
    activeFiles.value = data.files ?? []
    activeCapturedImages.value = data.capturedImages ?? []
    activeParts.value = data.parts ?? []
    activeStockedParts.value = data.stockedParts ?? []
    activeLoaners.value = data.loaners ?? (data.loaner ? [data.loaner] : [])
}

function onFilesUpdated(nextFiles) {
    activeFiles.value = Array.isArray(nextFiles) ? nextFiles : []
}

function onReloadAttachments() {
    if (!activeRecord.value?.orderID) return
    loadAttachments(activeRecord.value.orderID)
}

function loadAttachments(orderID) {
    return new Promise((resolve) => {
        attachmentsLoading.value = true
        attachmentsError.value = ''
        activeNotes.value = []
        activeFiles.value = []
        activeCapturedImages.value = []
        activeParts.value = []
        activeStockedParts.value = []
        activeLoaners.value = []

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

    // loaner フィルター選択中は貸出案件詳細ページへ遷移
    if (orderTypeFilter.value === 'loaner') {
        const returnUrl = typeof window !== 'undefined' ? window.location.href : ''
        const params = returnUrl ? `?returnUrl=${encodeURIComponent(returnUrl)}` : ''
        window.location.href = `${page.props.appBaseUrl}/servicerecord/loaner/detail/${record.orderID}${params}`
        return
    }

    detailOpenError.value = ''
    attachmentsError.value = ''
    activeRecord.value = record
    draftRecord.value = { ...record }
    if (props.mode === 'logistics') {
        detailLayout.value = 'logistics'
    } else if (props.mode === 'shippingPrep') {
        detailLayout.value = 'invoice'
    } else if (orderTypeFilter.value === 'closing') {
        detailLayout.value = 'closing'
    } else if (orderTypeFilter.value === 'invoice') {
        detailLayout.value = 'invoice'
    } else {
        detailLayout.value = 'A'
    }
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

function resetDetailState() {
    isDetailOpen.value = false
    activeRecord.value = null
    draftRecord.value = null
    activeNotes.value = []
    activeFiles.value = []
    activeCapturedImages.value = []
    activeParts.value = []
    activeStockedParts.value = []
    activeLoaners.value = []
    attachmentsLoading.value = false
    attachmentsError.value = ''
    detailLoading.value = false
    detailOpenError.value = ''
    saveError.value = ''
    isSavingRecord.value = false
    closeDialog()
}

async function closeDetail() {
    resetDetailState()
    // administrator: 詳細から一覧へ戻るたびに一覧を再取得
    if (props.mode === 'admin') {
        await reloadListRecords({ preserveState: true })
    }
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

function onStockedPartSelected(payload) {
    openDialog('STOCKED_PART_QTY', {
        mode: 'create',
        ...payload,
    })
}

async function onDialogSaved(result) {
    if (activeDialog.value === 'MASTER_SELECT' && result && draftRecord.value) {
        Object.assign(draftRecord.value, result)
        closeDialog()
        return
    }

    const isRemandNote = activeDialog.value === 'NOTE' && (dialogPayload.value?.remand || result?.remand)

    if (result && activeRecord.value) {
        Object.assign(activeRecord.value, result)
    }

    if (isRemandNote) {
        try {
            await updateActiveRecordStatus(40)
            closeDialog()
            await finishEngineerWorkflow()
        } catch (e) {
            saveError.value = e.message || '差戻処理に失敗しました。'
            if (activeRecord.value?.orderID) {
                await loadAttachments(activeRecord.value.orderID)
            }
            closeDialog()
        }
        return
    }

    if (activeRecord.value?.orderID) {
        await loadAttachments(activeRecord.value.orderID)
    }

    closeDialog()
}

async function updateActiveRecordStatus(status) {
    if (!activeRecord.value?.orderID) {
        throw new Error('案件が選択されていません。')
    }

    const url = `${window.location.origin}${getBasePath()}/${activeRecord.value.orderID}`
    const result = await apiFetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ status }),
    })

    if (!result?.response?.ok) {
        throw new Error(result?.data?.message || `status の更新に失敗しました。（HTTP ${result?.response?.status ?? ''}）`)
    }

    if (draftRecord.value) draftRecord.value.status = status
    activeRecord.value.status = status
    return result.data
}

async function onEngineerWorkflowDone() {
    await finishListWorkflow()
}

async function finishListWorkflow() {
    resetDetailState()
    await reloadListRecords({ preserveState: true })
}

async function finishEngineerWorkflow() {
    await finishListWorkflow()
}

function reloadListRecords(options = {}) {
    const {
        preserveState = false,
    } = options

    return new Promise((resolve) => {
        router.get(
            window.location.pathname,
            {},
            {
                only: ['initialRecords'],
                preserveState,
                preserveScroll: true,
                replace: true,
                onFinish: () => resolve(),
            },
        )
    })
}

function reloadEngineerList() {
    return reloadListRecords()
}

async function refreshLogisticsData() {
    if (!isBoardMode.value) return
    if (typeof document !== 'undefined' && document.hidden) return
    if (logisticsAutoRefreshing.value) return

    logisticsAutoRefreshing.value = true
    try {
        await reloadListRecords({ preserveState: true })
        await nextTick()
        logisticsCalendarRef.value?.refetchEvents?.()
    } finally {
        logisticsAutoRefreshing.value = false
    }
}

function startLogisticsAutoRefresh() {
    stopLogisticsAutoRefresh()
    logisticsAutoRefreshTimer.value = window.setInterval(() => {
        refreshLogisticsData()
    }, LOGISTICS_AUTO_REFRESH_MS)
}

function stopLogisticsAutoRefresh() {
    if (logisticsAutoRefreshTimer.value != null) {
        window.clearInterval(logisticsAutoRefreshTimer.value)
        logisticsAutoRefreshTimer.value = null
    }
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
                discount_service: draftRecord.value.discount_service,
                a2la: draftRecord.value.a2la,
                sentOut: draftRecord.value.sentOut,
                shipTo: draftRecord.value.shipTo,
                rmaNumOverSea: draftRecord.value.rmaNumOverSea,
                shippingOut_requiredDate: draftRecord.value.shippingOut_requiredDate,
                incident: draftRecord.value.incident,
                symptoms: draftRecord.value.symptoms,
                mapics_inv: draftRecord.value.mapics_inv,
                mapics47: draftRecord.value.mapics47,
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

        const previousReturnCode = activeRecord.value.returnCode
        Object.assign(activeRecord.value, draftRecord.value)
        if (activeRecord.value.order_type === 'loaner') {
            activeRecord.value.status_master_loaner = page.props.statusesLoaner?.find(
                status => String(status.processID_new) === String(draftRecord.value.status),
            ) ?? null
            activeRecord.value.status_master = null
        } else if (activeRecord.value.order_type === 'waiting_list') {
            activeRecord.value.status_master = null
            activeRecord.value.status_master_loaner = null
        } else {
            activeRecord.value.status_master = page.props.statuses?.find(
                status => String(status.processID_new) === String(draftRecord.value.status),
            ) ?? null
        }
        activeRecord.value.return_code_master = page.props.returnCodes?.find(code => String(code.id) === String(draftRecord.value.returnCode)) ?? null
        activeRecord.value.labor_master = page.props.labors?.find(labor => String(labor.laborID) === String(draftRecord.value.laborID)) ?? null
        activeRecord.value.serviceMaster = findServiceMaster(page.props.servicesMaster, {
            productName: draftRecord.value.productName,
            entityID: draftRecord.value.entityID,
            serviceID: draftRecord.value.serviceID,
        }, draftRecord.value.orderDate)

        // 作業内容(returnCode)変更時: 子 loaner の保存済み価格を反映
        if (Array.isArray(data.loaners) && data.loaners.length) {
            const priceByOrderId = new Map(
                data.loaners.map(item => [String(item.orderID), item]),
            )
            activeLoaners.value = activeLoaners.value.map((loaner) => {
                const updated = priceByOrderId.get(String(loaner.orderID))
                if (!updated) return loaner
                return {
                    ...loaner,
                    price: updated.price,
                    masterPrice: updated.masterPrice ?? loaner.masterPrice,
                }
            })
        } else if (String(draftRecord.value.returnCode ?? '') !== String(previousReturnCode ?? '')) {
            onReloadAttachments()
        }
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
    flex-wrap: wrap;
    gap: 8px;
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
    align-items: center;
    gap: 10px;
}

.mode-badge {
    padding: 6px 10px;
    border-radius: 6px;
    background: #0f766e;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
}

.logistics-view-controls {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
    padding: 0 8px;
}

.view-mode-btn {
    padding: 6px 10px;
    border: 1px solid #64748b;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.view-mode-btn.active {
    background: #0f766e;
    border-color: #0f766e;
    color: #fff;
}

.view-mode-btn.swap-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.home-link-area a {
    color: #1e3a8a;
    font-weight: 700;
    text-decoration: none;
}

.home-link-area a.calendar-link {
    padding: 6px 12px;
    border-radius: 6px;
    background: #2563eb;
    color: #fff;
}

.scrollable-table-zone {
    flex: 1;
    min-height: 0;
    padding-left: 10px;
    padding-right: 10px;
    overflow: auto;
    background: #e2e8f0;
}

.abroad-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0 10px;
    flex-shrink: 0;
}

.abroad-excel-btn {
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    background: #0f766e;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.abroad-excel-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.abroad-excel-message {
    font-size: 12px;
    font-weight: 700;
    color: #0f766e;
}

.abroad-preview-overlay {
    position: fixed;
    inset: 0;
    z-index: 260;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.55);
    box-sizing: border-box;
}

.abroad-preview-panel {
    width: min(1100px, 100%);
    max-height: min(85vh, 900px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.abroad-preview-panel-wide {
    width: min(1280px, 100%);
    max-height: min(92vh, 980px);
}

.abroad-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid #cbd5e1;
    background: #f8fafc;
}

.abroad-preview-header h2 {
    margin: 0 0 4px;
    font-size: 18px;
    color: #0f172a;
}

.abroad-preview-header p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    font-weight: 700;
}

.abroad-preview-close {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 6px;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.abroad-preview-body {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 12px 18px;
    background: #fff;
}

.abroad-preview-body-split {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.abroad-preview-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 0;
}

.abroad-preview-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.abroad-preview-section-head h3 {
    margin: 0;
    font-size: 14px;
    color: #0f172a;
}

.abroad-preview-table-wrap {
    max-height: 280px;
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

.abroad-preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    font-weight: 700;
}

.abroad-preview-table th,
.abroad-preview-table td {
    border: 1px solid #94a3b8;
    padding: 6px 8px;
    text-align: left;
    white-space: nowrap;
    vertical-align: middle;
}

.abroad-preview-table th {
    position: sticky;
    top: 0;
    background: #0f766e;
    color: #fff;
    z-index: 1;
}

.abroad-preview-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.abroad-cell-input {
    width: 100%;
    min-width: 88px;
    box-sizing: border-box;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 5px 6px;
    font-size: 12px;
    font-weight: 700;
    background: #fff;
}

.abroad-preview-empty {
    margin: 0;
    padding: 16px;
    border: 1px dashed #94a3b8;
    border-radius: 6px;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    background: #f8fafc;
}

.abroad-image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
}

.abroad-image-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px;
    background: #fff;
}

.abroad-image-card img {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: 4px;
    background: #e2e8f0;
}

.abroad-image-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 11px;
    color: #475569;
}

.abroad-image-meta strong {
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.abroad-image-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.abroad-preview-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-top: 1px solid #cbd5e1;
    background: #f8fafc;
}

.abroad-preview-btn {
    padding: 9px 14px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.abroad-preview-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.abroad-preview-btn-small {
    padding: 5px 8px;
    font-size: 12px;
}

.abroad-preview-btn-primary {
    border-color: #0f766e;
    background: #0f766e;
    color: #fff;
}

.abroad-preview-btn-danger {
    border-color: #dc2626;
    background: #dc2626;
    color: #fff;
}

.abroad-gallery-overlay {
    z-index: 270;
}

.abroad-gallery-panel {
    width: min(1200px, 100%);
    max-height: min(92vh, 980px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.abroad-gallery-body {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 12px 16px 16px;
}

.logistics-split {
    flex: 1;
    min-height: 0;
}

.logistics-split-pane {
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}

.logistics-pane-body {
    height: 100%;
}

.logistics-calendar-zone {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    background: #e2e8f0;
}

#myLargeTable {
    width: 100%;
    border-collapse: collapse;
    background: #f0f0f0;
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
    font-weight: 700;
}

#myLargeTable tbody td {
    background: #f5f5f5;
    font-weight: 700;
}

.table-row {
    cursor: pointer;
}

.active-row td {
    color: rgb(255, 255, 255) !important;
    background-color: #7e25eb !important;
}

#myLargeTable td.order-id-status-300 {
    text-decoration: underline;
    text-decoration-color: #facc15;
    text-decoration-thickness: 3px;
    text-underline-offset: 3px;
}

#myLargeTable td.order-id-status-385 {
    text-decoration: underline;
    text-decoration-color: #2563eb;
    text-decoration-thickness: 3px;
    text-underline-offset: 3px;
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