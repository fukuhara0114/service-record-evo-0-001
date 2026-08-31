<template>
    <div class="list-page-container list-page-scale-shell">
        <Head :title="documentTabTitle" />
        <div class="list-page-inner list-page-scale">
        <!-- 第1階層: 検索窓 -->
        <div class="fixed-header-zone">
            <div class="header-left">
                <span v-if="mode === 'engineer'" class="mode-badge">Engineer</span>
                <span v-else-if="mode === 'logistics'" class="mode-badge">Logistics (status=350)</span>
                <span v-else-if="mode === 'shippingPrep'" class="mode-badge">出荷準備 (status=300,310,350,385)</span>
                <div v-if="!isRestrictedListMode" class="order-type-filters">
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: orderTypeFilter === 'service' }"
                        @click="orderTypeFilter = 'service'"
                    >
                        service
                        <span
                            v-if="serviceRemandBadgeCount > 0"
                            class="order-type-badge"
                            :title="`差戻: ${serviceRemandBadgeCount}件`"
                        >{{ serviceRemandBadgeCount }}</span>
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: orderTypeFilter === 'tech_comp' }"
                        @click="orderTypeFilter = 'tech_comp'"
                    >
                        Tech Comp.
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
                        Invoice
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: orderTypeFilter === 'loaner' }"
                        @click="orderTypeFilter = 'loaner'"
                    >
                        loaner
                        <span
                            v-if="loanerReturnedBadgeCount > 0"
                            class="order-type-badge"
                            :title="`返却: ${loanerReturnedBadgeCount}件`"
                        >{{ loanerReturnedBadgeCount }}</span>
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: orderTypeFilter === 'waiting_list' }"
                        @click="orderTypeFilter = 'waiting_list'"
                    >
                        waiting
                        <span
                            v-if="waitingPromotionReadyBadgeCount > 0"
                            class="order-type-badge"
                            :title="`繰上可: ${waitingPromotionReadyBadgeCount}件`"
                        >{{ waitingPromotionReadyBadgeCount }}</span>
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
            </div>
            <!-- グループB: 件数 + 日付フィルタ + Quick Filter + Clear + RMA + Update SM -->
            <div class="header-center">
                <span class="filtered-count" aria-live="polite" title="Quick Filter を含む絞り込み後の件数">
                    {{ filteredRecords.length }}件
                </span>
                <div v-if="!isRestrictedListMode" class="arrival-date-filters">
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: effectiveArrivalFilter === 'all' }"
                        :disabled="!isArrivalFilterEnabled"
                        @click="arrivalFilter = 'all'"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: effectiveArrivalFilter === 'active' }"
                        :disabled="!isArrivalFilterEnabled"
                        @click="arrivalFilter = 'active'"
                        title="status が着荷(20)以上〜出荷準備完了 起伝依頼(300)未満"
                    >
                        Active
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: effectiveArrivalFilter === 'hide_future' }"
                        :disabled="!isArrivalFilterEnabled"
                        @click="arrivalFilter = 'hide_future'"
                    >
                        Hide Future
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: effectiveArrivalFilter === 'today' }"
                        :disabled="!isArrivalFilterEnabled"
                        @click="arrivalFilter = 'today'"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: effectiveArrivalFilter === '1day' }"
                        :disabled="!isArrivalFilterEnabled"
                        @click="arrivalFilter = '1day'"
                    >
                        1Day
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: effectiveArrivalFilter === '2day' }"
                        :disabled="!isArrivalFilterEnabled"
                        @click="arrivalFilter = '2day'"
                    >
                        2Day
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: effectiveArrivalFilter === '3day' }"
                        :disabled="!isArrivalFilterEnabled"
                        @click="arrivalFilter = '3day'"
                    >
                        3Day
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: effectiveArrivalFilter === '1wk' }"
                        :disabled="!isArrivalFilterEnabled"
                        @click="arrivalFilter = '1wk'"
                    >
                        1Wk
                    </button>
                </div>
                <div
                    v-if="mode === 'logistics' || mode === 'shippingPrep'"
                    class="order-type-filters logistics-shipping-date-filters"
                >
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: shippingDateFilter === 'all' }"
                        @click="shippingDateFilter = 'all'"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: shippingDateFilter === 'today' }"
                        @click="shippingDateFilter = 'today'"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        class="order-type-btn"
                        :class="{ active: shippingDateFilter === 'tomorrow' }"
                        @click="shippingDateFilter = 'tomorrow'"
                    >
                        Tomorrow
                    </button>
                </div>
                <div class="search-area">
                    <input
                        type="text"
                        id="customSearchInput"
                        v-model="searchQuery"
                        placeholder="Quick Filter : 複数キーワードはスペース区切り（例: sony 修理）"
                    >
                    <button type="button" @click="clearSearch">Clear</button>
                    <button
                        v-if="mode === 'logistics'"
                        type="button"
                        class="order-type-btn logistics-loaner-btn"
                        :class="{ active: logisticsLoanerFilter }"
                        title="order_type=loaner かつ 貸出中(388)"
                        @click="toggleLogisticsLoanerFilter"
                    >
                        Loaner
                        <span
                            v-if="logisticsLoanerLendingBadgeCount > 0"
                            class="order-type-badge"
                            :title="`貸出中: ${logisticsLoanerLendingBadgeCount}件`"
                        >{{ logisticsLoanerLendingBadgeCount }}</span>
                    </button>
                    <button
                        v-if="mode === 'engineer'"
                        type="button"
                        class="sm-mode-btn"
                        :class="{ active: engineerQuoteCoMode }"
                        @click="toggleEngineerQuoteCoMode"
                    >Quote/CO</button>
                    <button
                        v-if="mode === 'engineer'"
                        type="button"
                        class="sm-mode-btn sm-mode-btn-spaced sm-mode-btn-sm-submit"
                        :class="{ active: engineerSmSubmitMode }"
                        @click="toggleEngineerSmSubmitMode"
                    >SM Submit</button>
                    <button
                        v-if="mode === 'engineer'"
                        type="button"
                        class="sm-mode-btn sm-mode-btn-spaced sm-mode-btn-daily-report"
                        :class="{ active: engineerDailyReportMode }"
                        @click="toggleEngineerDailyReportMode"
                    >Daily Report</button>
                    <span
                        v-if="mode === 'engineer' && smQuoteCopyMessage"
                        class="sm-quote-copy-message"
                    >{{ smQuoteCopyMessage }}</span>
                    <button
                        v-if="!isRestrictedListMode"
                        type="button"
                        class="sm-mode-btn"
                        :class="{ active: orderTypeFilter === 'rma' }"
                        @click="orderTypeFilter = 'rma'"
                    >RMA</button>
                    <button
                        v-if="!isRestrictedListMode"
                        type="button"
                        class="sm-mode-btn"
                        :class="{ active: orderTypeFilter === 'update_sm' }"
                        @click="orderTypeFilter = 'update_sm'"
                    >Update SM</button>
                    <button
                        v-if="!isRestrictedListMode"
                        type="button"
                        class="sm-mode-btn sm-mode-btn-check"
                        @click="onCheckSmHeaderClick"
                    >Check SM</button>
                </div>
            </div>
            <div class="header-right">
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
                    <template v-if="mode === 'shippingPrep'">
                        <button
                            type="button"
                            class="abroad-excel-btn shipping-paste-btn"
                            :disabled="abroadSelectedCount === 0 || shippingExcelCopyBusy"
                            @click="copySelectedRowsForExcelPaste"
                        >
                            Excel用コピー{{ abroadSelectedCount > 0 ? ` (${abroadSelectedCount})` : '' }}
                        </button>
                        <span v-if="shippingExcelCopyMessage" class="abroad-excel-message">{{ shippingExcelCopyMessage }}</span>
                    </template>
                </div>
                <div class="home-link-area">
                    <a v-if="!isRestrictedListMode" :href="shippingCalendarUrl" class="calendar-link">出荷カレンダー</a>
                    <CloseToHomeButton :href="homeUrl" />
                </div>
            </div>
        </div>

        <!-- 第1階層: テーブル / Logistics・出荷準備はカレンダー併用 -->
        <template v-if="isBoardMode">
            <div v-if="logisticsViewMode === 'list'" class="scrollable-table-zone">
                <table id="myLargeTable">
                    <thead>
                        <tr v-if="isLogisticsLoanerList">
                            <SortableTh
                                sort-key="orderID"
                                :active-key="listColumnSortKey"
                                :direction="listColumnSortDir"
                                style="width: 80px; text-align: center;"
                                @sort="toggleColumnSort"
                            >OrderID</SortableTh>
                            <SortableTh sort-key="sentOut" class="logistics-loaner-col-100" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">出荷日</SortableTh>
                            <SortableTh sort-key="productName" class="logistics-loaner-col-200" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">製品名</SortableTh>
                            <SortableTh sort-key="item" class="logistics-loaner-col-200" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">item</SortableTh>
                            <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">SN</SortableTh>
                            <SortableTh sort-key="deliveryDestination_company" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">会社名</SortableTh>
                            <SortableTh sort-key="deliveryDestination_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                            <SortableTh sort-key="deliveryDestination_zipcode" class="logistics-loaner-col-100" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">〒</SortableTh>
                            <SortableTh sort-key="deliveryDestination_address1" class="logistics-loaner-col-100" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">都道府県</SortableTh>
                            <SortableTh sort-key="deliveryDestination_address2" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">住所</SortableTh>
                        </tr>
                        <tr v-else>
                            <SortableTh
                                sort-key="orderID"
                                :active-key="listColumnSortKey"
                                :direction="listColumnSortDir"
                                style="width: 80px; text-align: center;"
                                @sort="toggleColumnSort"
                            >OrderID</SortableTh>
                            <th v-if="mode === 'shippingPrep'" style="width: 44px; text-align: center;">
                                <input
                                    type="checkbox"
                                    :checked="abroadAllVisibleSelected"
                                    :indeterminate.prop="abroadSomeVisibleSelected && !abroadAllVisibleSelected"
                                    title="表示中を全選択"
                                    @click.stop
                                    @change="toggleAbroadSelectAll($event)"
                                >
                            </th>
                            <SortableTh sort-key="shippingOut_requiredDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">予定出荷日</SortableTh>
                            <SortableTh sort-key="status" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">ステータス</SortableTh>
                            <SortableTh sort-key="RMA" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">RMA#</SortableTh>
                            <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">製品名</SortableTh>
                            <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">S/N</SortableTh>
                            <SortableTh sort-key="returnCode" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業内容</SortableTh>
                            <SortableTh sort-key="laborName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                            <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">販売店</SortableTh>
                            <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                            <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                            <SortableTh sort-key="email" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Email</SortableTh>
                            <SortableTh sort-key="phone" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Phone</SortableTh>
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
                            <template v-if="isLogisticsLoanerList">
                                <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                                <td class="logistics-loaner-col-100">{{ formatListDate(r.sentOut) }}</td>
                                <td class="logistics-loaner-col-200">{{ r.productName }}</td>
                                <td class="logistics-loaner-col-200">{{ r.item || '' }}</td>
                                <td>{{ r.SN }}</td>
                                <td>{{ r.deliveryDestination_company }}</td>
                                <td>{{ r.deliveryDestination_depart }}</td>
                                <td class="logistics-loaner-col-100">{{ r.deliveryDestination_zipcode }}</td>
                                <td class="logistics-loaner-col-100">{{ r.deliveryDestination_address1 }}</td>
                                <td>{{ r.deliveryDestination_address2 }}</td>
                            </template>
                            <template v-else>
                            <td
                                style="text-align: center; font-weight: bold;"
                                :class="shippingStatusCellUnderlineClass(r)"
                            >{{ r.orderID }}</td>
                            <td
                                v-if="mode === 'shippingPrep'"
                                style="text-align: center;"
                                @click.stop
                                @dblclick.stop
                            >
                                <input
                                    type="checkbox"
                                    :checked="isAbroadSelected(r.orderID)"
                                    @change="toggleAbroadSelect(r.orderID, $event)"
                                >
                            </td>
                            <td>{{ formatListDate(r.shippingOut_requiredDate) }}</td>
                            <td :class="shippingStatusCellUnderlineClass(r)">{{ statusLabel(r) }}</td>
                            <td>
                                <span
                                    v-if="loanerCaseRmaBadgeKind(r) === 'loaner'"
                                    class="loaner-case-rma-badge loaner-case-rma-badge--loaner"
                                >貸出機案件</span>
                                <span
                                    v-else-if="loanerCaseRmaBadgeKind(r) === 'legacy'"
                                    class="loaner-case-rma-badge loaner-case-rma-badge--legacy"
                                >旧貸出機案件</span>
                                <template v-else>{{ r.RMA }}</template>
                            </td>
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
                    :color-by-status="mode !== 'shippingPrep'"
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
                                <tr v-if="isLogisticsLoanerList">
                                    <SortableTh
                                        sort-key="orderID"
                                        :active-key="listColumnSortKey"
                                        :direction="listColumnSortDir"
                                        style="width: 80px; text-align: center;"
                                        @sort="toggleColumnSort"
                                    >OrderID</SortableTh>
                                    <SortableTh sort-key="sentOut" class="logistics-loaner-col-100" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">出荷日</SortableTh>
                                    <SortableTh sort-key="productName" class="logistics-loaner-col-200" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">製品名</SortableTh>
                                    <SortableTh sort-key="item" class="logistics-loaner-col-200" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">item</SortableTh>
                                    <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">SN</SortableTh>
                                    <SortableTh sort-key="deliveryDestination_company" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">会社名</SortableTh>
                                    <SortableTh sort-key="deliveryDestination_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                                    <SortableTh sort-key="deliveryDestination_zipcode" class="logistics-loaner-col-100" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">〒</SortableTh>
                                    <SortableTh sort-key="deliveryDestination_address1" class="logistics-loaner-col-100" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">都道府県</SortableTh>
                                    <SortableTh sort-key="deliveryDestination_address2" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">住所</SortableTh>
                                </tr>
                                <tr v-else>
                                    <SortableTh
                                        sort-key="orderID"
                                        :active-key="listColumnSortKey"
                                        :direction="listColumnSortDir"
                                        style="width: 80px; text-align: center;"
                                        @sort="toggleColumnSort"
                                    >OrderID</SortableTh>
                                    <th v-if="mode === 'shippingPrep'" style="width: 44px; text-align: center;">
                                        <input
                                            type="checkbox"
                                            :checked="abroadAllVisibleSelected"
                                            :indeterminate.prop="abroadSomeVisibleSelected && !abroadAllVisibleSelected"
                                            title="表示中を全選択"
                                            @click.stop
                                            @change="toggleAbroadSelectAll($event)"
                                        >
                                    </th>
                                    <SortableTh sort-key="shippingOut_requiredDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">予定出荷日</SortableTh>
                                    <SortableTh sort-key="status" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">ステータス</SortableTh>
                                    <SortableTh sort-key="RMA" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">RMA#</SortableTh>
                                    <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">製品名</SortableTh>
                                    <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">S/N</SortableTh>
                                    <SortableTh sort-key="returnCode" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業内容</SortableTh>
                                    <SortableTh sort-key="laborName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                                    <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">販売店</SortableTh>
                                    <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                                    <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                                    <SortableTh sort-key="email" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Email</SortableTh>
                                    <SortableTh sort-key="phone" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Phone</SortableTh>
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
                                    <template v-if="isLogisticsLoanerList">
                                        <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                                        <td class="logistics-loaner-col-100">{{ formatListDate(r.sentOut) }}</td>
                                        <td class="logistics-loaner-col-200">{{ r.productName }}</td>
                                        <td class="logistics-loaner-col-200">{{ r.item || '' }}</td>
                                        <td>{{ r.SN }}</td>
                                        <td>{{ r.deliveryDestination_company }}</td>
                                        <td>{{ r.deliveryDestination_depart }}</td>
                                        <td class="logistics-loaner-col-100">{{ r.deliveryDestination_zipcode }}</td>
                                        <td class="logistics-loaner-col-100">{{ r.deliveryDestination_address1 }}</td>
                                        <td>{{ r.deliveryDestination_address2 }}</td>
                                    </template>
                                    <template v-else>
                                    <td
                                        style="text-align: center; font-weight: bold;"
                                        :class="shippingStatusCellUnderlineClass(r)"
                                    >{{ r.orderID }}</td>
                                    <td
                                        v-if="mode === 'shippingPrep'"
                                        style="text-align: center;"
                                        @click.stop
                                        @dblclick.stop
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="isAbroadSelected(r.orderID)"
                                            @change="toggleAbroadSelect(r.orderID, $event)"
                                        >
                                    </td>
                                    <td>{{ formatListDate(r.shippingOut_requiredDate) }}</td>
                                    <td :class="shippingStatusCellUnderlineClass(r)">{{ statusLabel(r) }}</td>
                                    <td>
                                        <span
                                            v-if="loanerCaseRmaBadgeKind(r) === 'loaner'"
                                            class="loaner-case-rma-badge loaner-case-rma-badge--loaner"
                                        >貸出機案件</span>
                                        <span
                                            v-else-if="loanerCaseRmaBadgeKind(r) === 'legacy'"
                                            class="loaner-case-rma-badge loaner-case-rma-badge--legacy"
                                        >旧貸出機案件</span>
                                        <template v-else>{{ r.RMA }}</template>
                                    </td>
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
                            :color-by-status="mode !== 'shippingPrep'"
                            @select-order="onLogisticsCalendarSelect"
                        />
                    </div>
                </Pane>
            </Splitpanes>
        </template>
        <div v-else class="scrollable-table-zone">
            <!-- abroad: Excel 作成画面 -->
            <div v-if="orderTypeFilter === 'abroad'" class="abroad-toolbar abroad-toolbar-sm">
                <div class="abroad-toolbar-main">
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
                <button
                    type="button"
                    class="order-type-btn abroad-overseas-rma-btn"
                    :class="{ active: abroadOverseasRmaFilter }"
                    title="海外RMA（rmaNumOverSea）= 123 の案件を抽出"
                    @click="toggleAbroadOverseasRmaFilter"
                >
                    海外RMA申請
                    <span
                        v-if="abroadOverseasRmaBadgeCount > 0"
                        class="order-type-badge"
                        :title="`海外RMA=123: ${abroadOverseasRmaBadgeCount}件`"
                    >{{ abroadOverseasRmaBadgeCount }}</span>
                </button>
            </div>
            <!-- Invoice: Excel 貼付用クリップボードコピー -->
            <div v-else-if="orderTypeFilter === 'invoice'" class="abroad-toolbar">
                <button
                    type="button"
                    class="abroad-excel-btn"
                    :disabled="abroadSelectedCount === 0 || shippingExcelCopyBusy"
                    @click="copySelectedRowsForExcelPaste"
                >
                    Excel用コピー{{ abroadSelectedCount > 0 ? ` (${abroadSelectedCount})` : '' }}
                </button>
                <span v-if="shippingExcelCopyMessage" class="abroad-excel-message">{{ shippingExcelCopyMessage }}</span>
            </div>
            <!-- Engineer: Daily Report -->
            <div v-if="mode === 'engineer' && engineerDailyReportMode" class="abroad-toolbar abroad-toolbar-sm">
                <div class="abroad-toolbar-main">
                    <button
                        type="button"
                        class="abroad-excel-btn"
                        :disabled="filteredRecords.length === 0"
                        @click="openDailyReportEmailPreview"
                    >
                        メール・プレビュー{{ dailyReportPreviewCount > 0 ? ` (${dailyReportPreviewCount})` : '' }}
                    </button>
                </div>
            </div>
            <!-- Engineer: Quote/CO → smsync -->
            <div v-else-if="mode === 'engineer' && engineerQuoteCoMode" class="abroad-toolbar abroad-toolbar-sm">
                <div class="abroad-toolbar-main">
                    <button
                        type="button"
                        class="abroad-excel-btn abroad-sync-sm-btn"
                        :disabled="engineerQuoteCoBusy"
                        @click="syncQuoteCoSelected"
                    >
                        Sync SM{{ abroadSelectedCount > 0 ? ` (${abroadSelectedCount})` : '' }}
                    </button>
                </div>
            </div>
            <!-- RMA / Update SM: Sync SM + Auto update -->
            <div v-else-if="isSmListMode" class="abroad-toolbar abroad-toolbar-sm">
                <div class="abroad-toolbar-main">
                    <button
                        type="button"
                        class="abroad-excel-btn abroad-sync-sm-btn"
                        :disabled="abroadSyncSmBusy"
                        @click="syncSmSelected"
                    >
                        Sync SM{{ abroadSelectedCount > 0 ? ` (${abroadSelectedCount})` : '' }}
                    </button>
                    <span v-if="abroadExcelMessage" class="abroad-excel-message">{{ abroadExcelMessage }}</span>
                </div>
                <label class="auto-update-toggle" :class="{ on: smListAutoUpdate }">
                    <span>Auto update</span>
                    <input
                        type="checkbox"
                        role="switch"
                        :checked="smListAutoUpdate"
                        @change="smListAutoUpdate = $event.target.checked"
                    >
                    <span class="auto-update-track" aria-hidden="true">
                        <span class="auto-update-thumb" />
                    </span>
                </label>
            </div>
            <table
                id="myLargeTable"
                :class="{
                    'daily-report-table': engineerDailyReportMode,
                    'quote-co-table': mode === 'engineer' && engineerQuoteCoMode,
                    'sm-submit-table': mode === 'engineer' && engineerSmSubmitMode,
                }"
            >
                <thead>
                    <tr v-if="mode === 'engineer' && engineerDailyReportMode">
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">orderID</SortableTh>
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
                        <SortableTh sort-key="receivedDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Date</SortableTh>
                        <SortableTh sort-key="RMA" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">RMA</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Product</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">SN</SortableTh>
                        <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">販売店</SortableTh>
                        <th>対応内容</th>
                        <th>Service Type</th>
                    </tr>
                    <tr v-else-if="mode === 'engineer'">
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">OrderID</SortableTh>
                        <th v-if="engineerQuoteCoLikeMode" style="width: 44px; text-align: center;">
                            <input
                                type="checkbox"
                                :checked="abroadAllVisibleSelected"
                                :indeterminate.prop="abroadSomeVisibleSelected && !abroadAllVisibleSelected"
                                title="表示中を全選択"
                                @click.stop
                                @change="toggleAbroadSelectAll($event)"
                            >
                        </th>
                        <SortableTh sort-key="receivedDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">受領日</SortableTh>
                        <SortableTh sort-key="order_type" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">order_type</SortableTh>
                        <SortableTh sort-key="status" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">ステータス</SortableTh>
                        <SortableTh sort-key="RMA" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">RMA</SortableTh>
                        <SortableTh sort-key="sm_workorder" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">WORKORDER</SortableTh>
                        <SortableTh sort-key="sm_quote" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">QUOTE</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">製品名</SortableTh>
                        <SortableTh sort-key="item" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">item</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">S/N</SortableTh>
                        <SortableTh sort-key="returnCode" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業内容</SortableTh>
                        <SortableTh sort-key="laborName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">販売店</SortableTh>
                        <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                        <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="email" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Email</SortableTh>
                        <SortableTh sort-key="phone" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Phone</SortableTh>
                    </tr>
                    <tr v-else-if="orderTypeFilter === 'abroad'">
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">OrderID</SortableTh>
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
                        <SortableTh sort-key="status" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">status</SortableTh>
                        <SortableTh sort-key="receivedDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">receivedDate</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">productName</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">SN</SortableTh>
                        <SortableTh sort-key="returnCode" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業内容</SortableTh>
                        <SortableTh sort-key="laborName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業担当</SortableTh>
                        <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">dealer</SortableTh>
                        <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                        <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="deliveryDestination_company" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">発送先</SortableTh>
                        <SortableTh sort-key="rmaNumOverSea" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">海外RMA</SortableTh>
                        <SortableTh sort-key="shippedDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">海外発送日</SortableTh>
                        <SortableTh sort-key="sentOut" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">sentOut</SortableTh>
                        <SortableTh sort-key="sm_workorder" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">sm_workorder</SortableTh>
                        <SortableTh sort-key="sm_quote" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">sm_quote</SortableTh>
                        <SortableTh sort-key="a2la" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">A2LA</SortableTh>
                        <SortableTh sort-key="symptoms" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">symptoms</SortableTh>
                    </tr>
                    <tr v-else-if="isSmListMode">
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">OrderID</SortableTh>
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
                        <SortableTh sort-key="RMA" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">RMA#</SortableTh>
                        <SortableTh sort-key="sm_workorder" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">sm_workorder</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">productName</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">SN</SortableTh>
                        <SortableTh sort-key="entityID" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">entityID</SortableTh>
                        <SortableTh sort-key="returnCode" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業内容</SortableTh>
                        <SortableTh sort-key="incident" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">incident</SortableTh>
                        <SortableTh sort-key="symptomsNum" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 96px; text-align: center;" @sort="toggleColumnSort">symptomsNum</SortableTh>
                        <SortableTh sort-key="symptoms" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">symptoms</SortableTh>
                    </tr>
                    <tr v-else-if="orderTypeFilter === 'loaner'">
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">orderID</SortableTh>
                        <SortableTh sort-key="parentID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">parentID</SortableTh>
                        <SortableTh sort-key="status" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">status</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">productName</SortableTh>
                        <SortableTh sort-key="item" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">item</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">SN</SortableTh>
                        <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">dealer</SortableTh>
                        <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">dealer_depart</SortableTh>
                        <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">contactPerson</SortableTh>
                        <SortableTh sort-key="shippingOut_requiredDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">shippingOut_requiredDate</SortableTh>
                        <SortableTh sort-key="shippedDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">shippedDate</SortableTh>
                    </tr>
                    <tr v-else-if="orderTypeFilter === 'waiting_list'">
                        <SortableTh sort-key="promotion_ready" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 88px; text-align: center;" @sort="toggleColumnSort">繰上</SortableTh>
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">orderID</SortableTh>
                        <SortableTh sort-key="parentID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">ParentID</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">ProductName</SortableTh>
                        <SortableTh sort-key="item" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">item</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">SN</SortableTh>
                        <SortableTh sort-key="enduser_SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">enduser_SN</SortableTh>
                        <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">dealer</SortableTh>
                        <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">dealer_depart</SortableTh>
                        <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">contactPerson</SortableTh>
                        <SortableTh sort-key="promotion_source_orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">返却元</SortableTh>
                    </tr>
                    <tr v-else-if="orderTypeFilter === 'invoice'">
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">OrderID</SortableTh>
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
                        <SortableTh sort-key="shippingOut_requiredDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">出荷予定日</SortableTh>
                        <SortableTh sort-key="status" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">ステータス</SortableTh>
                        <SortableTh sort-key="RMA" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">RMA#</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">製品名</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">S/N</SortableTh>
                        <SortableTh sort-key="returnCode" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業内容</SortableTh>
                        <SortableTh sort-key="laborName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">販売店</SortableTh>
                        <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                        <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="email" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Email</SortableTh>
                        <SortableTh sort-key="phone" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Phone</SortableTh>
                    </tr>
                    <tr v-else-if="orderTypeFilter === 'closing'">
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">OrderID</SortableTh>
                        <SortableTh sort-key="receivedDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">受領日</SortableTh>
                        <SortableTh sort-key="status" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">ステータス</SortableTh>
                        <SortableTh sort-key="RMA" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">RMA#</SortableTh>
                        <SortableTh sort-key="orderDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">受注日</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">製品名</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">S/N</SortableTh>
                        <SortableTh sort-key="returnCode" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業内容</SortableTh>
                        <SortableTh sort-key="laborName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">販売店</SortableTh>
                        <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                        <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="email" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Email</SortableTh>
                        <SortableTh sort-key="phone" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Phone</SortableTh>
                    </tr>
                    <tr v-else>
                        <SortableTh sort-key="orderID" :active-key="listColumnSortKey" :direction="listColumnSortDir" style="width: 80px; text-align: center;" @sort="toggleColumnSort">OrderID</SortableTh>
                        <SortableTh sort-key="receivedDate" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">受領日</SortableTh>
                        <SortableTh sort-key="status" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">ステータス</SortableTh>
                        <SortableTh sort-key="RMA" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">RMA#</SortableTh>
                        <SortableTh sort-key="productName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">製品名</SortableTh>
                        <SortableTh sort-key="SN" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">S/N</SortableTh>
                        <SortableTh sort-key="returnCode" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">作業内容</SortableTh>
                        <SortableTh sort-key="laborName" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="dealer" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">販売店</SortableTh>
                        <SortableTh sort-key="dealer_depart" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">部署</SortableTh>
                        <SortableTh sort-key="contactPerson" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">担当者</SortableTh>
                        <SortableTh sort-key="email" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Email</SortableTh>
                        <SortableTh sort-key="phone" :active-key="listColumnSortKey" :direction="listColumnSortDir" @sort="toggleColumnSort">Phone</SortableTh>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in filteredRecords"
                        :key="r.orderID"
                        class="table-row"
                        :class="{
                            'active-row': selectedOrderId === r.orderID,
                            'promotion-ready-row': isPromotionReady(r),
                        }"
                        :title="promotionRowTitle(r)"
                        @click="selectedOrderId = r.orderID"
                        @dblclick="onListRowDblClick(r)"
                    >
                        <template v-if="mode === 'engineer' && engineerDailyReportMode">
                            <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                            <td style="text-align: center;" @click.stop @dblclick.stop>
                                <input
                                    type="checkbox"
                                    :checked="isAbroadSelected(r.orderID)"
                                    @change="toggleAbroadSelect(r.orderID, $event)"
                                >
                            </td>
                            <td @click.stop @dblclick.stop>
                                <input
                                    type="date"
                                    class="daily-report-input daily-report-input-date"
                                    :value="toDateInputValue(dailyReportDraft(r).date)"
                                    @input="updateDailyReportDraft(r.orderID, 'date', $event.target.value)"
                                >
                            </td>
                            <td @click.stop @dblclick.stop>
                                <input
                                    type="text"
                                    class="daily-report-input"
                                    :value="dailyReportDraft(r).rma"
                                    @input="updateDailyReportDraft(r.orderID, 'rma', $event.target.value)"
                                >
                            </td>
                            <td @click.stop @dblclick.stop>
                                <input
                                    type="text"
                                    class="daily-report-input daily-report-input-product"
                                    :value="dailyReportDraft(r).product"
                                    @input="updateDailyReportDraft(r.orderID, 'product', $event.target.value)"
                                >
                            </td>
                            <td @click.stop @dblclick.stop>
                                <input
                                    type="text"
                                    class="daily-report-input daily-report-input-sn"
                                    :value="dailyReportDraft(r).sn"
                                    @input="updateDailyReportDraft(r.orderID, 'sn', $event.target.value)"
                                >
                            </td>
                            <td @click.stop @dblclick.stop>
                                <input
                                    type="text"
                                    class="daily-report-input"
                                    :value="dailyReportDraft(r).dealer"
                                    @input="updateDailyReportDraft(r.orderID, 'dealer', $event.target.value)"
                                >
                            </td>
                            <td @click.stop @dblclick.stop>
                                <input
                                    type="text"
                                    class="daily-report-input daily-report-input-response"
                                    :value="dailyReportDraft(r).response"
                                    @input="updateDailyReportDraft(r.orderID, 'response', $event.target.value)"
                                >
                            </td>
                            <td @click.stop @dblclick.stop>
                                <input
                                    type="text"
                                    class="daily-report-input daily-report-input-service-type"
                                    :value="dailyReportDraft(r).serviceType"
                                    @input="updateDailyReportDraft(r.orderID, 'serviceType', $event.target.value)"
                                >
                            </td>
                        </template>
                        <template v-else-if="mode === 'engineer'">
                            <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                            <td v-if="engineerQuoteCoLikeMode" style="text-align: center;" @click.stop @dblclick.stop>
                                <input
                                    type="checkbox"
                                    :checked="isAbroadSelected(r.orderID)"
                                    @change="toggleAbroadSelect(r.orderID, $event)"
                                >
                            </td>
                            <td>{{ r.receivedDate }}</td>
                            <td>{{ engineerOrderTypeLabel(r) }}</td>
                            <td>{{ statusLabel(r) }}</td>
                            <td>{{ r.RMA }}</td>
                            <td>{{ r.sm_workorder }}</td>
                            <td>{{ r.sm_quote }}</td>
                            <td>{{ r.productName }}</td>
                            <td>{{ r.item || '' }}</td>
                            <td>{{ r.SN }}</td>
                            <td>{{ r.return_code_master?.description || '' }}</td>
                            <td>{{ r.labor_master?.laborName || '' }}</td>
                            <td>{{ r.dealer }}</td>
                            <td>{{ r.dealer_depart }}</td>
                            <td>{{ r.contactPerson }}</td>
                            <td>{{ r.email }}</td>
                            <td>{{ r.phone }}</td>
                        </template>
                        <template v-else-if="orderTypeFilter === 'abroad'">
                            <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                            <td style="text-align: center;" @click.stop @dblclick.stop>
                                <input
                                    type="checkbox"
                                    :checked="isAbroadSelected(r.orderID)"
                                    @change="toggleAbroadSelect(r.orderID, $event)"
                                >
                            </td>
                            <td>{{ statusLabel(r) }}</td>
                            <td>{{ formatListDate(r.receivedDate) }}</td>
                            <td>{{ r.productName }}</td>
                            <td>{{ r.SN }}</td>
                            <td>{{ r.return_code_master?.description || '' }}</td>
                            <td>{{ r.labor_master?.laborName || '' }}</td>
                            <td>{{ r.dealer }}</td>
                            <td>{{ r.dealer_depart }}</td>
                            <td>{{ r.contactPerson }}</td>
                            <td>{{ r.deliveryDestination_company }}</td>
                            <td>{{ r.rmaNumOverSea }}</td>
                            <td>{{ formatListDate(r.shippedDate) }}</td>
                            <td>{{ formatListDate(r.sentOut) }}</td>
                            <td>{{ r.sm_workorder }}</td>
                            <td>{{ r.sm_quote }}</td>
                            <td>{{ abroadA2laLabel(r.a2la) }}</td>
                            <td>{{ r.symptoms }}</td>
                        </template>
                        <template v-else-if="isSmListMode">
                            <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                            <td style="text-align: center;" @click.stop @dblclick.stop>
                                <input
                                    type="checkbox"
                                    :checked="isAbroadSelected(r.orderID)"
                                    @change="toggleAbroadSelect(r.orderID, $event)"
                                >
                            </td>
                            <td>{{ r.RMA }}</td>
                            <td>{{ r.sm_workorder }}</td>
                            <td>{{ r.productName }}</td>
                            <td>{{ r.SN }}</td>
                            <td @click.stop @dblclick.stop>
                                <input
                                    type="text"
                                    class="entity-id-input"
                                    :value="listEntityId(r)"
                                    :disabled="entityIdSavingOrderId === r.orderID"
                                    @focus="onEntityIdFocus(r, $event)"
                                    @keydown.enter.prevent="$event.target.blur()"
                                    @blur="onEntityIdBlur(r, $event)"
                                >
                            </td>
                            <td>{{ smReturnCodeLabel(r.returnCode) || r.return_code_master?.description || '' }}</td>
                            <td>{{ r.incident }}</td>
                            <td style="text-align: center;">{{ symptomsNumForRecord(r) }}</td>
                            <td>{{ r.symptoms }}</td>
                        </template>
                        <template v-else-if="orderTypeFilter === 'loaner'">
                            <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                            <td style="text-align: center;">{{ r.parentID }}</td>
                            <td>
                                <span
                                    v-if="loanerStatusBadgeClass(r)"
                                    class="loaner-status-badge"
                                    :class="loanerStatusBadgeClass(r)"
                                >{{ statusLabel(r) }}</span>
                                <template v-else>{{ statusLabel(r) }}</template>
                            </td>
                            <td>{{ r.productName }}</td>
                            <td>{{ r.item || '' }}</td>
                            <td>{{ r.SN }}</td>
                            <td>{{ r.dealer }}</td>
                            <td>{{ r.dealer_depart }}</td>
                            <td>{{ r.contactPerson }}</td>
                            <td>{{ formatListDate(r.shippingOut_requiredDate) }}</td>
                            <td>{{ formatListDate(r.shippedDate) }}</td>
                        </template>
                        <template v-else-if="orderTypeFilter === 'waiting_list'">
                            <td style="text-align: center;">
                                <span
                                    v-if="isPromotionReady(r)"
                                    class="promotion-ready-badge"
                                    :title="promotionRowTitle(r)"
                                >繰上可</span>
                            </td>
                            <td style="text-align: center; font-weight: bold;">{{ r.orderID }}</td>
                            <td style="text-align: center;">{{ r.parentID }}</td>
                            <td>{{ r.productName }}</td>
                            <td>{{ r.item || '' }}</td>
                            <td>{{ r.SN }}</td>
                            <td>{{ r.enduser_SN || '' }}</td>
                            <td>{{ r.dealer }}</td>
                            <td>{{ r.dealer_depart }}</td>
                            <td>{{ r.contactPerson }}</td>
                            <td style="text-align: center;">{{ r.promotion_source_orderID || '—' }}</td>
                        </template>
                        <template v-else-if="orderTypeFilter === 'invoice'">
                            <td
                                style="text-align: center; font-weight: bold;"
                                :class="shippingStatusCellUnderlineClass(r)"
                            >{{ r.orderID }}</td>
                            <td style="text-align: center;" @click.stop @dblclick.stop>
                                <input
                                    type="checkbox"
                                    :checked="isAbroadSelected(r.orderID)"
                                    @change="toggleAbroadSelect(r.orderID, $event)"
                                >
                            </td>
                            <td>{{ formatListDate(r.shippingOut_requiredDate) }}</td>
                            <td :class="shippingStatusCellUnderlineClass(r)">{{ statusLabel(r) }}</td>
                            <td>
                                <span
                                    v-if="loanerCaseRmaBadgeKind(r) === 'loaner'"
                                    class="loaner-case-rma-badge loaner-case-rma-badge--loaner"
                                >貸出機案件</span>
                                <span
                                    v-else-if="loanerCaseRmaBadgeKind(r) === 'legacy'"
                                    class="loaner-case-rma-badge loaner-case-rma-badge--legacy"
                                >旧貸出機案件</span>
                                <template v-else>{{ r.RMA }}</template>
                            </td>
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
                        <template v-else-if="orderTypeFilter === 'closing'">
                            <td style="text-align: center; font-weight: bold;">
                                <span
                                    v-if="isRemandRecord(r)"
                                    class="remand-order-badge"
                                    title="差戻"
                                >{{ r.orderID }}</span>
                                <template v-else>{{ r.orderID }}</template>
                            </td>
                            <td>{{ formatListDate(r.receivedDate) }}</td>
                            <td>{{ statusLabel(r) }}</td>
                            <td>
                                <span
                                    v-if="loanerCaseRmaBadgeKind(r) === 'loaner'"
                                    class="loaner-case-rma-badge loaner-case-rma-badge--loaner"
                                >貸出機案件</span>
                                <span
                                    v-else-if="loanerCaseRmaBadgeKind(r) === 'legacy'"
                                    class="loaner-case-rma-badge loaner-case-rma-badge--legacy"
                                >旧貸出機案件</span>
                                <template v-else>{{ r.RMA }}</template>
                            </td>
                            <td>{{ formatListDate(r.orderDate) }}</td>
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
                        <template v-else>
                            <td style="text-align: center; font-weight: bold;">
                                <span
                                    v-if="isRemandRecord(r)"
                                    class="remand-order-badge"
                                    title="差戻"
                                >{{ r.orderID }}</span>
                                <template v-else>{{ r.orderID }}</template>
                            </td>
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
                                        <th
                                            v-for="(header, headerIndex) in abroadExcelHeaders"
                                            :key="header"
                                            :class="{ 'abroad-rma-header': headerIndex === ABROAD_RMA_HEADER_INDEX }"
                                        >{{ header }}</th>
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
                                                :readonly="cellIndex === ABROAD_RMA_HEADER_INDEX"
                                                :title="cellIndex === ABROAD_RMA_HEADER_INDEX ? 'RMA# は常に空欄です' : undefined"
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
            ref="detailShellRef"
            :record="activeRecord"
            :draft-record="draftRecord"
            :notes="activeNotes"
            :files="activeFiles"
            :captured-images="activeCapturedImages"
            :parts="activeParts"
            :stocked-parts="activeStockedParts"
            :loaners="activeLoaners"
            :attached-loaners="activeAttachedLoaners"
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
        <DailyReportEmailPreviewDialog
            v-if="showDailyReportEmailPreview"
            :rows="dailyReportPreviewRows"
            :subject="dailyReportEmailSubject"
            @close="showDailyReportEmailPreview = false"
        />
        <LogisticsLoanerLendingDialog
            v-if="logisticsLoanerDialogRecord"
            :record="logisticsLoanerDialogRecord"
            @close="closeLogisticsLoanerDialog"
            @returned="onLogisticsLoanerReturned"
        />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import ExcelJS from 'exceljs'
