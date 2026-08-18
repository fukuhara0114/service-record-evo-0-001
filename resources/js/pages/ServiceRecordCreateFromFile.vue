<template>
    <div class="create-page">
        <div class="page-header">
            <div>
                <h1>{{ pageTitle }}</h1>
            </div>
            <div class="header-actions">
                <a :href="intakeListUrl" class="btn btn-secondary">未登録ファイル一覧へ戻る</a>
                <a :href="adminUrl" class="btn btn-secondary">既存案件一覧</a>
                <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
                    {{ saveButtonLabel }}
                </button>
            </div>
        </div>

        <p v-if="error" class="global-error">{{ error }}</p>
        <p v-if="success" class="global-success">{{ success }}</p>

        <div class="create-layout">
            <Splitpanes class="default-theme create-splitpanes" @resized="syncPaneSizes">
                <Pane class="create-pane create-pane-pdf" :size="leftPaneSize" :min-size="28">
                    <section class="panel panel-pdf">
                        <div class="panel-header">
                            <div>
                                <h2>{{ hasSourceFile ? '申請フォーム' : '添付ファイル無し' }}</h2>
                                <div v-if="hasSourceFile" class="panel-meta">
                                    <span>ID: {{ sourceFile?.id }}</span>
                                    <span>{{ sourceFile?.documentName || '（名称なし）' }}</span>
                                    <span>{{ sourceFile?.fileType || '' }}</span>
                                </div>
                                <div v-else class="panel-meta">
                                    <span>情報入力のみで新規案件を作成します</span>
                                </div>
                            </div>
                            <div v-if="hasSourceFile" class="panel-header-actions">
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    :disabled="ocrLoading"
                                    @click="() => runOcrFromSourceFile()"
                                >
                                    {{ ocrLoading ? 'OCR読取中...' : 'OCR読取' }}
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    @click="openPreview(sourceFile)"
                                >
                                    拡大・回転
                                </button>
                            </div>
                        </div>
                        <div v-if="hasSourceFile" class="pdf-preview-shell">
                            <iframe
                                v-if="isSourcePdf"
                                :src="sourceFileUrl"
                                class="pdf-frame"
                                title="申請フォーム"
                            />
                            <img
                                v-else-if="isSourceImage"
                                :src="sourceFileUrl"
                                :alt="sourceFile?.documentName || '画像'"
                                class="pdf-frame image-frame"
                            >
                            <div v-else class="no-preview-panel">
                                <p>{{ sourceFile?.documentName || '（名称なし）' }}</p>
                                <p>{{ sourceFile?.fileType || 'この形式はプレビュー非対応です' }}</p>
                                <a :href="sourceFileUrl" target="_blank" rel="noopener" class="btn btn-secondary">別タブで開く</a>
                            </div>
                        </div>
                        <div v-else class="pdf-preview-shell no-file-shell">
                            <div class="no-preview-panel">
                                <p class="no-file-title">添付ファイル無し</p>
                                <p>必要なら「関連する未登録書類」からファイルを選択できます。</p>
                            </div>
                        </div>
                    </section>
                </Pane>

                <Pane class="create-pane create-pane-form" :size="rightPaneSize" :min-size="35">
                    <section class="panel panel-form">
                <div class="tab-bar" role="tablist">
                    <div class="tab-bar-tabs">
                        <button
                            type="button"
                            class="tab-btn"
                            :class="{ active: activeTab === 'basic' }"
                            role="tab"
                            @click="activeTab = 'basic'"
                        >
                            基本情報
                        </button>
                        <button
                            type="button"
                            class="tab-btn"
                            :class="{ active: activeTab === 'related' }"
                            role="tab"
                            @click="activeTab = 'related'"
                        >
                            関連する{{ isLoanerCase ? '証憑書類' : '未登録書類' }}({{ relatedFileCount }}枚)
                        </button>
                        <button
                            type="button"
                            class="tab-btn"
                            :class="{ active: activeTab === 'existing' }"
                            role="tab"
                            @click="switchToExistingTab"
                        >
                            既存案件(サービス案件)検索
                        </button>
                        <button
                            type="button"
                            class="tab-btn"
                            :class="{ active: activeTab === 'loaner' }"
                            role="tab"
                            @click="switchToLoanerTab"
                        >
                            loaner案件検索
                            <span v-if="selectedLoaners.length">（{{ selectedLoaners.length }}）</span>
                        </button>
                    </div>
                    <div v-if="!isLoanerCase" class="tab-bar-center">
                        <button
                            type="button"
                            class="tab-btn tab-btn-action"
                            role="button"
                            :disabled="maintenanceSearchLoading"
                            @click="searchMaintenanceContracts"
                        >
                            {{ maintenanceSearchLoading ? '検索中...' : '保守検索' }}
                        </button>
                    </div>
                </div>

                <div v-show="activeTab === 'basic'" class="tab-panel" :class="{ 'tab-panel-loaner-basic': isLoanerCase }">
                    <div v-if="isLoanerCase" class="form-stack form-stack-loaner">
                        <section class="info-card info-card-loaner-top">
                            <div class="loaner-top-row">
                                <label class="field field-inline">
                                    <span>機種</span>
                                    <button
                                        type="button"
                                        class="field-button"
                                        :class="{ placeholder: !form.item && !form.productName }"
                                        @click="openSelectDialog('loanerProduct')"
                                    >
                                        {{ form.item || form.productName || 'item' }}
                                    </button>
                                </label>
                                <label class="field field-inline field-sn">
                                    <span>SN</span>
                                    <input
                                        v-model="form.SN"
                                        type="text"
                                        placeholder="SN"
                                        lang="en"
                                        inputmode="latin"
                                        :readonly="Boolean(selectedLoanerUnit?.SN)"
                                    >
                                </label>
                                <p v-if="loanerAvailabilityChecking" class="availability-hint availability-inline">
                                    在庫確認中...
                                </p>
                                <p
                                    v-else-if="loanerAvailability?.order_type === 'waiting_list'"
                                    class="availability-hint availability-inline wait"
                                >
                                    在庫なし → waiting_list
                                </p>
                                <p
                                    v-else-if="loanerAvailability?.order_type === 'loaner'"
                                    class="availability-hint availability-inline ok"
                                >
                                    在庫有 {{ availableLoanerCount }}台
                                    <template v-if="form.loanerID">（loanerID: {{ form.loanerID }}）</template>
                                </p>
                                <label class="field field-inline field-instrument-name">
                                    <span>機種名</span>
                                    <input
                                        v-model="form.instrumentName"
                                        type="text"
                                        placeholder="機種名"
                                        lang="en"
                                        inputmode="latin"
                                    >
                                </label>
                                <label class="field field-inline field-enduser-sn">
                                    <span>enduser_SN</span>
                                    <input
                                        v-model="form.enduser_SN"
                                        type="text"
                                        placeholder="enduser_SN"
                                        lang="en"
                                        inputmode="latin"
                                    >
                                </label>
                                <button
                                    type="button"
                                    class="tab-btn tab-btn-action btn-loaner-maintenance"
                                    :disabled="maintenanceSearchLoading"
                                    @click="searchMaintenanceContracts"
                                >
                                    {{ maintenanceSearchLoading ? '検索中...' : '保守検索' }}
                                </button>
                                <button
                                    type="button"
                                    class="tab-btn tab-btn-action btn-parent-case"
                                    @click="openParentCaseDialog"
                                >
                                    親案件
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-stock-list"
                                    :disabled="!form.productName"
                                    @click="openLoanerStockDialog"
                                >
                                    在庫リスト
                                </button>
                            </div>
                        </section>

                        <div class="loaner-stakeholder-stack">
                            <section class="info-card info-card-dealer stakeholder-card">
                                <aside class="stakeholder-side">
                                    <div class="stakeholder-label">dealer</div>
                                    <button type="button" class="switch-btn" @click="swapStakeholders('dealer', 'endUser')">
                                        switch E/U
                                    </button>
                                    <button type="button" class="switch-btn" @click="swapStakeholders('dealer', 'delivery')">
                                        switch delivery
                                    </button>
                                </aside>
                                <div class="stakeholder-body">
                                    <div class="form-row row-dealer-top">
                                        <button
                                            type="button"
                                            class="field-button field-button-pick"
                                            @click="openSelectDialog('dealer')"
                                        >
                                            dealer選択
                                        </button>
                                        <input
                                            v-model="form.dealer"
                                            type="text"
                                            class="w-dealer-name"
                                            placeholder="dealer"
                                            lang="ja"
                                        >
                                    </div>
                                    <div class="form-row row-full">
                                        <input v-model="form.dealer_depart" type="text" placeholder="dealer_depart" lang="ja">
                                    </div>
                                    <div class="form-row row-contact">
                                        <input v-model="form.contactPerson" type="text" class="w-contact" placeholder="contactPerson" lang="ja">
                                    </div>
                                    <div class="form-row row-phone-email">
                                        <input v-model="form.phone" type="text" class="w-phone" placeholder="Phone" lang="en" inputmode="tel">
                                        <input v-model="form.email" type="text" class="w-email" placeholder="EMail" lang="en" inputmode="email">
                                    </div>
                                    <div class="form-row row-zip">
                                        <input
                                            v-model="form.zipcode"
                                            type="text"
                                            class="w-zip"
                                            lang="en"
                                            inputmode="numeric"
                                            maxlength="8"
                                            placeholder="Zipcode"
                                            @input="onZipcodeInput('dealer')"
                                        >
                                    </div>
                                    <div class="form-row row-address">
                                        <input v-model="form.address1" type="text" class="w-address1" placeholder="address1" lang="ja">
                                        <input v-model="form.address2" type="text" class="w-address2" placeholder="address2" lang="ja">
                                    </div>
                                </div>
                            </section>

                            <section class="info-card info-card-enduser stakeholder-card">
                                <aside class="stakeholder-side">
                                    <div class="stakeholder-label">endUser</div>
                                    <button type="button" class="switch-btn" @click="swapStakeholders('endUser', 'dealer')">
                                        switch dealer
                                    </button>
                                    <button type="button" class="switch-btn" @click="swapStakeholders('endUser', 'delivery')">
                                        switch delivery
                                    </button>
                                </aside>
                                <div class="stakeholder-body">
                                    <div class="form-row row-full">
                                        <input v-model="form.endUser" type="text" placeholder="endUser" lang="ja">
                                    </div>
                                    <div class="form-row row-full">
                                        <input v-model="form.endUser_depart" type="text" placeholder="endUser_depart" lang="ja">
                                    </div>
                                    <div class="form-row row-contact">
                                        <input v-model="form.endUser_contactPerson" type="text" class="w-contact" placeholder="contactPerson" lang="ja">
                                    </div>
                                    <div class="form-row row-phone-email">
                                        <input v-model="form.endUser_phone" type="text" class="w-phone" placeholder="Phone" lang="en" inputmode="tel">
                                        <input v-model="form.endUser_email" type="text" class="w-email" placeholder="EMail" lang="en" inputmode="email">
                                    </div>
                                    <div class="form-row row-zip">
                                        <input
                                            v-model="form.endUser_zipcode"
                                            type="text"
                                            class="w-zip"
                                            lang="en"
                                            inputmode="numeric"
                                            maxlength="8"
                                            placeholder="Zipcode"
                                            @input="onZipcodeInput('endUser')"
                                        >
                                    </div>
                                    <div class="form-row row-address">
                                        <input v-model="form.endUser_address1" type="text" class="w-address1" placeholder="address1" lang="ja">
                                        <input v-model="form.endUser_address2" type="text" class="w-address2" placeholder="address2" lang="ja">
                                    </div>
                                </div>
                            </section>

                            <section class="info-card info-card-delivery stakeholder-card">
                                <aside class="stakeholder-side">
                                    <div class="stakeholder-label">delivery</div>
                                    <button type="button" class="switch-btn" @click="swapStakeholders('delivery', 'dealer')">
                                        switch dealer
                                    </button>
                                    <button type="button" class="switch-btn" @click="swapStakeholders('delivery', 'endUser')">
                                        switch E/U
                                    </button>
                                </aside>
                                <div class="stakeholder-body">
                                    <div class="form-row row-full">
                                        <input v-model="form.deliveryDestination_company" type="text" placeholder="delivery" lang="ja">
                                    </div>
                                    <div class="form-row row-full">
                                        <input v-model="form.deliveryDestination_depart" type="text" placeholder="delivery_depart" lang="ja">
                                    </div>
                                    <div class="form-row row-contact">
                                        <input v-model="form.deliveryDestination_contactPerson" type="text" class="w-contact" placeholder="contactPerson" lang="ja">
                                    </div>
                                    <div class="form-row row-phone-email">
                                        <input v-model="form.deliveryDestination_phone" type="text" class="w-phone" placeholder="Phone" lang="en" inputmode="tel">
                                        <input v-model="form.deliveryDestination_email" type="text" class="w-email" placeholder="EMail" lang="en" inputmode="email">
                                    </div>
                                    <div class="form-row row-zip">
                                        <input
                                            v-model="form.deliveryDestination_zipcode"
                                            type="text"
                                            class="w-zip"
                                            lang="en"
                                            inputmode="numeric"
                                            maxlength="8"
                                            placeholder="Zipcode"
                                            @input="onZipcodeInput('delivery')"
                                        >
                                    </div>
                                    <div class="form-row row-address">
                                        <input v-model="form.deliveryDestination_address1" type="text" class="w-address1" placeholder="address1" lang="ja">
                                        <input v-model="form.deliveryDestination_address2" type="text" class="w-address2" placeholder="address2" lang="ja">
                                    </div>
                                </div>
                            </section>
                        </div>

                        <section
                            v-if="maintenanceSearchDone || maintenanceContracts.length"
                            class="info-card info-card-maintenance"
                        >
                            <div class="maintenance-header">
                                <h3>保守契約検索結果</h3>
                                <span v-if="maintenanceSearchDone" class="maintenance-count">
                                    {{ maintenanceContracts.length }}件
                                </span>
                                <button
                                    v-if="selectedMaintenanceContractId"
                                    type="button"
                                    class="btn btn-secondary maintenance-clear-btn"
                                    @click="clearMaintenanceSelection"
                                >
                                    選択解除
                                </button>
                            </div>
                            <p v-if="maintenanceSearchError" class="maintenance-error">{{ maintenanceSearchError }}</p>
                            <div v-else-if="maintenanceContracts.length" class="maintenance-table-wrap">
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
                                条件に一致する有効な保守契約はありません。
                            </p>
                            <p v-if="selectedMaintenanceContractId" class="maintenance-selected-hint">
                                選択中の保守契約は保存時に Note として案件へ紐づけられます。
                            </p>
                        </section>
                    </div>

                    <div v-else class="form-stack">
                        <section class="info-card info-card-main">
                            <div class="form-row row-product-top">
                                <button
                                    type="button"
                                    class="field-button field-button-pick"
                                    :class="{ placeholder: !form.serviceID }"
                                    @click="openSelectDialog('serviceMaster')"
                                >
                                    {{ form.serviceID ? `選択 (#${form.serviceID})` : '機種選択' }}
                                </button>
                                <input
                                    v-model="form.productName"
                                    type="text"
                                    class="w-product-name"
                                    placeholder="productName"
                                    lang="en"
                                    inputmode="latin"
                                    @input="onProductNameTyped"
                                >
                                <input :value="form.entityID || ''" type="text" placeholder="entityID" readonly>
                            </div>
                            <div class="form-row row-product-sn">
                                <input v-model="form.SN" type="text" placeholder="SN" lang="en" inputmode="latin">
                            </div>
                            <div class="form-row row-product-meta">
                                <input v-model="form.receivedDate" type="date" class="w-received">
                                <select v-model="form.status" class="w-status">
                                    <option value="">status</option>
                                    <option v-for="status in statuses" :key="status.processID_new" :value="String(status.processID_new)">
                                        {{ loanerStatusOptionLabel(status) }}
                                    </option>
                                </select>
                                <select v-model="form.returnCode" class="w-return">
                                    <option value="">returnCode</option>
                                    <option v-for="returnCode in returnCodes" :key="returnCode.id" :value="String(returnCode.id)">
                                        {{ returnCode.description }} ({{ returnCode.id }})
                                    </option>
                                </select>
                            </div>
                        </section>

                        <section class="info-card info-card-dealer stakeholder-card">
                            <aside class="stakeholder-side">
                                <div class="stakeholder-label">dealer</div>
                                <button type="button" class="switch-btn" @click="swapStakeholders('dealer', 'endUser')">
                                    switch E/U
                                </button>
                                <button type="button" class="switch-btn" @click="swapStakeholders('dealer', 'delivery')">
                                    switch delivery
                                </button>
                            </aside>
                            <div class="stakeholder-body">
                                <div class="form-row row-dealer-top">
                                    <button
                                        type="button"
                                        class="field-button field-button-pick"
                                        @click="openSelectDialog('dealer')"
                                    >
                                        dealer選択
                                    </button>
                                    <input
                                        v-model="form.dealer"
                                        type="text"
                                        class="w-dealer-name"
                                        placeholder="dealer"
                                        lang="ja"
                                    >
                                </div>
                                <div class="form-row row-full">
                                    <input v-model="form.dealer_depart" type="text" placeholder="dealer_depart" lang="ja">
                                </div>
                                <div class="form-row row-contact">
                                    <input v-model="form.contactPerson" type="text" class="w-contact" placeholder="contactPerson" lang="ja">
                                </div>
                                <div class="form-row row-phone-email">
                                    <input v-model="form.phone" type="text" class="w-phone" placeholder="Phone" lang="en" inputmode="tel">
                                    <input v-model="form.email" type="text" class="w-email" placeholder="EMail" lang="en" inputmode="email">
                                </div>
                                <div class="form-row row-zip">
                                    <input
                                        v-model="form.zipcode"
                                        type="text"
                                        class="w-zip"
                                        lang="en"
                                        inputmode="numeric"
                                        maxlength="8"
                                        placeholder="Zipcode"
                                        @input="onZipcodeInput('dealer')"
                                    >
                                </div>
                                <div class="form-row row-address">
                                    <input v-model="form.address1" type="text" class="w-address1" placeholder="address1" lang="ja">
                                    <input v-model="form.address2" type="text" class="w-address2" placeholder="address2" lang="ja">
                                </div>
                            </div>
                        </section>

                        <section class="info-card info-card-enduser stakeholder-card">
                            <aside class="stakeholder-side">
                                <div class="stakeholder-label">endUser</div>
                                <button type="button" class="switch-btn" @click="swapStakeholders('endUser', 'dealer')">
                                    switch dealer
                                </button>
                                <button type="button" class="switch-btn" @click="swapStakeholders('endUser', 'delivery')">
                                    switch delivery
                                </button>
                            </aside>
                            <div class="stakeholder-body">
                                <div class="form-row row-full">
                                    <input v-model="form.endUser" type="text" placeholder="endUser" lang="ja">
                                </div>
                                <div class="form-row row-full">
                                    <input v-model="form.endUser_depart" type="text" placeholder="endUser_depart" lang="ja">
                                </div>
                                <div class="form-row row-contact">
                                    <input v-model="form.endUser_contactPerson" type="text" class="w-contact" placeholder="contactPerson" lang="ja">
                                </div>
                                <div class="form-row row-phone-email">
                                    <input v-model="form.endUser_phone" type="text" class="w-phone" placeholder="Phone" lang="en" inputmode="tel">
                                    <input v-model="form.endUser_email" type="text" class="w-email" placeholder="EMail" lang="en" inputmode="email">
                                </div>
                                <div class="form-row row-zip">
                                    <input
                                        v-model="form.endUser_zipcode"
                                        type="text"
                                        class="w-zip"
                                        lang="en"
                                        inputmode="numeric"
                                        maxlength="8"
                                        placeholder="Zipcode"
                                        @input="onZipcodeInput('endUser')"
                                    >
                                </div>
                                <div class="form-row row-address">
                                    <input v-model="form.endUser_address1" type="text" class="w-address1" placeholder="address1" lang="ja">
                                    <input v-model="form.endUser_address2" type="text" class="w-address2" placeholder="address2" lang="ja">
                                </div>
                            </div>
                        </section>

                        <section class="info-card info-card-delivery stakeholder-card">
                            <aside class="stakeholder-side">
                                <div class="stakeholder-label">delivery</div>
                                <button type="button" class="switch-btn" @click="swapStakeholders('delivery', 'dealer')">
                                    switch dealer
                                </button>
                                <button type="button" class="switch-btn" @click="swapStakeholders('delivery', 'endUser')">
                                    switch E/U
                                </button>
                            </aside>
                            <div class="stakeholder-body">
                                <div class="form-row row-full">
                                    <input v-model="form.deliveryDestination_company" type="text" placeholder="delivery" lang="ja">
                                </div>
                                <div class="form-row row-full">
                                    <input v-model="form.deliveryDestination_depart" type="text" placeholder="delivery_depart" lang="ja">
                                </div>
                                <div class="form-row row-contact">
                                    <input v-model="form.deliveryDestination_contactPerson" type="text" class="w-contact" placeholder="contactPerson" lang="ja">
                                </div>
                                <div class="form-row row-phone-email">
                                    <input v-model="form.deliveryDestination_phone" type="text" class="w-phone" placeholder="Phone" lang="en" inputmode="tel">
                                    <input v-model="form.deliveryDestination_email" type="text" class="w-email" placeholder="EMail" lang="en" inputmode="email">
                                </div>
                                <div class="form-row row-zip">
                                    <input
                                        v-model="form.deliveryDestination_zipcode"
                                        type="text"
                                        class="w-zip"
                                        lang="en"
                                        inputmode="numeric"
                                        maxlength="8"
                                        placeholder="Zipcode"
                                        @input="onZipcodeInput('delivery')"
                                    >
                                </div>
                                <div class="form-row row-address">
                                    <input v-model="form.deliveryDestination_address1" type="text" class="w-address1" placeholder="address1" lang="ja">
                                    <input v-model="form.deliveryDestination_address2" type="text" class="w-address2" placeholder="address2" lang="ja">
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="maintenanceSearchDone || maintenanceContracts.length"
                            class="info-card info-card-maintenance"
                        >
                            <div class="maintenance-header">
                                <h3>保守契約検索結果</h3>
                                <span v-if="maintenanceSearchDone" class="maintenance-count">
                                    {{ maintenanceContracts.length }}件
                                </span>
                                <button
                                    v-if="selectedMaintenanceContractId"
                                    type="button"
                                    class="btn btn-secondary maintenance-clear-btn"
                                    @click="clearMaintenanceSelection"
                                >
                                    選択解除
                                </button>
                            </div>
                            <p v-if="maintenanceSearchError" class="maintenance-error">{{ maintenanceSearchError }}</p>
                            <div v-else-if="maintenanceContracts.length" class="maintenance-table-wrap">
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
                                条件に一致する有効な保守契約はありません。
                            </p>
                            <p v-if="selectedMaintenanceContractId" class="maintenance-selected-hint">
                                選択中の保守契約は保存時に Note として案件へ紐づけられます。
                            </p>
                        </section>
                    </div>
                </div>

                <div v-show="activeTab === 'related'" class="tab-panel">
                    <div class="related-toolbar">
                        <p class="section-help">この案件に紐付けたい未登録書類にチェックを入れてください。プレビューをクリックすると拡大表示できます。</p>
                        <span class="related-selected">{{ selectedAdditionalCount }}件選択中</span>
                    </div>
                    <div class="related-files">
                        <div
                            v-for="file in additionalFileCandidates"
                            :key="file.id"
                            class="file-option"
                            :class="{ selected: form.additionalFileIds.includes(String(file.id)) }"
                        >
                            <div class="file-option-top">
                                <input
                                    v-model="form.additionalFileIds"
                                    type="checkbox"
                                    :value="String(file.id)"
                                >
                                <div class="file-option-body" />
                            </div>
                            <button type="button" class="related-preview-wrap" @click="openPreview(file)">
                                <iframe
                                    v-if="isPdf(file)"
                                    :src="buildFileUrl(file.id)"
                                    class="related-preview"
                                    :title="`related pdf ${file.id}`"
                                    tabindex="-1"
                                />
                                <img
                                    v-else-if="isImage(file)"
                                    :src="buildFileUrl(file.id)"
                                    :alt="file.documentName || '画像'"
                                    class="related-preview-image"
                                >
                                <div v-else class="related-preview-fallback">プレビュー非対応</div>
                                <span class="preview-hint">クリックで拡大</span>
                            </button>
                        </div>
                        <p v-if="!additionalFileCandidates.length" class="empty-message">関連する未登録書類はありません。</p>
                    </div>
                </div>

                <div v-show="activeTab === 'existing'" class="tab-panel tab-panel-existing">
                    <ExistingRecordSearchDialog
                        inline
                        purpose="file"
                        :records="existingSearchRecords"
                        :query-summary="existingSearchSummary"
                        :statuses="statuses"
                        :searching="existingSearchLoading"
                        :has-searched="existingHasSearched"
                        @search="openExistingRecordSearch"
                        @link-selected="linkToExistingRecord"
                    />
                </div>

                <div v-show="activeTab === 'loaner'" class="tab-panel tab-panel-existing">
                    <div class="loaner-flow-note">
                        <p>
                            検索条件: サービス案件の <strong>productName</strong> が loaner の <strong>item</strong> に含まれる /
                            サービスの <strong>dealer</strong> が loaner の <strong>dealer</strong> に含まれる。
                            紐づけでは、この画面で<strong>新規 service 案件を作成</strong>し、
                            得た orderID を選択した loaner の parentID に設定します。
                            最低限 <strong>productName / SN / dealer / contactPerson</strong> の入力が必要です。
                        </p>
                    </div>
                    <div v-if="selectedLoaners.length" class="selected-loaners">
                        <h4>紐づけ対象（保存時に新規 service 作成 → parentID 設定）</h4>
                        <div class="selected-loaner-list">
                            <div
                                v-for="loaner in selectedLoaners"
                                :key="loaner.orderID"
                                class="selected-loaner-chip"
                            >
                                <span>
                                    #{{ loaner.orderID }}
                                    {{ loaner.productName || '—' }}
                                    / {{ loaner.SN || 'SNなし' }}
                                </span>
                                <button type="button" class="chip-remove" @click="removeSelectedLoaner(loaner.orderID)">×</button>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-primary loaner-create-btn"
                            :disabled="saving"
                            @click="save"
                        >
                            {{ saving ? '作成中...' : '新規 service を作成して紐づけ' }}
                        </button>
                    </div>
                    <ExistingRecordSearchDialog
                        inline
                        purpose="loaner"
                        :records="loanerSearchRecords"
                        :query-summary="loanerSearchSummary"
                        :searching="loanerSearchLoading"
                        :has-searched="loanerHasSearched"
                        @search="openLoanerRecordSearch"
                        @loaner-selected="onLoanerSelected"
                    />
                </div>
                    </section>
                </Pane>
            </Splitpanes>
        </div>

        <div v-if="showLoanerRequirementDialog" class="confirm-overlay" @click.self="cancelLoanerRequirementDialog">
            <div class="confirm-panel">
                <div class="confirm-header">
                    <h3>基本情報が不足しています</h3>
                    <button type="button" class="close-btn" @click="cancelLoanerRequirementDialog">×</button>
                </div>
                <div class="confirm-body">
                    <p>
                        loaner に紐づける新規 service 案件を作成するには、
                        productName / SN / dealer / contactPerson が必要です。
                    </p>
                    <ul class="missing-fields">
                        <li v-for="field in missingLoanerLinkFields" :key="field">{{ field }}</li>
                    </ul>
                    <p>不足項目を OCR で読み取りますか？</p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" @click="cancelLoanerRequirementDialog">キャンセル</button>
                    <button type="button" class="btn btn-secondary" @click="chooseManualEntryForLoaner">手入力する</button>
                    <button type="button" class="btn btn-primary" :disabled="ocrLoading || !hasSourceFile" @click="chooseOcrForLoaner">
                        {{ ocrLoading ? 'OCR読取中...' : 'OCRで読み取る' }}
                    </button>
                </div>
            </div>
        </div>

        <IntakeMasterSelectDialog
            v-if="activeSelectKind"
            :kind="activeSelectKind"
            :items="activeSelectItems"
            :initial-value="activeSelectInitialValue"
            :initial-search-query="activeSelectSearchQuery"
            @close="activeSelectKind = null"
            @selected="onMasterSelected"
        />

        <IntakeFilePreviewDialog
            v-if="previewFile"
            :file="previewFile"
            :files="previewFiles"
            :selectable="true"
            :selected-file-ids="form.additionalFileIds"
            :fixed-file-ids="hasSourceFile ? [String(sourceFile?.id)] : []"
            @close="previewFile = null"
            @saved="onPreviewSaved"
            @navigate="openPreview"
            @toggle-selected="toggleAdditionalFile"
        />

        <div v-if="showWaitingConfirm" class="confirm-overlay" @click.self="cancelWaitingList">
            <div class="confirm-panel">
                <div class="confirm-header">
                    <h3>在庫なし</h3>
                </div>
                <div class="confirm-body">
                    <p class="confirm-warning">在庫が無いので予約リストに追加しますか？</p>
                    <p class="confirm-detail">機種: {{ form.item || form.productName || '—' }}</p>
                    <p v-if="loanerAvailability?.suggestedPeriod" class="confirm-detail">
                        予定期間:
                        {{ loanerAvailability.suggestedPeriod.plannedSentDate }}
                        〜
                        {{ loanerAvailability.suggestedPeriod.plannedReturnedDate }}
                        <template v-if="loanerAvailability.suggestedPeriod.basedOnReturnedDate">
                            （現行貸出終了 {{ loanerAvailability.suggestedPeriod.basedOnReturnedDate }} の翌日開始）
                        </template>
                    </p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" @click="cancelWaitingList">キャンセル</button>
                    <button type="button" class="btn btn-warning" @click="acceptWaitingList">予約リストに追加</button>
                </div>
            </div>
        </div>

        <div v-if="showParentCaseDialog" class="confirm-overlay" @click.self="closeParentCaseDialog">
            <div class="confirm-panel parent-case-panel" @click.stop>
                <div class="confirm-header">
                    <h3>親案件検索</h3>
                    <button type="button" class="close-btn" @click="closeParentCaseDialog">×</button>
                </div>
                <div class="confirm-body parent-case-body">
                    <div class="parent-case-search-row">
                        <label class="parent-case-search-field">
                            <span>parentID</span>
                            <input
                                v-model="parentCaseSearchId"
                                type="number"
                                min="1"
                                class="parent-case-search-input"
                                placeholder="orderID"
                                @keydown.enter.prevent="searchParentCase"
                            >
                        </label>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="parentCaseLoading"
                            @click="searchParentCase"
                        >
                            {{ parentCaseLoading ? '検索中...' : '検索' }}
                        </button>
                    </div>
                    <p v-if="parentCaseError" class="parent-case-error">{{ parentCaseError }}</p>
                    <div v-else-if="parentCaseRecord" class="parent-case-result">
                        <p class="parent-case-result-meta">
                            orderID: {{ parentCaseRecord.orderID }}
                            <template v-if="parentCaseRecord.order_type"> / {{ parentCaseRecord.order_type }}</template>
                            <template v-if="parentCaseRecord.productName"> / {{ parentCaseRecord.productName }}</template>
                            <template v-if="parentCaseRecord.SN"> / SN {{ parentCaseRecord.SN }}</template>
                        </p>
                        <div class="parent-case-stakeholder-grid">
                            <section class="parent-case-stakeholder">
                                <h4>dealer</h4>
                                <dl>
                                    <div><dt>dealer</dt><dd>{{ displayText(parentCaseRecord.dealer) }}</dd></div>
                                    <div><dt>depart</dt><dd>{{ displayText(parentCaseRecord.dealer_depart) }}</dd></div>
                                    <div><dt>contact</dt><dd>{{ displayText(parentCaseRecord.contactPerson) }}</dd></div>
                                    <div><dt>phone</dt><dd>{{ displayText(parentCaseRecord.phone) }}</dd></div>
                                    <div><dt>email</dt><dd>{{ displayText(parentCaseRecord.email) }}</dd></div>
                                    <div><dt>zip</dt><dd>{{ displayText(parentCaseRecord.zipcode) }}</dd></div>
                                    <div><dt>address1</dt><dd>{{ displayText(parentCaseRecord.address1) }}</dd></div>
                                    <div><dt>address2</dt><dd>{{ displayText(parentCaseRecord.address2) }}</dd></div>
                                </dl>
                            </section>
                            <section class="parent-case-stakeholder">
                                <h4>endUser</h4>
                                <dl>
                                    <div><dt>endUser</dt><dd>{{ displayText(parentCaseRecord.endUser) }}</dd></div>
                                    <div><dt>depart</dt><dd>{{ displayText(parentCaseRecord.endUser_depart) }}</dd></div>
                                    <div><dt>contact</dt><dd>{{ displayText(parentCaseRecord.endUser_contactPerson) }}</dd></div>
                                    <div><dt>phone</dt><dd>{{ displayText(parentCaseRecord.endUser_phone) }}</dd></div>
                                    <div><dt>email</dt><dd>{{ displayText(parentCaseRecord.endUser_email) }}</dd></div>
                                    <div><dt>zip</dt><dd>{{ displayText(parentCaseRecord.endUser_zipcode) }}</dd></div>
                                    <div><dt>address1</dt><dd>{{ displayText(parentCaseRecord.endUser_address1) }}</dd></div>
                                    <div><dt>address2</dt><dd>{{ displayText(parentCaseRecord.endUser_address2) }}</dd></div>
                                </dl>
                            </section>
                            <section class="parent-case-stakeholder">
                                <h4>delivery</h4>
                                <dl>
                                    <div><dt>delivery</dt><dd>{{ displayText(parentCaseRecord.deliveryDestination_company) }}</dd></div>
                                    <div><dt>depart</dt><dd>{{ displayText(parentCaseRecord.deliveryDestination_depart) }}</dd></div>
                                    <div><dt>contact</dt><dd>{{ displayText(parentCaseRecord.deliveryDestination_contactPerson) }}</dd></div>
                                    <div><dt>phone</dt><dd>{{ displayText(parentCaseRecord.deliveryDestination_phone) }}</dd></div>
                                    <div><dt>email</dt><dd>{{ displayText(parentCaseRecord.deliveryDestination_email) }}</dd></div>
                                    <div><dt>zip</dt><dd>{{ displayText(parentCaseRecord.deliveryDestination_zipcode) }}</dd></div>
                                    <div><dt>address1</dt><dd>{{ displayText(parentCaseRecord.deliveryDestination_address1) }}</dd></div>
                                    <div><dt>address2</dt><dd>{{ displayText(parentCaseRecord.deliveryDestination_address2) }}</dd></div>
                                </dl>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" @click="closeParentCaseDialog">閉じる</button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="!parentCaseRecord"
                        @click="adoptParentCase"
                    >
                        採用
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showLoanerStockDialog" class="confirm-overlay" @click.self="closeLoanerStockDialog">
            <div class="confirm-panel stock-list-panel">
                <div class="confirm-header">
                    <h3>在庫リスト</h3>
                    <button type="button" class="close-btn" @click="closeLoanerStockDialog">×</button>
                </div>
                <div class="confirm-body stock-list-body">
                    <p class="confirm-detail">機種: {{ form.item || form.productName || '—' }}</p>
                    <div v-if="hasLoanerStock" class="stock-list-hint-row">
                        <p class="confirm-detail ok-text">在庫ありの行をクリックして選択できます</p>
                        <div class="stock-status-legend">
                            <span class="stock-legend-item stock-legend-available">
                                <span class="stock-legend-swatch" aria-hidden="true">■</span>在庫有
                            </span>
                            <span class="stock-legend-item stock-legend-loaned">
                                <span class="stock-legend-swatch" aria-hidden="true">■</span>貸し出し中
                            </span>
                        </div>
                    </div>
                    <p v-else-if="loanerAvailability?.order_type === 'waiting_list'" class="confirm-detail wait-text">
                        在庫なし（参考表示）
                    </p>
                    <div class="loaner-unit-panel loaner-unit-panel-dialog">
                        <table v-if="loanerUnitsForProduct.length" class="loaner-unit-table">
                            <thead>
                                <tr>
                                    <th class="col-loaner-id">loanerID</th>
                                    <th>item</th>
                                    <th>productName</th>
                                    <th>SN</th>
                                    <th>manageNum</th>
                                    <th>certificatedDate</th>
                                    <th>Note1</th>
                                    <th>Note2</th>
                                    <th>Note3</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="unit in loanerUnitsForProduct"
                                    :key="`${unit.loanerID}-${unit.SN}`"
                                    class="loaner-unit-row"
                                    :class="{
                                        available: isLoanerUnitAvailable(unit),
                                        selected: isSelectedLoanerUnit(unit),
                                        selectable: canSelectLoanerUnit(unit),
                                    }"
                                    :title="isLoanerUnitAvailable(unit) ? '在庫' : '貸出中等'"
                                    @click="selectLoanerUnit(unit)"
                                >
                                    <td class="col-loaner-id">{{ unit.loanerID || '—' }}</td>
                                    <td>{{ unit.item || '—' }}</td>
                                    <td>{{ unit.productName || '—' }}</td>
                                    <td>{{ unit.SN || '—' }}</td>
                                    <td>{{ unit.manageNum || '—' }}</td>
                                    <td>{{ formatLoanerDate(unit.certificatedDate) }}</td>
                                    <td class="col-note">{{ unit.note1 || '—' }}</td>
                                    <td class="col-note">{{ unit.note2 || '—' }}</td>
                                    <td class="col-note">{{ unit.note3 || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="loaner-unit-empty">
                            {{ form.productName ? '該当する貸出機がありません' : '機種を選択してください' }}
                        </p>
                    </div>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" @click="closeLoanerStockDialog">閉じる</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import { apiFetch } from '@/utils/apiFetch'
import { loanerStatusOptionLabel } from '@/utils/loanerStatusLabel'
import { startFileImport } from '@/utils/startFileImport'
import { latestMastersByKey } from '@/utils/resolveServiceWorkPrice'
import IntakeMasterSelectDialog from '@/components/ServiceRecord/Intake/IntakeMasterSelectDialog.vue'
import IntakeFilePreviewDialog from '@/components/ServiceRecord/Intake/IntakeFilePreviewDialog.vue'
import ExistingRecordSearchDialog from '@/components/ServiceRecord/Intake/ExistingRecordSearchDialog.vue'

const props = defineProps({
    sourceFile: {
        type: Object,
        default: null,
    },
    unregisteredFiles: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    returnCodes: {
        type: Array,
        default: () => [],
    },
    labors: {
        type: Array,
        default: () => [],
    },
    dealersMaster: {
        type: Array,
        default: () => [],
    },
    servicesMaster: {
        type: Array,
        default: () => [],
    },
    orderType: {
        type: String,
        default: 'service',
    },
    loanerProducts: {
        type: Array,
        default: () => [],
    },
    loaners: {
        type: Array,
        default: () => [],
    },
    loanerStatusColumn: {
        type: String,
        default: 'currentStatus',
    },
})

const page = usePage()
const resolvedOrderType = computed(() => (
    props.orderType === 'loaner' ? 'loaner' : 'service'
))
const isLoanerCase = computed(() => resolvedOrderType.value === 'loaner')
const pageTitle = computed(() => (
    isLoanerCase.value ? '新規案件作成（Loaner）' : '新規案件作成（サービス）'
))
const saving = ref(false)
const error = ref('')
const success = ref('')
const ocrLoading = ref(false)
const activeTab = ref('basic')
const activeSelectKind = ref(null)
const previewFile = ref(null)
const previewCacheBust = ref(Date.now())
const existingSearchLoading = ref(false)
const existingHasSearched = ref(false)
const existingSearchRecords = ref([])
const loanerSearchLoading = ref(false)
const loanerHasSearched = ref(false)
const loanerSearchRecords = ref([])
const selectedLoaners = ref([])
const showLoanerRequirementDialog = ref(false)
const pendingLoanerRecord = ref(null)
const loanerRequirementContext = ref(null) // 'select' | 'save'
const leftPaneSize = ref(42)
const rightPaneSize = ref(58)

function syncPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

function defaultPeriodStart() {
    return new Date().toISOString().slice(0, 10)
}

function defaultPeriodEnd() {
    const d = new Date()
    d.setDate(d.getDate() + 14)
    return d.toISOString().slice(0, 10)
}

const form = reactive({
    receivedDate: '',
    status: '0',
    serviceID: '',
    productName: '',
    item: '',
    entityID: '',
    SN: '',
    enduser_SN: '',
    instrumentName: '',
    loanerID: '',
    returnCode: '',
    plannedSentDate: defaultPeriodStart(),
    plannedReturnedDate: defaultPeriodEnd(),
    dealer: '',
    dealer_depart: '',
    contactPerson: '',
    email: '',
    phone: '',
    zipcode: '',
    address1: '',
    address2: '',
    endUser: '',
    endUser_depart: '',
    endUser_contactPerson: '',
    endUser_phone: '',
    endUser_email: '',
    endUser_zipcode: '',
    endUser_address1: '',
    endUser_address2: '',
    deliveryDestination_company: '',
    deliveryDestination_depart: '',
    deliveryDestination_contactPerson: '',
    deliveryDestination_phone: '',
    deliveryDestination_email: '',
    deliveryDestination_zipcode: '',
    deliveryDestination_address1: '',
    deliveryDestination_address2: '',
    additionalFileIds: [],
})

const loanerAvailability = ref(null)
const loanerAvailabilityChecking = ref(false)
const waitingListAccepted = ref(false)
const showWaitingConfirm = ref(false)
const showLoanerStockDialog = ref(false)
const showParentCaseDialog = ref(false)
const parentCaseSearchId = ref('')
const parentCaseRecord = ref(null)
const parentCaseLoading = ref(false)
const parentCaseError = ref('')
const maintenanceContracts = ref([])
const selectedMaintenanceContractId = ref(null)
const maintenanceSearchLoading = ref(false)
const maintenanceSearchDone = ref(false)
const maintenanceSearchError = ref('')

const STAKEHOLDER_FIELDS = {
    dealer: ['dealer', 'dealer_depart', 'contactPerson', 'phone', 'email', 'zipcode', 'address1', 'address2'],
    endUser: ['endUser', 'endUser_depart', 'endUser_contactPerson', 'endUser_phone', 'endUser_email', 'endUser_zipcode', 'endUser_address1', 'endUser_address2'],
    delivery: [
        'deliveryDestination_company',
        'deliveryDestination_depart',
        'deliveryDestination_contactPerson',
        'deliveryDestination_phone',
        'deliveryDestination_email',
        'deliveryDestination_zipcode',
        'deliveryDestination_address1',
        'deliveryDestination_address2',
    ],
}

function readStakeholder(kind) {
    const fields = STAKEHOLDER_FIELDS[kind] || []
    return fields.map(field => form[field] ?? '')
}

function writeStakeholder(kind, values) {
    const fields = STAKEHOLDER_FIELDS[kind] || []
    fields.forEach((field, index) => {
        form[field] = values[index] ?? ''
    })
}

function swapStakeholders(left, right) {
    if (left === right) return
    if (!STAKEHOLDER_FIELDS[left] || !STAKEHOLDER_FIELDS[right]) return
    const leftValues = readStakeholder(left)
    const rightValues = readStakeholder(right)
    writeStakeholder(left, rightValues)
    writeStakeholder(right, leftValues)
}

function matchFromEndUser(target) {
    if (!STAKEHOLDER_FIELDS[target] || target === 'endUser') return
    writeStakeholder(target, readStakeholder('endUser'))
}

const statuses = computed(() => props.statuses ?? [])
const returnCodes = computed(() => props.returnCodes ?? [])
const dealers = computed(() => props.dealersMaster ?? [])
const services = computed(() => latestMastersByKey(props.servicesMaster ?? [], 'serviceID'))
const loanerUnits = computed(() => {
    const seen = new Set()
    const unique = []
    for (const unit of props.loaners ?? []) {
        const key = unit?.loanerID != null && unit.loanerID !== ''
            ? String(unit.loanerID)
            : `id:${unit?.id ?? ''}`
        if (seen.has(key)) continue
        seen.add(key)
        unique.push(unit)
    }
    return unique
})
const loanerProductOptions = computed(() =>
    (props.loanerProducts ?? [])
        .filter((item) => {
            const text = String(item?.item ?? '')
            return !text.includes('使用不可') && !text.includes('サービス終了')
        })
        .map(item => ({
            item: item.item ?? '',
            productName: item.productName,
            availableCount: item.availableCount,
            totalCount: item.totalCount,
            order_type: item.order_type,
        }))
        .sort((a, b) => {
            const ka = String(a.item ?? '')
                .replace(/【簿外】/g, '')
                .replace(/[^0-9A-Za-z]+/g, '')
                .toLowerCase()
            const kb = String(b.item ?? '')
                .replace(/【簿外】/g, '')
                .replace(/[^0-9A-Za-z]+/g, '')
                .toLowerCase()
            return ka.localeCompare(kb, 'en', { numeric: true, sensitivity: 'base' })
                || String(a.productName ?? '').localeCompare(String(b.productName ?? ''), 'en', { numeric: true })
        }),
)
const loanerUnitsForProduct = computed(() => {
    const productName = String(form.productName || '').trim()
    if (!productName) return []
    return loanerUnits.value.filter((unit) => {
        if (String(unit.productName || '') !== productName) return false
        return !isExcludedLoanerItem(unit?.item)
    })
})

const selectedLoanerUnit = computed(() => {
    if (!form.loanerID) return null
    return loanerUnitsForProduct.value.find(unit => String(unit.loanerID) === String(form.loanerID)) ?? null
})

const hasLoanerStock = computed(() => loanerAvailability.value?.order_type === 'loaner')
const availableLoanerCount = computed(() =>
    loanerUnitsForProduct.value.filter(unit => isLoanerUnitAvailable(unit)).length,
)

function isExcludedLoanerItem(itemText) {
    const text = String(itemText ?? '')
    return text.includes('使用不可') || text.includes('サービス終了')
}

function loanerUnitStatusValue(unit) {
    const column = props.loanerStatusColumn || 'currentStatus'
    if (unit?.[column] != null) return Number(unit[column])
    if (unit?.currentStatus != null) return Number(unit.currentStatus)
    if (unit?.current_status != null) return Number(unit.current_status)
    return null
}

function isLoanerUnitAvailable(unit) {
    if (isExcludedLoanerItem(unit?.item)) return false
    return loanerUnitStatusValue(unit) === 0
}

function formatLoanerDate(value) {
    if (value == null || value === '') return '—'
    if (typeof value === 'string') return value.slice(0, 10)
    return String(value).slice(0, 10)
}

function canSelectLoanerUnit(unit) {
    return hasLoanerStock.value && isLoanerUnitAvailable(unit)
}

function isSelectedLoanerUnit(unit) {
    return form.loanerID !== '' && String(unit?.loanerID) === String(form.loanerID)
}

function selectLoanerUnit(unit) {
    if (!canSelectLoanerUnit(unit) || isExcludedLoanerItem(unit?.item)) return
    form.loanerID = unit.loanerID != null ? String(unit.loanerID) : ''
    form.SN = unit.SN ?? ''
    if (unit.item) form.item = unit.item
    if (unit.productName) form.productName = unit.productName
    showLoanerStockDialog.value = false
}

function openLoanerStockDialog() {
    if (!form.productName) return
    showLoanerStockDialog.value = true
}

function closeLoanerStockDialog() {
    showLoanerStockDialog.value = false
}

function displayText(value) {
    const text = value == null ? '' : String(value).trim()
    return text === '' ? '—' : text
}

function openParentCaseDialog() {
    parentCaseError.value = ''
    showParentCaseDialog.value = true
}

function closeParentCaseDialog() {
    if (parentCaseLoading.value) return
    showParentCaseDialog.value = false
    parentCaseError.value = ''
}

async function searchParentCase() {
    const orderId = String(parentCaseSearchId.value ?? '').trim()
    if (!orderId) {
        parentCaseError.value = 'parentID を入力してください。'
        parentCaseRecord.value = null
        return
    }

    parentCaseLoading.value = true
    parentCaseError.value = ''
    parentCaseRecord.value = null

    try {
        const url = `${page.props.appBaseUrl}/servicerecord/record/${encodeURIComponent(orderId)}`
        const result = await apiFetch(url)
        if (!result) return

        const { response, data } = result
        if (response.status === 404) {
            throw new Error(`parentID ${orderId} の案件は見つかりません。`)
        }
        if (!response.ok) {
            throw new Error(data.message || `検索に失敗しました。（HTTP ${response.status}）`)
        }
        parentCaseRecord.value = data
    } catch (e) {
        parentCaseError.value = e.message || '親案件の検索に失敗しました。'
    } finally {
        parentCaseLoading.value = false
    }
}

function adoptParentCase() {
    const record = parentCaseRecord.value
    if (!record) return

    Object.values(STAKEHOLDER_FIELDS).flat().forEach((field) => {
        form[field] = record[field] == null ? '' : String(record[field])
    })

    showParentCaseDialog.value = false
    parentCaseError.value = ''
}

function autoSelectFirstAvailableUnit() {
    const first = loanerUnitsForProduct.value.find(unit => isLoanerUnitAvailable(unit))
    if (!first) {
        form.loanerID = ''
        return
    }
    form.loanerID = first.loanerID != null ? String(first.loanerID) : ''
    form.SN = first.SN ?? ''
}

const zipLookupTimers = {
    dealer: null,
    endUser: null,
    delivery: null,
}

const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)
const intakeListUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/intake`)
const hasSourceFile = computed(() => Boolean(props.sourceFile?.id))
const isSourcePdf = computed(() => props.sourceFile?.fileType === 'application/pdf')
const isSourceImage = computed(() => String(props.sourceFile?.fileType || '').startsWith('image/'))
const sourceFileUrl = computed(() => (
    hasSourceFile.value ? buildFileUrl(props.sourceFile.id) : ''
))

const additionalFileCandidates = computed(() =>
    (props.unregisteredFiles ?? []).filter(file => (
        !hasSourceFile.value || Number(file.id) !== Number(props.sourceFile.id)
    )),
)
const previewFiles = computed(() => (
    hasSourceFile.value
        ? [props.sourceFile, ...additionalFileCandidates.value]
        : [...additionalFileCandidates.value]
))

const selectedAdditionalCount = computed(() => form.additionalFileIds.length)
const relatedFileCount = computed(() => additionalFileCandidates.value.length)

const selectedProductLabel = computed(() => {
    if (form.productName) {
        return `${form.productName} (${form.serviceID})`
    }
    return 'productName'
})
const existingSearchTerms = computed(() =>
    [
        form.productName,
        form.SN,
        form.dealer,
        form.contactPerson,
    ]
        .map(value => String(value ?? '').trim())
        .filter(Boolean)
)
const existingSearchSummary = computed(() => existingSearchTerms.value.join(' / '))

const loanerSearchTerms = computed(() =>
    [form.productName, form.dealer]
        .map(value => String(value ?? '').trim())
        .filter(Boolean)
)
const loanerSearchSummary = computed(() => loanerSearchTerms.value.join(' / '))

const missingLoanerLinkFields = computed(() => {
    const missing = []
    if (!String(form.productName || '').trim()) missing.push('productName')
    if (!String(form.SN || '').trim()) missing.push('SN')
    if (!String(form.dealer || '').trim()) missing.push('dealer')
    if (!String(form.contactPerson || '').trim()) missing.push('contactPerson')
    return missing
})

const hasLoanerLinkRequiredFields = computed(() => missingLoanerLinkFields.value.length === 0)

const saveButtonLabel = computed(() => {
    if (saving.value) {
        return (!isLoanerCase.value && selectedLoaners.value.length) ? '作成中...' : '保存中...'
    }
    if (!isLoanerCase.value && selectedLoaners.value.length) {
        return '新規 service を作成して紐づけ'
    }
    return '保存'
})

watch(hasLoanerLinkRequiredFields, (ok) => {
    if (!ok || !pendingLoanerRecord.value) return
    if (loanerRequirementContext.value !== 'select') return

    addSelectedLoaner(pendingLoanerRecord.value)
    pendingLoanerRecord.value = null
    loanerRequirementContext.value = null
    error.value = ''
    activeTab.value = 'loaner'
})

const activeSelectItems = computed(() => {
    if (activeSelectKind.value === 'dealer') return dealers.value
    if (activeSelectKind.value === 'serviceMaster') return services.value
    if (activeSelectKind.value === 'loanerProduct') return loanerProductOptions.value
    return []
})

const activeSelectInitialValue = computed(() => {
    if (activeSelectKind.value === 'serviceMaster') {
        const matched = services.value.find(item =>
            form.productName
            && String(item.productName) === String(form.productName),
        )
        return matched?.id ?? null
    }
    if (activeSelectKind.value === 'loanerProduct') {
        return form.productName || null
    }
    if (activeSelectKind.value === 'dealer') {
        const matched = dealers.value.find(item => item.dealerName === form.dealer)
        return matched?.id ?? null
    }
    return null
})

const activeSelectSearchQuery = computed(() => {
    if (activeSelectKind.value === 'serviceMaster' || activeSelectKind.value === 'loanerProduct') {
        return String(form.productName ?? '').trim()
    }
    return ''
})

function openSelectDialog(kind) {
    activeSelectKind.value = kind
}

function openPreview(file) {
    previewFile.value = file
}

function onPreviewSaved() {
    previewCacheBust.value = Date.now()
}

function toggleAdditionalFile(file) {
    const fileId = String(file?.id ?? '')
    if (!fileId || fileId === String(props.sourceFile?.id)) return

    const index = form.additionalFileIds.findIndex(id => String(id) === fileId)
    if (index === -1) {
        form.additionalFileIds.push(fileId)
        return
    }

    form.additionalFileIds.splice(index, 1)
}

function onProductNameTyped() {
    // 手入力時はマスタ選択との紐づけを外す（再選択で再設定）
    form.serviceID = ''
    form.entityID = ''
}

function onMasterSelected(result) {
    if (activeSelectKind.value === 'serviceMaster') {
        form.serviceID = result.serviceID != null ? String(result.serviceID) : ''
        form.productName = result.productName ?? ''
        form.entityID = result.entityID ?? ''
    }

    if (activeSelectKind.value === 'loanerProduct') {
        form.productName = result.productName ?? ''
        form.item = result.item ?? ''
        form.instrumentName = String(result.item || result.productName || '').trim()
        form.serviceID = ''
        form.entityID = ''
        form.SN = ''
        form.loanerID = ''
        loanerAvailability.value = null
        waitingListAccepted.value = false
        showWaitingConfirm.value = false
        checkLoanerAvailability()
    }

    if (activeSelectKind.value === 'dealer') {
        form.dealer = result.dealer ?? ''
        form.dealer_depart = result.dealer_depart ?? ''
        form.contactPerson = result.contactPerson ?? ''
        form.email = result.email ?? ''
        form.phone = result.phone ?? ''
    }

    activeSelectKind.value = null
}

function clearLoanerProductSelection() {
    form.productName = ''
    form.item = ''
    form.instrumentName = ''
    form.SN = ''
    form.loanerID = ''
    loanerAvailability.value = null
    waitingListAccepted.value = false
    showWaitingConfirm.value = false
}

function acceptWaitingList() {
    waitingListAccepted.value = true
    showWaitingConfirm.value = false
}

function cancelWaitingList() {
    clearLoanerProductSelection()
}

async function checkLoanerAvailability() {
    if (!form.productName) {
        loanerAvailability.value = null
        return
    }

    loanerAvailabilityChecking.value = true
    error.value = ''

    try {
        const params = new URLSearchParams({ productName: form.productName })
        const url = `${page.props.appBaseUrl}/servicerecord/loaner/availability?${params.toString()}`
        const result = await apiFetch(url)
        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `在庫確認に失敗しました。（HTTP ${response.status}）`)
        }

        loanerAvailability.value = data
        if (data.order_type === 'waiting_list') {
            form.loanerID = ''
            form.SN = ''
            waitingListAccepted.value = false
            showWaitingConfirm.value = true
            if (data.suggestedPeriod?.plannedSentDate) {
                form.plannedSentDate = data.suggestedPeriod.plannedSentDate
            }
            if (data.suggestedPeriod?.plannedReturnedDate) {
                form.plannedReturnedDate = data.suggestedPeriod.plannedReturnedDate
            }
        } else {
            waitingListAccepted.value = false
            showWaitingConfirm.value = false
            form.plannedSentDate = defaultPeriodStart()
            form.plannedReturnedDate = defaultPeriodEnd()
            autoSelectFirstAvailableUnit()
        }
    } catch (e) {
        loanerAvailability.value = null
        error.value = e.message || '在庫確認に失敗しました。'
    } finally {
        loanerAvailabilityChecking.value = false
    }
}

function buildFileUrl(fileId) {
    return `${page.props.appBaseUrl}/servicerecord/files/${fileId}?t=${previewCacheBust.value}#view=FitV`
}

