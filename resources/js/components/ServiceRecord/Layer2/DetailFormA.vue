<template>
    <div class="detail-form">
        <!-- <div class="detail-form-header">
            <h2>詳細フォーム A</h2>
            <button type="button" class="reset-layout-btn" @click="resetPaneSizes">デフォルト比率に戻す</button>
        </div> -->

        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <Splitpanes v-else class="default-theme detail-splitpanes" @resized="syncOuterPaneSizes">
            <Pane class="detail-pane detail-pane-left" :size="leftPaneSize" :min-size="50">
                <div class="left-column-layout">
                    <div
                        class="left-fixed-header"
                        :class="{ 'left-fixed-header-with-loaner': showLinkedLoaners }"
                    >
                            <div class="left-top-section left-top-section-main">
                                <div class="detail-top-grid">
                                <section class="section-card detail-card">
                                    <dl class="info-grid compact-info-grid">
                                        <dt>受領日</dt>
                                        <dd>
                                            <DateInputWithToday
                                                class="field-input"
                                                :model-value="toDateInputValue(draftRecord?.receivedDate ?? record?.receivedDate)"
                                                @update:model-value="updateDraftDateValue('receivedDate', $event)"
                                            />
                                        </dd>
                                        <dt>status</dt>
                                        <dd>
                                            <template v-if="isWaitingListRecord">
                                                <span class="status-empty">—（waiting_list / status=-1）</span>
                                            </template>
                                            <select
                                                v-else
                                                class="field-select"
                                                :value="draftRecord?.status ?? record?.status ?? ''"
                                                @change="updateNumericDraftValue('status', $event.target.value)"
                                            >
                                                <option value="">選択してください</option>
                                                <option
                                                    v-for="status in statusOptions"
                                                    :key="status.processID_new"
                                                    :value="status.processID_new"
                                                >
                                                    {{ loanerStatusOptionLabel(status) }}
                                                </option>
                                            </select>
                                        </dd>
                                        <dt class="dt-product-name">製品名</dt>
                                        <dd class="dd-product-name">
                                            <button type="button" class="field-button" @click="openServiceMasterSelect">
                                                {{ draftRecord?.productName || record?.productName || '選択してください' }}
                                            </button>
                                            <span class="entity-id-display">
                                                <span class="entity-id-label">entityID</span>
                                                <span class="entity-id-value">{{ displayEntityId }}</span>
                                            </span>
                                        </dd>
                                        <dt>S/N</dt>
                                        <dd>
                                            <input
                                                type="text"
                                                class="field-input"
                                                :value="draftRecord?.SN ?? record?.SN ?? ''"
                                                @input="updateDraftValue('SN', $event.target.value)"
                                            >
                                        </dd>
                                        <dt>作業内容</dt>
                                        <dd>
                                            <select
                                                class="field-select"
                                                :value="draftRecord?.returnCode ?? record?.returnCode ?? ''"
                                                @change="updateNumericDraftValue('returnCode', $event.target.value)"
                                            >
                                                <option value="">選択してください</option>
                                                <option
                                                    v-for="returnCode in page.props.returnCodes ?? []"
                                                    :key="returnCode.id"
                                                    :value="returnCode.id"
                                                >
                                                    {{ returnCode.description }} ({{ returnCode.id }})
                                                </option>
                                            </select>
                                        </dd>
                                        <dt>作業担当</dt>
                                        <dd>
                                            <select
                                                class="field-select"
                                                :value="draftRecord?.laborID ?? record?.laborID ?? ''"
                                                @change="updateNumericDraftValue('laborID', $event.target.value)"
                                            >
                                                <option value="">選択してください</option>
                                                <option
                                                    v-for="labor in page.props.labors ?? []"
                                                    :key="labor.laborID"
                                                    :value="labor.laborID"
                                                >
                                                    {{ labor.laborName }} ({{ labor.laborID }})
                                                </option>
                                            </select>
                                        </dd>
                                        <dt></dt>
                                        <dd class="dd-a2la-actions">
                                            <button
                                                type="button"
                                                class="a2la-toggle"
                                                :class="{ active: isA2laOn }"
                                                @click="toggleA2la"
                                            >
                                                {{ isA2laOn ? 'A2LA' : 'A2LA' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="maintenance-search-btn"
                                                :disabled="maintenanceSearchLoading"
                                                @click="openMaintenanceSearchDialog"
                                            >
                                                保守検索
                                            </button>
                                        </dd>
                                    </dl>
                                </section>

                                <section class="section-card detail-card detail-card-rma-order">
                                    <dl class="info-grid compact-info-grid rma-order-grid">
                                        <dt>RMA</dt>
                                        <dd>
                                            <input type="text" class="field-input" :value="draftRecord?.RMA ?? record?.RMA ?? ''" @input="updateDraftValue('RMA', $event.target.value)">
                                        </dd>
                                        <dt>WO</dt>
                                        <dd>
                                            <input type="text" class="field-input" :value="draftRecord?.sm_workorder ?? record?.sm_workorder ?? ''" @input="updateDraftValue('sm_workorder', $event.target.value)">
                                        </dd>
                                        <dt>QUOTE</dt>
                                        <dd>
                                            <input type="text" class="field-input" :value="draftRecord?.sm_quote ?? record?.sm_quote ?? ''" @input="updateDraftValue('sm_quote', $event.target.value)">
                                        </dd>
                                        <dt>coNum</dt>
                                        <dd>
                                            <input type="text" class="field-input" :value="draftRecord?.coNum ?? record?.coNum ?? ''" @input="updateDraftValue('coNum', $event.target.value)">
                                        </dd>
                                        <dt>見積 #</dt>
                                        <dd class="dd-inline-fields dd-order-num">
                                            <input
                                                type="text"
                                                class="field-input"
                                                :value="draftRecord?.quoteNum ?? record?.quoteNum ?? ''"
                                                @input="updateDraftValue('quoteNum', $event.target.value)"
                                            >
                                            <DateInputWithToday
                                                class="field-input field-date"
                                                title="quoteDate"
                                                :model-value="toDateInputValue(draftRecord?.quoteDate ?? record?.quoteDate)"
                                                @update:model-value="updateDraftDateValue('quoteDate', $event)"
                                            />
                                        </dd>
                                        <dt>受注 #</dt>
                                        <dd class="dd-inline-fields dd-order-num">
                                            <input
                                                type="text"
                                                class="field-input"
                                                :value="draftRecord?.orderNum ?? record?.orderNum ?? ''"
                                                @input="updateDraftValue('orderNum', $event.target.value)"
                                            >
                                            <DateInputWithToday
                                                class="field-input field-date"
                                                title="orderDate"
                                                :model-value="toDateInputValue(draftRecord?.orderDate ?? record?.orderDate)"
                                                @update:model-value="updateDraftDateValue('orderDate', $event)"
                                            />
                                        </dd>
                                        <dt>注文 #</dt>
                                        <dd>
                                            <input
                                                type="text"
                                                class="field-input"
                                                :value="draftRecord?.poNum ?? record?.poNum ?? ''"
                                                @input="updateDraftValue('poNum', $event.target.value)"
                                            >
                                        </dd>
                                    </dl>
                                </section>

                                <section class="section-card detail-card detail-card-misc">
                                    <div class="misc-block">
                                        <label class="misc-field">
                                            <span>海外RMA：</span>
                                            <input
                                                type="text"
                                                class="field-input"
                                                :value="draftRecord?.rmaNumOverSea ?? record?.rmaNumOverSea ?? ''"
                                                @input="updateDraftValue('rmaNumOverSea', $event.target.value)"
                                            >
                                        </label>
                                        <label class="misc-field">
                                            <span>海外発送日：</span>
                                            <DateInputWithToday
                                                class="field-input"
                                                :model-value="toDateInputValue(draftRecord?.sentOut ?? record?.sentOut)"
                                                @update:model-value="updateDraftDateValue('sentOut', $event)"
                                            />
                                        </label>
                                    </div>

                                    <div class="misc-block">
                                        <label class="misc-field">
                                            <span>出荷日：</span>
                                            <DateInputWithToday
                                                class="field-input"
                                                :model-value="toDateInputValue(draftRecord?.shippingOut_requiredDate ?? record?.shippingOut_requiredDate)"
                                                @update:model-value="updateDraftDateValue('shippingOut_requiredDate', $event)"
                                            />
                                        </label>
                                        <button type="button" class="yayoi-search-btn">弥生検索</button>
                                    </div>

                                    <div class="misc-block misc-block-incidents">
                                        <button
                                            type="button"
                                            class="incidents-header"
                                            @click="openIncidentSelect"
                                        >
                                            Incidents
                                        </button>
                                        <input
                                            type="text"
                                            class="field-input incidents-input"
                                            :value="draftRecord?.incident ?? record?.incident ?? ''"
                                            @input="updateNumericDraftValue('incident', $event.target.value)"
                                        >
                                    </div>
                                </section>

                                </div>

                                <section class="section-card price-adjust-row">
                                    <div class="price-adjust-main">
                                        <span class="price-adjust-label">価格</span>
                                        <strong class="price-adjust-value">{{ formatPrice(displayPrice) }}</strong>
                                    </div>
                                    <div class="price-adjust-actions">
                                        <button
                                            type="button"
                                            class="action-btn action-btn-primary"
                                            :disabled="!record?.orderID || priceAdjustSaving"
                                            @click="openPriceAdjustDialog"
                                        >
                                            価格調整
                                        </button>
                                        <div class="price-adjust-delta">
                                            <span class="price-adjust-label">調整額</span>
                                            <strong>{{ formatSignedAmount(displayAdjustmentAmount) }}</strong>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="action-btn"
                                        @click="showGalleryDialog = true"
                                    >
                                        Gallery
                                    </button>
                                    <button
                                        type="button"
                                        class="action-btn"
                                        :disabled="emailDraftCreating"
                                        @click="openEmailDraftDialog"
                                    >
                                        Email
                                    </button>
                                    <label class="price-adjust-symptoms">
                                        <input
                                            type="text"
                                            class="field-input symptoms-input"
                                            placeholder="symptoms入力"
                                            :value="draftRecord?.symptoms ?? record?.symptoms ?? ''"
                                            @input="updateDraftValue('symptoms', $event.target.value)"
                                        >
                                    </label>
                                </section>
                            </div>

                            <section
                                v-if="showLinkedLoaners"
                                class="section-card detail-card linked-loaner-card"
                                :class="{ 'linked-loaner-card-has-items': loaners.length > 0 }"
                            >
                                <div class="section-header">
                                    <h3>loaner案件（{{ loaners.length }}件）</h3>
                                </div>
                                <!-- <p class="linked-loaner-help">
                                    parentID = この service 案件の orderID（{{ record?.orderID }}）の loaner / waiting_list
                                </p> -->
                                <div v-if="loaners.length" class="attachment-table-wrap">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>orderID</th>
                                                <th>order_type</th>
                                                <th>status</th>
                                                <th>productName</th>
                                                <th>SN</th>
                                                <th>price</th>
                                                <th>期間</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="loaner in loaners" :key="loaner.orderID">
                                                <td>{{ loaner.orderID }}</td>
                                                <td>{{ loaner.order_type || '—' }}</td>
                                                <td>
                                                    <template v-if="loaner.order_type === 'waiting_list'">—</template>
                                                    <template v-else>{{ loaner.status_label || loaner.status || '—' }}</template>
                                                </td>
                                                <td>{{ loaner.productName || '—' }}</td>
                                                <td>{{ loaner.SN || '—' }}</td>
                                                <td>{{ formatPrice(loanerDisplayPrice(loaner)) }}</td>
                                                <td>
                                                    <template v-if="loaner.plannedSentDate || loaner.plannedReturnedDate">
                                                        {{ loaner.plannedSentDate || '—' }}
                                                        〜
                                                        {{ loaner.plannedReturnedDate || '—' }}
                                                    </template>
                                                    <template v-else>—</template>
                                                </td>
                                                <td>
                                                    <a
                                                        v-if="loaner.orderID"
                                                        class="loaner-period-link"
                                                        :href="loanerDetailUrl(loaner.orderID)"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        詳細
                                                    </a>
                                                    <span v-else class="loaner-period-missing">期間なし</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="empty-message">関連loaner案件はありません。</p>
                            </section>
                    </div>

                    <Splitpanes
                        class="default-theme detail-splitpanes detail-splitpanes-left"
                        horizontal
                        @resized="syncLeftPaneSizes"
                    >
                        <Pane
                            class="detail-pane detail-pane-contacts"
                            :size="leftTopPaneSize"
                            :min-size="0"
                        >
                            <div class="left-top-section left-top-section-contacts">
                                <div class="detail-bottom-grid">
                                <section class="section-card detail-card detail-card-input">
                                    <div class="section-header dealer-header">
                                        <button type="button" class="action-btn action-btn-primary" @click="openDealerSelect">依頼社選択</button>
                                    </div>
                                    <div class="input-grid">
                                        <label class="input-field">
                                            <input type="text" placeholder="会社名" :value="draftRecord?.dealer ?? record?.dealer ?? ''" @input="updateDraftValue('dealer', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="部署名" :value="draftRecord?.dealer_depart ?? record?.dealer_depart ?? ''" @input="updateDraftValue('dealer_depart', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="担当者" :value="draftRecord?.contactPerson ?? record?.contactPerson ?? ''" @input="updateDraftValue('contactPerson', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="電話番等" :value="draftRecord?.phone ?? record?.phone ?? ''" @input="updateDraftValue('phone', $event.target.value)">
                                        </label>
                                        <label class="input-field input-field-span2">
                                            <input type="text" placeholder="E-mail" :value="draftRecord?.email ?? record?.email ?? ''" @input="updateDraftValue('email', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span>〒</span>
                                                <input
                                                    type="text"
                                                    placeholder="〒"
                                                    :value="draftRecord?.zipcode ?? record?.zipcode ?? ''"
                                                    @input="onZipcodeFieldInput('dealer', $event.target.value)"
                                                >
                                            </div>
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="都道府県" :value="draftRecord?.address1 ?? record?.address1 ?? ''" @input="updateDraftValue('address1', $event.target.value)">
                                        </label>
                                        <label class="input-field input-field-span2">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span>&nbsp;&nbsp;&nbsp;</span>
                                                <input type="text" placeholder="住所" :value="draftRecord?.address2 ?? record?.address2 ?? ''" @input="updateDraftValue('address2', $event.target.value)">
                                            </div>
                                        </label>
                                    </div>
                                </section>

                                <section class="section-card detail-card detail-card-input">
                                    <div class="section-header">
                                        <h3>E/U</h3>
                                    </div>
                                    <div class="input-grid">
                                        <label class="input-field">
                                            <input type="text" placeholder="E/U会社名" :value="draftRecord?.endUser ?? record?.endUser ?? ''" @input="updateDraftValue('endUser', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="E/U部署名" :value="draftRecord?.endUser_depart ?? record?.endUser_depart ?? ''" @input="updateDraftValue('endUser_depart', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="担当者" :value="draftRecord?.endUser_contactPerson ?? record?.endUser_contactPerson ?? ''" @input="updateDraftValue('endUser_contactPerson', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="電話番等" :value="draftRecord?.endUser_phone ?? record?.endUser_phone ?? ''" @input="updateDraftValue('endUser_phone', $event.target.value)">
                                        </label>
                                        <label class="input-field input-field-span2">
                                            <input type="text" placeholder="E-mail" :value="draftRecord?.endUser_email ?? record?.endUser_email ?? ''" @input="updateDraftValue('endUser_email', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span>〒</span>
                                                <input
                                                    type="text"
                                                    placeholder="〒"
                                                    :value="draftRecord?.endUser_zipcode ?? record?.endUser_zipcode ?? ''"
                                                    @input="onZipcodeFieldInput('endUser', $event.target.value)"
                                                >
                                            </div>
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="都道府県" :value="draftRecord?.endUser_address1 ?? record?.endUser_address1 ?? ''" @input="updateDraftValue('endUser_address1', $event.target.value)">
                                        </label>
                                        <label class="input-field input-field-span2">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span>&nbsp;&nbsp;&nbsp;</span>
                                                <input type="text" placeholder="住所" :value="draftRecord?.endUser_address2 ?? record?.endUser_address2 ?? ''" @input="updateDraftValue('endUser_address2', $event.target.value)">
                                            </div>
                                        </label>
                                    </div>
                                </section>

                                <section class="section-card detail-card detail-card-input">
                                    <div class="section-header delivery-header">
                                        <h3>発送先</h3>
                                        <div class="section-actions">
                                            <button type="button" class="action-btn" @click="copyStakeholderToDelivery('dealer')">
                                                Copy Dealer
                                            </button>
                                            <button type="button" class="action-btn" @click="copyStakeholderToDelivery('endUser')">
                                                Copy EndUser
                                            </button>
                                        </div>
                                    </div>
                                    <div class="input-grid">
                                        <label class="input-field">
                                            <input type="text" placeholder="発送先会社名" :value="draftRecord?.deliveryDestination_company ?? record?.deliveryDestination_company ?? ''" @input="updateDraftValue('deliveryDestination_company', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="発送先部署名" :value="draftRecord?.deliveryDestination_depart ?? record?.deliveryDestination_depart ?? ''" @input="updateDraftValue('deliveryDestination_depart', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="担当者" :value="draftRecord?.deliveryDestination_contactPerson ?? record?.deliveryDestination_contactPerson ?? ''" @input="updateDraftValue('deliveryDestination_contactPerson', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="電話番等" :value="draftRecord?.deliveryDestination_phone ?? record?.deliveryDestination_phone ?? ''" @input="updateDraftValue('deliveryDestination_phone', $event.target.value)">
                                        </label>
                                        <label class="input-field input-field-span2">
                                            <input type="text" placeholder="E-mail" :value="draftRecord?.deliveryDestination_email ?? record?.deliveryDestination_email ?? ''" @input="updateDraftValue('deliveryDestination_email', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span>〒</span>
                                                <input
                                                    type="text"
                                                    placeholder="〒"
                                                    :value="draftRecord?.deliveryDestination_zipcode ?? record?.deliveryDestination_zipcode ?? ''"
                                                    @input="onZipcodeFieldInput('delivery', $event.target.value)"
                                                >
                                            </div>
                                        </label>
                                        <label class="input-field">
                                            <input type="text" placeholder="都道府県" :value="draftRecord?.deliveryDestination_address1 ?? record?.deliveryDestination_address1 ?? ''" @input="updateDraftValue('deliveryDestination_address1', $event.target.value)">
                                        </label>
                                        <label class="input-field input-field-span2">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span>&nbsp;&nbsp;&nbsp;</span>
                                                <input type="text" placeholder="住所" :value="draftRecord?.deliveryDestination_address2 ?? record?.deliveryDestination_address2 ?? ''" @input="updateDraftValue('deliveryDestination_address2', $event.target.value)">
                                            </div>
                                        </label>
                                    </div>
                                </section>
                                </div>
                            </div>
                        </Pane>

                    <Pane class="detail-pane detail-pane-left-bottom" :size="leftBottomPaneSize" :min-size="10">
                        <Splitpanes class="default-theme detail-splitpanes detail-splitpanes-bottom" @resized="syncBottomPaneSizes">
                            <Pane class="detail-pane detail-pane-notes" :size="notesPaneSize" :min-size="25">
                                <div class="pane-content pane-content-scroll">
                                    <section class="section-card section-card-compact section-card-fill notes-card">
                                        <div class="section-header">
                                            <div class="notes-header-title">
                                                <h3>Notes（{{ sharedNotes.length }}件）</h3>
                                                <span class="notes-tbc-count">要確認　({{ tbcNotesCount }}件)</span>
                                            </div>
                                            <div class="section-actions">
                                                <button type="button" class="action-btn" :disabled="!selectedNoteId" :title="noteEditDeleteTitle" @click="openNoteEdit">編集</button>
                                                <button type="button" class="action-btn action-btn-danger" :disabled="!selectedNoteId" :title="noteEditDeleteTitle" @click="openNoteDelete">削除</button>
                                                <button type="button" class="action-btn" @click="openEmailNoteLink">メール紐づけ</button>
                                                <button type="button" class="action-btn action-btn-primary" @click="openNoteCreate">新規追加</button>
                                            </div>
                                        </div>
                                        <NotesTable
                                            v-model:selected-id="selectedNoteId"
                                            :notes="sharedNotes"
                                            :record-order-id="record?.orderID"
                                            :show-confirm-status="true"
                                            :current-user-name="authUserName"
                                            @edit="openNoteEdit"
                                        />
                                    </section>
                                </div>
                            </Pane>

                            <Pane class="detail-pane detail-pane-parts" :size="partsPaneSize" :min-size="25">
                                <div class="pane-content pane-content-scroll">
                                    <section class="section-card section-card-compact section-card-fill parts-card">
                                        <div class="section-header">
                                            <h3>Parts（{{ parts.length }}件）</h3>
                                            <div class="section-actions">
                                                <span class="parts-total-inline">合計 {{ formatPrice(partsPriceTotal) }}</span>
                                                <button type="button" class="action-btn action-btn-danger" :disabled="!selectedPartId" :title="selectedPartId ? '' : '部品を選択してください'" @click="openPartDelete">削除</button>
                                                <button type="button" class="action-btn action-btn-primary" @click="openPartCreate">新規追加</button>
                                            </div>
                                        </div>
                                        <div v-if="parts.length" class="attachment-table-wrap">
                                            <table class="data-table parts-table">
                                                <thead>
                                                    <tr>
                                                        <th>Part ID</th>
                                                        <th>部品名</th>
                                                        <th>説明</th>
                                                        <th>price_discounted</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="part in parts"
                                                        :key="part.id"
                                                        class="table-row"
                                                        :class="{ 'active-row': selectedPartId === part.id }"
                                                        @click="selectedPartId = part.id"
                                                    >
                                                        <td>{{ part.partID }}</td>
                                                        <td>{{ part.part_master?.partName || '—' }}</td>
                                                        <td class="text-cell">{{ part.part_master?.description || '—' }}</td>
                                                        <td>{{ formatPrice(partVersionPrice(part)) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p v-else class="empty-message">Parts がありません。</p>
                                    </section>
                                </div>
                            </Pane>
                        </Splitpanes>
                    </Pane>
                </Splitpanes>
                </div>
            </Pane>

            <Pane class="detail-pane detail-pane-files" :size="rightPaneSize" :min-size="10">
                <div class="pane-content">
                    <section class="section-card section-card-files">
                        <div class="section-header">
                            <h3>
                                Files（書類 {{ sortedFiles.length }}件
                                ／ 撮影画像 {{ capturedImages.length }}件）
                            </h3>
                            <div class="section-actions">
                                <button type="button" class="action-btn action-btn-danger" :disabled="!selectedFileId" :title="selectedFileId ? '' : 'ファイルを選択してください'" @click="openFileDelete">削除</button>
                                <button type="button" class="action-btn action-btn-primary" @click="openFileCreate">新規追加</button>
                            </div>
                        </div>
                        <div class="captured-images-panel">
                            <button
                                type="button"
                                class="captured-toggle"
                                :class="{ 'has-images': capturedImages.length > 0 }"
                                @click="capturedImagesOpen = !capturedImagesOpen"
                            >
                                <span>撮影画像（{{ capturedImages.length }}件）</span>
                                <span class="captured-toggle-icon">{{ capturedImagesOpen ? '▲' : '▼' }}</span>
                            </button>
                            <div v-show="capturedImagesOpen" class="captured-images-body">
                                <AssociatedCapturedImages
                                    :images="capturedImages"
                                    @changed="emit('reload-attachments')"
                                />
                                <p v-if="!capturedImages.length" class="empty-message">撮影画像がありません。</p>
                            </div>
                        </div>

                        <div
                            v-if="showFileDropzone"
                            class="file-dropzone"
                            :class="{
                                'file-dropzone-active': fileDropActive,
                                'file-dropzone-disabled': !canDropFiles || fileDropUploading,
                            }"
                            @dragenter.prevent="onFileDragEnter"
                            @dragover.prevent="onFileDragOver"
                            @dragleave.prevent="onFileDragLeave"
                            @drop.prevent="onFileDrop"
                            @click="openFileDropPicker"
                        >
                            <input
                                ref="fileDropInputEl"
                                type="file"
                                class="file-drop-input"
                                multiple
                                @change="onFileDropInputChange"
                            >
                            <div class="file-dropzone-top" @click.stop>
                                <p class="file-dropzone-title">
                                    {{ fileDropUploading ? `アップロード中...（${fileDropProgress}）` : 'ファイルをドロップ、またはクリックして選択' }}
                                </p>
                                <button
                                    type="button"
                                    class="action-btn file-dropzone-cancel"
                                    :disabled="fileDropUploading"
                                    @click="closeFileDropzone"
                                >
                                    閉じる
                                </button>
                            </div>
                            <p class="file-dropzone-help">
                                Explorer から任意ファイル（.eml / .msg / PDF / 画像など）を追加できます
                            </p>
                            <p v-if="fileDropError" class="file-dropzone-error" @click.stop>{{ fileDropError }}</p>
                        </div>

                        <div class="files-list-wrap">
                            <AttachedFileItem
                                v-for="(file, index) in sortedFiles"
                                :key="file.id"
                                :file="file"
                                :order-id="record?.orderID"
                                :selected="selectedFileId === file.id"
                                :can-move-up="index > 0"
                                :can-move-down="index < sortedFiles.length - 1"
                                :sorting="fileSortSaving"
                                @select="selectedFileId = file.id"
                                @move="(direction) => moveFile(file.id, direction)"
                                @sort-num-change="(sortNum) => updateFileSortNum(file.id, sortNum)"
                            />
                            <p v-if="!sortedFiles.length" class="empty-message">書類ファイルがありません。</p>
                        </div>
                    </section>
                </div>
            </Pane>
        </Splitpanes>

        <div v-if="showPriceAdjustDialog" class="confirm-overlay" @click.self="closePriceAdjustDialog">
            <div class="confirm-panel" @click.stop>
                <div class="confirm-header">
                    <h3>価格調整</h3>
                    <button type="button" class="close-btn" @click="closePriceAdjustDialog">×</button>
                </div>
                <div class="confirm-body">
                    <p class="confirm-current-price">
                        元価格: {{ formatPrice(basePrice) }}
                        ／ 表示価格: {{ formatPrice(displayPrice) }}
                    </p>
                    <label class="confirm-field">
                        調整額
                        <input
                            v-model="priceAdjustForm.amount"
                            type="number"
                            class="confirm-input"
                            placeholder="例: 5000（表示は 元価格 + 調整額）"
                        >
                    </label>
                    <label class="confirm-field">
                        調整理由
                        <textarea
                            v-model="priceAdjustForm.reason"
                            rows="4"
                            class="confirm-textarea"
                            placeholder="調整理由を入力"
                        />
                    </label>
                    <p v-if="priceAdjustError" class="confirm-error">{{ priceAdjustError }}</p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="action-btn" :disabled="priceAdjustSaving" @click="closePriceAdjustDialog">
                        キャンセル
                    </button>
                    <button type="button" class="action-btn action-btn-primary" :disabled="priceAdjustSaving" @click="confirmPriceAdjust">
                        {{ priceAdjustSaving ? '保存中...' : 'OK' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showMaintenanceSearchDialog" class="confirm-overlay" @click.self="closeMaintenanceSearchDialog">
            <div class="confirm-panel maintenance-search-panel" @click.stop>
                <div class="confirm-header">
                    <h3>保守契約検索</h3>
                    <button type="button" class="close-btn" @click="closeMaintenanceSearchDialog">×</button>
                </div>
                <div class="confirm-body">
                    <div class="maintenance-search-fields">
                        <label class="confirm-field">
                            productName
                            <input v-model="maintenanceSearchForm.productName" type="text" class="confirm-input">
                        </label>
                        <label class="confirm-field">
                            SN
                            <input v-model="maintenanceSearchForm.SN" type="text" class="confirm-input">
                        </label>
                        <label class="confirm-field">
                            dealer
                            <input v-model="maintenanceSearchForm.dealer" type="text" class="confirm-input">
                        </label>
                        <button
                            type="button"
                            class="action-btn action-btn-primary maintenance-research-btn"
                            :disabled="maintenanceSearchLoading"
                            @click="runMaintenanceSearch"
                        >
                            {{ maintenanceSearchLoading ? '検索中...' : '再検索' }}
                        </button>
                    </div>
                    <p class="maintenance-search-hint">
                        条件: productName先頭5文字 / SN完全一致 / dealer含む　／　有効契約（expireDate &gt; 今日）
                    </p>
                    <p v-if="maintenanceSearchError" class="confirm-error">{{ maintenanceSearchError }}</p>
                    <div v-else-if="maintenanceSearchDone && maintenanceContracts.length" class="maintenance-table-wrap">
                        <table class="maintenance-table">
                            <thead>
                                <tr>
                                    <th style="width: 36px;"></th>
                                    <th>dealer</th>
                                    <th>契約種別</th>
                                    <th>instrumentName</th>
                                    <th>SN</th>
                                    <th>開始</th>
                                    <th>契約終了</th>
                                    <th>RefNumber</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in maintenanceContracts"
                                    :key="row.id"
                                    :class="{ selected: isMaintenanceSelected(row.id) }"
                                    @click="toggleMaintenanceSelection(row.id)"
                                >
                                    <td style="text-align: center;" @click.stop>
                                        <input
                                            type="checkbox"
                                            :checked="isMaintenanceSelected(row.id)"
                                            @change="toggleMaintenanceSelection(row.id)"
                                        >
                                    </td>
                                    <td>{{ row.dealer || '—' }}</td>
                                    <td>{{ row.contractTypeName || row.contractTypeDescription || '—' }}</td>
                                    <td>{{ row.instrumentName || '—' }}</td>
                                    <td>{{ row.SN || '—' }}</td>
                                    <td>{{ row.startDate || '—' }}</td>
                                    <td>{{ row.expireDate || '—' }}</td>
                                    <td>{{ row.RefNumber || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else-if="maintenanceSearchDone" class="maintenance-empty">
                        条件に一致する有効な保守契約はありません。条件を編集して再検索してください。
                    </p>
                </div>
                <div class="confirm-actions">
                    <button
                        type="button"
                        class="action-btn"
                        :disabled="maintenanceNoteSaving || !selectedMaintenanceContractId"
                        @click="openSelectedMaintenanceDetail"
                    >
                        詳細を開く
                    </button>
                    <button
                        type="button"
                        class="action-btn"
                        :disabled="maintenanceNoteSaving"
                        @click="clearMaintenanceSelection"
                    >
                        選択解除
                    </button>
                    <button
                        type="button"
                        class="action-btn"
                        :disabled="maintenanceNoteSaving"
                        @click="closeMaintenanceSearchDialog"
                    >
                        キャンセル
                    </button>
                    <button
                        type="button"
                        class="action-btn action-btn-primary"
                        :disabled="maintenanceNoteSaving || !selectedMaintenanceContractId"
                        @click="confirmMaintenanceSelection"
                    >
                        {{ maintenanceNoteSaving ? '追加中...' : 'OK' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showAssignNotifyDialog"
            class="confirm-overlay assign-notify-overlay"
            @click.self="cancelAssignNotifyDialog"
        >
            <div class="confirm-panel" role="dialog" aria-modal="true" aria-labelledby="assign-notify-title" @click.stop>
                <div class="confirm-header">
                    <h3 id="assign-notify-title">アサイン通知</h3>
                </div>
                <div class="confirm-body">
                    <p>{{ assignNotifyDialogMessage }}</p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="action-btn" @click="resolveAssignNotifyDialog(false)">
                        送信しない
                    </button>
                    <button type="button" class="action-btn action-btn-primary" @click="resolveAssignNotifyDialog(true)">
                        送信する
                    </button>
                </div>
            </div>
        </div>

        <EmailDraftTypeDialog
            v-if="showEmailDraftDialog"
            :creating="emailDraftCreating"
            :error="emailDraftError"
            confirm-label="プレビュー"
            @close="closeEmailDraftDialog"
            @confirm="previewEmailDraft"
        />

        <EmailDraftPreviewDialog
            v-if="showEmailDraftPreviewDialog"
            :to="emailDraftPreview.to"
            :subject="emailDraftPreview.subject"
            :body="emailDraftPreview.bodyHtml"
            :body-html="emailDraftPreview.bodyHtml"
            :body-text="emailDraftPreview.bodyText"
            :template-label="emailDraftPreview.templateLabel"
            :associated-id="galleryAssociatedId"
            @close="showEmailDraftPreviewDialog = false"
        />

        <CapturedImageGalleryDialog
            v-if="showGalleryDialog"
            title="Gallery"
            :associatedID="galleryAssociatedId"
            :associated-id="galleryAssociatedId"
            @close="showGalleryDialog = false"
            @associated="emit('reload-attachments')"
        />
    </div>
</template>

<script setup>
import { computed, defineExpose, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import DateInputWithToday from '@/components/DateInputWithToday.vue'
import AssociatedCapturedImages from '@/components/ServiceRecord/AssociatedCapturedImages.vue'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import CapturedImageGalleryDialog from '@/components/ServiceRecord/CapturedImageGalleryDialog.vue'
import NotesTable from '@/components/ServiceRecord/NotesTable.vue'
import EmailDraftTypeDialog from '@/components/ServiceRecord/Layer3/EmailDraftTypeDialog.vue'
import EmailDraftPreviewDialog from '@/components/ServiceRecord/Layer3/EmailDraftPreviewDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { loanerStatusOptionLabel } from '@/utils/loanerStatusLabel'
import { findServiceMaster, resolveServiceWorkPrice, findPartMaster, pickMasterVersion, PAID_LOANER_RETURN_CODES } from '@/utils/resolveServiceWorkPrice'

const page = usePage()

const props = defineProps({
    record: Object,
    draftRecord: Object,
    notes: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    capturedImages: { type: Array, default: () => [] },
    parts: { type: Array, default: () => [] },
    loaners: { type: Array, default: () => [] },
    attachmentsLoading: { type: Boolean, default: false },
    attachmentsError: { type: String, default: '' },
    currentUserKanji: { type: String, default: '' },
})

const emit = defineEmits(['open-dialog', 'files-updated', 'reload-attachments'])

/** 受注日あり: その日の版 / 未定: 最新版（空文字は未定扱い） */
const priceAsOfDate = computed(() => {
    const raw = props.draftRecord?.orderDate || props.record?.orderDate || null
    if (raw == null || raw === '') return null
    const match = String(raw).match(/(\d{4}-\d{2}-\d{2})/)
    return match ? match[1] : String(raw)
})

const leftPaneSize = ref(64)
const rightPaneSize = ref(36)
const leftTopPaneSize = ref(45)
const leftBottomPaneSize = ref(55)
const notesPaneSize = ref(70)
const partsPaneSize = ref(30)
const selectedNoteId = ref(null)
const selectedPartId = ref(null)
const selectedFileId = ref(null)
const fileSortSaving = ref(false)
const showPriceAdjustDialog = ref(false)
const showMaintenanceSearchDialog = ref(false)
const showGalleryDialog = ref(false)
const showEmailDraftDialog = ref(false)
const showEmailDraftPreviewDialog = ref(false)
const emailDraftCreating = ref(false)
const emailDraftError = ref('')
const emailDraftPreview = ref({
    to: '',
    subject: '',
    bodyHtml: '',
    bodyText: '',
    templateLabel: '',
})
const capturedImagesOpen = ref(false)
const galleryAssociatedId = computed(() => props.record?.orderID ?? null)
const priceAdjustSaving = ref(false)
const priceAdjustError = ref('')
const maintenanceSearchLoading = ref(false)
const maintenanceSearchDone = ref(false)
const maintenanceSearchError = ref('')
const maintenanceNoteSaving = ref(false)
const maintenanceContracts = ref([])
const selectedMaintenanceContractId = ref(null)
const maintenanceSearchForm = reactive({
    productName: '',
    SN: '',
    dealer: '',
})
const priceAdjustForm = reactive({
    amount: '',
    reason: '',
})
const sessionAdjustmentAmount = ref(null)
const fileDropInputEl = ref(null)
const showFileDropzone = ref(false)
const fileDropActive = ref(false)
const fileDropUploading = ref(false)
const fileDropError = ref('')
const fileDropProgress = ref('')
const fileDragDepth = ref(0)

const authUserName = computed(() => {
    const fromProp = String(props.currentUserKanji ?? '').trim()
    if (fromProp) return fromProp

    const fromPage = String(page.props.authUser?.kanji_name ?? '').trim()
    if (fromPage) return fromPage

    if (typeof document !== 'undefined') {
        return String(document.querySelector('meta[name="auth-kanji-name"]')?.content ?? '').trim()
    }

    return ''
})
const sharedNotes = computed(() =>
    (props.notes ?? []).filter(note => !isPersonalNote(note)),
)
const tbcNotesCount = computed(() =>
    sharedNotes.value.filter((note) => {
        const tbc = note?.tbc === true || note?.tbc === 1 || note?.tbc === '1'
        const done = note?.done === true || note?.done === 1 || note?.done === '1'
        return tbc && !done
    }).length,
)
const selectedNote = computed(() => sharedNotes.value.find(n => Number(n.id) === Number(selectedNoteId.value)))

function isPersonalNote(note) {
    return note?.personal === true || note?.personal === 1 || note?.personal === '1'
}

const recordOrderType = computed(() =>
    props.draftRecord?.order_type ?? props.record?.order_type ?? null,
)
const isLoanerRecord = computed(() => recordOrderType.value === 'loaner')
const isWaitingListRecord = computed(() => recordOrderType.value === 'waiting_list')
const isServiceRecord = computed(() =>
    recordOrderType.value === 'service'
    || recordOrderType.value == null
    || recordOrderType.value === '',
)
const showLinkedLoaners = computed(() => isServiceRecord.value)
const statusOptions = computed(() => {
    if (isLoanerRecord.value) {
        return page.props.statusesLoaner ?? []
    }
    return page.props.statuses ?? []
})

function loanerDetailUrl(orderId) {
    const returnUrl = typeof window !== 'undefined' ? window.location.href : ''
    const params = returnUrl ? `?returnUrl=${encodeURIComponent(returnUrl)}` : ''
    return `${page.props.appBaseUrl}/servicerecord/loaner/detail/${orderId}${params}`
}

function isNoteOwner(note) {
    if (!note) return false

    const who = String(note.whoWrote ?? '').trim()
    if (!who) return false

    if (note.is_mine === true || note.is_mine === 1 || note.is_mine === '1') {
        return true
    }

    const me = authUserName.value
    return me !== '' && me === who
}

const canModifySelectedNote = computed(() => !!selectedNote.value && isNoteOwner(selectedNote.value))

const noteEditDeleteTitle = computed(() => {
    if (!selectedNoteId.value) return 'Note を選択してください'
    if (!selectedNote.value) return 'Note を選択してください'
    if (!isNoteOwner(selectedNote.value)) {
        return `自分が書いた Note のみ編集・削除できます（ログイン: ${authUserName.value || '不明'} / 記入者: ${selectedNote.value.whoWrote || '不明'}）`
    }
    return ''
})

const displayEntityId = computed(() => {
    const entityId = props.draftRecord?.entityID ?? props.record?.entityID
    if (entityId != null && entityId !== '') {
        return entityId
    }

    const service = findServiceMaster(page.props.servicesMaster, {
        productName: props.draftRecord?.productName ?? props.record?.productName,
        entityID: props.draftRecord?.entityID ?? props.record?.entityID,
        serviceID: props.draftRecord?.serviceID ?? props.record?.serviceID,
    }, priceAsOfDate.value)
    return service?.entityID ?? '—'
})

function getDefaultPaneSizes() {
    const width = window.innerWidth
    if (width >= 1800) return { left: 68, right: 32 }
    if (width >= 1500) return { left: 64, right: 36 }
    if (width >= 1300) return { left: 60, right: 40 }
    if (width >= 1100) return { left: 56, right: 44 }
    return { left: 52, right: 48 }
}

function applyDefaultPaneSizes() {
    const { left, right } = getDefaultPaneSizes()
    leftPaneSize.value = left
    rightPaneSize.value = right
    leftTopPaneSize.value = 45
    leftBottomPaneSize.value = 55
    notesPaneSize.value = 70
    partsPaneSize.value = 30
}

function resetPaneSizes() {
    applyDefaultPaneSizes()
}

function syncOuterPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

function syncLeftPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftTopPaneSize.value = panes[0].size
    leftBottomPaneSize.value = panes[1].size
}

function syncBottomPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    notesPaneSize.value = panes[0].size
    partsPaneSize.value = panes[1].size
}

onMounted(() => {
    applyDefaultPaneSizes()
})

watch(() => props.notes, () => {
    if (
        selectedNoteId.value != null &&
        !sharedNotes.value.some(n => Number(n.id) === Number(selectedNoteId.value))
    ) {
        selectedNoteId.value = null
    }
})

watch(() => props.files, (newFiles) => {
    if (selectedFileId.value && !newFiles.some(f => f.id === selectedFileId.value)) {
        selectedFileId.value = null
    }
}, { immediate: true })

watch(() => props.parts, (newParts) => {
    if (selectedPartId.value && !newParts.some(p => p.id === selectedPartId.value)) {
        selectedPartId.value = null
    }
}, { immediate: true })

watch(() => props.record?.orderID, () => {
    applyDefaultPaneSizes()
    selectedNoteId.value = null
    selectedPartId.value = null
    selectedFileId.value = null
})

function openNoteEdit() {
    const note = selectedNote.value
    if (!note) return
    if (!isNoteOwner(note)) {
        window.alert(`自分が書いた Note のみ編集できます。\nログイン: ${authUserName.value || '不明'}\n記入者: ${note.whoWrote || '不明'}`)
        return
    }
    emit('open-dialog', 'NOTE', { mode: 'edit', note })
}

function openNoteCreate() {
    emit('open-dialog', 'NOTE', { mode: 'create', personal: false })
}

function openEmailNoteLink() {
    emit('open-dialog', 'EMAIL_NOTE_LINK')
}

function isEmlFile(file) {
    const name = String(file?.documentName || '').toLowerCase()
    const type = String(file?.fileType || '').toLowerCase()
    return name.endsWith('.eml')
        || type.includes('message/rfc822')
        || type === 'application/eml'
        || type === 'message/rfc822'
}

function resolveEmailDraftSourceFile() {
    const selected = selectedFile.value
    if (selected && isEmlFile(selected)) return selected
    return sortedFiles.value.find((file) => isEmlFile(file)) || null
}

function openEmailDraftDialog() {
    emailDraftError.value = ''
    showEmailDraftDialog.value = true
}

function closeEmailDraftDialog() {
    if (emailDraftCreating.value) return
    showEmailDraftDialog.value = false
    emailDraftError.value = ''
}

function getRecordApiBase() {
    const basePath = window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
    return `${window.location.origin}${basePath}`
}

async function previewEmailDraft(templateType) {
    if (!templateType) {
        emailDraftError.value = '定型メールの種類を選択してください。'
        return
    }

    const orderID = props.record?.orderID
    if (orderID == null || orderID === '') {
        emailDraftError.value = '案件が特定できません。'
        return
    }

    emailDraftCreating.value = true
    emailDraftError.value = ''

    try {
        const sourceFile = resolveEmailDraftSourceFile()
        const result = await apiFetch(`${getRecordApiBase()}/${orderID}/email-draft-preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                templateType,
                fileId: sourceFile?.id ?? null,
            }),
        })

        if (!result) {
            throw new Error('メールプレビューの取得に失敗しました。')
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data?.message || `メールプレビューの取得に失敗しました。（HTTP ${response.status}）`)
        }

        emailDraftPreview.value = {
            to: data.to || '',
            subject: data.subject || '',
            bodyHtml: data.bodyHtml || data.body || '',
            bodyText: data.bodyText || '',
            templateLabel: data.templateLabel || '',
        }
        showEmailDraftDialog.value = false
        showEmailDraftPreviewDialog.value = true
    } catch (e) {
        emailDraftError.value = e.message || 'メールプレビューの取得に失敗しました。'
    } finally {
        emailDraftCreating.value = false
    }
}

function openServiceMasterSelect() {
    emit('open-dialog', 'MASTER_SELECT', {
        kind: 'serviceMaster',
        serviceID: props.draftRecord?.serviceID ?? props.record?.serviceID,
        productName: props.draftRecord?.productName ?? props.record?.productName,
    })
}

function openDealerSelect() {
    emit('open-dialog', 'MASTER_SELECT', {
        kind: 'dealer',
        dealer: props.draftRecord?.dealer ?? props.record?.dealer,
    })
}

function openIncidentSelect() {
    emit('open-dialog', 'MASTER_SELECT', {
        kind: 'incident',
        incident: props.draftRecord?.incident ?? props.record?.incident,
    })
}

function updateDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value
}

const ZIPCODE_FIELD_MAP = {
    dealer: {
        zip: 'zipcode',
        address1: 'address1',
        address2: 'address2',
    },
    endUser: {
        zip: 'endUser_zipcode',
        address1: 'endUser_address1',
        address2: 'endUser_address2',
    },
    delivery: {
        zip: 'deliveryDestination_zipcode',
        address1: 'deliveryDestination_address1',
        address2: 'deliveryDestination_address2',
    },
}

const zipLookupTimers = {
    dealer: null,
    endUser: null,
    delivery: null,
}

async function fetchAddressByZipcode(zipcode) {
    const digits = String(zipcode ?? '').replace(/\D/g, '')
    if (digits.length !== 7) return null

    const response = await fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${digits}`)
    if (!response.ok) {
        throw new Error('郵便番号の検索に失敗しました。')
    }

    const data = await response.json()
    const result = data?.results?.[0]
    if (!result) return null

    return {
        address1: result.address1 || '',
        address2: `${result.address2 || ''}${result.address3 || ''}`,
    }
}

function onZipcodeFieldInput(kind, value) {
    const fields = ZIPCODE_FIELD_MAP[kind]
    if (!fields) return

    updateDraftValue(fields.zip, value)

    if (zipLookupTimers[kind]) {
        clearTimeout(zipLookupTimers[kind])
    }

    zipLookupTimers[kind] = setTimeout(async () => {
        const digits = String(props.draftRecord?.[fields.zip] ?? value ?? '').replace(/\D/g, '')
        if (digits.length !== 7) return

        try {
            const address = await fetchAddressByZipcode(digits)
            if (!address) return
            updateDraftValue(fields.address1, address.address1)
            updateDraftValue(fields.address2, address.address2)
        } catch {
            // 自動検索失敗時は手入力を継続できるよう握りつぶす
        }
    }, 350)
}

function fieldValue(field) {
    const draft = props.draftRecord?.[field]
    if (draft !== undefined && draft !== null) return draft
    return props.record?.[field] ?? ''
}

function copyStakeholderToDelivery(source) {
    if (!props.draftRecord) return

    if (source === 'dealer') {
        updateDraftValue('deliveryDestination_company', fieldValue('dealer'))
        updateDraftValue('deliveryDestination_depart', fieldValue('dealer_depart'))
        updateDraftValue('deliveryDestination_contactPerson', fieldValue('contactPerson'))
        updateDraftValue('deliveryDestination_phone', fieldValue('phone'))
        updateDraftValue('deliveryDestination_email', fieldValue('email'))
        updateDraftValue('deliveryDestination_zipcode', fieldValue('zipcode'))
        updateDraftValue('deliveryDestination_address1', fieldValue('address1'))
        updateDraftValue('deliveryDestination_address2', fieldValue('address2'))
        return
    }

    if (source === 'endUser') {
        updateDraftValue('deliveryDestination_company', fieldValue('endUser'))
        updateDraftValue('deliveryDestination_depart', fieldValue('endUser_depart'))
        updateDraftValue('deliveryDestination_contactPerson', fieldValue('endUser_contactPerson'))
        updateDraftValue('deliveryDestination_phone', fieldValue('endUser_phone'))
        updateDraftValue('deliveryDestination_email', fieldValue('endUser_email'))
        updateDraftValue('deliveryDestination_zipcode', fieldValue('endUser_zipcode'))
        updateDraftValue('deliveryDestination_address1', fieldValue('endUser_address1'))
        updateDraftValue('deliveryDestination_address2', fieldValue('endUser_address2'))
    }
}

function updateNumericDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value === '' ? null : Number(value)
}

const isA2laOn = computed(() => {
    const value = props.draftRecord?.a2la ?? props.record?.a2la
    return value === 1 || value === '1' || value === true
})

const selectedServiceMaster = computed(() => {
    return findServiceMaster(page.props.servicesMaster, {
        productName: props.draftRecord?.productName ?? props.record?.productName,
        entityID: props.draftRecord?.entityID ?? props.record?.entityID,
        serviceID: props.draftRecord?.serviceID ?? props.record?.serviceID,
    }, priceAsOfDate.value)
})

const workPrice = computed(() => {
    // 1=再校正 / 9=新台/校正 → servicemaster の受注日版 priceC_0
    const returnCode = props.draftRecord?.returnCode ?? props.record?.returnCode
    return resolveServiceWorkPrice(selectedServiceMaster.value, returnCode)
})

const a2laPrice = computed(() => {
    if (!isA2laOn.value) return 0
    const value = Number(selectedServiceMaster.value?.price_a2la ?? 0)
    return Number.isFinite(value) ? value : 0
})

const partsPriceTotal = computed(() =>
    (props.parts ?? []).reduce((sum, part) => {
        const versioned = findPartMaster(
            page.props.partsMaster,
            part.partID,
            priceAsOfDate.value,
        )
        const raw = versioned?.price_discounted
            ?? part.part_master?.price_discounted
            ?? part.partMaster?.price_discounted
        const value = Number(raw)
        return sum + (Number.isNaN(value) ? 0 : value)
    }, 0),
)

const basePrice = computed(() => workPrice.value + a2laPrice.value + partsPriceTotal.value)

const displayAdjustmentAmount = computed(() => {
    if (sessionAdjustmentAmount.value != null && sessionAdjustmentAmount.value !== '') {
        return sessionAdjustmentAmount.value
    }
    return props.draftRecord?.discount_service ?? props.record?.discount_service ?? ''
})

const displayPrice = computed(() => {
    const discount = Number(displayAdjustmentAmount.value)
    const discountValue = Number.isFinite(discount) ? discount : 0
    return basePrice.value + discountValue
})

// 画面上の「価格」数値を price カラムへ反映（draft 差し替え時も再同期）
watch(
    [displayPrice, () => props.draftRecord],
    () => {
        if (!props.draftRecord) return
        const num = Number(displayPrice.value)
        props.draftRecord.price = Number.isFinite(num) ? num : null
    },
    { immediate: true },
)

function toggleA2la() {
    if (!props.draftRecord) return
    props.draftRecord.a2la = isA2laOn.value ? 0 : 1
}

function currentMaintenanceSearchSource() {
    return {
        productName: String(props.draftRecord?.productName ?? props.record?.productName ?? '').trim(),
        SN: String(props.draftRecord?.SN ?? props.record?.SN ?? '').trim(),
        dealer: String(props.draftRecord?.dealer ?? props.record?.dealer ?? '').trim(),
    }
}

function openMaintenanceSearchDialog() {
    const source = currentMaintenanceSearchSource()
    maintenanceSearchForm.productName = source.productName
    maintenanceSearchForm.SN = source.SN
    maintenanceSearchForm.dealer = source.dealer
    maintenanceContracts.value = []
    selectedMaintenanceContractId.value = null
    maintenanceSearchError.value = ''
    maintenanceSearchDone.value = false
    showMaintenanceSearchDialog.value = true
    nextTick(() => {
        runMaintenanceSearch()
    })
}

function closeMaintenanceSearchDialog() {
    if (maintenanceSearchLoading.value || maintenanceNoteSaving.value) return
    showMaintenanceSearchDialog.value = false
    maintenanceSearchError.value = ''
}

async function runMaintenanceSearch() {
    if (typeof document !== 'undefined' && document.activeElement instanceof HTMLElement) {
        document.activeElement.blur()
    }
    await nextTick()

    const productName = String(maintenanceSearchForm.productName ?? '').trim()
    const sn = String(maintenanceSearchForm.SN ?? '').trim()
    const dealer = String(maintenanceSearchForm.dealer ?? '').trim()

    const missing = []
    if (!productName) missing.push('productName')
    if (!sn) missing.push('SN')
    if (!dealer) missing.push('dealer')
    if (missing.length) {
        maintenanceSearchError.value = `${missing.join(' / ')} を入力してから検索してください。`
        maintenanceSearchDone.value = true
        maintenanceContracts.value = []
        selectedMaintenanceContractId.value = null
        return
    }

    maintenanceSearchLoading.value = true
    maintenanceSearchError.value = ''
    maintenanceSearchDone.value = false

    try {
        const params = new URLSearchParams({ productName, SN: sn, dealer })
        const url = `${page.props.appBaseUrl}/servicerecord/maintenance-contracts/search?${params.toString()}`
        const result = await apiFetch(url)
        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data?.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data?.message || `保守検索に失敗しました。（HTTP ${response.status}）`)
        }

        maintenanceContracts.value = Array.isArray(data.contracts) ? data.contracts : []
        selectedMaintenanceContractId.value = null
        maintenanceSearchDone.value = true
    } catch (e) {
        maintenanceContracts.value = []
        selectedMaintenanceContractId.value = null
        maintenanceSearchDone.value = true
        maintenanceSearchError.value = e.message || '保守検索に失敗しました。'
    } finally {
        maintenanceSearchLoading.value = false
    }
}

function isMaintenanceSelected(id) {
    return String(selectedMaintenanceContractId.value ?? '') === String(id ?? '')
}

function toggleMaintenanceSelection(id) {
    if (isMaintenanceSelected(id)) {
        selectedMaintenanceContractId.value = null
        return
    }
    selectedMaintenanceContractId.value = id
}

function clearMaintenanceSelection() {
    selectedMaintenanceContractId.value = null
}

function selectedMaintenanceContract() {
    return maintenanceContracts.value.find((row) => isMaintenanceSelected(row.id)) || null
}

function openSelectedMaintenanceDetail() {
    const contract = selectedMaintenanceContract()
    if (!contract?.id) {
        maintenanceSearchError.value = '保守契約を選択してください。'
        return
    }
    const url = `${page.props.appBaseUrl}/servicerecord/maintenance-contracts/${contract.id}`
    window.open(url, '_blank', 'noopener,noreferrer')
}

async function confirmMaintenanceSelection() {
    const contract = selectedMaintenanceContract()
    if (!contract) {
        maintenanceSearchError.value = '保守契約を選択してください。'
        return
    }
    if (!props.record?.orderID) {
        maintenanceSearchError.value = '案件が選択されていません。'
        return
    }

    const ref = String(contract.RefNumber || '').trim() || '—'
    const start = String(contract.startDate || '').trim() || '—'
    const end = String(contract.expireDate || '').trim() || '—'
    const noteText = `保守契約番号：${ref}、保守契約期間：${start}～${end}`

    maintenanceNoteSaving.value = true
    maintenanceSearchError.value = ''

    try {
        const result = await apiFetch(getNotesApiBase(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                associatedID: props.record.orderID,
                note: noteText,
                important: false,
            }),
        })

        if (!result) {
            throw new Error('Notes の追加に失敗しました。')
        }

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `Notes の追加に失敗しました。（HTTP ${response.status}）`)
        }

        showMaintenanceSearchDialog.value = false
        emit('reload-attachments')
    } catch (e) {
        maintenanceSearchError.value = e.message || 'Notes の追加に失敗しました。'
    } finally {
        maintenanceNoteSaving.value = false
    }
}

function openPriceAdjustDialog() {
    const currentDiscount = props.draftRecord?.discount_service ?? props.record?.discount_service
    priceAdjustForm.amount = currentDiscount == null || currentDiscount === '' ? '' : String(currentDiscount)
    priceAdjustForm.reason = ''
    priceAdjustError.value = ''
    showPriceAdjustDialog.value = true
}

function closePriceAdjustDialog() {
    if (priceAdjustSaving.value) return
    showPriceAdjustDialog.value = false
    priceAdjustError.value = ''
}

function getNotesApiBase() {
    const basePath = window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
    return `${window.location.origin}${basePath}/notes`
}

async function confirmPriceAdjust() {
    if (!props.record?.orderID || !props.draftRecord) {
        priceAdjustError.value = '案件が選択されていません。'
        return
    }

    const amountRaw = String(priceAdjustForm.amount ?? '').trim()
    const reason = String(priceAdjustForm.reason ?? '').trim()
    if (amountRaw === '' || !Number.isFinite(Number(amountRaw))) {
        priceAdjustError.value = '調整額を数値で入力してください。'
        return
    }
    if (!reason) {
        priceAdjustError.value = '調整理由を入力してください。'
        return
    }

    const amount = Number(amountRaw)
    const noteText = `[調整理由]　${reason}`

    priceAdjustSaving.value = true
    priceAdjustError.value = ''

    try {
        const result = await apiFetch(getNotesApiBase(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                associatedID: props.record.orderID,
                note: noteText,
                important: true,
            }),
        })

        if (!result) {
            throw new Error('Notes の追加に失敗しました。')
        }

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `Notes の追加に失敗しました。（HTTP ${response.status}）`)
        }

        // discount_service を更新し、表示価格（displayPrice）は watch 経由で price へ反映
        props.draftRecord.discount_service = amount
        sessionAdjustmentAmount.value = amount
        showPriceAdjustDialog.value = false
        emit('reload-attachments')
    } catch (e) {
        priceAdjustError.value = e.message || '価格調整に失敗しました。'
    } finally {
        priceAdjustSaving.value = false
    }
}

function toDateInputValue(value) {
    if (!value) return ''
    const normalized = String(value).trim().replace(' ', 'T')
    const date = new Date(normalized)
    if (Number.isNaN(date.getTime())) {
        const match = String(value).match(/^(\d{4}-\d{2}-\d{2})/)
        return match ? match[1] : ''
    }
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

function updateDraftDateValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value || null
}

function openNoteDelete() {
    const note = selectedNote.value
    if (!note) return
    if (!isNoteOwner(note)) {
        window.alert(`自分が書いた Note のみ削除できます。\nログイン: ${authUserName.value || '不明'}\n記入者: ${note.whoWrote || '不明'}`)
        return
    }
    emit('open-dialog', 'D', { action: 'delete-note', note, noteId: note.id })
}

const selectedFile = computed(() => props.files.find(f => f.id === selectedFileId.value))
const selectedPart = computed(() => props.parts.find(p => p.id === selectedPartId.value))

function compareFilesBySortNum(a, b) {
    const aNull = a?.sortNum == null
    const bNull = b?.sortNum == null
    if (aNull && bNull) return Number(a?.id ?? 0) - Number(b?.id ?? 0)
    if (aNull) return 1
    if (bNull) return -1
    if (Number(a.sortNum) !== Number(b.sortNum)) {
        return Number(a.sortNum) - Number(b.sortNum)
    }
    return Number(a?.id ?? 0) - Number(b?.id ?? 0)
}

const sortedFiles = computed(() =>
    [...(props.files ?? [])].sort(compareFilesBySortNum),
)

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function getFilesApiBase() {
    const basePath = window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
    return `${window.location.origin}${basePath}/files`
}

async function persistFileSortNum(fileId, sortNum) {
    const result = await apiFetch(`${getFilesApiBase()}/${fileId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ sortNum }),
    })

    if (!result) {
        throw new Error('順序の更新に失敗しました。')
    }

    const { response, data } = result
    if (!response.ok) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : null
        throw new Error(validationMessage || data.message || `順序の更新に失敗しました。（HTTP ${response.status}）`)
    }

    return data.file
}

async function updateFileSortNum(fileId, sortNum) {
    if (fileSortSaving.value) return

    fileSortSaving.value = true
    try {
        const updated = await persistFileSortNum(fileId, sortNum)
        const nextFiles = (props.files ?? []).map((file) => (
            String(file.id) === String(fileId)
                ? { ...file, ...updated, sortNum: updated?.sortNum ?? sortNum }
                : file
        ))
        emit('files-updated', nextFiles.sort(compareFilesBySortNum))
    } catch (e) {
        window.alert(e.message || '順序の更新に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

async function moveFile(fileId, direction) {
    if (fileSortSaving.value) return

    const list = [...sortedFiles.value]
    const index = list.findIndex(file => String(file.id) === String(fileId))
    if (index < 0) return

    const swapIndex = direction === 'up' ? index - 1 : index + 1
    if (swapIndex < 0 || swapIndex >= list.length) return

    ;[list[index], list[swapIndex]] = [list[swapIndex], list[index]]

    const updates = list.map((file, idx) => ({
        id: file.id,
        sortNum: (idx + 1) * 10,
    }))

    fileSortSaving.value = true
    try {
        const results = await Promise.all(
            updates.map(item => persistFileSortNum(item.id, item.sortNum)),
        )
        const byId = new Map(results.map(file => [String(file.id), file]))
        const nextFiles = (props.files ?? []).map((file) => {
            const updated = byId.get(String(file.id))
            return updated ? { ...file, ...updated } : file
        })
        emit('files-updated', nextFiles.sort(compareFilesBySortNum))
    } catch (e) {
        window.alert(e.message || '表示順の変更に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

function openFileCreate() {
    if (!canDropFiles.value) {
        window.alert('案件が選択されていません。')
        return
    }
    showFileDropzone.value = true
    fileDropError.value = ''
    fileDropActive.value = false
    fileDragDepth.value = 0
}

function closeFileDropzone() {
    if (fileDropUploading.value) return
    showFileDropzone.value = false
    fileDropActive.value = false
    fileDropError.value = ''
    fileDragDepth.value = 0
}

function openFileDelete() {
    const file = selectedFile.value
    if (!file) return
    emit('open-dialog', 'D', { action: 'delete-file', file, fileId: file.id })
}

const canDropFiles = computed(() => Boolean(props.record?.orderID))

function guessDocumentType(file) {
    const name = String(file?.name || '').toLowerCase()
    const type = String(file?.type || '').toLowerCase()
    if (name.endsWith('.eml') || name.endsWith('.msg') || type.includes('message') || type.includes('ms-outlook')) {
        return 'メール'
    }
    if (type === 'application/pdf' || name.endsWith('.pdf')) {
        return 'PDF'
    }
    if (type.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|tiff?)$/i.test(name)) {
        return '画像'
    }
    return '添付ファイル'
}

function nextSortNum() {
    const nums = (props.files ?? [])
        .map(file => Number(file.sortNum))
        .filter(num => Number.isFinite(num))
    if (!nums.length) return 10
    return Math.max(...nums) + 10
}

function onFileDragEnter(event) {
    if (!canDropFiles.value || fileDropUploading.value) return
    if (![...event.dataTransfer?.types ?? []].includes('Files')) return
    fileDragDepth.value += 1
    fileDropActive.value = true
}

function onFileDragOver(event) {
    if (!canDropFiles.value || fileDropUploading.value) return
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy'
    }
    fileDropActive.value = true
}

function onFileDragLeave() {
    fileDragDepth.value = Math.max(0, fileDragDepth.value - 1)
    if (fileDragDepth.value === 0) {
        fileDropActive.value = false
    }
}

function onFileDrop(event) {
    fileDragDepth.value = 0
    fileDropActive.value = false
    if (!canDropFiles.value || fileDropUploading.value) return
    const files = [...(event.dataTransfer?.files ?? [])]
    if (!files.length) {
        fileDropError.value = 'ドロップされた内容からファイルを取得できませんでした。Explorer に保存したファイルをドロップしてください。'
        return
    }
    uploadDroppedFiles(files)
}

function openFileDropPicker() {
    if (!canDropFiles.value || fileDropUploading.value) return
    fileDropInputEl.value?.click()
}

function onFileDropInputChange(event) {
    const files = [...(event.target.files ?? [])]
    event.target.value = ''
    if (!files.length) return
    uploadDroppedFiles(files)
}

async function uploadSingleDroppedFile(file, sortNum) {
    const formData = new FormData()
    formData.append('associatedID', props.record.orderID)
    formData.append('file', file)
    formData.append('documentName', file.name || 'untitled')
    formData.append('documentType', guessDocumentType(file))
    formData.append('sortNum', String(sortNum))

    const result = await apiFetch(getFilesApiBase(), {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: formData,
    })

    if (!result) {
        throw new Error(`${file.name || 'ファイル'} のアップロードに失敗しました。`)
    }

    const { response, data } = result
    if (!response.ok) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : null
        throw new Error(
            validationMessage
            || data.message
            || `${file.name || 'ファイル'} のアップロードに失敗しました。（HTTP ${response.status}）`,
        )
    }

    return data.file
}

async function uploadDroppedFiles(files) {
    if (!canDropFiles.value) {
        fileDropError.value = '案件が選択されていません。'
        return
    }

    const list = files.filter(file => file && file.size >= 0)
    if (!list.length) {
        fileDropError.value = 'アップロード可能なファイルがありません。'
        return
    }

    fileDropUploading.value = true
    fileDropError.value = ''
    let startSort = nextSortNum()

    try {
        for (let i = 0; i < list.length; i += 1) {
            const file = list[i]
            fileDropProgress.value = `${i + 1}/${list.length}: ${file.name || 'untitled'}`
            await uploadSingleDroppedFile(file, startSort)
            startSort += 10
        }
        emit('reload-attachments')
        showFileDropzone.value = false
        fileDropActive.value = false
        fileDragDepth.value = 0
    } catch (e) {
        fileDropError.value = e.message || 'アップロードに失敗しました。'
        emit('reload-attachments')
    } finally {
        fileDropUploading.value = false
        fileDropProgress.value = ''
    }
}

function openPartCreate() {
    emit('open-dialog', 'PART', {
        mode: 'create',
        attachedPartIds: props.parts.map(part => part.partID),
    })
}

function openPartDelete() {
    const part = selectedPart.value
    if (!part) return
    emit('open-dialog', 'D', { action: 'delete-part', part, partId: part.id })
}

function formatPrice(value) {
    const num = Number(value)
    if (Number.isNaN(num)) return '—'
    return num.toLocaleString('ja-JP')
}

const PAID_LOANER_RETURN_CODES_LOCAL = PAID_LOANER_RETURN_CODES
const currentReturnCode = computed(() => {
    const value = props.draftRecord?.returnCode ?? props.record?.returnCode
    const num = Number(value)
    return Number.isFinite(num) ? num : null
})

function partVersionPrice(part) {
    const versioned = findPartMaster(page.props.partsMaster, part.partID, priceAsOfDate.value)
    const raw = versioned?.price_discounted
        ?? part.part_master?.price_discounted
        ?? part.partMaster?.price_discounted
    const value = Number(raw)
    return Number.isFinite(value) ? value : null
}

function loanerDisplayPrice(loaner) {
    if (!PAID_LOANER_RETURN_CODES_LOCAL.includes(currentReturnCode.value)) {
        return 0
    }
    const asOf = loaner?.orderDate || priceAsOfDate.value
    if (Array.isArray(loaner?.priceVersions) && loaner.priceVersions.length) {
        const picked = pickMasterVersion(loaner.priceVersions, asOf)
        const value = Number(picked?.price)
        if (Number.isFinite(value)) return value
    }
    const master = Number(loaner?.masterPrice)
    if (Number.isFinite(master)) return master
    const stored = Number(loaner?.price)
    return Number.isFinite(stored) ? stored : 0
}

function formatSignedAmount(value) {
    if (value === '' || value == null) return '—'
    const num = Number(value)
    if (Number.isNaN(num)) return '—'
    const formatted = Math.abs(num).toLocaleString('ja-JP')
    if (num > 0) return `+${formatted}`
    if (num < 0) return `-${formatted}`
    return '0'
}

function formatDateTime(value) {
    if (!value) return '—'
    const normalized = String(value).replace(' ', 'T')
    const date = new Date(normalized)
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleString('ja-JP')
}

function formatDate(value) {
    if (!value) return '—'
    const normalized = String(value).replace(' ', 'T')
    const date = new Date(normalized)
    if (Number.isNaN(date.getTime())) return value
    // toLocaleString から toLocaleDateString に変更
    return date.toLocaleDateString('ja-JP') 
}

const showAssignNotifyDialog = ref(false)
const assignNotifyDialogMessage = ref('')
let assignNotifyDialogResolve = null

function buildAssignNotifyConfirmMessage(count) {
    const laborLabel = String(
        props.draftRecord?.labor_master?.laborName
        ?? props.record?.labor_master?.laborName
        ?? '',
    ).trim()
    if (laborLabel) {
        return `[宛先] ${laborLabel} ${count} 名にアサイン通知メールを送信しますか？`
    }
    return `同じ laborID の担当者 ${count} 名にアサイン通知メールを送信しますか？`
}

function confirmAssignNotifyMail(count) {
    return new Promise((resolve) => {
        assignNotifyDialogMessage.value = buildAssignNotifyConfirmMessage(count)
        assignNotifyDialogResolve = resolve
        showAssignNotifyDialog.value = true
    })
}

function resolveAssignNotifyDialog(send) {
    showAssignNotifyDialog.value = false
    assignNotifyDialogResolve?.(!!send)
    assignNotifyDialogResolve = null
}

function cancelAssignNotifyDialog() {
    resolveAssignNotifyDialog(false)
}

defineExpose({
    confirmAssignNotifyMail,
})
</script>

<style scoped>
.assign-notify-overlay {
    z-index: 460;
}

.detail-form {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    min-height: 0;
    font-size: 14px;
}

.detail-form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.detail-form-header h2 {
    margin: 0;
}

.reset-layout-btn {
    padding: 6px 12px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    font-size: 14px;
    cursor: pointer;
}

.detail-splitpanes {
    flex: 1;
    min-height: 0;
    width: 100%;
}

.detail-splitpanes-bottom {
    min-height: 0;
    height: 100%;
    width: 100%;
    background: #ccccff
}

.detail-pane {
    min-width: 0;
    min-height: 0;
    display: flex;
}

.detail-pane-left {
    flex-direction: column;
}

.left-column-layout {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}

.left-fixed-header {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    overflow: hidden;
    box-sizing: border-box;
}

.left-fixed-header-with-loaner {
    flex: 0 0 auto;
}

.detail-splitpanes-left {
    flex: 1 1 auto;
    min-height: 0;
}

.detail-pane-left-top,
.detail-pane-contacts,
.detail-pane-left-bottom,
.detail-pane-notes,
.detail-pane-parts {
    min-height: 0;
}

.detail-pane-contacts {
    flex-direction: column;
    width: 100%;
    min-width: 0;
}

.detail-pane-notes,
.detail-pane-parts {
    min-width: 0;
}

.detail-pane-left-top,
.detail-pane-contacts {
    font-size: 14px;
}

.detail-pane-left-top .info-grid dt,
.detail-pane-left-top .info-grid dd,
.detail-pane-left-top .field-input,
.detail-pane-left-top .field-select,
.detail-pane-left-top .field-button,
.detail-pane-left-top .input-field,
.detail-pane-left-top .input-field input,
.detail-pane-left-top .input-field input::placeholder,
.detail-pane-left-top .action-btn,
.detail-pane-left-top .section-card h3,
.detail-pane-left-top .entity-id-label,
.detail-pane-left-top .entity-id-value,
.left-fixed-header .info-grid dt,
.left-fixed-header .info-grid dd,
.left-fixed-header .field-input,
.left-fixed-header .field-select,
.left-fixed-header .field-button,
.left-fixed-header .input-field,
.left-fixed-header .input-field input,
.left-fixed-header .input-field input::placeholder,
.left-fixed-header .action-btn,
.left-fixed-header .section-card h3,
.left-fixed-header .entity-id-label,
.left-fixed-header .entity-id-value,
.detail-pane-contacts .input-field,
.detail-pane-contacts .input-field input,
.detail-pane-contacts .input-field input::placeholder,
.detail-pane-contacts .action-btn,
.detail-pane-contacts .section-card h3 {
    font-size: 14px;
}

.detail-pane-left-top .info-grid dd input:not(.field-input),
.left-fixed-header .info-grid dd input:not(.field-input) {
    font-size: 14px;
    font-weight: bold;
}

.pane-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow: hidden;
    box-sizing: border-box;
}

.left-top-layout {
    display: grid;
    grid-template-rows: auto minmax(72px, 1fr);
    gap: 4px;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    box-sizing: border-box;
}

.left-top-layout-with-loaner {
    grid-template-rows: auto auto minmax(0, 1fr);
}

.left-top-section {
    min-width: 0;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    scrollbar-gutter: stable;
}

.left-top-section-main {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
    min-width: 0;
    flex: 0 0 auto;
    overflow: hidden;
    box-sizing: border-box;
}

.left-top-section-contacts {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
    min-width: 0;
    flex: 1 1 auto;
    height: 100%;
    overflow-y: auto;
    box-sizing: border-box;
}

.pane-content-scroll {
    overflow: auto;
}

.section-card {
    padding: 8px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}

.detail-top-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr) minmax(0, 1fr);
    gap: 4px;
    align-items: stretch;
}

.detail-top-grid > .detail-card {
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: #d3d4d6;
}

.detail-top-grid > .detail-card .field-input,
.detail-top-grid > .detail-card .field-select,
.detail-top-grid > .detail-card .field-button,
.detail-top-grid > .detail-card input[type="text"],
.detail-top-grid > .detail-card input[type="date"],
.detail-top-grid > .detail-card select {
    background: #fff;
}

.detail-bottom-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 4px;
    align-items: stretch;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    margin-top: 0;
    flex: 1 1 auto;
}

.price-adjust-row {
    display: flex;
    flex: 0 0 auto;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: flex-start;
    gap: 12px 16px;
    padding: 2px 12px;
    border-color: #93c5fd;
    background:rgb(210, 210, 220);
}

.price-adjust-main,
.price-adjust-actions,
.price-adjust-delta {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 10px;
}

.price-adjust-symptoms {
    display: flex;
    flex: 1 1 auto;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.symptoms-input{
    padding: 5px 5px;

}
.symptoms-input {
    flex: 1 1 auto;
    min-width: 0;
    width: 100%;
    background: #fff;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    color: #0f172a;
}

.symptoms-input::placeholder {
    color: #94a3b8;
    font-weight: 500;
}

.price-adjust-label {
    font-size: 14px;
    font-weight: 700;
    color: #475569;
    white-space: nowrap;
}

.price-adjust-value {
    font-size: 20px;
    color: #0f172a;
    min-width: 96px;
}

.price-adjust-delta strong {
    font-size: 16px;
    color: #1d4ed8;
    min-width: 72px;
}

.a2la-toggle {
    min-width: 72px;
    padding: 6px 12px;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    background: #e2e8f0;
    color: #475569;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.a2la-toggle.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.dd-a2la-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.maintenance-search-btn {
    min-width: 88px;
    padding: 6px 12px;
    border: 1px solid #2563eb;
    border-radius: 8px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.maintenance-search-btn:hover:not(:disabled) {
    background: #dbeafe;
}

.maintenance-search-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.confirm-panel.maintenance-search-panel {
    width: min(98vw, 1800px);
    max-width: 98vw;
    min-width: min(98vw, 1100px);
    max-height: 96vh;
    display: flex;
    flex-direction: column;
    overflow: auto;
}

.confirm-panel.maintenance-search-panel .confirm-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: visible;
    display: flex;
    flex-direction: column;
}

.maintenance-search-fields {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
    gap: 8px;
    align-items: end;
    margin-bottom: 8px;
    flex: 0 0 auto;
}

.maintenance-research-btn {
    white-space: nowrap;
    height: 34px;
}

.maintenance-search-hint {
    margin: 0 0 8px;
    font-size: 12px;
    color: #64748b;
    flex: 0 0 auto;
}

.maintenance-empty {
    margin: 8px 0 0;
    font-size: 13px;
    color: #64748b;
}

.maintenance-table-wrap {
    flex: 0 0 auto;
    overflow-x: hidden;
    overflow-y: visible;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
}

.maintenance-table {
    width: 100%;
    min-width: 100%;
    table-layout: auto;
    border-collapse: collapse;
    font-size: 13px;
}

.maintenance-table th,
.maintenance-table td {
    border-bottom: 1px solid #e2e8f0;
    padding: 8px 10px;
    text-align: left;
    white-space: nowrap;
}

.maintenance-table th {
    position: sticky;
    top: 0;
    background: #f1f5f9;
    color: #334155;
    font-weight: 700;
    z-index: 1;
}

.maintenance-table tbody tr {
    cursor: pointer;
}

.maintenance-table tbody tr:hover {
    background: #f8fafc;
}

.maintenance-table tbody tr.selected,
.maintenance-table tbody tr.selected td {
    background: #dbeafe;
}

.confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 420;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.45);
}

.confirm-panel {
    width: min(440px, 100%);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.2);
}

.confirm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
}

.confirm-header h3 {
    margin: 0;
    font-size: 16px;
    color: #0f172a;
}

.close-btn {
    border: none;
    background: transparent;
    font-size: 22px;
    line-height: 1;
    color: #64748b;
    cursor: pointer;
}

.confirm-body {
    padding: 14px 16px;
}

.confirm-current-price {
    margin: 0 0 12px;
    color: #475569;
    font-size: 14px;
}

.confirm-field {
    display: block;
    margin-bottom: 12px;
    font-size: 14px;
    font-weight: 700;
    color: #334155;
}

.confirm-input,
.confirm-textarea {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 400;
    box-sizing: border-box;
}

.confirm-error {
    margin: 0;
    color: #b91c1c;
    font-size: 14px;
}

.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px 16px;
}

.detail-bottom-grid > .detail-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    width: 100%;
    max-width: none;
    box-sizing: border-box;
}

.detail-card {
    min-height: 0;
}

.detail-card-wide {
    width: 100%;
}

.linked-loaner-card {
    display: flex;
    flex-direction: column;
    flex: 0 0 auto;
    min-width: 0;
    min-height: 0;
    max-height: clamp(96px, 16vh, 160px);
    margin-top: 0;
    padding: 4px 8px;
    border-color: #e6f2ff;
    background: #eff6ff;
    overflow: hidden;
}

.linked-loaner-card-has-items {
    min-height: 0;
}

.linked-loaner-card .section-header {
    flex: 0 0 auto;
    margin-bottom: 2px;
    min-height: 0;
}

.linked-loaner-card .section-header h3 {
    margin: 0;
    font-size: 13px;
    line-height: 1.2;
    color: #000;
    font-weight: 700;
}

.linked-loaner-card .attachment-table-wrap {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
    overflow: auto;
    overscroll-behavior: contain;
}

.linked-loaner-card .data-table {
    min-width: 0;
    table-layout: fixed;
}

.linked-loaner-card .data-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
}

.linked-loaner-card .data-table th,
.linked-loaner-card .data-table td {
    padding: 4px 6px;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.linked-loaner-card .data-table tbody td {
    background: #fff;
}

.linked-loaner-card .data-table th:last-child,
.linked-loaner-card .data-table td:last-child {
    width: 48px;
}

.linked-loaner-help {
    margin: 0 0 8px;
    font-size: 13px;
    color: #64748b;
}

.loaner-period-link {
    color: #1d4ed8;
    font-weight: 700;
    text-decoration: none;
}

.loaner-period-link:hover {
    text-decoration: underline;
}

.loaner-period-missing {
    color: #94a3b8;
    font-size: 13px;
}

.section-card h3 {
    margin: 0 0 6px;
    font-size: 16px;
    color: #000;
    font-weight: 700;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 2px;
}

.detail-card-input .section-header {
    min-height: 26px;
    margin-bottom: 6px;
}

.detail-card-input .section-header h3 {
    margin: 0;
    line-height: 1.2;
}

.detail-card-input .section-header .action-btn {
    padding: 2px 8px;
    font-size: 12px;
    line-height: 1.2;
}

.dealer-header {
    justify-content: flex-start;
}

.section-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.action-btn {
    padding: 6px 12px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    font-size: 14px;
    cursor: pointer;
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-btn-primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.action-btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}

.info-grid {
    display: grid;
    grid-template-columns: 100px 1fr;
    gap: 8px 16px;
}

.compact-info-grid {
    grid-template-columns: 60px 1fr;
    gap: 6px 12px;
}


.info-grid dt {
    font-weight: 700;
    color: #000;
}

.info-grid dd {
    min-width: 0;
}

.info-grid dd .date-input-with-today {
    width: 100%;
    box-sizing: border-box;
}

.info-grid dd .field-input {
    width: 100%;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    background: #fff;
    color: #1e293b;
    font-weight: bold;
}

.dd-inline-fields {
    display: flex;
    gap: 8px;
    align-items: center;
}

.dd-inline-fields .field-input {
    flex: 1 1 auto;
    min-width: 0;
}

.dd-inline-fields .date-input-with-today {
    flex: 0 0 168px;
    width: 168px;
    min-width: 0;
}

.dd-inline-fields .field-date {
    flex: 1 1 auto;
    width: auto;
    min-width: 0;
}

.detail-card-rma-order .rma-order-grid {
    /* compact 80px の半分 + 列ギャップ 12px の半分 */
    grid-template-columns: 50px 1fr;
    gap: 6px 6px;
}

.detail-card-rma-order .rma-order-grid dt {
    white-space: nowrap;
}

.detail-card-rma-order .rma-order-grid dt:nth-of-type(n + 5) {
    color: #000;
}

.dd-order-num {
    display: flex;
    gap: 6px;
    align-items: center;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

.dd-order-num .field-input {
    flex: 1 1 0;
    width: 0;
    min-width: 0;
    max-width: none;
}

.dd-order-num .date-input-with-today {
    flex: 1 1 0;
    width: 0;
    min-width: 0;
}

.dd-order-num .field-date,
.dd-order-num input[type="date"] {
    flex: 1 1 auto;
    width: auto;
    min-width: 0;
}

.dots-btn {
    flex: 0 0 auto;
    min-width: 36px;
    height: 28px;
    padding: 0 8px;
    border: none;
    border-radius: 4px;
    background: #4b5563;
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: 1px;
    cursor: pointer;
}

.dots-btn:hover {
    background: #374151;
}

.detail-card-misc {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.misc-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.misc-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 700;
    color: #000;
}

.misc-field > span {
    flex: 0 0 auto;
    white-space: nowrap;
    min-width: 5.5em;
    color: #000;
    font-weight: 700;
}

.misc-field .date-input-with-today {
    flex: 1 1 auto;
    min-width: 0;
    width: auto;
}

.misc-field .field-input {
    flex: 1 1 auto;
    min-width: 0;
    width: auto;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    background: #fff;
    color: #1e293b;
    font-weight: bold;
}

.yayoi-search-btn {
    align-self: stretch;
    margin-top: 2px;
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    background: #4b5563;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.yayoi-search-btn:hover {
    background: #374151;
}

.misc-block-incidents {
    gap: 0;
}

.incidents-header {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 28px;
    padding: 4px 8px;
    border: none;
    border-top: 3px solid #3b82f6;
    background: #4b5563;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer;
}

.incidents-header:hover {
    background: #374151;
}

.incidents-input {
    width: 100%;
    margin-top: 6px;
    padding: 6px 8px;
    border: 1px solid #111827;
    border-radius: 4px;
    box-sizing: border-box;
    background: #fff;
    color: #111827;
    font-size: 18px;
    font-weight: 700;
    text-align: left;
}

.dd-product-name {
    display: flex;
    gap: 8px;
    align-items: flex-start;
}

.dt-product-name {
    display: flex;
    align-items: center;
    align-self: start;
    box-sizing: border-box;
    min-height: 30px;
}

.dd-product-name .field-button {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 30px;
    display: flex;
    align-items: center;
    font-weight: 700;
}

.entity-id-display {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 0 0 auto;
    min-width: 72px;
    font-size: 13px;
    color: #000;
}

.entity-id-label {
    font-weight: 700;
    color: #000;
    line-height: 1.2;
}

.entity-id-value {
    padding: 4px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #f8fafc;
    color: #1e293b;
    line-height: 1.2;
    white-space: nowrap;
}

.detail-card-input .input-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 12px;
}

.input-field {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 14px;
    color: #000;
    font-weight: 700;
}

.input-field input {
    width: 100%;
    padding: 5px 7px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    color: #1e293b;
    background: white;
    font-weight: bold;
    line-height: 1.2;
}

.input-field input::placeholder {
    color: #94a3b8;
}

.input-field-span2 {
    grid-column: span 2;
}

.field-select {
    width: 100%;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    box-sizing: border-box;
    font-size: 14px;
    font-weight: bold;
}

.field-select option {
    font-weight: bold;
}

.status-empty {
    color: #64748b;
    font-size: 14px;
}

.field-button {
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    cursor: pointer;
    text-align: left;
    width: 100%;
}

.field-button:hover {
    background: #f8fafc;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    border: 1px solid #94a3b8;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
}

.data-table thead th {
    background: #e2e8f0;
    color: #000;
    font-weight: 700;
}

.table-row {
    cursor: pointer;
}

.table-row:hover td {
    background: #dbeafe;
}

.active-row td {
    color: #fff !important;
    background: #7e25eb !important;
}

.table-row.active-row:hover td {
    background: #7e25eb !important;
}

.text-cell {
    white-space: pre-wrap;
    word-break: break-word;
}

:deep(.note-autolink) {
    color: #1d4ed8;
    text-decoration: underline;
    word-break: break-all;
}

:deep(.active-row .note-autolink) {
    color: #fff;
}

.notes-table {
    table-layout: fixed;
    background: #fff;
}

.notes-table th,
.notes-table td {
    font-weight: 700;
}

.notes-table tbody td {
    background: #fff;
}

.notes-table .col-note-date,
.notes-table .col-note-author {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    box-sizing: border-box;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notes-table .col-note-body {
    width: auto;
}

.important-row td {
    background: #fef3c7;
}

.empty-message,
.status-message {
    margin: 0;
    color: #64748b;
}

.section-card-compact h3 {
    margin: 0;
    font-size: 14px;
    color: #000;
    font-weight: 700;
}

.section-card-compact .section-header {
    margin-bottom: 8px;
}

.section-card-compact .data-table th,
.section-card-compact .data-table td {
    font-size: 13px;
    padding: 4px 6px;
}

.section-card-compact .empty-message {
    font-size: 13px;
}

.section-card-compact .action-btn {
    font-size: 13px;
    padding: 4px 10px;
}

.parts-total-inline {
    font-size: 13px;
    color: #334155;
    white-space: nowrap;
}

.status-message.error {
    color: #b91c1c;
}

.action-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.action-row button {
    padding: 8px 16px;
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.section-card-files {
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
}

.file-dropzone {
    position: relative;
    flex: 0 0 auto;
    margin-bottom: 10px;
    padding: 14px 12px;
    border: 2px dashed #94a3b8;
    border-radius: 8px;
    background: #f8fafc;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.file-dropzone:hover {
    border-color: #2563eb;
    background: #eff6ff;
}

.file-dropzone-active {
    border-color: #2563eb;
    background: #dbeafe;
}

.file-dropzone-disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.file-drop-input {
    display: none;
}

.file-dropzone-title {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
}

.file-dropzone-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.file-dropzone-cancel {
    flex: 0 0 auto;
}

.file-dropzone-help {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.file-dropzone-error {
    margin: 8px 0 0;
    font-size: 13px;
    color: #b91c1c;
}

.section-card-fill {
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
    background: #cccccc;
}

.notes-card,
.parts-card {
    background: #e0f2fe4f;
}

.notes-header-title {
    display: flex;
    align-items: center;
    gap: 100px;
    min-width: 0;
}

.notes-tbc-count {
    font-size: 14px;
    font-weight: 700;
    color: #dc2626;
    white-space: nowrap;
}

.parts-table {
    background: #fff;
}

.parts-table th,
.parts-table td {
    font-weight: 700;
}

.parts-table tbody td {
    background: #fff;
}

.attachment-table-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.files-list-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.captured-images-panel {
    flex: 0 0 auto;
    margin: 0 0 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    overflow: hidden;
}

.captured-toggle {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 6px 10px;
    border: none;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.captured-toggle:hover {
    background: #cbd5e1;
}

.captured-toggle.has-images {
    background: #86efac;
    color: #14532d;
}

.captured-toggle.has-images:hover {
    background: #4ade80;
}

.captured-toggle.has-images .captured-toggle-icon {
    color: #166534;
}

.captured-toggle-icon {
    font-size: 11px;
    color: #475569;
}

.captured-images-body {
    max-height: 200px;
    overflow: auto;
    padding: 8px;
}

:deep(.splitpanes__splitter) {
    /* background: #cbd5e1; */
    background: #ff0000;
    
}

:deep(.splitpanes__splitter:hover) {
    /* background: #94a3b8; */
    background: #0000ff;
}
</style>