import { loanerStatusLabel } from '@/utils/loanerStatusLabel'
import { apiFetch } from '@/utils/apiFetch'
import { loanerDetailUrl } from '@/utils/serviceRecordPath'
import { applySensitivityLabel } from '@/utils/applySensitivityLabel'
import { findServiceMaster, normalizePriceAsOfDate, resolveDisplayPriceAsOfDate, parentOrderDateFromRecord, resolvePriceCardTotals } from '@/utils/resolveServiceWorkPrice'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import SortableTh from '@/components/SortableTh.vue'
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
import DailyReportEmailPreviewDialog from '@/components/ServiceRecord/Layer3/DailyReportEmailPreviewDialog.vue'
import ShippingOutDateDialog from '@/components/ServiceRecord/Layer3/ShippingOutDateDialog.vue'
import LogisticsLoanerLendingDialog from '@/components/ServiceRecord/Layer3/LogisticsLoanerLendingDialog.vue'
import HolidayJp from '@holiday-jp/holiday_jp'

const props = defineProps({
    initialRecords: Array,
    statuses: Array,
    statusesLoaner: Array,
    returnCodes: Array,
    labors: Array,
    mode: String,
    tabBadgeCounts: {
        type: Object,
        default: () => ({ loanerReturned: 0, waitingPromotionReady: 0, serviceRemand: 0, loanerLending: 0 }),
    },
})