function isPdf(file) {
    return file?.fileType === 'application/pdf'
}

function isImage(file) {
    return String(file?.fileType || '').startsWith('image/')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function fetchAddressByZipcode(zipcode) {
    const digits = String(zipcode ?? '').replace(/\D/g, '')
    if (digits.length !== 7) {
        return null
    }

    const response = await fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${digits}`)
    if (!response.ok) {
        throw new Error('郵便番号の検索に失敗しました。')
    }

    const data = await response.json()
    const result = data?.results?.[0]
    if (!result) {
        return null
    }

    return {
        address1: result.address1 || '',
        address2: `${result.address2 || ''}${result.address3 || ''}`,
    }
}

function onZipcodeInput(kind) {
    if (zipLookupTimers[kind]) {
        clearTimeout(zipLookupTimers[kind])
    }

    zipLookupTimers[kind] = setTimeout(async () => {
        const map = {
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
        const fields = map[kind]
        if (!fields) return

        const digits = String(form[fields.zip] ?? '').replace(/\D/g, '')
        if (digits.length !== 7) return

        try {
            const address = await fetchAddressByZipcode(digits)
            if (!address) return
            form[fields.address1] = address.address1
            form[fields.address2] = address.address2
        } catch (e) {
            error.value = e.message || '郵便番号の検索に失敗しました。'
        }
    }, 350)
}

async function openExistingRecordSearch() {
    if (existingSearchTerms.value.length === 0) {
        error.value = 'productName / SN / dealer / contactPerson のいずれかを入力してから検索してください。'
        activeTab.value = 'basic'
        return
    }

    existingSearchLoading.value = true
    error.value = ''

    try {
        const params = new URLSearchParams({ order_type: 'service' })
        if (form.productName) params.set('productName', form.productName)
        if (form.SN) params.set('SN', form.SN)
        if (form.dealer) params.set('dealer', form.dealer)
        if (form.contactPerson) params.set('contactPerson', form.contactPerson)

        const url = `${page.props.appBaseUrl}/servicerecord/search-existing?${params.toString()}`
        const result = await apiFetch(url)

        if (!result) {
            return
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `検索に失敗しました。（HTTP ${response.status}）`)
        }

        existingSearchRecords.value = data.records ?? []
        existingHasSearched.value = true
        activeTab.value = 'existing'
    } catch (e) {
        error.value = e.message || '検索に失敗しました。'
    } finally {
        existingSearchLoading.value = false
    }
}

async function openLoanerRecordSearch() {
    if (loanerSearchTerms.value.length === 0) {
        error.value = 'productName / dealer のいずれかを入力してから検索してください。（productName→item / dealer→dealer）'
        activeTab.value = 'basic'
        return
    }

    loanerSearchLoading.value = true
    error.value = ''

    try {
        const params = new URLSearchParams({ order_type: 'loaner' })
        if (form.productName) params.set('productName', form.productName)
        if (form.dealer) params.set('dealer', form.dealer)

        const url = `${page.props.appBaseUrl}/servicerecord/search-existing?${params.toString()}`
        const result = await apiFetch(url)

        if (!result) {
            return
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `検索に失敗しました。（HTTP ${response.status}）`)
        }

        loanerSearchRecords.value = data.records ?? []
        loanerHasSearched.value = true
        activeTab.value = 'loaner'
    } catch (e) {
        error.value = e.message || '検索に失敗しました。'
    } finally {
        loanerSearchLoading.value = false
    }
}

function switchToExistingTab() {
    activeTab.value = 'existing'
    if (!existingHasSearched.value && !existingSearchLoading.value) {
        openExistingRecordSearch()
    }
}

function switchToLoanerTab() {
    activeTab.value = 'loaner'
    if (!loanerHasSearched.value && !loanerSearchLoading.value) {
        openLoanerRecordSearch()
    }
}

async function searchMaintenanceContracts() {
    // 入力確定前（IME 変換中など）の値漏れを防ぐ
    if (typeof document !== 'undefined' && document.activeElement instanceof HTMLElement) {
        document.activeElement.blur()
    }
    await nextTick()

    // DOM 上の実値も同期（表示はあるが v-model 未反映のケース対策）
    if (typeof document !== 'undefined') {
        const root = isLoanerCase.value
            ? document.querySelector('.form-stack-loaner')
            : (document.querySelector('.panel-form .form-stack:not(.form-stack-loaner)')
                || document.querySelector('.panel-form'))
        if (root) {
            if (isLoanerCase.value) {
                const dealerInput = root.querySelector('input.w-dealer-name')
                const enduserSnInput = root.querySelector('.field-enduser-sn input')
                const instrumentInput = root.querySelector('.field-instrument-name input')
                if (dealerInput && String(dealerInput.value || '').trim()) {
                    form.dealer = String(dealerInput.value).trim()
                }
                if (enduserSnInput && String(enduserSnInput.value || '').trim()) {
                    form.enduser_SN = String(enduserSnInput.value).trim()
                }
                if (instrumentInput && String(instrumentInput.value || '').trim()) {
                    form.instrumentName = String(instrumentInput.value).trim()
                }
            } else {
                const productInput = root.querySelector('input.w-product-name')
                const dealerInput = root.querySelector('input.w-dealer-name')
                const snInput = root.querySelector('.info-card-main input[placeholder="SN"]')
                    || root.querySelector('input[placeholder="SN"]')
                if (productInput && String(productInput.value || '').trim()) {
                    form.productName = String(productInput.value).trim()
                }
                if (dealerInput && String(dealerInput.value || '').trim()) {
                    form.dealer = String(dealerInput.value).trim()
                }
                if (snInput && String(snInput.value || '').trim()) {
                    form.SN = String(snInput.value).trim()
                }
            }
        }
    }

    const itemOrProduct = isLoanerCase.value
        ? String(form.instrumentName ?? '').trim()
        : String(form.productName ?? '').trim()
    const sn = isLoanerCase.value
        ? String(form.enduser_SN ?? '').trim()
        : String(form.SN ?? '').trim()
    const dealer = String(form.dealer ?? '').trim()

    const missing = []
    if (isLoanerCase.value) {
        if (!itemOrProduct) missing.push('機種名')
        if (!sn) missing.push('enduser_SN')
    } else {
        if (!itemOrProduct) missing.push('productName')
        if (!sn) missing.push('SN')
    }
    if (!dealer) missing.push('dealer')

    if (missing.length) {
        maintenanceSearchError.value = `${missing.join(' / ')} を入力してから検索してください。`
        maintenanceSearchDone.value = true
        maintenanceContracts.value = []
        selectedMaintenanceContractId.value = null
        activeTab.value = 'basic'
        return
    }

    maintenanceSearchLoading.value = true
    maintenanceSearchError.value = ''
    maintenanceSearchDone.value = false
    error.value = ''
    activeTab.value = 'basic'

    try {
        const params = new URLSearchParams({
            productName: itemOrProduct,
            SN: sn,
            dealer,
        })
        if (isLoanerCase.value) {
            params.set('match', 'contains')
        }
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

function addSelectedLoaner(record) {
    if (!record?.orderID) return
    if (record.parentID) {
        error.value = `orderID ${record.orderID} は既に parentID=${record.parentID} へ紐づいています。`
        return
    }
    if (selectedLoaners.value.some(item => String(item.orderID) === String(record.orderID))) {
        return
    }
    const isFirst = selectedLoaners.value.length === 0
    selectedLoaners.value = [...selectedLoaners.value, record]
    // 初回紐づけ時、未設定/未着荷なら「未着荷―貸出機先行」へ
    if (isFirst && (form.status === '' || form.status === '0')) {
        form.status = '3'
    }
    error.value = ''
}

function openLoanerRequirementDialog(context, record = null) {
    loanerRequirementContext.value = context
    pendingLoanerRecord.value = record
    showLoanerRequirementDialog.value = true
}

function cancelLoanerRequirementDialog() {
    showLoanerRequirementDialog.value = false
    pendingLoanerRecord.value = null
    loanerRequirementContext.value = null
}

function chooseManualEntryForLoaner() {
    showLoanerRequirementDialog.value = false
    activeTab.value = 'basic'
    error.value = `不足項目を入力してください: ${missingLoanerLinkFields.value.join(', ')}（入力後、loaner案件検索から再度追加できます）`
}

function applyOcrFields(fields) {
    if (!fields || typeof fields !== 'object') return 0
    let applied = 0
    Object.entries(fields).forEach(([key, value]) => {
        if (!(key in form)) return
        if (value === null || value === undefined) return
        const text = String(value).trim()
        if (text === '' || text.toLowerCase() === 'null') return
        form[key] = text
        applied += 1
    })
    return applied
}

async function runOcrFromSourceFile({ continueLoanerFlow = false } = {}) {
    if (!hasSourceFile.value) {
        error.value = 'OCR 対象の申請フォームがありません。'
        return false
    }
    if (ocrLoading.value) return false

    ocrLoading.value = true
    error.value = ''
    success.value = ''
    activeTab.value = 'basic'

    try {
        const url = `${page.props.appBaseUrl}/servicerecord/intake/ocr`
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                fileId: Number(props.sourceFile.id),
            }),
        })

        if (!result) {
            throw new Error('OCR の実行に失敗しました。')
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data?.message || `OCR の実行に失敗しました。（HTTP ${response.status}）`)
        }

        const applied = applyOcrFields(data?.fields ?? {})
        success.value = data?.message
            ? `${data.message}（${applied}項目を反映）`
            : `OCR 読み取り結果を反映しました。（${applied}項目）`

        if (continueLoanerFlow) {
            if (hasLoanerLinkRequiredFields.value && pendingLoanerRecord.value) {
                const record = pendingLoanerRecord.value
                showLoanerRequirementDialog.value = false
                pendingLoanerRecord.value = null
                loanerRequirementContext.value = null
                addSelectedLoaner(record)
            } else {
                showLoanerRequirementDialog.value = false
                error.value = `OCR 後も不足があります: ${missingLoanerLinkFields.value.join(', ') || '必須項目'}`
            }
        }

        return true
    } catch (e) {
        error.value = e.message || 'OCR の実行に失敗しました。'
        return false
    } finally {
        ocrLoading.value = false
    }
}

async function chooseOcrForLoaner() {
    await runOcrFromSourceFile({ continueLoanerFlow: true })
}

function onLoanerSelected(payload) {
    const record = payload?.record ?? payload
    if (!record?.orderID) return
    if (record.parentID) {
        error.value = `orderID ${record.orderID} は既に parentID=${record.parentID} へ紐づいています。`
        return
    }
    if (!hasLoanerLinkRequiredFields.value) {
        openLoanerRequirementDialog('select', record)
        return
    }
    addSelectedLoaner(record)
}

function removeSelectedLoaner(orderID) {
    selectedLoaners.value = selectedLoaners.value.filter(item => String(item.orderID) !== String(orderID))
}

async function linkToExistingRecord(payload) {
    const record = payload?.record ?? payload
    if (!record?.orderID) return
    if (!hasSourceFile.value) {
        error.value = '添付ファイル無しの場合は既存案件へのファイル紐づけはできません。新規保存を利用してください。'
        return
    }

    existingSearchLoading.value = true
    error.value = ''

    try {
        const url = `${page.props.appBaseUrl}/servicerecord/intake/link-existing`
        const body = {
            orderID: Number(record.orderID),
            sourceFileId: Number(props.sourceFile.id),
            additionalFileIds: form.additionalFileIds.map(id => Number(id)),
        }
        if (Object.prototype.hasOwnProperty.call(payload ?? {}, 'receivedDate')) {
            body.receivedDate = payload.receivedDate
        }
        if (payload?.status != null) {
            body.status = Number(payload.status)
        }

        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        })

        if (!result) {
            return
        }

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `紐付けに失敗しました。（HTTP ${response.status}）`)
        }

        window.location.href = intakeListUrl.value
    } catch (e) {
        error.value = e.message || '紐付けに失敗しました。'
    } finally {
        existingSearchLoading.value = false
    }
}

onMounted(() => {
    // 同期キューでも UI を塞がないよう、待たずに起動する
    startFileImport({
        appBaseUrl: page.props.appBaseUrl,
        associatedID: -1,
    }).then((result) => {
        // 「処理を開始しました」等の情報カードは表示しない。ロック時のみエラー表示。
        if (result.status === 423) {
            error.value = result.message || '他の処理が実行中です。'
        }
    }).catch(() => {
        // 取込開始失敗は入力を妨げない
    })
})

onBeforeUnmount(() => {
    Object.keys(zipLookupTimers).forEach((key) => {
        if (zipLookupTimers[key]) {
            clearTimeout(zipLookupTimers[key])
            zipLookupTimers[key] = null
        }
    })
})

async function saveLoanerCase() {
    if (!form.productName) {
        error.value = 'productName を選択してください。'
        return
    }

    if (loanerAvailability.value?.order_type === 'waiting_list' && !waitingListAccepted.value) {
        showWaitingConfirm.value = true
        return
    }

    if (hasLoanerStock.value && !form.loanerID) {
        error.value = '在庫機を一覧から選択してください。'
        return
    }

    if (form.plannedSentDate && form.plannedReturnedDate && form.plannedReturnedDate < form.plannedSentDate) {
        error.value = '貸出終了日は開始日以降にしてください。'
        return
    }

    saving.value = true
    error.value = ''

    try {
        const url = `${page.props.appBaseUrl}/servicerecord/loaner/store`
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                productName: form.productName,
                receivedDate: null,
                linkMode: 'none',
                parentID: null,
                status: null,
                returnCode: null,
                SN: form.SN || null,
                enduser_SN: form.enduser_SN === '' || form.enduser_SN == null
                    ? null
                    : String(form.enduser_SN).trim(),
                loanerID: form.loanerID === '' ? null : Number(form.loanerID),
                plannedSentDate: form.plannedSentDate || null,
                plannedReturnedDate: form.plannedReturnedDate || null,
                dealer: form.dealer || null,
                dealer_depart: form.dealer_depart || null,
                contactPerson: form.contactPerson || null,
                email: form.email || null,
                phone: form.phone || null,
                zipcode: form.zipcode || null,
                address1: form.address1 || null,
                address2: form.address2 || null,
                endUser: form.endUser || null,
                endUser_depart: form.endUser_depart || null,
                endUser_contactPerson: form.endUser_contactPerson || null,
                endUser_phone: form.endUser_phone || null,
                endUser_email: form.endUser_email || null,
                endUser_zipcode: form.endUser_zipcode || null,
                endUser_address1: form.endUser_address1 || null,
                endUser_address2: form.endUser_address2 || null,
                deliveryDestination_company: form.deliveryDestination_company || null,
                deliveryDestination_depart: form.deliveryDestination_depart || null,
                deliveryDestination_contactPerson: form.deliveryDestination_contactPerson || null,
                deliveryDestination_phone: form.deliveryDestination_phone || null,
                deliveryDestination_zipcode: form.deliveryDestination_zipcode || null,
                deliveryDestination_address1: form.deliveryDestination_address1 || null,
                deliveryDestination_address2: form.deliveryDestination_address2 || null,
                sourceFileId: hasSourceFile.value ? props.sourceFile.id : null,
                additionalFileIds: form.additionalFileIds.map(id => Number(id)),
                maintenanceContractId: selectedMaintenanceContractId.value
                    ? Number(selectedMaintenanceContractId.value)
                    : null,
            }),
        })

        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        if (data.attachedLoanerId) {
            window.location.href = `${page.props.appBaseUrl}/servicerecord/loaner/period/${data.attachedLoanerId}`
        } else {
            window.location.href = intakeListUrl.value
        }
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}

async function save() {
    if (isLoanerCase.value) {
        await saveLoanerCase()
        return
    }

    if (selectedLoaners.value.length > 0) {
        if (!hasLoanerLinkRequiredFields.value) {
            openLoanerRequirementDialog('save')
            return
        }
    } else if (!String(form.productName || '').trim()) {
        error.value = 'productName を入力または選択してください。'
        return
    }

    saving.value = true
    error.value = ''

    const url = `${page.props.appBaseUrl}/servicerecord/intake/store`

    try {
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                sourceFileId: hasSourceFile.value ? props.sourceFile.id : null,
                additionalFileIds: form.additionalFileIds.map(id => Number(id)),
                receivedDate: form.receivedDate || null,
                status: form.status === '' ? null : Number(form.status),
                serviceID: form.serviceID === '' ? null : Number(form.serviceID),
                productName: String(form.productName || '').trim() || null,
                SN: form.SN || null,
                returnCode: form.returnCode === '' ? null : Number(form.returnCode),
                dealer: form.dealer || null,
                dealer_depart: form.dealer_depart || null,
                contactPerson: form.contactPerson || null,
                email: form.email || null,
                phone: form.phone || null,
                zipcode: form.zipcode || null,
                address1: form.address1 || null,
                address2: form.address2 || null,
                endUser: form.endUser || null,
                endUser_depart: form.endUser_depart || null,
                endUser_contactPerson: form.endUser_contactPerson || null,
                endUser_phone: form.endUser_phone || null,
                endUser_email: form.endUser_email || null,
                endUser_zipcode: form.endUser_zipcode || null,
                endUser_address1: form.endUser_address1 || null,
                endUser_address2: form.endUser_address2 || null,
                deliveryDestination_company: form.deliveryDestination_company || null,
                deliveryDestination_depart: form.deliveryDestination_depart || null,
                deliveryDestination_contactPerson: form.deliveryDestination_contactPerson || null,
                deliveryDestination_phone: form.deliveryDestination_phone || null,
                deliveryDestination_email: form.deliveryDestination_email || null,
                deliveryDestination_zipcode: form.deliveryDestination_zipcode || null,
                deliveryDestination_address1: form.deliveryDestination_address1 || null,
                deliveryDestination_address2: form.deliveryDestination_address2 || null,
                loanerOrderIds: selectedLoaners.value.map(item => Number(item.orderID)),
                order_type: resolvedOrderType.value,
                maintenanceContractId: selectedMaintenanceContractId.value
                    ? Number(selectedMaintenanceContractId.value)
                    : null,
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

        window.location.href = intakeListUrl.value
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.create-page {
    height: 100vh;
    min-height: 100vh;
    padding: 12px 16px;
    background: #e2e8f0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 12px;
    flex: 0 0 auto;
}

.page-header h1 {
    margin: 0 0 8px;
    font-size: 24px;
    color: #1e293b;
}

.page-header p {
    margin: 0;
    color: #475569;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.global-error {
    margin: 0 0 16px;
    padding: 10px 14px;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    background: #fef2f2;
    color: #b91c1c;
}

.global-success {
    margin: 0 0 16px;
    padding: 10px 14px;
    border: 1px solid #86efac;
    border-radius: 6px;
    background: #f0fdf4;
    color: #166534;
}

.create-layout {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.create-splitpanes {
    flex: 1;
    min-height: 0;
    width: 100%;
}

.create-pane {
    min-width: 0;
    min-height: 0;
    display: flex;
    height: 100%;
}

.create-pane-pdf,
.create-pane-form {
    flex-direction: column;
}

.panel {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
    width: 100%;
    overflow: hidden;
    box-sizing: border-box;
}

.panel-pdf {
    min-width: 0;
}

.panel-form {
    min-width: 0;
    gap: 0;
    overflow: hidden;
    padding-top: 8px;
}

:deep(.splitpanes__splitter) {
    background: #cbd5e1;
    min-width: 8px;
}

:deep(.splitpanes--horizontal > .splitpanes__splitter) {
    min-height: 8px;
    min-width: 100%;
}

:deep(.splitpanes__splitter:hover) {
    background: #94a3b8;
}

.tab-bar {
    position: relative;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    border-bottom: 1px solid #cbd5e1;
    margin-bottom: 12px;
    min-height: 42px;
    overflow: visible;
}

.tab-bar-tabs {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1 1 auto;
    min-width: 0;
    overflow-x: auto;
}

.tab-bar-center {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    z-index: 2;
    pointer-events: none;
}

.tab-bar-center .tab-btn-action {
    pointer-events: auto;
}

.tab-btn {
    appearance: none;
    border: none;
    background: transparent;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    white-space: nowrap;
}

.tab-btn:hover {
    color: #1e293b;
    background: #f8fafc;
}

.tab-btn.active {
    color: #1d4ed8;
    border-bottom-color: #2563eb;
}

.tab-btn-action {
    margin-left: 0;
    border: 1px solid #2563eb;
    border-radius: 6px;
    border-bottom: 1px solid #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 6px 14px;
    line-height: 1.2;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.tab-btn-action:hover:not(:disabled) {
    background: #dbeafe;
    color: #1e40af;
}

.tab-btn-action:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.info-card-maintenance {
    margin-top: 8px;
}

.maintenance-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.maintenance-header h3 {
    margin: 0;
    font-size: 14px;
    color: #0f172a;
}

.maintenance-count {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
}

.maintenance-clear-btn {
    margin-left: auto;
    padding: 4px 10px;
    font-size: 12px;
}

.maintenance-error,
.maintenance-empty {
    margin: 0;
    font-size: 13px;
    color: #b91c1c;
}

.maintenance-empty {
    color: #64748b;
}

.maintenance-selected-hint {
    margin: 8px 0 0;
    font-size: 12px;
    color: #1d4ed8;
    font-weight: 600;
}

.maintenance-table-wrap {
    overflow: auto;
    max-height: 240px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
}

.maintenance-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.maintenance-table th,
.maintenance-table td {
    border-bottom: 1px solid #e2e8f0;
    padding: 6px 8px;
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

.tab-panel {
    flex: 1;
    min-height: 0;
    overflow: auto;
    display: flex;
    flex-direction: column;
}

.tab-panel-loaner-basic {
    overflow: hidden;
}

.tab-panel-existing {
    overflow: hidden;
}

.selected-loaners {
    flex-shrink: 0;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #93c5fd;
    border-radius: 6px;
    background: #eff6ff;
}

.selected-loaners h4 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #1e40af;
}

.selected-loaner-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.selected-loaner-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #dbeafe;
    color: #1e293b;
    font-size: 12px;
}

.chip-remove {
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 14px;
    cursor: pointer;
    line-height: 1;
}

.loaner-flow-note {
    flex-shrink: 0;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    background: #fffbeb;
}

.loaner-flow-note p {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: #92400e;
}

.loaner-create-btn {
    margin-top: 10px;
}

.confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 400;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.45);
}

.confirm-panel {
    width: min(480px, 100%);
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
    color: #334155;
    font-size: 14px;
    line-height: 1.55;
}

.confirm-body p {
    margin: 0 0 10px;
}

.missing-fields {
    margin: 0 0 12px;
    padding-left: 1.2em;
    color: #b91c1c;
}

.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px 16px;
}

.form-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-bottom: 8px;
}

.form-stack-loaner {
    flex: 1;
    min-height: 0;
    height: 100%;
    gap: 10px;
    padding-bottom: 0;
    overflow: auto;
}

.loaner-stakeholder-stack {
    display: flex;
    flex-direction: column;
    gap: 0;
    min-height: 0;
    flex: 1 1 auto;
}

.loaner-stakeholder-stack > .info-card {
    border-radius: 0;
}

.loaner-stakeholder-stack > .info-card + .info-card {
    border-top: none;
}

.loaner-stakeholder-stack > .info-card:first-child {
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}

.loaner-stakeholder-stack > .info-card:last-child {
    border-bottom-left-radius: 6px;
    border-bottom-right-radius: 6px;
}

.info-card {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 10px 10px;
    background: #fff;
}

.info-card-main {
    border-color: #000000;
    background: #cdcdcd;
}

.info-card-dealer {
    border-color: #000000;
    background: #aaaaaa;
}

.info-card-enduser {
    border-color: #000000;
    background: #aaaaaa;
}

.info-card-delivery {
    border-color: #000000;
    background: #aaaaaa;
}

.info-card-loaner-top {
    border-color: #000000;
    background: #cdcdcd;
    flex: 0 0 auto;
}

.btn-stock-list {
    flex: 0 0 auto;
    margin-left: auto;
    white-space: nowrap;
    padding: 6px 12px;
    font-size: 12px;
}

.loaner-top-row {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 10px;
    min-width: 0;
    overflow-x: auto;
}

.loaner-top-row .field-inline {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 6px;
    margin: 0;
    min-width: 0;
}

.loaner-top-row .field-inline > span {
    flex: 0 0 auto;
    font-size: 12px;
    font-weight: 700;
    color: #000;
    white-space: nowrap;
}

.loaner-top-row .field-inline .field-button,
.loaner-top-row .field-inline input {
    flex: 1 1 auto;
    min-width: 96px;
}

.loaner-top-row .field-sn {
    flex: 0 1 160px;
}

.loaner-top-row .field-enduser-sn {
    flex: 0 1 180px;
}

.loaner-top-row .field-instrument-name {
    flex: 0 1 180px;
}

.btn-loaner-maintenance,
.btn-parent-case {
    flex: 0 0 auto;
    white-space: nowrap;
}

.loaner-top-row .field-date {
    flex: 0 0 auto;
}

.loaner-top-row .field-date input {
    width: 142px;
    min-width: 142px;
}

.loaner-list-title {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 700;
    color: #000;
}

.loaner-unit-panel {
    min-height: 120px;
    max-height: 360px;
    overflow: auto;
    background: #0f172a;
    border: 1px solid #000;
    border-radius: 2px;
    padding: 6px;
}

.loaner-unit-panel-dialog {
    max-height: min(62vh, 560px);
}

.stock-list-panel {
    width: min(1280px, 96vw);
    max-width: 96vw;
}

.parent-case-panel {
    width: min(1080px, 96vw);
    max-width: 96vw;
}

.parent-case-body {
    padding-top: 12px;
}

.parent-case-search-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    margin-bottom: 12px;
}

.parent-case-search-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 0;
}

.parent-case-search-field span {
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}

.parent-case-search-input {
    width: 180px;
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 14px;
}

.parent-case-error {
    margin: 0 0 10px;
    color: #b91c1c;
    font-weight: 700;
}

.parent-case-result-meta {
    margin: 0 0 10px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

.parent-case-stakeholder-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.parent-case-stakeholder {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    padding: 10px;
    min-width: 0;
}

.parent-case-stakeholder h4 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #1e40af;
}

.parent-case-stakeholder dl {
    margin: 0;
}

.parent-case-stakeholder dl > div {
    display: grid;
    grid-template-columns: 72px 1fr;
    gap: 6px;
    margin-bottom: 4px;
}

.parent-case-stakeholder dt {
    color: #64748b;
    font-size: 11px;
}

.parent-case-stakeholder dd {
    margin: 0;
    font-size: 12px;
    overflow-wrap: anywhere;
    color: #0f172a;
}

@media (max-width: 900px) {
    .parent-case-stakeholder-grid {
        grid-template-columns: 1fr;
    }
}

.stock-list-body {
    padding-top: 4px;
}

.stock-list-hint-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 100px;
    margin-bottom: 8px;
}

.stock-list-hint-row .confirm-detail {
    margin: 0;
}

.stock-status-legend {
    display: inline-flex;
    align-items: center;
    gap: 16px;
    font-size: 13px;
    font-weight: 600;
}

.stock-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.stock-legend-swatch {
    font-size: 14px;
    line-height: 1;
}

.stock-legend-available {
    color: #166534;
}

.stock-legend-available .stock-legend-swatch {
    color: #22c55e;
}

.stock-legend-loaned {
    color: #475569;
}

.stock-legend-loaned .stock-legend-swatch {
    color: #94a3b8;
}

.ok-text {
    color: #166534;
    font-weight: 600;
}

.wait-text {
    color: #9a3412;
    font-weight: 600;
}

.loaner-unit-table {
    width: 100%;
    min-width: 1080px;
    border-collapse: collapse;
    color: #e2e8f0;
    font-size: 12px;
}

.loaner-unit-table th,
.loaner-unit-table td {
    padding: 4px 6px;
    border-bottom: 1px solid #334155;
    text-align: left;
    white-space: nowrap;
}

.loaner-unit-table th {
    color: #94a3b8;
    font-weight: 600;
    position: sticky;
    top: 0;
    background: #0f172a;
}

.loaner-unit-table .col-loaner-id {
    width: 64px;
    max-width: 72px;
    white-space: nowrap;
}

.loaner-unit-table .col-note {
    max-width: 220px;
    white-space: normal;
    overflow-wrap: anywhere;
}

.loaner-unit-row.available td {
    color: #86efac;
}

.loaner-unit-row.selectable {
    cursor: pointer;
}

.loaner-unit-row.selectable:hover td {
    background: #1e293b;
}

.loaner-unit-row.selected td {
    background: #14532d;
    color: #bbf7d0;
    font-weight: 700;
}

.loaner-unit-empty {
    margin: 24px 8px;
    color: #94a3b8;
    font-size: 12px;
    text-align: center;
}

.availability-hint {
    margin: 8px 0 0;
    font-size: 12px;
    color: #334155;
}

.availability-hint.availability-inline {
    margin: 0;
    flex: 1 1 140px;
    min-width: 120px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}

.availability-hint.ok {
    color: #166534;
    font-weight: 600;
}

.availability-hint.wait {
    color: #9a3412;
    font-weight: 600;
}

.confirm-warning {
    color: #9a3412;
    font-weight: 700;
}

.confirm-detail {
    margin: 0 0 6px;
    color: #334155;
    font-size: 13px;
}

.btn-warning {
    background: #ea580c;
    border-color: #c2410c;
    color: #fff;
}

.btn-warning:hover {
    background: #c2410c;
}

.stakeholder-card {
    display: flex;
    gap: 8px;
    align-items: stretch;
}

.stakeholder-side {
    flex: 0 0 92px;
    width: 92px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.stakeholder-label {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
}

.switch-btn {
    width: 100%;
    padding: 5px 4px;
    border: 1px solid #64748b;
    border-radius: 3px;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 11px;
    line-height: 1.2;
    cursor: pointer;
    text-align: center;
}

.switch-btn:hover {
    background: #cbd5e1;
}

.stakeholder-body {
    flex: 1 1 auto;
    min-width: 0;
}

.stakeholder-body > .form-row:first-child {
    margin-top: 0;
}

.form-row {
    display: grid;
    gap: 6px;
    align-items: stretch;
    margin-top: 6px;
}

.info-card > .form-row:first-of-type,
.info-card-main > .form-row:first-child {
    margin-top: 0;
}

.row-product-top {
    grid-template-columns: 80px minmax(0, 1fr) minmax(120px, 0.7fr);
}

.row-product-top .field-button-pick {
    width: 80px;
    max-width: 80px;
    min-width: 80px;
    padding-left: 4px;
    padding-right: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 12px;
}

.row-product-top .w-product-name {
    width: 100%;
}

.row-dealer-top {
    grid-template-columns: 80px minmax(0, 1fr);
}

.row-dealer-top .field-button-pick {
    width: 80px;
    max-width: 80px;
    min-width: 80px;
    padding-left: 4px;
    padding-right: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 12px;
}

.row-dealer-top .w-dealer-name {
    width: 100%;
}

.row-product-sn {
    grid-template-columns: minmax(0, 1.6fr) minmax(120px, 0.7fr);
}

.row-product-sn > input {
    grid-column: 1;
}

.row-product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.row-product-meta .w-received,
.row-product-meta .w-status {
    flex: 0 0 200px;
    width: 200px;
    max-width: 100%;
}

.row-product-meta .w-return {
    flex: 0 0 400px;
    width: 400px;
    max-width: 100%;
}

.row-full {
    grid-template-columns: minmax(0, 1fr);
}

.row-contact,
.row-phone-email,
.row-zip,
.row-address {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.w-contact,
.w-phone,
.w-zip {
    flex: 0 0 200px;
    width: 200px;
    max-width: 100%;
}

.w-email {
    flex: 0 0 400px;
    width: 400px;
    max-width: 100%;
}

.w-address1 {
    flex: 0 0 150px;
    width: 150px;
    max-width: 100%;
}

.w-address2 {
    flex: 1 1 220px;
    min-width: 0;
}

.info-card input,
.info-card select,
.info-card .field-button {
    width: 100%;
    min-width: 0;
    padding: 6px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    background: #fff;
    color: #1e293b;
    font-size: 13px;
}

.info-card .w-contact,
.info-card .w-phone,
.info-card .w-zip {
    width: 200px;
}

.info-card .w-email {
    width: 400px;
}

.info-card .w-address1 {
    width: 150px;
}

.info-card input[readonly] {
    background: #f8fafc;
    color: #475569;
}

.field-button {
    text-align: left;
    cursor: pointer;
}

.field-button:hover {
    background: #f8fafc;
}

.field-button.placeholder {
    color: #94a3b8;
}

.info-card input::placeholder,
.info-card select:invalid,
.info-card select option[value=""] {
    color: #94a3b8;
}

.related-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.related-selected {
    flex-shrink: 0;
    font-size: 13px;
    color: #475569;
    font-weight: 700;
}

.empty-message {
    margin: 0;
    padding: 16px;
    color: #64748b;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    flex: 0 0 auto;
}

.panel-header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.panel-header h2 {
    margin: 0 0 8px;
    font-size: 18px;
    color: #1e293b;
}

.panel-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    color: #64748b;
    font-size: 13px;
}

.pdf-preview-shell {
    flex: 1;
    min-height: 0;
    width: 100%;
    display: flex;
    justify-content: stretch;
    align-items: stretch;
    overflow: hidden;
}

.no-file-shell {
    align-items: center;
}

.no-preview-panel {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 16px;
    box-sizing: border-box;
    background: #f8fafc;
    border: 1px dashed #94a3b8;
    border-radius: 6px;
    color: #64748b;
    text-align: center;
}

.no-preview-panel p {
    margin: 0;
}

.no-file-title {
    font-size: 18px;
    font-weight: 700;
    color: #334155;
}

.image-frame {
    object-fit: contain;
    background: #fff;
}

.pdf-frame {
    width: 100%;
    height: 100%;
    max-width: none;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
    display: block;
}

.section-help {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.related-files {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
    flex: 1;
    min-height: 0;
    overflow: auto;
    align-content: start;
}

.file-option {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    cursor: pointer;
}

.file-option.selected {
    border-color: #2563eb;
    background: #eff6ff;
}

.file-option-top {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    min-height: 18px;
}

.file-option-body {
    flex: 1;
    min-width: 0;
}

.related-preview-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 210 / 297;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;
    padding: 0;
    cursor: zoom-in;
}

.related-preview,
.related-preview-image {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    pointer-events: none;
}

.related-preview-image {
    object-fit: contain;
}

.related-preview-fallback {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 12px;
}

.preview-hint {
    position: absolute;
    left: 8px;
    bottom: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    background: rgba(15, 23, 42, 0.75);
    color: #fff;
    font-size: 12px;
    pointer-events: none;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 1280px) {
    .create-page {
        height: auto;
        min-height: 100vh;
        overflow: auto;
    }

    .create-layout {
        flex: none;
        min-height: 70vh;
    }

    .create-splitpanes {
        min-height: 70vh;
    }

    .panel-form {
        overflow: visible;
    }

    .tab-panel {
        overflow: visible;
    }

    .pdf-preview-shell {
        min-height: 360px;
    }

    .pdf-frame {
        width: 100%;
        height: 100%;
    }

    .row-product,
    .row-3 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .row-address {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr);
    }
}

@media (max-width: 720px) {
    .row-product,
    .row-3,
    .row-2,
    .row-address {
        grid-template-columns: 1fr;
    }

    .row-zip {
        grid-template-columns: minmax(100px, 160px);
    }
}
</style>