const page = usePage()

const isBoardMode = computed(() => props.mode === 'logistics' || props.mode === 'shippingPrep')
const isRestrictedListMode = computed(() =>
    props.mode === 'engineer' || props.mode === 'logistics' || props.mode === 'shippingPrep',
)
const LOANER_LENDING_STATUS = 388
const logisticsLoanerFilter = ref(false)
const isLogisticsLoanerList = computed(() =>
    props.mode === 'logistics' && logisticsLoanerFilter.value,
)

const boardStatusFilter = computed(() => {
    if (props.mode === 'shippingPrep') return '300,310,350,385'
    if (props.mode === 'logistics') {
        return logisticsLoanerFilter.value ? String(LOANER_LENDING_STATUS) : '350'
    }
    return '300,350'
})

const loanerReturnedBadgeCount = computed(() =>
    Number(props.tabBadgeCounts?.loanerReturned ?? 0) || 0,
)
const waitingPromotionReadyBadgeCount = computed(() =>
    Number(props.tabBadgeCounts?.waitingPromotionReady ?? 0) || 0,
)
const serviceRemandBadgeCount = computed(() =>
    Number(props.tabBadgeCounts?.serviceRemand ?? 0) || 0,
)
const logisticsLoanerLendingBadgeCount = computed(() => {
    if (props.tabBadgeCounts && Object.prototype.hasOwnProperty.call(props.tabBadgeCounts, 'loanerLending')) {
        return Number(props.tabBadgeCounts.loanerLending) || 0
    }
    return (props.initialRecords ?? []).filter(isLogisticsLoanerLending).length
})

const currentUserKanji = computed(() => {
    const fromPage = String(page.props.authUser?.kanji_name ?? '').trim()
    if (fromPage) return fromPage
    if (typeof document !== 'undefined') {
        return String(document.querySelector('meta[name="auth-kanji-name"]')?.content ?? '').trim()
    }
    return ''
})
const currentUserSignature = computed(() => String(page.props.authUser?.signature ?? '').trim())
const currentUserEmployeeId = computed(() => {
    const raw = page.props.authUser?.EmployeeID
    if (raw == null || raw === '') return ''
    return String(raw).trim()
})
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const shippingCalendarUrl = computed(() => {
    const base = getBasePath()
    return `${window.location.origin}${base}/shipping-calendar`
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

    const openOrderID = resolveOpenOrderIdFromSearch(window.location.search)
    if (openOrderID) {
        try {
            const url = new URL(window.location.href)
            url.searchParams.delete('openOrderID')
            // メール化けした amp;openOrderID なども除去
            for (const key of [...url.searchParams.keys()]) {
                if (key !== 'openOrderID' && /(^|;)openOrderID$/i.test(key)) {
                    url.searchParams.delete(key)
                }
            }
            window.history.replaceState({}, '', url.href)
        } catch {
            // ignore
        }
        nextTick(async () => {
            try {
                const existing = (props.initialRecords ?? []).find(
                    (item) => String(item.orderID) === String(openOrderID),
                )
                if (existing) {
                    await openSecondLayer(existing)
                    return
                }
                const fullRecord = await fetchRecord(openOrderID)
                await openSecondLayer(fullRecord)
            } catch (e) {
                detailOpenError.value = e.message || '案件詳細の取得に失敗しました。'
            }
        })
    }
})

onUnmounted(() => {
    stopLogisticsAutoRefresh()
    stopSmListAutoRefresh()
    if (typeof document !== 'undefined') document.title = DEFAULT_DOCUMENT_TAB_TITLE
})

function resolveOpenOrderIdFromSearch(search) {
    try {
        const params = new URLSearchParams(search)
        const direct = params.get('openOrderID')?.trim()
        if (direct) return direct

        // メール等で & が &amp; になり amp;openOrderID になった場合
        for (const [key, value] of params.entries()) {
            const normalized = String(key).replace(/^amp;/i, '')
            if (normalized === 'openOrderID') {
                const trimmed = String(value ?? '').trim()
                if (trimmed) return trimmed
            }
        }
    } catch {
        // ignore
    }
    return null
}

// --- 第1階層 ---
const searchQuery = ref('')
const ORDER_TYPE_FILTERS = [
    'service',
    'tech_comp',
    'closing',
    'invoice',
    'loaner',
    'waiting_list',
    'abroad',
    'rma',
    'update_sm',
]
const ORDER_TYPE_FILTER_STORAGE_KEY = 'serviceRecordOrderTypeFilter'

function resolveInitialOrderTypeFilter() {
    if (typeof window !== 'undefined') {
        try {
            const fromQuery = new URLSearchParams(window.location.search).get('orderType')
            if (ORDER_TYPE_FILTERS.includes(fromQuery)) return fromQuery
        } catch {
            // ignore
        }
    }
    if (typeof sessionStorage !== 'undefined') {
        try {
            const stored = sessionStorage.getItem(ORDER_TYPE_FILTER_STORAGE_KEY)
            if (ORDER_TYPE_FILTERS.includes(stored)) return stored
        } catch {
            // ignore
        }
    }
    return 'service'
}

function persistOrderTypeFilter(value) {
    if (typeof sessionStorage === 'undefined') return
    try {
        sessionStorage.setItem(ORDER_TYPE_FILTER_STORAGE_KEY, value)
    } catch {
        // ignore
    }
}

function syncOrderTypeQuery(value) {
    if (typeof window === 'undefined' || !window.history?.replaceState) return
    try {
        const url = new URL(window.location.href)
        if (ORDER_TYPE_FILTERS.includes(value) && value !== 'service') {
            url.searchParams.set('orderType', value)
        } else {
            url.searchParams.delete('orderType')
        }
        window.history.replaceState({}, '', url.href)
    } catch {
        // ignore
    }
}

const orderTypeFilter = ref(resolveInitialOrderTypeFilter())
const isSmListMode = computed(() =>
    orderTypeFilter.value === 'rma' || orderTypeFilter.value === 'update_sm',
)

const DEFAULT_DOCUMENT_TAB_TITLE = 'ServiceRecord Evo'
const ORDER_TYPE_DOCUMENT_TAB_TITLES = {
    service: 'service',
    tech_comp: 'Tech Comp.',
    closing: 'closing',
    invoice: 'Invoice',
    loaner: 'loaner',
    waiting_list: 'waiting',
    abroad: 'abroad',
}

const documentTabTitle = computed(() => {
    if (isRestrictedListMode.value) return DEFAULT_DOCUMENT_TAB_TITLE
    return ORDER_TYPE_DOCUMENT_TAB_TITLES[orderTypeFilter.value] || DEFAULT_DOCUMENT_TAB_TITLE
})

watch(documentTabTitle, (title) => {
    if (typeof document !== 'undefined') document.title = title
}, { immediate: true })

const ARRIVAL_FILTER_STORAGE_KEY = 'serviceRecordArrivalFilter'
const ARRIVAL_FILTERS = ['all', 'active', 'hide_future', 'today', '1day', '2day', '3day', '1wk']

function loadArrivalFilter() {
    if (typeof window !== 'undefined') {
        try {
            const fromQuery = new URLSearchParams(window.location.search).get('arrival')
            if (ARRIVAL_FILTERS.includes(fromQuery)) return fromQuery
        } catch {
            // ignore
        }
    }
    if (typeof sessionStorage === 'undefined') return 'hide_future'
    try {
        const raw = sessionStorage.getItem(ARRIVAL_FILTER_STORAGE_KEY)
        if (ARRIVAL_FILTERS.includes(raw)) return raw
    } catch {
        // private mode 等は無視
    }
    return 'hide_future'
}

function saveArrivalFilter(value) {
    if (typeof sessionStorage === 'undefined') return
    try {
        sessionStorage.setItem(ARRIVAL_FILTER_STORAGE_KEY, value)
    } catch {
        // quota / private mode 等は無視
    }
}

function syncArrivalQuery(value) {
    if (typeof window === 'undefined' || !window.history?.replaceState) return
    try {
        const url = new URL(window.location.href)
        if (ARRIVAL_FILTERS.includes(value) && value !== 'hide_future') {
            url.searchParams.set('arrival', value)
        } else {
            url.searchParams.delete('arrival')
        }
        window.history.replaceState({}, '', url.href)
    } catch {
        // ignore
    }
}

const arrivalFilter = ref(loadArrivalFilter())
/** Logistics: 出荷予定日フィルタ（デフォルト Today）／Invoice: デフォルト All */
const shippingDateFilter = ref(props.mode === 'logistics' ? 'today' : 'all')
const isArrivalFilterEnabled = computed(() => orderTypeFilter.value === 'service')
const effectiveArrivalFilter = computed(() =>
    isArrivalFilterEnabled.value ? arrivalFilter.value : 'all',
)

// /home → Admin のクエリ指定、または詳細復帰時の URL を session に同期
persistOrderTypeFilter(orderTypeFilter.value)
saveArrivalFilter(arrivalFilter.value)
syncOrderTypeQuery(orderTypeFilter.value)
syncArrivalQuery(arrivalFilter.value)
const selectedOrderId = ref(null)
const abroadSelectedIds = ref(new Set())
const abroadOverseasRmaFilter = ref(false)
const abroadExcelMessage = ref('')
const abroadExcelPreviewOpen = ref(false)
const abroadGalleryPickerOpen = ref(false)
const abroadExcelPreviewRows = ref([])
const abroadAttachedImages = ref([])
const abroadExcelCreating = ref(false)
const abroadSyncSmBusy = ref(false)
const shippingExcelCopyBusy = ref(false)
const shippingExcelCopyMessage = ref('')
const entityIdSavingOrderId = ref(null)
const entityIdEditOriginal = new Map()
const smListAutoUpdate = ref(false)
const smListAutoRefreshTimer = ref(null)
const smListAutoRefreshing = ref(false)
const engineerQuoteCoMode = ref(false)
const engineerQuoteCoBusy = ref(false)
const engineerSmSubmitMode = ref(false)
const smQuoteCopyMessage = ref('')
let smQuoteCopyMessageTimer = null
const engineerQuoteCoLikeMode = computed(() =>
    engineerQuoteCoMode.value || engineerSmSubmitMode.value,
)
const engineerDailyReportMode = ref(false)
const engineerDailyReportDrafts = ref({})
const showDailyReportEmailPreview = ref(false)
const dailyReportForceTodayOnOpen = ref(false)
const SM_LIST_AUTO_REFRESH_MS = 60 * 1000
/** 詳細オープン直後の誤 close（dblclick の残存 click / Inertia 競合）を無視する */
const detailCloseGuardUntil = ref(0)
const CALLER_RETURN_URL_KEY = 'sr_list_return_url'

function sanitizeSameOriginUrl(raw) {
    if (!raw) return null
    try {
        const url = new URL(raw, typeof window !== 'undefined' ? window.location.origin : undefined)
        if (typeof window !== 'undefined' && url.origin !== window.location.origin) return null
        return url.href
    } catch {
        return null
    }
}

function resolveCallerReturnUrl() {
    if (typeof window === 'undefined') return null
    let fromQuery = null
    let hasOpenOrderID = false
    try {
        const params = new URLSearchParams(window.location.search)
        fromQuery = sanitizeSameOriginUrl(params.get('returnUrl'))
        hasOpenOrderID = Boolean(resolveOpenOrderIdFromSearch(window.location.search))
    } catch {
        // ignore
    }

    if (fromQuery) {
        try {
            sessionStorage.setItem(CALLER_RETURN_URL_KEY, fromQuery)
        } catch {
            // ignore
        }
        return fromQuery
    }

    // openOrderID 付きで来た場合のみ sessionStorage を信頼（通常 Admin に古い復帰先を残さない）
    if (hasOpenOrderID) {
        try {
            return sanitizeSameOriginUrl(sessionStorage.getItem(CALLER_RETURN_URL_KEY))
        } catch {
            return null
        }
    }

    try {
        sessionStorage.removeItem(CALLER_RETURN_URL_KEY)
    } catch {
        // ignore
    }
    return null
}

const callerReturnUrl = ref(resolveCallerReturnUrl())
const abroadExcelHeaders = [
    'Product Name',
    'S/N',
    'PO#',
    'RMA#',
    'Request Type (Repair/Recertification)',
    'ISO/A2LA Required ?',
    'Pre-Test Results Required ?',
    'Post-Test Results Required ?',
    'Customer Failure Description',
    'Other requests, etc.',
]
const ABROAD_RMA_HEADER_INDEX = 3
const ABROAD_PRETEST_HEADER_INDEX = 6
const ABROAD_POSTTEST_HEADER_INDEX = 7

/** symptomsNum: returnCode が 1 のとき 3、それ以外は 0 */
function symptomsNumForRecord(record) {
    return Number(record?.returnCode) === 1 ? 3 : 0
}

/** 旧システムと同様: servicerecord.returnCode → SM 連携用 returnCode 文字列 */
function smReturnCodeValue(returnCode) {
    const code = Number(returnCode)
    if ([1, 5].includes(code)) return 'CERTIFICATION'
    if ([2, 4].includes(code)) return 'FLAT RATE REPAIR'
    if (code === 3) return 'WARRANTY REPAIR'
    if (code === 12) return 'FIELD SERVICE REGIONAL'
    return ''
}

function smReturnCodeLabel(returnCode) {
    return smReturnCodeValue(returnCode)
}

function quoteCoWarrantyPeriod(dealer) {
    return String(dealer ?? '').includes('小森コーポレーション') ? '6' : '3'
}

function matchesEngineerQuoteCoSmQuote(record) {
    const smQuoteRaw = record?.sm_quote
    if (smQuoteRaw === null || smQuoteRaw === undefined) return true
    const smQuote = Number(smQuoteRaw)
    return Number.isFinite(smQuote) && smQuote < 100000
}

function matchesEngineerListStatus(record) {
    const orderType = record?.order_type ?? 'service'
    const status = Number(record?.status)
    if (orderType === 'service' || orderType === '' || orderType == null) {
        return Number.isFinite(status) && status >= 90 && status <= 185
    }
    if (orderType === 'loaner') {
        return status === 396
    }
    return false
}

function matchesDailyReportStatus(record) {
    const orderType = record?.order_type ?? 'service'
    const status = Number(record?.status)
    return (orderType === 'service' || orderType === '' || orderType == null)
        && (status === 90 || status === 180 || status === 185)
}

function listEntityId(record) {
    const stored = String(record?.entityID ?? '').trim()
    if (stored !== '') return stored

    const service = findServiceMaster(page.props.servicesMaster, {
        productName: record?.productName,
        serviceID: record?.serviceID,
        entityID: record?.entityID,
    }, normalizePriceAsOfDate(record?.orderDate))
    return String(service?.entityID ?? '').trim()
}

function onEntityIdFocus(record, event) {
    entityIdEditOriginal.set(record.orderID, String(event.target.value ?? ''))
}

async function onEntityIdBlur(record, event) {
    const next = String(event.target.value ?? '')
    const prev = String(entityIdEditOriginal.get(record.orderID) ?? record.entityID ?? '')
    entityIdEditOriginal.delete(record.orderID)
    record.entityID = next
    if (next === prev || entityIdSavingOrderId.value === record.orderID) return

    entityIdSavingOrderId.value = record.orderID
    abroadExcelMessage.value = ''
    const url = `${window.location.origin}${getBasePath()}/${record.orderID}`
    try {
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ entityID: next }),
        })
        if (!result) {
            record.entityID = prev
            event.target.value = prev
            return
        }
        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `entityID の保存に失敗しました。（HTTP ${response.status}）`)
        }
        if (activeRecord.value?.orderID === record.orderID) {
            activeRecord.value.entityID = next
            if (draftRecord.value) draftRecord.value.entityID = next
        }
    } catch (e) {
        record.entityID = prev
        event.target.value = prev
        abroadExcelMessage.value = e.message || 'entityID の保存に失敗しました。'
    } finally {
        entityIdSavingOrderId.value = null
    }
}

function exportIncidentParamJson(theUserNameKanji, smMode = 'rma_wo') {
    console.log('commonScript::exportIncidentParamJson was called')

    const rows = filteredRecords.value
    if (!rows.length) {
        alert('データテーブルが表示されていません。')
        return
    }

    const jsonData = []
    for (const r of rows) {
        if (!isAbroadSelected(r.orderID)) continue

        const orderID = String(r.orderID ?? '').trim()
        const incident = String(r.incident ?? '').trim()
        const entityID = listEntityId(r)
        const sn = String(r.SN ?? '').trim()
        const symptoms = String(r.symptoms ?? '').trim()
        const poNum = String(r.poNum ?? r.RMA ?? '').trim()
        const symptomNum = String(symptomsNumForRecord(r) ?? '').trim()
        const returnCode = smReturnCodeValue(r.returnCode)

        if (!entityID || !symptoms || !symptomNum || !returnCode || !incident) {
            alert(`選択された行（OrderID: ${orderID || '不明'}）に入力漏れの項目があります。すべての項目を入力してください。`)
            return
        }

        if (entityID.includes(',') || entityID.includes('，') || entityID.includes('、')) {
            alert('entityIDがカンマ区切りで複数含まれている案件があります,何れかを選択して下さい')
            return
        }

        jsonData.push({
            orderID,
            incident,
            entityID,
            SN: sn,
            symptomNum,
            returnCode,
            symptoms,
            poNum,
        })
    }

    if (jsonData.length === 0) {
        alert('「Sel」列にチェックが入っている行がありません。出力したいデータの「Sel」にチェックを入れてください。')
        return
    }

    const finalOutput = {
        sm_mode: smMode,
        who_exported: theUserNameKanji,
        param: jsonData,
    }

    const encodedJson = encodeURIComponent(JSON.stringify(finalOutput, null, 2))
    window.location.href = `smsync://action?json=${encodedJson}`
}

function exportUpdatePoParamJson(theUserNameKanji, smMode = 'update_po') {
    console.log('commonScript::exportUpdatePoParamJson was called')

    const rows = filteredRecords.value
    if (!rows.length) {
        alert('データテーブルが表示されていません。')
        return
    }

    const jsonData = []
    for (const r of rows) {
        if (!isAbroadSelected(r.orderID)) continue

        const orderID = String(r.orderID ?? '').trim()
        const entityID = listEntityId(r)
        const sn = String(r.SN ?? '').trim()
        const RMA = String(r.RMA ?? '').trim()
        const WO = String(r.sm_workorder ?? '').trim()
        const symptoms = String(r.symptoms ?? '').trim()
        const poNum = String(r.poNum ?? '').trim()
        const symptomNum = String(symptomsNumForRecord(r) ?? '').trim()
        const returnCode = smReturnCodeValue(r.returnCode)

        if (!entityID || !symptoms || !symptomNum || !returnCode || !RMA || !WO || !poNum) {
            alert(`選択された行（OrderID: ${orderID || '不明'}）に入力漏れの項目があります。すべての項目を入力してください。`)
            console.log('symptoms : ' + symptoms)
            console.log('symptomNum : ' + symptomNum)
            console.log('RMA : ' + RMA)
            console.log('WO : ' + WO)
            console.log('returnCode : ' + returnCode)
            console.log('poNum : ' + poNum)
            return
        }

        if (entityID.includes(',') || entityID.includes('，') || entityID.includes('、')) {
            alert('entityIDがカンマ区切りで複数含まれている案件があります,何れかを選択して下さい')
            return
        }

        jsonData.push({
            orderID,
            entityID,
            SN: sn,
            RMA,
            WO,
            symptomNum,
            returnCode,
            symptoms,
            poNum,
        })
    }

    if (jsonData.length === 0) {
        alert('「Sel」列にチェックが入っている行がありません。出力したいデータの「Sel」にチェックを入れてください。')
        return
    }

    const finalOutput = {
        sm_mode: smMode,
        who_exported: theUserNameKanji,
        param: jsonData,
    }

    const encodedJson = encodeURIComponent(JSON.stringify(finalOutput, null, 2))
    window.location.href = `smsync://action?json=${encodedJson}`
}

function syncSmSelected() {
    if (abroadSyncSmBusy.value) return
    const userName = currentUserKanji.value
    if (orderTypeFilter.value === 'update_sm') {
        exportUpdatePoParamJson(userName)
        return
    }
    exportIncidentParamJson(userName)
}

function onCheckSmHeaderClick() {
    // Check SM の動作は別途指定予定
}

function toggleEngineerQuoteCoMode() {
    engineerQuoteCoMode.value = !engineerQuoteCoMode.value
    if (engineerQuoteCoMode.value) {
        engineerSmSubmitMode.value = false
        engineerDailyReportMode.value = false
        showDailyReportEmailPreview.value = false
        abroadSelectedIds.value = new Set()
    } else {
        clearAbroadSelection()
    }
}

function toggleEngineerSmSubmitMode() {
    engineerSmSubmitMode.value = !engineerSmSubmitMode.value
    if (engineerSmSubmitMode.value) {
        engineerQuoteCoMode.value = false
        engineerDailyReportMode.value = false
        showDailyReportEmailPreview.value = false
        abroadSelectedIds.value = new Set()
        clearAbroadSelection()
    } else {
        clearAbroadSelection()
    }
}

function toDateInputValue(value) {
    if (value == null || value === '') return ''
    const ymd = formatListDate(value).replace(/\./g, '-')
    return /^\d{4}-\d{2}-\d{2}$/.test(ymd) ? ymd : ''
}

function formatDailyReportDateDisplay(value) {
    const ymd = toDateInputValue(value)
    return ymd ? ymd.replace(/-/g, '.') : ''
}

function formatDailyReportResponse(value) {
    const text = String(value ?? '').trim()
    if (!text) return ''
    if (text.includes('再校正')) return 'Certification'
    if (text.includes('修理')) return 'Repair'
    return text
}

function buildDailyReportDraft(record) {
    return {
        date: tokyoTodayYmd(),
        rma: String(record?.RMA ?? ''),
        product: String(record?.productName ?? ''),
        sn: String(record?.SN ?? ''),
        dealer: String(record?.dealer ?? ''),
        response: formatDailyReportResponse(record?.return_code_master?.description || record?.symptoms || ''),
        serviceType: '',
    }
}

function dailyReportDraft(record) {
    const id = String(record?.orderID ?? '')
    return engineerDailyReportDrafts.value[id] ?? buildDailyReportDraft(record)
}

function updateDailyReportDraft(orderID, field, value) {
    const id = String(orderID ?? '')
    if (!id) return
    const record = (filteredRecords.value ?? []).find((r) => String(r.orderID) === id)
    const current = engineerDailyReportDrafts.value[id] ?? buildDailyReportDraft(record ?? { orderID })
    engineerDailyReportDrafts.value = {
        ...engineerDailyReportDrafts.value,
        [id]: { ...current, [field]: value },
    }
}

function seedDailyReportDrafts(records, { forceToday = false } = {}) {
    const today = tokyoTodayYmd()
    const next = { ...engineerDailyReportDrafts.value }
    let changed = false
    for (const record of records ?? []) {
        const id = String(record?.orderID ?? '')
        if (!id) continue
        if (!next[id]) {
            next[id] = buildDailyReportDraft(record)
            changed = true
            continue
        }
        if (forceToday && next[id].date !== today) {
            next[id] = { ...next[id], date: today }
            changed = true
        }
    }
    if (changed) {
        engineerDailyReportDrafts.value = next
    }
}

function toggleEngineerDailyReportMode() {
    engineerDailyReportMode.value = !engineerDailyReportMode.value
    if (engineerDailyReportMode.value) {
        engineerQuoteCoMode.value = false
        engineerSmSubmitMode.value = false
        abroadSelectedIds.value = new Set()
        showDailyReportEmailPreview.value = false
        dailyReportForceTodayOnOpen.value = true
    } else {
        clearAbroadSelection()
        showDailyReportEmailPreview.value = false
        dailyReportForceTodayOnOpen.value = false
    }
}

async function copySmQuoteFromRecord(record) {
    const text = String(record?.sm_quote ?? '').trim()
    if (!text) {
        smQuoteCopyMessage.value = 'sm_quote が空です'
        if (smQuoteCopyMessageTimer) clearTimeout(smQuoteCopyMessageTimer)
        smQuoteCopyMessageTimer = setTimeout(() => {
            smQuoteCopyMessage.value = ''
        }, 2000)
        return
    }
    try {
        await writeTextToClipboard(text)
        smQuoteCopyMessage.value = `sm_quote をコピーしました: ${text}`
    } catch {
        smQuoteCopyMessage.value = 'コピーに失敗しました'
    }
    if (smQuoteCopyMessageTimer) clearTimeout(smQuoteCopyMessageTimer)
    smQuoteCopyMessageTimer = setTimeout(() => {
        smQuoteCopyMessage.value = ''
    }, 2000)
}

function onListRowDblClick(record) {
    if (engineerDailyReportMode.value) return
    if (engineerSmSubmitMode.value) {
        copySmQuoteFromRecord(record)
        return
    }
    openSecondLayer(record)
}

function openDailyReportEmailPreview() {
    if (!filteredRecords.value.length) return
    showDailyReportEmailPreview.value = true
}

function quoteCoStockedPartsFromAttachment(stockedParts) {
    return (stockedParts ?? []).map((part) => ({
        partname: String(part.stocked_part_master?.partName ?? '').trim(),
        quantity: String(Number(part.quantity ?? 0)),
    }))
}

async function fetchStockedPartsForQuoteCo(orderID) {
    const url = `${window.location.origin}${getBasePath()}/attachments/${orderID}`
    const result = await apiFetch(url)
    if (!result) {
        throw new Error(`OrderID ${orderID}: stocked Parts の取得に失敗しました。`)
    }
    const { response, data } = result
    if (!response.ok) {
        throw new Error(data?.message || `OrderID ${orderID}: stocked Parts の取得に失敗しました。（HTTP ${response.status}）`)
    }
    if (data?.error) {
        throw new Error(`OrderID ${orderID}: ${data.error}`)
    }
    return data?.stockedParts ?? []
}

async function exportQuoteCoParamJson(theUserNameKanji, smMode = 'quote_co') {
    console.log('commonScript::exportQuoteCoParamJson was called')

    const rows = filteredRecords.value
    if (!rows.length) {
        alert('データテーブルが表示されていません。')
        return
    }

    const selectedRows = rows.filter((r) => isAbroadSelected(r.orderID))
    if (selectedRows.length === 0) {
        alert('「Sel」列にチェックが入っている行がありません。出力したいデータの「Sel」にチェックを入れてください。')
        return
    }

    const jsonData = []
    for (const r of selectedRows) {
        const orderID = String(r.orderID ?? '').trim()
        const sm_workorder = String(r.sm_workorder ?? '').trim()
        const entityID = listEntityId(r)
        const sn = String(r.SN ?? '').trim()

        let stockedParts = []
        try {
            stockedParts = await fetchStockedPartsForQuoteCo(orderID)
        } catch (e) {
            alert(e.message || `OrderID ${orderID || '不明'}: stocked Parts の取得に失敗しました。`)
            return
        }

        jsonData.push({
            orderid: orderID,
            signature: currentUserSignature.value,
            employeeid: currentUserEmployeeId.value,
            sm_workorder,
            entityid: entityID,
            sn,
            returncode: smReturnCodeValue(r.returnCode),
            warranty_period: quoteCoWarrantyPeriod(r.dealer),
            price: String(r.price ?? '').trim(),
            ponum: String(r.poNum ?? '').trim(),
            stockedparts: quoteCoStockedPartsFromAttachment(stockedParts),
        })
    }

    const finalOutput = {
        sm_mode: smMode,
        who_exported: theUserNameKanji,
        param: jsonData,
    }

    const encodedJson = encodeURIComponent(JSON.stringify(finalOutput, null, 2))
    window.location.href = `smsync://action?json=${encodedJson}`
}

async function syncQuoteCoSelected() {
    if (engineerQuoteCoBusy.value) return
    engineerQuoteCoBusy.value = true
    try {
        await exportQuoteCoParamJson(currentUserKanji.value, 'quote_co')
    } finally {
        engineerQuoteCoBusy.value = false
    }
}

async function refreshSmListData() {
    if (!isSmListMode.value || !smListAutoUpdate.value) return
    if (typeof document !== 'undefined' && document.hidden) return
    if (smListAutoRefreshing.value) return
    // 詳細表示中 / オープン直後は一覧リロードしない（Inertia 競合で詳細が閉じるのを防ぐ）
    if (isDetailOpen.value || Date.now() < detailCloseGuardUntil.value) return

    smListAutoRefreshing.value = true
    try {
        await reloadListRecords({ preserveState: true })
        // リロード中に詳細が開いた場合は以降の処理を打ち切る
        if (isDetailOpen.value) return
    } finally {
        smListAutoRefreshing.value = false
    }
}

function startSmListAutoRefresh() {
    stopSmListAutoRefresh()
    if (!smListAutoUpdate.value || !isSmListMode.value) return
    smListAutoRefreshTimer.value = window.setInterval(() => {
        refreshSmListData()
    }, SM_LIST_AUTO_REFRESH_MS)
}

function stopSmListAutoRefresh() {
    if (smListAutoRefreshTimer.value != null) {
        window.clearInterval(smListAutoRefreshTimer.value)
        smListAutoRefreshTimer.value = null
    }
}
// Home→Logistics / 出荷準備 遷移時は「カレンダーのみ / 日 / 今日」を初期表示
// 自動更新等でコンポーネントが再マウントされても、選択済みの表示方法を維持する
const BOARD_VIEW_STORAGE_PREFIX = 'serviceRecord.boardView.'
const BOARD_VIEW_MODES = ['list', 'both', 'calendar']

function boardViewStorageKey(mode) {
    return `${BOARD_VIEW_STORAGE_PREFIX}${mode}`
}

function loadBoardViewPrefs(mode) {
    if (typeof sessionStorage === 'undefined' || !mode) return null
    try {
        const raw = sessionStorage.getItem(boardViewStorageKey(mode))
        if (!raw) return null
        const parsed = JSON.parse(raw)
        if (!parsed || typeof parsed !== 'object') return null
        const viewMode = BOARD_VIEW_MODES.includes(parsed.viewMode) ? parsed.viewMode : null
        const calendarOnLeft = typeof parsed.calendarOnLeft === 'boolean' ? parsed.calendarOnLeft : null
        if (!viewMode && calendarOnLeft === null) return null
        return { viewMode, calendarOnLeft }
    } catch {
        return null
    }
}

function saveBoardViewPrefs(mode, viewMode, calendarOnLeft) {
    if (typeof sessionStorage === 'undefined' || !mode) return
    try {
        sessionStorage.setItem(
            boardViewStorageKey(mode),
            JSON.stringify({ viewMode, calendarOnLeft }),
        )
    } catch {
        // quota / private mode 等は無視
    }
}

const savedBoardView = isBoardMode.value ? loadBoardViewPrefs(props.mode) : null
const logisticsViewMode = ref(
    savedBoardView?.viewMode ?? (isBoardMode.value ? 'list' : 'both'),
) // list | both | calendar — Logistics / 出荷準備のデフォルトは一覧のみ
const logisticsCalendarOnLeft = ref(savedBoardView?.calendarOnLeft ?? false)
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

function shippingStatusCellUnderlineClass(record) {
    const inShippingPrepOrInvoice = props.mode === 'shippingPrep'
        || props.mode === 'logistics'
        || (!isBoardMode.value && orderTypeFilter.value === 'invoice')
    if (!inShippingPrepOrInvoice) return ''

    const status = Number(record?.status)
    if (status === 350) return 'status-cell-underline-350'
    if (status === 385) return 'status-cell-underline-385'
    return ''
}

function onLogisticsSplitResized() {
    nextTick(() => {
        window.dispatchEvent(new Event('resize'))
    })
}

watch([logisticsViewMode, logisticsCalendarOnLeft], () => {
    if (isBoardMode.value) {
        saveBoardViewPrefs(props.mode, logisticsViewMode.value, logisticsCalendarOnLeft.value)
    }
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

function tokyoTodayYmd() {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date())
}

function addDaysYmd(ymd, days) {
    const [y, m, d] = ymd.split('-').map(Number)
    const utc = new Date(Date.UTC(y, m - 1, d + days))
    const pad = (n) => String(n).padStart(2, '0')
    return `${utc.getUTCFullYear()}-${pad(utc.getUTCMonth() + 1)}-${pad(utc.getUTCDate())}`
}

function isNonBusinessDayYmd(ymd) {
    const [y, m, d] = ymd.split('-').map(Number)
    const date = new Date(y, m - 1, d, 12, 0, 0)
    const day = date.getDay()
    if (day === 0 || day === 6) return true
    return HolidayJp.isHoliday(date)
}

/** 翌日以降の最初の営業日（土日・日本の祝日を除外） */
function nextBusinessDayYmd(fromYmd = tokyoTodayYmd()) {
    let ymd = addDaysYmd(fromYmd, 1)
    for (let i = 0; i < 14; i++) {
        if (!isNonBusinessDayYmd(ymd)) return ymd
        ymd = addDaysYmd(ymd, 1)
    }
    return ymd
}

function isLogisticsLoanerLending(record) {
    return (record?.order_type === 'loaner') && Number(record?.status) === LOANER_LENDING_STATUS
}

/** Invoice / Closing / Logistics / 出荷準備 の RMA# バッジ対象か */
function isLoanerCaseRmaBadgeTarget() {
    return props.mode === 'logistics'
        || props.mode === 'shippingPrep'
        || orderTypeFilter.value === 'invoice'
        || orderTypeFilter.value === 'closing'
}

/** RMA が数字のみか（前後空白を除く） */
function isNumericRma(rma) {
    return /^\d+$/.test(String(rma ?? '').trim())
}

/**
 * RMA# 列の「貸出機案件」バッジ種別。
 * - loaner → 'loaner'（赤背景・白文字「貸出機案件」）
 * - 非 loaner かつ RMA が数字でない → 'legacy'（緑背景・白文字「旧貸出機案件」）
 * - それ以外 → null（通常の RMA 表示）
 */
function loanerCaseRmaBadgeKind(record) {
    if (!isLoanerCaseRmaBadgeTarget()) return null
    const orderType = String(record?.order_type ?? '').trim().toLowerCase()
    if (orderType === 'loaner') return 'loaner'
    if (!isNumericRma(record?.RMA)) return 'legacy'
    return null
}

function toggleLogisticsLoanerFilter() {
    logisticsLoanerFilter.value = !logisticsLoanerFilter.value
}

const logisticsLoanerDialogRecord = ref(null)

function closeLogisticsLoanerDialog() {
    logisticsLoanerDialogRecord.value = null
}

async function openLogisticsLoanerDialog(record) {
    const snapshot = { ...record }
    logisticsLoanerDialogRecord.value = snapshot
    try {
        const full = await fetchRecord(record.orderID)
        if (logisticsLoanerDialogRecord.value?.orderID !== record.orderID) return
        logisticsLoanerDialogRecord.value = {
            ...full,
            item: full.item || snapshot.item,
        }
    } catch {
        // 一覧の情報で表示を続ける
    }
}

async function onLogisticsLoanerReturned() {
    logisticsLoanerDialogRecord.value = null
    await reloadListRecords({ preserveState: true })
}

function matchesLogisticsShippingDateFilter(record, filter) {
    if (filter === 'all') return true
    const ymd = formatListDate(record?.shippingOut_requiredDate)
    if (!ymd) return false
    const today = tokyoTodayYmd()
    if (filter === 'today') return ymd === today
    if (filter === 'tomorrow') return ymd === nextBusinessDayYmd(today)
    return true
}

function matchesArrivalFilter(record, filter) {
    if (filter === 'all') return true

    // Active: 着荷(20)以上 〜 出荷準備完了 起伝依頼(300)未満
    if (filter === 'active') {
        const status = Number(record?.status)
        if (!Number.isFinite(status)) return false
        return status >= 20 && status < 300
    }

    const ymd = formatListDate(record?.receivedDate)
    const today = tokyoTodayYmd()

    if (filter === 'hide_future') {
        if (!ymd) return true
        return ymd <= today
    }
    if (!ymd) return false
    if (filter === 'today') return ymd === today
    if (filter === '1day') return ymd === addDaysYmd(today, -1)
    if (filter === '2day') return ymd >= addDaysYmd(today, -2) && ymd <= today
    if (filter === '3day') return ymd >= addDaysYmd(today, -3) && ymd <= today
    if (filter === '1wk') return ymd >= addDaysYmd(today, -7) && ymd <= today
    return true
}

/** status 昇順 → 出荷予定日の降順 → dealer のあいうえお昇順 → orderID */
function sortByStatusAscThenShippingOutDescThenDealer(records) {
    return [...records].sort((a, b) => {
        const statusA = Number(a?.status)
        const statusB = Number(b?.status)
        const sa = Number.isFinite(statusA) ? statusA : Number.POSITIVE_INFINITY
        const sb = Number.isFinite(statusB) ? statusB : Number.POSITIVE_INFINITY
        if (sa !== sb) return sa - sb

        const da = formatListDate(a?.shippingOut_requiredDate) || ''
        const db = formatListDate(b?.shippingOut_requiredDate) || ''
        if (da !== db) {
            if (!da) return 1
            if (!db) return -1
            const byDate = db.localeCompare(da)
            if (byDate !== 0) return byDate
        }

        const dealerA = String(a?.dealer ?? '')
        const dealerB = String(b?.dealer ?? '')
        const byDealer = dealerA.localeCompare(dealerB, 'ja')
        if (byDealer !== 0) return byDealer

        const idA = Number(a?.orderID)
        const idB = Number(b?.orderID)
        return (Number.isFinite(idA) ? idA : 0) - (Number.isFinite(idB) ? idB : 0)
    })
}

function sortByStatusAscThenOrderId(records) {
    return [...records].sort((a, b) => {
        const statusA = Number(a?.status)
        const statusB = Number(b?.status)
        const sa = Number.isFinite(statusA) ? statusA : Number.POSITIVE_INFINITY
        const sb = Number.isFinite(statusB) ? statusB : Number.POSITIVE_INFINITY
        if (sa !== sb) return sa - sb
        const idA = Number(a?.orderID)
        const idB = Number(b?.orderID)
        const oa = Number.isFinite(idA) ? idA : 0
        const ob = Number.isFinite(idB) ? idB : 0
        return oa - ob
    })
}

/** ヘッダークリックによる一覧ソート（全タブ共通） */
const listColumnSortKey = ref(null)
const listColumnSortDir = ref('asc') // asc | desc

function toggleColumnSort(key) {
    if (!key) return
    if (listColumnSortKey.value === key) {
        listColumnSortDir.value = listColumnSortDir.value === 'asc' ? 'desc' : 'asc'
        return
    }
    listColumnSortKey.value = key
    listColumnSortDir.value = 'asc'
}

function clearColumnSort() {
    listColumnSortKey.value = null
    listColumnSortDir.value = 'asc'
}

function recordColumnSortValue(record, key) {
    switch (key) {
        case 'orderID':
        case 'parentID':
        case 'promotion_source_orderID':
        case 'status': {
            const n = Number(record?.[key])
            return Number.isFinite(n) ? n : Number.NEGATIVE_INFINITY
        }
        case 'symptomsNum': {
            const n = Number(symptomsNumForRecord(record))
            return Number.isFinite(n) ? n : Number.NEGATIVE_INFINITY
        }
        case 'promotion_ready':
            return isPromotionReady(record) ? 1 : 0
        case 'returnCode':
            return record?.return_code_master?.description || ''
        case 'laborName':
            return record?.labor_master?.laborName || ''
        case 'order_type':
            return engineerOrderTypeLabel(record) || ''
        case 'a2la':
            return abroadA2laLabel(record?.a2la) || ''
        case 'receivedDate':
        case 'orderDate':
        case 'shippingOut_requiredDate':
        case 'shippedDate':
        case 'shipTo':
        case 'sentOut':
            return formatListDate(record?.[key]) || ''
        case 'entityID':
            return listEntityId(record)
        default:
            return record?.[key] ?? ''
    }
}

function sortRecordsByColumn(records, key, dir) {
    const mult = dir === 'desc' ? -1 : 1
    return [...records].sort((a, b) => {
        const va = recordColumnSortValue(a, key)
        const vb = recordColumnSortValue(b, key)
        let cmp = 0
        if (typeof va === 'number' && typeof vb === 'number') {
            cmp = va - vb
        } else {
            cmp = String(va).localeCompare(String(vb), 'ja', {
                numeric: true,
                sensitivity: 'base',
            })
        }
        if (cmp !== 0) return cmp * mult
        const idA = Number(a?.orderID)
        const idB = Number(b?.orderID)
        return (Number.isFinite(idA) ? idA : 0) - (Number.isFinite(idB) ? idB : 0)
    })
}

const filteredRecords = computed(() => {
    let records = props.initialRecords ?? []

    if (props.mode === 'engineer') {
        if (engineerDailyReportMode.value) {
            records = records.filter((r) => matchesDailyReportStatus(r))
        } else if (engineerSmSubmitMode.value) {
            records = records.filter((r) => {
                const orderType = r?.order_type ?? 'service'
                const status = Number(r?.status)
                return (orderType === 'service' || orderType === '' || orderType == null)
                    && status === 185
            })
        } else if (engineerQuoteCoMode.value) {
            records = records.filter((r) => {
                const orderType = r?.order_type ?? 'service'
                const status = Number(r?.status)
                return (orderType === 'service' || orderType === '' || orderType == null)
                    && status === 180
                    && matchesEngineerQuoteCoSmQuote(r)
            })
        } else {
            records = records.filter((r) => matchesEngineerListStatus(r))
        }
    } else if (!isBoardMode.value) {
        if (orderTypeFilter.value === 'abroad' && abroadOverseasRmaFilter.value) {
            records = records.filter(matchesOverseasRmaFilter)
        } else {
            records = records.filter((r) => matchesOrderTypeFilter(r, orderTypeFilter.value))
        }
    }

    if (!isRestrictedListMode.value && orderTypeFilter.value !== 'invoice') {
        records = records.filter((r) => matchesArrivalFilter(r, effectiveArrivalFilter.value))
    }

    if (props.mode === 'logistics') {
        if (logisticsLoanerFilter.value) {
            records = records.filter(isLogisticsLoanerLending)
        } else {
            records = records.filter((r) => !isLogisticsLoanerLending(r))
            records = records.filter((r) =>
                matchesLogisticsShippingDateFilter(r, shippingDateFilter.value),
            )
        }
    } else if (props.mode === 'shippingPrep') {
        records = records.filter((r) =>
            matchesLogisticsShippingDateFilter(r, shippingDateFilter.value),
        )
    }

    if (searchQuery.value) {
        const queries = searchQuery.value
            .toLowerCase()
            .trim()
            .split(/\s+/)
            .filter(q => q.length > 0)

        if (queries.length > 0) {
            records = records.filter(r => {
                const rowText = [
                    r.orderID?.toString(),
                    r.parentID?.toString(),
                    r.receivedDate,
                    formatListDate(r.shippingOut_requiredDate),
                    r.shippingOut_requiredDate,
                    formatListDate(r.shippedDate),
                    r.shippedDate,
                    statusLabel(r),
                    engineerOrderTypeLabel(r),
                    r.order_type,
                    r.RMA,
                    r.productName,
                    r.item,
                    r.SN,
                    r.enduser_SN,
                    r.returnCode,
                    r.return_code_master?.description,
                    r.labor_master?.laborName,
                    r.dealer,
                    r.dealer_depart,
                    r.contactPerson,
                    r.deliveryDestination_company,
                    r.deliveryDestination_depart,
                    r.deliveryDestination_zipcode,
                    r.deliveryDestination_address1,
                    r.deliveryDestination_address2,
                    r.rmaNumOverSea,
                    formatListDate(r.shipTo),
                    r.shipTo,
                    formatListDate(r.sentOut),
                    r.sentOut,
                    r.email,
                    r.phone,
                    r.order_type,
                    r.poNum,
                    r.a2la,
                    r.symptoms,
                    r.sm_workorder,
                    r.sm_quote,
                    listEntityId(r),
                    r.incident,
                    String(symptomsNumForRecord(r)),
                    r.RMA,
                    r.promotion_ready_at,
                    r.promotion_source_orderID?.toString(),
                    isPromotionReady(r) ? '繰上可' : '',
                    ...(engineerDailyReportMode.value ? Object.values(dailyReportDraft(r)) : []),
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()

                return queries.every(q => rowText.includes(q))
            })
        }
    }

    if (listColumnSortKey.value) {
        records = sortRecordsByColumn(
            records,
            listColumnSortKey.value,
            listColumnSortDir.value,
        )
    } else {
        const sortByStatusShippingDealer =
            props.mode === 'logistics'
            || props.mode === 'shippingPrep'
            || (!isBoardMode.value && orderTypeFilter.value === 'invoice')

        if (sortByStatusShippingDealer) {
            records = sortByStatusAscThenShippingOutDescThenDealer(records)
        } else if (!isBoardMode.value && orderTypeFilter.value === 'loaner') {
            records = sortByStatusAscThenOrderId(records)
        }
    }

    return records
})

const abroadSelectedCount = computed(() => abroadSelectedIds.value.size)
const abroadOverseasRmaBadgeCount = computed(() =>
    (props.initialRecords ?? []).filter(matchesOverseasRmaFilter).length,
)
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

const dailyReportSelectedRows = computed(() => {
    const rows = filteredRecords.value
    const selected = rows.filter((r) => isAbroadSelected(r.orderID))
    return selected.length ? selected : rows
})
const dailyReportPreviewCount = computed(() => dailyReportSelectedRows.value.length)
const dailyReportPreviewRows = computed(() =>
    dailyReportSelectedRows.value.map((r) => {
        const draft = dailyReportDraft(r)
        return {
            orderID: r.orderID,
            ...draft,
            date: formatDailyReportDateDisplay(draft.date),
        }
    }),
)
const dailyReportEmailSubject = computed(() => {
    const today = formatDailyReportDateDisplay(new Date())
    return `Daily Report ${today}`
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
    shippingExcelCopyMessage.value = ''
    closeAbroadExcelPreview()
}

/** Excel 貼付用: shippingOut_requiredDate + 空11セル + SN + Conum + dealer + price */
function buildShippingExcelPasteRow(record) {
    const emptyCells = Array.from({ length: 11 }, () => '')
    return [
        formatListDate(record?.shippingOut_requiredDate),
        ...emptyCells,
        record?.SN ?? '',
        record?.coNum ?? '',
        record?.dealer ?? '',
        record?.price ?? '',
    ].join('\t')
}

async function writeTextToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text)
        return
    }
    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.setAttribute('readonly', '')
    textarea.style.position = 'fixed'
    textarea.style.left = '-9999px'
    document.body.appendChild(textarea)
    textarea.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(textarea)
    if (!ok) throw new Error('クリップボードへのコピーに失敗しました。')
}

async function copySelectedRowsForExcelPaste() {
    if (abroadSelectedIds.value.size === 0 || shippingExcelCopyBusy.value) return

    const rows = filteredRecords.value.filter((r) => abroadSelectedIds.value.has(r.orderID))
    if (!rows.length) {
        shippingExcelCopyMessage.value = '行を選択してください。'
        return
    }

    const tsv = rows.map((r) => buildShippingExcelPasteRow(r)).join('\n')

    shippingExcelCopyBusy.value = true
    shippingExcelCopyMessage.value = ''
    try {
        await writeTextToClipboard(tsv)
        shippingExcelCopyMessage.value = `${rows.length} 件をクリップボードにコピーしました。Excel に貼り付けてください。`
    } catch (e) {
        shippingExcelCopyMessage.value = e.message || 'クリップボードへのコピーに失敗しました。'
    } finally {
        shippingExcelCopyBusy.value = false
    }
}

function abroadRequestType(returnCode) {
    const code = Number(returnCode)
    if ([1, 5, 9].includes(code)) return 'Certification'
    if ([2, 3, 4, 13].includes(code)) return 'Repair'
    return ''
}

function abroadA2laLabel(a2la) {
    return a2la === 1 || a2la === '1' || a2la === true ? 'Yes' : 'No'
}

function buildAbroadExcelRows() {
    return filteredRecords.value
        .filter((r) => abroadSelectedIds.value.has(r.orderID))
        .map((r) => [
            String(r.productName || ''),
            String(r.SN || ''),
            String(r.RMA || ''), // PO# ← servicerecord.RMA
            '', // RMA# は常に空欄
            abroadRequestType(r.returnCode),
            abroadA2laLabel(r.a2la),
            'No',
            'No',
            String(r.symptoms || ''),
            '',
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
    const row = abroadExcelHeaders.map(() => '')
    row[ABROAD_PRETEST_HEADER_INDEX] = 'No'
    row[ABROAD_POSTTEST_HEADER_INDEX] = 'No'
    abroadExcelPreviewRows.value.push(row)
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

/** 例: RMA Request 20260702_03 (1).xlsx */
function nextAbroadExcelFileName() {
    const ymd = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date()).replace(/-/g, '')

    const storageKey = `abroadExcelSeq_${ymd}`
    let seq = Number(localStorage.getItem(storageKey) || '0') + 1
    if (!Number.isFinite(seq) || seq < 1) seq = 1
    localStorage.setItem(storageKey, String(seq))
    const seqStr = String(seq).padStart(2, '0')
    return `RMA Request ${ymd}_${seqStr} (1).xlsx`
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
        const thinBorder = {
            top: { style: 'thin', color: { argb: 'FF000000' } },
            left: { style: 'thin', color: { argb: 'FF000000' } },
            bottom: { style: 'thin', color: { argb: 'FF000000' } },
            right: { style: 'thin', color: { argb: 'FF000000' } },
        }
        const headerRowHeight = 36
        const cellFont = { name: 'Arial', size: 11, bold: true, color: { argb: 'FF000000' } }

        dataSheet.addRow([...abroadExcelHeaders])
        rows.forEach((row) => {
            const values = row.map((cell) => (cell == null ? '' : String(cell)))
            values[ABROAD_RMA_HEADER_INDEX] = ''
            dataSheet.addRow(values)
        })

        const headerRow = dataSheet.getRow(1)
        headerRow.font = { ...cellFont }
        headerRow.alignment = { wrapText: true, vertical: 'middle', horizontal: 'center' }
        headerRow.height = headerRowHeight
        abroadExcelHeaders.forEach((_, index) => {
            const cell = headerRow.getCell(index + 1)
            cell.font = { ...cellFont }
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: index === ABROAD_RMA_HEADER_INDEX ? 'FFFFFF00' : 'FFFFFFFF' },
            }
            cell.border = thinBorder
        })

        const columnWidths = [22, 14, 12, 12, 28, 18, 22, 22, 36, 28]
        columnWidths.forEach((width, index) => {
            dataSheet.getColumn(index + 1).width = width
        })

        rows.forEach((_, rowIndex) => {
            const excelRow = dataSheet.getRow(rowIndex + 2)
            excelRow.height = headerRowHeight
            excelRow.font = { ...cellFont }
            excelRow.alignment = { vertical: 'middle', wrapText: true }
            abroadExcelHeaders.forEach((__, colIndex) => {
                const cell = excelRow.getCell(colIndex + 1)
                cell.font = { ...cellFont }
                cell.border = thinBorder
                if (colIndex === ABROAD_RMA_HEADER_INDEX) {
                    cell.value = ''
                }
                if (
                    (colIndex === ABROAD_PRETEST_HEADER_INDEX || colIndex === ABROAD_POSTTEST_HEADER_INDEX)
                    && !String(cell.value ?? '').trim()
                ) {
                    cell.value = 'No'
                }
            })
        })

        if (abroadAttachedImages.value.length) {
            const imageHeightPx = 220
            const imageWidthPx = 280
            const colSpanPerImage = 3.2
            const imageTopRow = rows.length + 3
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

        let xlsxBuffer = await workbook.xlsx.writeBuffer()
        const msip = page.props.msip || {}
        const labelId = String(msip.publicLabelId || '').trim()
        const siteId = String(msip.siteId || '').trim()
        let labelApplied = false
        if (labelId && siteId) {
            xlsxBuffer = await applySensitivityLabel(xlsxBuffer, {
                labelId,
                siteId,
                method: 'Privileged',
                contentBits: 0,
            })
            labelApplied = true
        }

        const blob = new Blob(
            [xlsxBuffer],
            { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
        )
        const url = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.download = nextAbroadExcelFileName()
        document.body.appendChild(anchor)
        anchor.click()
        anchor.remove()
        URL.revokeObjectURL(url)

        abroadExcelMessage.value = `${rows.length} 行` +
            (abroadAttachedImages.value.length ? ` + 画像 ${abroadAttachedImages.value.length} 件` : '') +
            ' を 1 つの Excel ファイル（.xlsx）で出力しました。' +
            (labelApplied
                ? '（秘密度ラベル: Public）'
                : '（秘密度ラベル未設定: .env の MSIP_PUBLIC_LABEL_ID / MSIP_SITE_ID を設定してください）')
        closeAbroadExcelPreview()
    } catch (e) {
        abroadExcelMessage.value = e.message || 'ファイル作成に失敗しました。'
    } finally {
        abroadExcelCreating.value = false
    }
}

watch(logisticsLoanerFilter, () => {
    clearColumnSort()
    nextTick(() => {
        logisticsCalendarRef.value?.refetchEvents?.()
    })
})

watch(orderTypeFilter, (value) => {
    clearColumnSort()
    persistOrderTypeFilter(value)
    syncOrderTypeQuery(value)
    if (value !== 'abroad') {
        abroadOverseasRmaFilter.value = false
    }
    if (value !== 'abroad' && value !== 'rma' && value !== 'update_sm') {
        clearAbroadSelection()
        stopSmListAutoRefresh()
    } else {
        abroadSelectedIds.value = new Set()
        abroadExcelMessage.value = ''
        closeAbroadExcelPreview()
        if (value === 'rma' || value === 'update_sm') {
            if (smListAutoUpdate.value) startSmListAutoRefresh()
            else stopSmListAutoRefresh()
        } else {
            stopSmListAutoRefresh()
        }
    }
})

watch(smListAutoUpdate, (enabled) => {
    if (enabled && isSmListMode.value) startSmListAutoRefresh()
    else stopSmListAutoRefresh()
})

watch(arrivalFilter, (value) => {
    if (ARRIVAL_FILTERS.includes(value)) {
        saveArrivalFilter(value)
        syncArrivalQuery(value)
    }
})

watch(
    () => [engineerDailyReportMode.value, filteredRecords.value],
    () => {
        if (!engineerDailyReportMode.value) return
        seedDailyReportDrafts(filteredRecords.value, {
            forceToday: dailyReportForceTodayOnOpen.value,
        })
        dailyReportForceTodayOnOpen.value = false
    },
    { immediate: true },
)

function matchesAbroadFilter(record) {
    const laborID = Number(record?.laborID)
    const matchesLaborRange = Number.isFinite(laborID) && laborID >= 60 && laborID < 100
    return matchesLaborRange || matchesOverseasRmaFilter(record)
}

function matchesOverseasRmaFilter(record) {
    return Number(record?.rmaNumOverSea) === 123
        || String(record?.rmaNumOverSea ?? '').trim() === '123'
}

function toggleAbroadOverseasRmaFilter() {
    abroadOverseasRmaFilter.value = !abroadOverseasRmaFilter.value
    clearAbroadSelection()
}

function matchesRmaFilter(record) {
    return Number(record?.RMA) === 123
        || String(record?.RMA ?? '').trim() === '123'
}

function matchesUpdateSmFilter(record) {
    return Number(record?.sm_quote) === 123
        || String(record?.sm_quote ?? '').trim() === '123'
}

function matchesOrderTypeFilter(record, filter) {
    const orderType = record?.order_type ?? null
    const status = Number(record?.status)

    if (filter === 'service') {
        return orderType === 'service' || orderType == null || orderType === ''
    }
    if (filter === 'tech_comp') {
        const isService = orderType === 'service' || orderType == null || orderType === ''
        return isService && (status === 190 || status === 191)
    }
    if (filter === 'closing') {
        const isServiceOrLoaner = orderType === 'service'
            || orderType === 'loaner'
            || orderType == null
            || orderType === ''
        return isServiceOrLoaner && Number.isFinite(status) && status >= 200 && status < 300
    }
    if (filter === 'invoice') {
        // Invoiceページ（shipping-prep）と同じ: status 300 / 310 / 350 / 385
        return status === 300 || status === 310 || status === 350 || status === 385
    }
    if (filter === 'loaner') {
        return orderType === 'loaner'
            && Number.isFinite(status)
            && status >= 0
            && status < 400
    }
    if (filter === 'waiting_list') {
        return orderType === 'waiting_list'
    }
    if (filter === 'abroad') {
        return matchesAbroadFilter(record)
    }
    if (filter === 'rma') {
        return matchesRmaFilter(record)
    }
    if (filter === 'update_sm') {
        return matchesUpdateSmFilter(record)
    }
    return true
}

function statusLabel(record) {
    if (record?.order_type === 'waiting_list') {
        return ''
    }
    if (record?.order_type === 'loaner') {
        return loanerStatusLabel(record.status_master_loaner) || record.status_label || ''
    }
    return record.status_master?.status || ''
}

/** loaner 一覧で強調表示する status（返却 / 受け入れ確認中 / 完了前、予約確認） */
function loanerStatusBadgeClass(record) {
    if (record?.order_type !== 'loaner') return ''
    const status = Number(record?.status)
    if (status === 393) return 'loaner-status-badge--returned'
    if (status === 396) return 'loaner-status-badge--acceptance'
    if (status === 399) return 'loaner-status-badge--pre-complete'
    return ''
}

function engineerOrderTypeLabel(record) {
    return record?.order_type === 'loaner' ? '貸出機チェック' : 'サービス案件'
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

function isPromotionReady(record) {
    return record?.promotion_ready_at != null && record.promotion_ready_at !== ''
}

function isRemandRecord(record) {
    const value = record?.remand
    return value === 1 || value === '1' || value === true
}

function promotionRowTitle(record) {
    if (!isPromotionReady(record)) return undefined
    const source = record.promotion_source_orderID
    return source
        ? `繰り上がり候補（返却元 orderID: ${source}）`
        : '繰り上がり候補'
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
const activeAttachedLoaners = ref([])
const attachmentsLoading = ref(false)
const attachmentsError = ref('')
const isSavingRecord = ref(false)
const saveError = ref('')
const detailShellRef = ref(null)
const detailLoading = ref(false)
const detailOpenError = ref('')
const attachmentsRequestSeq = ref(0)

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
        activeAttachedLoaners.value = []
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
        activeAttachedLoaners.value = []
        return
    }

    attachmentsError.value = ''
    activeNotes.value = annotateNotesOwnership(data.notes ?? [])
    activeFiles.value = data.files ?? []
    activeCapturedImages.value = data.capturedImages ?? []
    activeParts.value = data.parts ?? []
    activeStockedParts.value = data.stockedParts ?? []
    activeLoaners.value = data.loaners ?? (data.loaner ? [data.loaner] : [])
    activeAttachedLoaners.value = data.attachedLoaners ?? []
}

function onFilesUpdated(nextFiles) {
    activeFiles.value = Array.isArray(nextFiles) ? nextFiles : []
}

function onReloadAttachments() {
    if (!activeRecord.value?.orderID) return
    loadAttachments(activeRecord.value.orderID)
}

/**
 * 添付は Inertia ではなく JSON API で取得する。
 * Auto update の router.reload と競合すると詳細 state が落ちて一覧に戻るため。
 */
async function loadAttachments(orderID) {
    const requestSeq = ++attachmentsRequestSeq.value
    attachmentsLoading.value = true
    attachmentsError.value = ''
    activeNotes.value = []
    activeFiles.value = []
    activeCapturedImages.value = []
    activeParts.value = []
    activeStockedParts.value = []
    activeLoaners.value = []
    activeAttachedLoaners.value = []

    try {
        const url = `${window.location.origin}${getBasePath()}/attachments/${orderID}`
        const result = await apiFetch(url)
        if (requestSeq !== attachmentsRequestSeq.value) return
        if (!result) {
            attachmentsError.value = '添付データの取得に失敗しました。'
            return
        }
        const { response, data } = result
        if (!response.ok) {
            attachmentsError.value = data?.message || `添付データの取得に失敗しました。（HTTP ${response.status}）`
            return
        }
        applyAttachmentData(data)
    } catch (e) {
        if (requestSeq !== attachmentsRequestSeq.value) return
        attachmentsError.value = e.message || '添付データの取得に失敗しました。'
    } finally {
        if (requestSeq === attachmentsRequestSeq.value) {
            attachmentsLoading.value = false
        }
    }
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

    if (props.mode === 'logistics' && isLogisticsLoanerLending(record)) {
        await openLogisticsLoanerDialog(record)
        return
    }

    // 貸出詳細へ行くのは、実データの order_type が loaner/waiting_list のときだけ。
    // Engineer では orderTypeFilter が session に残っていても service は通常詳細を開く。
    // Invoice / Closing / ShippingPrep / Logistics では loaner でも画面内の詳細（invoice 等）を開く。
    const isLoanerLike =
        record.order_type === 'loaner'
        || record.order_type === 'waiting_list'
    const preferInPanelDetail =
        props.mode === 'shippingPrep'
        || props.mode === 'logistics'
        || orderTypeFilter.value === 'invoice'
        || orderTypeFilter.value === 'closing'
    const openAsLoanerDetail = isLoanerLike && !preferInPanelDetail && (
        orderTypeFilter.value === 'loaner'
        || orderTypeFilter.value === 'waiting_list'
        || props.mode === 'engineer'
    )
    if (openAsLoanerDetail) {
        let returnUrl = typeof window !== 'undefined' ? window.location.href : ''
        try {
            const url = new URL(returnUrl || window.location.href)
            url.searchParams.set(
                'orderType',
                record.order_type === 'waiting_list' ? 'waiting_list' : 'loaner',
            )
            if (ARRIVAL_FILTERS.includes(arrivalFilter.value) && arrivalFilter.value !== 'hide_future') {
                url.searchParams.set('arrival', arrivalFilter.value)
            } else {
                url.searchParams.delete('arrival')
            }
            returnUrl = url.href
        } catch {
            // keep original returnUrl
        }
        const qs = {}
        if (returnUrl) qs.returnUrl = returnUrl
        if (props.mode === 'engineer') qs.from = 'engineer'
        window.location.href = loanerDetailUrl(record.orderID, qs)
        return
    }

    // 詳細表示中は Auto update を止め、誤 close を短時間ガードする
    stopSmListAutoRefresh()
    stopLogisticsAutoRefresh()
    detailCloseGuardUntil.value = Date.now() + 500

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
        if (!isDetailOpen.value || activeRecord.value?.orderID !== record.orderID) return
        const previous = activeRecord.value
        const parentOrderDate = parentOrderDateFromRecord(fullRecord)
            ?? parentOrderDateFromRecord(previous)
        const parentRecord = parentOrderDateFromRecord(fullRecord)
            ? (fullRecord.parentRecord ?? fullRecord.parent_record)
            : (previous?.parentRecord ?? previous?.parent_record)
        const priceVersions = (Array.isArray(fullRecord.priceVersions) && fullRecord.priceVersions.length)
            ? fullRecord.priceVersions
            : (previous?.priceVersions ?? [])
        activeRecord.value = {
            ...previous,
            ...fullRecord,
            parentOrderDate,
            parentRecord: parentRecord ?? previous?.parentRecord,
            priceVersions,
        }
        draftRecord.value = { ...activeRecord.value }
    } catch (e) {
        if (!isDetailOpen.value || activeRecord.value?.orderID !== record.orderID) return
        detailOpenError.value = `${e.message || '詳細データの取得に失敗しました。'}（一覧の情報のみ表示しています）`
    } finally {
        if (isDetailOpen.value && activeRecord.value?.orderID === record.orderID) {
            detailLoading.value = false
        }
    }

    if (!isDetailOpen.value || activeRecord.value?.orderID !== record.orderID) return
    await loadAttachments(record.orderID)
}

function switchDetailLayout(layout) {
    detailLayout.value = layout
}

function resetDetailState() {
    attachmentsRequestSeq.value += 1
    isDetailOpen.value = false
    activeRecord.value = null
    draftRecord.value = null
    activeNotes.value = []
    activeFiles.value = []
    activeCapturedImages.value = []
    activeParts.value = []
    activeStockedParts.value = []
    activeLoaners.value = []
    activeAttachedLoaners.value = []
    attachmentsLoading.value = false
    attachmentsError.value = ''
    detailLoading.value = false
    detailOpenError.value = ''
    saveError.value = ''
    isSavingRecord.value = false
    closeDialog()
}

async function closeDetail() {
    // dblclick 直後の誤発火・競合による close を無視
    if (Date.now() < detailCloseGuardUntil.value) {
        return
    }

    // 呼び出し元一覧（例: /servicerecord_q）へ戻る
    const target =
        callerReturnUrl.value
        || resolveCallerReturnUrl()
        || sanitizeSameOriginUrl(
            typeof window !== 'undefined'
                ? new URLSearchParams(window.location.search).get('returnUrl')
                : null,
        )
    if (target) {
        try {
            sessionStorage.removeItem(CALLER_RETURN_URL_KEY)
        } catch {
            // ignore
        }
        callerReturnUrl.value = null
        window.location.href = target
        return
    }

    resetDetailState()
    if (smListAutoUpdate.value && isSmListMode.value) {
        startSmListAutoRefresh()
    }
    if (isBoardMode.value) {
        startLogisticsAutoRefresh()
    }
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
    const remandStatus = Number(dialogPayload.value?.remandStatus ?? 40)

    // 差戻: 先にダイアログを閉じ、status 更新中の再保存を防ぐ
    if (isRemandNote) {
        closeDialog()
        try {
            await updateActiveRecordStatus(Number.isFinite(remandStatus) ? remandStatus : 40, {
                notifyRemand: true,
            })
            await finishEngineerWorkflow()
        } catch (e) {
            saveError.value = e.message || '差戻処理に失敗しました。'
            if (activeRecord.value?.orderID) {
                await loadAttachments(activeRecord.value.orderID)
            }
        }
        return
    }

    if (activeRecord.value?.orderID) {
        await loadAttachments(activeRecord.value.orderID)
    }

    closeDialog()
}

async function updateActiveRecordStatus(status, options = {}) {
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
        body: JSON.stringify({
            status,
            notify_remand: !!options.notifyRemand,
        }),
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
    detailCloseGuardUntil.value = 0
    resetDetailState()
    if (smListAutoUpdate.value && isSmListMode.value) {
        startSmListAutoRefresh()
    }
    if (isBoardMode.value) {
        startLogisticsAutoRefresh()
    }
    await reloadListRecords({ preserveState: true })
}

async function finishEngineerWorkflow() {
    await finishListWorkflow()
}

function reloadListRecords(options = {}) {
    // Logistics / 出荷準備は表示方法（一覧/カレンダー）を保持するため常に preserveState
    const {
        preserveState = isBoardMode.value,
    } = options

    return new Promise((resolve) => {
        router.reload({
            only: ['initialRecords', 'tabBadgeCounts'],
            preserveState,
            preserveScroll: true,
            onFinish: () => resolve(),
        })
    })
}

function reloadEngineerList() {
    return reloadListRecords()
}

async function refreshLogisticsData() {
    if (!isBoardMode.value) return
    if (typeof document !== 'undefined' && document.hidden) return
    if (logisticsAutoRefreshing.value) return
    // 詳細表示中 / オープン直後は一覧・カレンダーをリロードしない
    if (isDetailOpen.value || Date.now() < detailCloseGuardUntil.value) return

    logisticsAutoRefreshing.value = true
    try {
        await reloadListRecords({ preserveState: true })
        if (isDetailOpen.value) return
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

function resolveDetailFormAPrice(draft, parts = []) {
    if (!draft) return null

    const asOfDate = resolveDisplayPriceAsOfDate({
        orderType: draft.order_type,
        orderDate: draft.orderDate,
    })
    const master = findServiceMaster(page.props.servicesMaster, {
        productName: draft.productName,
        entityID: draft.entityID,
        serviceID: draft.serviceID,
    }, asOfDate)

    const a2laOn = draft.a2la === 1 || draft.a2la === '1' || draft.a2la === true
    const totals = resolvePriceCardTotals({
        orderType: draft.order_type,
        returnCode: draft.returnCode,
        serviceMaster: master,
        loanerID: draft.loanerID,
        loanerPriceVersions: draft.priceVersions ?? [],
        asOfDate,
        storedPrice: draft.price,
        loanerNoCharge: draft.loaner_no_charge,
        a2laOn,
        parts,
        partsMaster: page.props.partsMaster ?? [],
        discountService: draft.discount_service ?? 0,
    })
    // 保存する price は作業内容（計ではない）。service は returnCode マスタ、loaner は既存 price を維持。
    const orderType = String(draft.order_type ?? '').trim().toLowerCase()
    if (orderType === 'loaner') {
        const stored = Number(draft.price)
        return Number.isFinite(stored) ? stored : totals.workPrice
    }
    return totals.workPrice
}

function confirmPendingTbcIfStatus300Plus(status) {
    const n = Number(status)
    if (!Number.isFinite(n) || n < 300) return true
    const hasPendingTbc = (activeNotes.value ?? []).some((note) => {
        const personal = note?.personal === true || note?.personal === 1 || note?.personal === '1'
        if (personal) return false
        const tbc = note?.tbc === true || note?.tbc === 1 || note?.tbc === '1'
        const done = note?.done === true || note?.done === 1 || note?.done === '1'
        return tbc && !done
    })
    if (!hasPendingTbc) return true
    return window.confirm('要確認事項があります')
}

async function saveRecord() {
    if (!activeRecord.value?.orderID || !draftRecord.value) {
        return
    }

    if (!confirmPendingTbcIfStatus300Plus(draftRecord.value.status)) {
        return
    }

    let notifyAssign = false
    if (detailLayout.value === 'A' && Number(draftRecord.value.status) === 90) {
        const laborId = draftRecord.value.laborID
        if (laborId !== null && laborId !== undefined && String(laborId).trim() !== '') {
            try {
                const previewUrl = `${window.location.origin}${getBasePath()}/assign-notify/targets?laborID=${encodeURIComponent(String(laborId))}`
                const preview = await apiFetch(previewUrl, {
                    method: 'GET',
                    headers: { Accept: 'application/json' },
                })
                const count = Number(preview?.data?.count ?? 0)
                if (Number.isFinite(count) && count > 0) {
                    notifyAssign = await detailShellRef.value?.confirmAssignNotifyMail?.(count) ?? false
                }
            } catch (e) {
                // 対象確認失敗時はメール無しで保存続行
                console.warn('assign notify targets check failed', e)
            }
        }
    }

    isSavingRecord.value = true
    saveError.value = ''

    // DetailFormA: 詳細再取得で draft が差し替わっても、保存時に表示価格を確実に price へ載せる
    if (detailLayout.value === 'A') {
        const resolvedPrice = resolveDetailFormAPrice(draftRecord.value, activeParts.value)
        if (resolvedPrice != null) {
            draftRecord.value.price = resolvedPrice
        }
    }

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
                zipcode: draftRecord.value.zipcode,
                address1: draftRecord.value.address1,
                address2: draftRecord.value.address2,
                endUser: draftRecord.value.endUser,
                endUser_depart: draftRecord.value.endUser_depart,
                endUser_contactPerson: draftRecord.value.endUser_contactPerson,
                endUser_email: draftRecord.value.endUser_email,
                endUser_phone: draftRecord.value.endUser_phone,
                endUser_fax: draftRecord.value.endUser_fax,
                endUser_zipcode: draftRecord.value.endUser_zipcode,
                endUser_address1: draftRecord.value.endUser_address1,
                endUser_address2: draftRecord.value.endUser_address2,
                deliveryDestination_company: draftRecord.value.deliveryDestination_company,
                deliveryDestination_depart: draftRecord.value.deliveryDestination_depart,
                deliveryDestination_contactPerson: draftRecord.value.deliveryDestination_contactPerson,
                deliveryDestination_email: draftRecord.value.deliveryDestination_email,
                deliveryDestination_phone: draftRecord.value.deliveryDestination_phone,
                deliveryDestination_zipcode: draftRecord.value.deliveryDestination_zipcode,
                deliveryDestination_address1: draftRecord.value.deliveryDestination_address1,
                deliveryDestination_address2: draftRecord.value.deliveryDestination_address2,
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
                remand: draftRecord.value.remand === 1 || draftRecord.value.remand === '1' || draftRecord.value.remand === true ? 1 : 0,
                notify_assign: notifyAssign,
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
        }, resolveDisplayPriceAsOfDate({
            orderType: draftRecord.value.order_type,
            orderDate: draftRecord.value.orderDate,
        }))

        // 受注日・作業内容変更時: 子 loaner の保存済み価格を反映
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
    height: 100dvh;
    min-height: 100vh;
    min-height: 100dvh;
    overflow: hidden;
    background: #e2e8f0;
    position: relative;
}

.list-page-inner {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    height: 100%;
}

/* 一覧画面: ブラウザ 110% 相当（10%拡大） */
.list-page-inner.list-page-scale {
    zoom: 1.1;
    width: 100%;
    height: calc(100% / 1.1);
    min-height: calc(100% / 1.1);
    transform-origin: top left;
}

.list-page-scale .fixed-header-zone {
    padding: 12px 16px;
}

.list-page-scale .header-left,
.list-page-scale .order-type-filters,
.list-page-scale .arrival-date-filters {
    gap: 10px;
}

.list-page-scale .header-center {
    gap: 14px;
}

.list-page-scale .header-right,
.list-page-scale .search-area,
.list-page-scale .home-link-area {
    gap: 12px;
}

.list-page-scale .order-type-btn {
    padding: 5px 10px;
    font-size: 11px;
}

.list-page-scale .order-type-badge {
    top: -6px;
    right: -6px;
    min-width: 16px;
    height: 16px;
    font-size: 10px;
    line-height: 16px;
}

.list-page-scale .filtered-count {
    padding: 5px 9px;
    font-size: 12px;
}

.list-page-scale .search-area input {
    width: min(320px, 28vw);
    padding: 5px 10px;
    font-size: 13px;
}

.list-page-scale .search-area > button:not(.calendar-link):not(.sm-mode-btn),
.list-page-scale .search-area button.sm-mode-btn {
    padding: 5px 10px;
    font-size: 11px;
}

.list-page-scale .search-area a.calendar-link,
.list-page-scale .search-area button.calendar-link,
.list-page-scale .home-link-area a.calendar-link,
.list-page-scale .home-link-area button.calendar-link {
    padding: 5px 10px;
    font-size: 12px;
}

.list-page-scale #myLargeTable td,
.list-page-scale #myLargeTable th {
    padding: 5px 7px;
    font-size: 11px;
}

.fixed-header-zone {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    /* A | ① | B | ② | C で ①=② になる */
    justify-content: space-between;
    gap: 8px;
    padding: 14px 20px;
    box-sizing: border-box;
    background: #dbdbdb;
    border-bottom: 2px solid #3b82f6;
    z-index: 20;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    min-width: 0;
}

/* グループA: ステータスフィルタ（左寄せ・内容を崩さない） */
.header-left {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
    min-width: 0;
    justify-content: flex-start;
}

/* グループB: 日付 + Quick Filter + Clear + RMA + Update SM（一塊のまま中央帯） */
.header-center {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 10px;
    flex: 1 1 auto;
    min-width: 0;
    justify-content: center;
    overflow: visible;
}

.filtered-count {
    flex: 0 0 auto;
    min-width: 4.5em;
    padding: 6px 10px;
    border-radius: 4px;
    background: #ecfdf5;
    border: 1px solid #6ee7b7;
    color: #065f46;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    text-align: center;
    line-height: 1.2;
}

/* グループC: 出荷カレンダー + 閉じる（右寄せ・内容を崩さない） */
.header-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: nowrap;
    gap: 8px;
    flex: 0 0 auto;
    flex-shrink: 0;
    min-width: max-content;
}

.order-type-filters {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
    justify-content: flex-start;
    overflow: visible;
    padding-top: 4px;
}

/* All と案件数の間隔（header-center の gap 10px を差し引き）／幅は Tomorrow に統一 */
.logistics-shipping-date-filters {
    margin-left: calc(100px - 10px);
}

.logistics-shipping-date-filters .order-type-btn {
    box-sizing: border-box;
    width: 7.2em;
    min-width: 7.2em;
    padding-left: 6px;
    padding-right: 6px;
    text-align: center;
}

.arrival-date-filters {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
    justify-content: flex-start;
}

.order-type-btn {
    position: relative;
    padding: 6px 12px;
    border: 1px solid #64748b;
    border-radius: 4px;
    background: #fff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.order-type-btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.order-type-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.order-type-btn.active:disabled {
    opacity: 0.85;
}

.order-type-badge {
    position: absolute;
    top: -7px;
    right: -7px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    line-height: 18px;
    text-align: center;
    box-shadow: 0 0 0 2px #fff;
    pointer-events: none;
}

.order-type-btn.active .order-type-badge {
    box-shadow: 0 0 0 2px #2563eb;
}

.search-area {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
    overflow: visible;
    padding-top: 4px;
}

.search-area label {
    font-weight: bold;
    font-size: 14px;
    white-space: nowrap;
}

.search-area input {
    width: 400px;
    max-width: 100%;
    padding: 6px 12px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 14px;
    background-color: #ffffff;
    color: #111827;
}

.search-area > button:not(.calendar-link):not(.sm-mode-btn):not(.order-type-btn) {
    padding: 6px 16px;
    background-color: #6b7280;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

.search-area a.calendar-link,
.search-area button.calendar-link {
    padding: 6px 12px;
    border-radius: 6px;
    background: #2563eb;
    color: #fff;
    border: none;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
}

.search-area a.calendar-link:hover,
.search-area button.calendar-link:hover {
    background: #1d4ed8;
}

/* RMA / Update SM: 未選択は白、選択時のみ緑 */
.search-area button.sm-mode-btn {
    padding: 6px 12px;
    border: 1px solid #64748b;
    border-radius: 4px;
    background: #fff;
    color: #334155;
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.search-area button.sm-mode-btn:hover:not(.active) {
    background: #f8fafc;
}

.search-area button.sm-mode-btn.active {
    background: #16a34a;
    border-color: #16a34a;
    color: #fff;
}

.search-area button.sm-mode-btn.sm-mode-btn-spaced {
    margin-left: 50px;
}

.search-area button.sm-mode-btn.sm-mode-btn-sm-submit.active {
    background: #eab308;
    border-color: #ca8a04;
    color: #111827;
}

.search-area button.sm-mode-btn.sm-mode-btn-daily-report.active {
    background: #111;
    border-color: #111;
    color: #fff;
}

.sm-quote-copy-message {
    margin-left: 10px;
    font-size: 12px;
    font-weight: 700;
    color: #0f766e;
    white-space: nowrap;
}

.abroad-sync-sm-btn {
    background: #0f766e;
}

.abroad-sync-sm-btn:hover:not(:disabled) {
    background: #0d9488;
}

.entity-id-input {
    width: 100%;
    min-width: 88px;
    max-width: 140px;
    box-sizing: border-box;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
    background: #fff;
    color: #111827;
}

.entity-id-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
}

.entity-id-input:disabled {
    opacity: 0.65;
    cursor: wait;
}

.daily-report-input {
    width: 100%;
    min-width: 72px;
    box-sizing: border-box;
    padding: 4px 6px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    background: #fff;
    color: #111827;
}

.daily-report-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
}

.daily-report-input-date {
    min-width: 138px;
    width: 148px;
}

.daily-report-input-product {
    min-width: 96px;
}

.daily-report-input-sn {
    min-width: 120px;
}

.daily-report-input-response {
    min-width: 220px;
}

.daily-report-input-service-type {
    min-width: 88px;
    width: 100px;
}

#myLargeTable.daily-report-table thead th {
    background: #111 !important;
}

#myLargeTable.quote-co-table thead th {
    background: #0f766e !important;
}

#myLargeTable.sm-submit-table thead th {
    background: #eab308 !important;
    color: #111827 !important;
}

#myLargeTable.daily-report-table td {
    overflow: visible;
}

.home-link-area {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.mode-badge {
    padding: 6px 10px;
    border-radius: 6px;
    background: #0f766e;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    flex: 0 0 auto;
}

.logistics-loaner-btn {
    margin-left: 2px;
}

.logistics-view-controls {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 6px;
    padding: 4px 0 0;
    overflow: visible;
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

.home-link-area a.calendar-link,
.home-link-area button.calendar-link {
    padding: 6px 12px;
    border-radius: 6px;
    background: #2563eb;
    color: #fff;
    border: none;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
}

.home-link-area a.calendar-link:hover,
.home-link-area button.calendar-link:hover {
    background: #1d4ed8;
}

.scrollable-table-zone {
    flex: 1 1 0;
    min-height: 0;
    padding-left: 10px;
    padding-right: 10px;
    overflow: auto;
    background: #e2e8f0;
}

.list-page-scale .scrollable-table-zone {
    flex: 1 1 0;
    min-height: 0;
}

.abroad-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0 10px;
    flex-shrink: 0;
}

.abroad-toolbar-sm {
    justify-content: space-between;
}

.abroad-overseas-rma-btn {
    margin-left: auto;
    flex-shrink: 0;
}

.abroad-toolbar-main {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.auto-update-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
    padding: 4px 2px;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}

.auto-update-toggle input {
    position: absolute;
    opacity: 0;
    width: 1px;
    height: 1px;
    pointer-events: none;
}

.auto-update-track {
    position: relative;
    width: 42px;
    height: 22px;
    border-radius: 999px;
    background: #94a3b8;
    transition: background 0.15s ease;
    flex-shrink: 0;
}

.auto-update-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.35);
    transition: transform 0.15s ease;
}

.auto-update-toggle.on .auto-update-track,
.auto-update-toggle:has(input:checked) .auto-update-track {
    background: #2563eb;
}

.auto-update-toggle.on .auto-update-thumb,
.auto-update-toggle:has(input:checked) .auto-update-thumb {
    transform: translateX(20px);
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

.shipping-paste-btn {
    margin-left: 8px;
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
    padding: 12px 16px;
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
    width: min(96vw, 1840px);
    max-height: min(96vh, 1100px);
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
    max-height: min(42vh, 420px);
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

.abroad-preview-table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    font-weight: 700;
    table-layout: fixed;
}

.abroad-preview-table th,
.abroad-preview-table td {
    border: 1px solid #94a3b8;
    padding: 6px 8px;
    text-align: left;
    vertical-align: middle;
}

.abroad-preview-table th {
    position: sticky;
    top: 0;
    background: #ffffff;
    color: #111827;
    z-index: 1;
    white-space: normal;
    line-height: 1.25;
    min-width: 110px;
}

.abroad-preview-table th:nth-child(1),
.abroad-preview-table td:nth-child(1) { min-width: 160px; width: 160px; }
.abroad-preview-table th:nth-child(2),
.abroad-preview-table td:nth-child(2) { min-width: 110px; width: 110px; }
.abroad-preview-table th:nth-child(3),
.abroad-preview-table td:nth-child(3) { min-width: 100px; width: 100px; }
.abroad-preview-table th:nth-child(4),
.abroad-preview-table td:nth-child(4) { min-width: 90px; width: 90px; }
.abroad-preview-table th:nth-child(5),
.abroad-preview-table td:nth-child(5) { min-width: 200px; width: 200px; }
.abroad-preview-table th:nth-child(6),
.abroad-preview-table td:nth-child(6) { min-width: 130px; width: 130px; }
.abroad-preview-table th:nth-child(7),
.abroad-preview-table td:nth-child(7) { min-width: 150px; width: 150px; }
.abroad-preview-table th:nth-child(8),
.abroad-preview-table td:nth-child(8) { min-width: 150px; width: 150px; }
.abroad-preview-table th:nth-child(9),
.abroad-preview-table td:nth-child(9) { min-width: 220px; width: 220px; }
.abroad-preview-table th:nth-child(10),
.abroad-preview-table td:nth-child(10) { min-width: 180px; width: 180px; }
.abroad-preview-table th:nth-child(11),
.abroad-preview-table td:nth-child(11) { min-width: 72px; width: 72px; }

.abroad-preview-table th.abroad-rma-header {
    background: #ffff00;
}

.abroad-preview-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.abroad-cell-input {
    width: 100%;
    min-width: 0;
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
    font-weight: 700 !important;
}

:deep(#myLargeTable th.logistics-loaner-col-100),
:deep(#myLargeTable td.logistics-loaner-col-100) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    box-sizing: border-box;
}

:deep(#myLargeTable th.logistics-loaner-col-200),
:deep(#myLargeTable td.logistics-loaner-col-200) {
    width: 200px;
    min-width: 200px;
    max-width: 200px;
    box-sizing: border-box;
}

#myLargeTable tbody td {
    background: #f5f5f5;
    font-weight: 700 !important;
}

:deep(#myLargeTable),
:deep(#myLargeTable th),
:deep(#myLargeTable td),
:deep(#myLargeTable input),
:deep(#myLargeTable select),
:deep(#myLargeTable textarea),
:deep(#myLargeTable button) {
    font-weight: 700 !important;
}

.scrollable-table-zone #myLargeTable {
    font-weight: 700;
}

.table-row {
    cursor: pointer;
}

/* Invoice / Closing / Logistics: RMA# の「貸出機案件」バッジ（文字は黒） */
.loaner-case-rma-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
    color: #fff;
}

.loaner-case-rma-badge--loaner {
    background: #dc2626;
    color: #fff;
}

.loaner-case-rma-badge--legacy {
    background: #16a34a;
    color: #fff;
}

.promotion-ready-row td {
    background-color: #fef9c3 !important;
}

.promotion-ready-row.active-row td {
    color: rgb(255, 255, 255) !important;
    background-color: #7e25eb !important;
}

.promotion-ready-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #f59e0b;
    color: #111827;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.remand-order-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.loaner-status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.loaner-status-badge--returned {
    background: #dc2626;
    color: #fff;
}

.loaner-status-badge--acceptance {
    background: #facc15;
    color: #111827;
}

.loaner-status-badge--pre-complete {
    background: #7dd3fc;
    color: #0c4a6e;
}

.active-row td {
    color: rgb(255, 255, 255) !important;
    background-color: #7e25eb !important;
}

#myLargeTable td.status-cell-underline-350 {
    border-bottom: 5px solid #facc15;
}

#myLargeTable td.status-cell-underline-385 {
    border-bottom: 5px solid #2563eb;
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