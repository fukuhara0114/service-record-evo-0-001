<template>
    <div class="loaner-detail-page">
        <header class="page-header">
            <div class="header-title-group">
                <h1 class="page-title">貸出案件詳細</h1>
                <strong class="page-order-id">OrderID : {{ record.orderID }}</strong>
            </div>
            <div class="header-actions">
                <span v-if="success" class="save-message success">{{ success }}</span>
                <span v-if="error" class="save-message error">{{ error }}</span>
                <button type="button" class="btn btn-primary" :disabled="saving" @click="save()">
                    {{ saving ? '保存中...' : '保存' }}
                </button>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="closePage">閉じる</button>
            </div>
        </header>

        <section
            v-if="isWaitingList"
            class="promote-banner"
            :class="{ 'is-ready': isPromotionReady }"
        >
            <div class="promote-banner-text">
                <strong v-if="isPromotionReady">繰上可</strong>
                <strong v-else>予約案件リスト</strong>
                <p>
                    <template v-if="isPromotionReady">
                        在庫復帰により繰り上げ可能です。貸出機を割り当てて loaner 案件へ変更してください。
                    </template>
                    <template v-else>
                        waiting_list 案件です。在庫がある場合は loaner へ繰り上げできます。
                    </template>
                </p>
                <p v-if="record.promotion_source_orderID" class="promote-source">
                    きっかけ OrderID: {{ record.promotion_source_orderID }}
                </p>
            </div>
            <div class="promote-banner-actions">
                <button
                    type="button"
                    class="btn btn-danger"
                    :disabled="promoting || cancellingReservation"
                    @click="showCancelReservationDialog = true"
                >
                    予約キャンセル
                </button>
                <label class="promote-unit-select">
                    <span>割当貸出機</span>
                    <select v-model="promoteLoanerId" :disabled="promoting || !availableUnits.length">
                        <option value="">自動（返却元 / 在庫から先頭）</option>
                        <option
                            v-for="unit in availableUnits"
                            :key="unit.loanerID"
                            :value="String(unit.loanerID)"
                        >
                            {{ unit.loanerID }} / {{ unit.SN || 'SNなし' }} / {{ unit.manageNum || '管理番号なし' }}
                            <template v-if="unit.isPromotionSource">（返却元）</template>
                        </option>
                    </select>
                </label>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="promoting || !availableUnits.length"
                    :title="availableUnits.length ? '' : '同機種の在庫がありません'"
                    @click="promoteToLoaner"
                >
                    {{ promoting ? '繰上中...' : 'loaner に繰り上げ' }}
                </button>
            </div>
        </section>

        <Splitpanes class="default-theme outer-splitpanes" @resized="onPanesResized">
            <Pane :size="leftPaneSize" :min-size="32" class="main-pane">
                <div class="left-pane">
                    <section class="panel loaner-panel">
                        <div class="loaner-top-layout">
                            <div class="loaner-identity-col">
                                <label>
                                    <span>製品名</span>
                                    <button type="button" class="master-value" @click="activeSelectKind = 'loanerUnit'">
                                        {{ displayItemLabel || '選択してください' }}
                                    </button>
                                </label>
                                <label><span>SN</span><input v-model="form.SN" type="text"></label>
                                <label><span>loanerID</span><input :value="form.loanerID" type="text" readonly></label>
                                <label>
                                    <span>管理番号</span>
                                    <input :value="selectedUnit?.manageNum || loanerMaster?.manageNum || ''" type="text" readonly>
                                </label>
                                <label class="parent-id-field">
                                    <span>parentID</span>
                                    <div class="parent-id-block">
                                        <div class="parent-id-controls">
                                            <input v-model="form.parentID" type="number">
                                            <button
                                                type="button"
                                                class="btn btn-secondary parent-id-open-btn"
                                                :disabled="!String(form.parentID || '').trim()"
                                                title="親の service 案件詳細を新規タブで開く"
                                                @click="openParentServiceDetail"
                                            >
                                                開く
                                            </button>
                                        </div>
                                        <div v-if="form.parentID" class="parent-id-meta">
                                            <div class="parent-id-meta-row">
                                                <span class="parent-id-meta-label">status</span>
                                                <span class="parent-id-meta-value">{{ parentStatusDisplay }}</span>
                                            </div>
                                            <div class="parent-id-meta-row">
                                                <span class="parent-id-meta-label">sentOut</span>
                                                <span class="parent-id-meta-value">{{ parentSentOutDisplay }}</span>
                                            </div>
                                            <div class="parent-id-meta-row">
                                                <span class="parent-id-meta-label">経過日数</span>
                                                <span class="parent-id-meta-value">{{ parentElapsedDaysDisplay }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="loaner-commerce-col">
                                <div class="commerce-row">
                                    <span class="commerce-label">見積 #</span>
                                    <input v-model="form.quoteNum" type="text" class="commerce-num">
                                    <span class="commerce-date-label">日付</span>
                                    <input v-model="form.quoteDate" type="date" class="commerce-date" title="見積日">
                                </div>
                                <div class="commerce-row">
                                    <span class="commerce-label">受注 #</span>
                                    <input v-model="form.orderNum" type="text" class="commerce-num">
                                    <span class="commerce-date-label">日付</span>
                                    <input v-model="form.orderDate" type="date" class="commerce-date" title="受注日">
                                </div>
                                <div class="commerce-row commerce-row-po">
                                    <span class="commerce-label">注文 #</span>
                                    <input v-model="form.poNum" type="text" class="commerce-po">
                                </div>
                                <div class="commerce-row commerce-row-po">
                                    <span class="commerce-label">発送予定日</span>
                                    <button
                                        type="button"
                                        class="master-value commerce-po shipping-date-btn"
                                        @click="openShippingDateDialog()"
                                    >
                                        {{ form.shippingOut_requiredDate || '選択してください' }}
                                    </button>
                                </div>
                                <div class="commerce-application-form-slot">
                                    <button
                                        type="button"
                                        class="btn btn-secondary application-form-issue-btn"
                                        :disabled="saving || applicationFormLoading"
                                        @click="openApplicationFormSetup"
                                    >
                                        {{ applicationFormLoading ? '生成中...' : '申込書発行' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="record.order_type === 'loaner'" class="status-action-row">
                            <div class="status-current-box">
                                <span class="status-box-label">currentStatus</span>
                                <strong class="status-current-value">{{ currentStatusLabel }}</strong>
                            </div>
                            <button
                                type="button"
                                class="btn btn-primary status-next-btn"
                                :disabled="saving || !nextStatusOption"
                                @click="advanceStatus"
                            >
                                {{ nextStatusOption ? `次へ: ${nextStatusOption.label}` : '次へ' }}
                            </button>
                            <label class="status-select-box">
                                <span>status</span>
                                <select v-model="form.status">
                                    <option value="">選択してください</option>
                                    <option
                                        v-for="status in statuses"
                                        :key="status.processID_new"
                                        :value="String(status.processID_new)"
                                    >
                                        {{ loanerStatusOptionLabel(status) }}
                                    </option>
                                </select>
                            </label>
                            <label v-if="isLaborEditable" class="labor-box labor-required-field">
                                <span>Labor</span>
                                <select v-model="form.laborID">
                                    <option value="">選択してください</option>
                                    <option v-for="labor in labors" :key="labor.laborID" :value="String(labor.laborID)">
                                        {{ labor.laborName }} ({{ labor.laborID }})
                                    </option>
                                </select>
                            </label>
                            <label v-else class="labor-box">
                                <span>Labor</span>
                                <input
                                    :value="laborDisplayLabel"
                                    type="text"
                                    readonly
                                    title="返却(393)のときのみ設定できます"
                                >
                            </label>
                        </div>
                        <div v-else class="status-action-row status-action-waiting">
                            <div class="status-current-box">
                                <span class="status-box-label">order_type</span>
                                <strong class="status-current-value">{{ record.order_type || '—' }}</strong>
                            </div>
                            <label class="status-select-box">
                                <span>割当状態</span>
                                <input v-model="form.assignStatus" type="text">
                            </label>
                        </div>

                        <div class="price-adjust-row">
                            <div class="price-adjust-main">
                                <span class="price-adjust-label">価格</span>
                                <strong class="price-adjust-value">{{ formatPrice(displayPrice) }}</strong>
                            </div>
                            <button
                                type="button"
                                class="btn btn-primary price-adjust-btn"
                                :disabled="priceAdjustSaving"
                                @click="openPriceAdjustDialog"
                            >
                                価格調整
                            </button>
                            <div class="price-adjust-delta">
                                <span class="price-adjust-label">調整額</span>
                                <strong>{{ formatSignedAmount(discountAmount) }}</strong>
                            </div>
                            <label class="price-adjust-enduser-sn">
                                <span class="price-adjust-label">enduser_SN</span>
                                <input
                                    v-model="form.enduser_SN"
                                    type="text"
                                    placeholder="enduser_SN"
                                >
                            </label>
                        </div>
                    </section>

                    <div class="people-row">
                        <section class="panel person-panel">
                            <div class="panel-heading">
                                <h2>依頼社</h2>
                                <button type="button" class="select-btn" @click="activeSelectKind = 'dealer'">マスター選択</button>
                            </div>
                            <div class="person-stack">
                                <label><span>会社名</span><input v-model="form.dealer" type="text"></label>
                                <label><span>部署名</span><input v-model="form.dealer_depart" type="text"></label>
                                <label><span>担当者</span><input v-model="form.contactPerson" type="text"></label>
                                <label><span>phone</span><input v-model="form.phone" type="text"></label>
                                <label><span>email</span><input v-model="form.email" type="text"></label>
                                <label class="zip-row">
                                    <span class="zip-mark">〒</span>
                                    <input
                                        v-model="form.zipcode"
                                        type="text"
                                        class="zip-input"
                                        placeholder="zipcode"
                                        @change="lookupZip('dealer')"
                                        @blur="lookupZip('dealer')"
                                    >
                                </label>
                                <div class="address-pair">
                                    <input v-model="form.address1" type="text" class="address1-input" placeholder="address1" aria-label="address1">
                                    <input v-model="form.address2" type="text" class="address2-input" placeholder="address2" aria-label="address2">
                                </div>
                            </div>
                        </section>

                        <section class="panel person-panel">
                            <div class="panel-heading delivery-heading">
                                <h2>発送先</h2>
                                <button
                                    type="button"
                                    class="select-btn delivery-copy-btn"
                                    @click="copyDealerToDelivery"
                                >
                                    依頼者Copy
                                </button>
                            </div>
                            <div class="person-stack">
                                <label><span>会社名</span><input v-model="form.deliveryDestination_company" type="text"></label>
                                <label><span>部署名</span><input v-model="form.deliveryDestination_depart" type="text"></label>
                                <label><span>担当者</span><input v-model="form.deliveryDestination_contactPerson" type="text"></label>
                                <label><span>phone</span><input v-model="form.deliveryDestination_phone" type="text"></label>
                                <label><span>email</span><input v-model="form.deliveryDestination_email" type="text"></label>
                                <label class="zip-row">
                                    <span class="zip-mark">〒</span>
                                    <input
                                        v-model="form.deliveryDestination_zipcode"
                                        type="text"
                                        class="zip-input"
                                        placeholder="zipcode"
                                        @change="lookupZip('delivery')"
                                        @blur="lookupZip('delivery')"
                                    >
                                </label>
                                <div class="address-pair">
                                    <input
                                        v-model="form.deliveryDestination_address1"
                                        type="text"
                                        class="address1-input"
                                        placeholder="address1"
                                        aria-label="address1"
                                    >
                                    <input
                                        v-model="form.deliveryDestination_address2"
                                        type="text"
                                        class="address2-input"
                                        placeholder="address2"
                                        aria-label="address2"
                                    >
                                </div>
                            </div>
                        </section>

                        <section class="panel person-panel">
                            <div class="panel-heading">
                                <h2>endUser</h2>
                                <button
                                    type="button"
                                    class="select-btn delivery-copy-btn"
                                    @click="copyEndUserToDelivery"
                                >
                                    発送先Copy
                                </button>
                            </div>
                            <div class="person-stack">
                                <label><span>会社名</span><input v-model="form.endUser" type="text"></label>
                                <label><span>部署名</span><input v-model="form.endUser_depart" type="text"></label>
                                <label><span>担当者</span><input v-model="form.endUser_contactPerson" type="text"></label>
                                <label><span>phone</span><input v-model="form.endUser_phone" type="text"></label>
                                <label><span>email</span><input v-model="form.endUser_email" type="text"></label>
                                <label class="zip-row">
                                    <span class="zip-mark">〒</span>
                                    <input
                                        v-model="form.endUser_zipcode"
                                        type="text"
                                        class="zip-input"
                                        placeholder="zipcode"
                                        @change="lookupZip('endUser')"
                                        @blur="lookupZip('endUser')"
                                    >
                                </label>
                                <div class="address-pair">
                                    <input v-model="form.endUser_address1" type="text" class="address1-input" placeholder="address1" aria-label="address1">
                                    <input v-model="form.endUser_address2" type="text" class="address2-input" placeholder="address2" aria-label="address2">
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="panel period-panel">
                        <h2>貸出期間</h2>
                        <label><span>予定開始</span><input v-model="form.plannedSentDate" type="date"></label>
                        <span>〜</span>
                        <label><span>予定終了</span><input v-model="form.plannedReturnedDate" type="date"></label>
                        <label><span>実開始</span><input v-model="form.sentDate" type="date"></label>
                        <span>〜</span>
                        <label><span>実終了</span><input v-model="form.returnedDate" type="date"></label>
                    </section>

                    <section class="panel tab-panel">
                        <div class="panel-heading tab-heading">
                            <div class="tab-buttons">
                                <button
                                    type="button"
                                    class="tab-btn"
                                    :class="{ active: bottomTab === 'notes' }"
                                    @click="bottomTab = 'notes'"
                                >
                                    Notes（{{ sharedNotes.length }}件）
                                </button>
                                <span
                                    v-if="bottomTab === 'notes'"
                                    class="notes-tbc-count"
                                >
                                    要確認　({{ tbcNotesCount }}件)
                                </span>
                                <button
                                    type="button"
                                    class="tab-btn"
                                    :class="{ active: bottomTab === 'calendar' }"
                                    @click="switchToCalendar"
                                >
                                    カレンダー
                                </button>
                            </div>
                            <div v-if="bottomTab === 'notes'" class="notes-actions">
                                <button
                                    type="button"
                                    class="select-btn"
                                    :disabled="!canModifySelectedNote"
                                    :title="noteEditDeleteTitle"
                                    @click="openNoteEdit"
                                >
                                    編集
                                </button>
                                <button
                                    type="button"
                                    class="select-btn delete-btn"
                                    :disabled="!canModifySelectedNote"
                                    :title="noteEditDeleteTitle"
                                    @click="openNoteDelete"
                                >
                                    削除
                                </button>
                                <button type="button" class="select-btn add-btn" @click="openNoteCreate">
                                    新規追加
                                </button>
                            </div>
                            <span v-else class="calendar-help">予定を移動／左右端で期間変更</span>
                        </div>

                        <div v-show="bottomTab === 'notes'" class="notes-shell">
                            <p v-if="noteError" class="calendar-error">{{ noteError }}</p>
                            <NotesTable
                                v-model:selected-id="selectedNoteId"
                                :notes="sharedNotes"
                                :record-order-id="record?.orderID"
                                :show-confirm-status="true"
                                :current-user-name="authUserName"
                                @edit="openNoteEdit"
                            />
                        </div>

                        <div v-show="bottomTab === 'calendar'" class="calendar-shell">
                            <p v-if="calendarError" class="calendar-error">{{ calendarError }}</p>
                            <FullCalendar ref="calendarRef" :options="calendarOptions" />
                        </div>
                    </section>
                </div>
            </Pane>

            <Pane :size="rightPaneSize" :min-size="32" class="main-pane">
                <section class="panel files-panel">
                    <div class="panel-heading">
                        <h2>Files（{{ fileItems.length }}件）</h2>
                        <div class="file-actions">
                            <button
                                type="button"
                                class="select-btn delete-btn"
                                :disabled="!selectedFileId || fileBusy"
                                @click="requestFileDelete"
                            >
                                削除
                            </button>
                            <button
                                type="button"
                                class="select-btn add-btn"
                                :disabled="fileBusy"
                                @click="openFilePicker"
                            >
                                {{ uploading ? '追加中...' : 'ファイル追加' }}
                            </button>
                            <input
                                ref="fileInput"
                                type="file"
                                class="file-input"
                                multiple
                                @change="onFileInputChange"
                            >
                        </div>
                    </div>
                    <p v-if="fileError" class="file-error">{{ fileError }}</p>
                    <div
                        class="file-dropzone"
                        :class="{ active: fileDropActive, disabled: fileBusy }"
                        @dragenter.prevent="onFileDragEnter"
                        @dragover.prevent="onFileDragOver"
                        @dragleave.prevent="onFileDragLeave"
                        @drop.prevent="onFileDrop"
                        @click="openFilePicker"
                    >
                        <strong>{{ uploading ? `アップロード中 ${uploadProgress}` : 'ファイルをドロップ、またはクリックして選択' }}</strong>
                        <span>PDF・画像・メール・その他のファイル（1ファイル10MBまで）</span>
                    </div>
                    <div v-if="sortedFiles.length" class="files-list">
                        <AttachedFileItem
                            v-for="(file, index) in sortedFiles"
                            :key="file.id"
                            :file="file"
                            :order-id="record.orderID"
                            :file-base-url="`${page.props.appBaseUrl}/servicerecord/files`"
                            :selected="selectedFileId === file.id"
                            :can-move-up="index > 0"
                            :can-move-down="index < sortedFiles.length - 1"
                            :sorting="fileSortSaving"
                            @select="selectedFileId = file.id"
                            @move="(direction) => moveFile(file.id, direction)"
                            @sort-num-change="(sortNum) => updateFileSortNum(file.id, sortNum)"
                        />
                    </div>
                    <p v-else class="empty">関連ファイルはありません。</p>
                </section>
            </Pane>
        </Splitpanes>

        <ShippingOutDateDialog
            v-if="showShippingDialog"
            :order-id="record.orderID"
            :product-name="form.productName || ''"
            :serial-number="form.SN || ''"
            :dealer="form.dealer || ''"
            :contact-person="form.contactPerson || ''"
            :preview-record="{
                ...record,
                ...form,
                status: form.status || record.status,
                shippingOut_requiredDate: form.shippingOut_requiredDate || record.shippingOut_requiredDate,
            }"
            :confirming="shippingConfirming"
            @close="onShippingDialogClose"
            @confirm="onShippingConfirm"
        />

        <IntakeMasterSelectDialog
            v-if="activeSelectKind"
            :kind="activeSelectKind"
            :items="activeSelectItems"
            :initial-value="activeSelectInitialValue"
            @close="activeSelectKind = null"
            @selected="onMasterSelected"
        />

        <div
            v-if="showCancelReservationDialog"
            class="confirm-overlay"
            @click.self="closeCancelReservationDialog"
        >
            <div class="confirm-panel" role="dialog" aria-modal="true" aria-labelledby="cancel-reservation-title">
                <h3 id="cancel-reservation-title">予約キャンセル</h3>
                <p>予約をキャンセルしますか？</p>
                <p v-if="cancelReservationError" class="confirm-error">{{ cancelReservationError }}</p>
                <div class="confirm-actions">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        :disabled="cancellingReservation"
                        @click="closeCancelReservationDialog"
                    >
                        いいえ
                    </button>
                    <button
                        type="button"
                        class="btn btn-danger"
                        :disabled="cancellingReservation"
                        @click="cancelReservation"
                    >
                        {{ cancellingReservation ? '処理中...' : 'はい' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="filePendingDelete" class="confirm-overlay" @click.self="filePendingDelete = null">
            <div class="confirm-panel">
                <h3>ファイル削除</h3>
                <p>「{{ filePendingDelete.documentName || '名称なし' }}」を削除しますか？</p>
                <p v-if="fileError" class="file-error">{{ fileError }}</p>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" :disabled="deleting" @click="filePendingDelete = null">キャンセル</button>
                    <button type="button" class="btn delete-btn" :disabled="deleting" @click="deleteFile">
                        {{ deleting ? '削除中...' : '削除' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showApplicationFormSetupDialog" class="confirm-overlay" @click.self="closeApplicationFormSetupDialog">
            <div class="confirm-panel" @click.stop>
                <div class="confirm-header">
                    <h3>申込書発行</h3>
                    <button type="button" class="close-btn" @click="closeApplicationFormSetupDialog">×</button>
                </div>
                <div class="confirm-body">
                    <fieldset class="application-form-charge-fieldset">
                        <legend>機材名</legend>
                        <label class="application-form-charge-option">
                            <input
                                v-model="applicationFormSetupForm.equipmentNameSource"
                                type="radio"
                                value="item"
                            >
                            item: {{ applicationFormItemPreview || '（未設定）' }}
                        </label>
                        <label class="application-form-charge-option">
                            <input
                                v-model="applicationFormSetupForm.equipmentNameSource"
                                type="radio"
                                value="productName"
                            >
                            productName: {{ displayProductNameLabel || '（未設定）' }}
                        </label>
                    </fieldset>
                    <fieldset class="application-form-charge-fieldset">
                        <legend>手数料</legend>
                        <label class="application-form-charge-option">
                            <input
                                v-model="applicationFormSetupForm.chargeType"
                                type="radio"
                                value="paid"
                            >
                            有償（loanermaster.price: {{ formatPrice(masterPrice) }}）
                        </label>
                        <label class="application-form-charge-option">
                            <input
                                v-model="applicationFormSetupForm.chargeType"
                                type="radio"
                                value="free"
                            >
                            無償（￥0）
                        </label>
                    </fieldset>
                    <label class="confirm-field">
                        enduser_SN
                        <input
                            v-model="applicationFormSetupForm.enduser_SN"
                            type="text"
                            class="confirm-input"
                            placeholder="修理機材のシリアルナンバー"
                        >
                    </label>
                    <p v-if="applicationFormSetupError" class="confirm-error">{{ applicationFormSetupError }}</p>
                </div>
                <div class="confirm-actions">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        :disabled="applicationFormLoading"
                        @click="closeApplicationFormSetupDialog"
                    >
                        キャンセル
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="applicationFormLoading"
                        @click="confirmApplicationFormSetup"
                    >
                        {{ applicationFormLoading ? '生成中...' : '発行' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showApplicationFormDialog"
            class="confirm-overlay application-form-overlay"
            @click.self="closeApplicationFormDialog"
        >
            <div class="confirm-panel application-form-panel" role="dialog" aria-modal="true" aria-labelledby="application-form-title">
                <div class="confirm-header application-form-header">
                    <h3 id="application-form-title">代替機申込書プレビュー</h3>
                    <div class="application-form-header-actions">
                        <button type="button" class="btn btn-secondary application-form-btn" @click="closeApplicationFormDialog">閉じる</button>
                        <button
                            v-if="applicationFormPdfUrl"
                            type="button"
                            class="btn btn-primary application-form-btn"
                            :disabled="applicationFormDownloading"
                            @click="downloadApplicationForm"
                        >
                            {{ applicationFormDownloading ? '保存中...' : 'ダウンロード' }}
                        </button>
                        <button type="button" class="close-btn" @click="closeApplicationFormDialog">×</button>
                    </div>
                </div>
                <div class="confirm-body application-form-body">
                    <p v-if="applicationFormError" class="confirm-error">{{ applicationFormError }}</p>
                    <div class="application-form-viewport">
                        <img
                            v-if="applicationFormPreviewUrl"
                            class="application-form-image"
                            :src="applicationFormPreviewUrl"
                            alt="代替機申込書プレビュー"
                        >
                    </div>
                </div>
            </div>
        </div>

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
                    <button type="button" class="btn btn-secondary" :disabled="priceAdjustSaving" @click="closePriceAdjustDialog">
                        キャンセル
                    </button>
                    <button type="button" class="btn btn-primary" :disabled="priceAdjustSaving" @click="confirmPriceAdjust">
                        {{ priceAdjustSaving ? '保存中...' : 'OK' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showNoteDialog" class="confirm-overlay" @click.self="closeNoteDialog">
            <div class="confirm-panel" @click.stop>
                <div class="confirm-header">
                    <h3>{{ noteDialogMode === 'edit' ? 'Note 編集' : 'Note 新規追加' }}</h3>
                    <button type="button" class="close-btn" @click="closeNoteDialog">×</button>
                </div>
                <div class="confirm-body">
                    <label class="confirm-field">
                        内容
                        <textarea v-model="noteForm.note" rows="6" class="confirm-textarea" />
                    </label>
                    <label class="confirm-checkbox">
                        <input v-model="noteForm.important" type="checkbox">
                        重要
                    </label>
                    <div class="confirm-toggles">
                        <button
                            type="button"
                            class="toggle-btn"
                            :class="{ on: noteForm.tbc }"
                            @click="toggleNoteTbc"
                        >
                            要
                        </button>
                        <button
                            v-if="noteForm.tbc"
                            type="button"
                            class="toggle-btn toggle-btn-done"
                            :class="{ on: noteForm.done }"
                            @click="noteForm.done = !noteForm.done"
                        >
                            済
                        </button>
                    </div>
                    <p v-if="noteDialogError" class="confirm-error">{{ noteDialogError }}</p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" :disabled="noteSaving" @click="closeNoteDialog">
                        キャンセル
                    </button>
                    <button type="button" class="btn btn-primary" :disabled="noteSaving || !noteForm.note.trim()" @click="saveNote">
                        {{ noteSaving ? '保存中...' : '保存' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="notePendingDelete" class="confirm-overlay" @click.self="notePendingDelete = null">
            <div class="confirm-panel">
                <h3>Note 削除</h3>
                <p>この Note を削除しますか？</p>
                <p class="note-delete-preview">{{ notePendingDelete.note }}</p>
                <p v-if="noteError" class="confirm-error">{{ noteError }}</p>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" :disabled="noteDeleting" @click="notePendingDelete = null">
                        キャンセル
                    </button>
                    <button type="button" class="btn delete-btn" :disabled="noteDeleting" @click="deleteNote">
                        {{ noteDeleting ? '削除中...' : '削除' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="promotionModalOpen"
            class="confirm-overlay"
            @click.self="closePromotionModal"
        >
            <div class="confirm-panel promotion-panel" role="dialog" aria-modal="true" aria-labelledby="promotion-modal-title">
                <h3 id="promotion-modal-title">繰り上がり候補</h3>
                <p class="promotion-lead">
                    <template v-if="promotionFromLending">
                        完了（status 400）へ進み、機材を在庫に戻しました。予約の繰り上げはありますか？（同機種: {{ record.productName }}）
                    </template>
                    <template v-else>
                        機材が在庫に戻ったため、同機種（{{ record.productName }}）の waiting_list に繰り上がり候補があります。
                    </template>
                </p>
                <div class="promotion-table-wrap">
                    <table v-if="promotionCandidates.length" class="promotion-table">
                        <thead>
                            <tr>
                                <th>orderID</th>
                                <th>ParentID</th>
                                <th>dealer</th>
                                <th>contactPerson</th>
                                <th>希望期間</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="candidate in promotionCandidates" :key="candidate.orderID">
                                <td>{{ candidate.orderID }}</td>
                                <td>{{ candidate.parentID ?? '—' }}</td>
                                <td>{{ candidate.dealer || '—' }}</td>
                                <td>{{ candidate.contactPerson || '—' }}</td>
                                <td>
                                    {{ candidate.plannedSentDate || '—' }}
                                    ~
                                    {{ candidate.plannedReturnedDate || '—' }}
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-secondary promotion-open-btn"
                                        @click="openPromotionCandidate(candidate)"
                                    >
                                        開く
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="promotion-empty">同機種の waiting_list 候補はありません。</p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-primary" @click="closePromotionModal">
                        後で対応
                    </button>
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
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import {
    handleMonthCellDoubleClickToDayView,
    ROLLING_MONTH_VIEW,
    fullCalendarDayCellClassNames,
    rollingMonthViewConfig,
} from '@/utils/fullCalendarCommon'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import NotesTable from '@/components/ServiceRecord/NotesTable.vue'
import IntakeMasterSelectDialog from '@/components/ServiceRecord/Intake/IntakeMasterSelectDialog.vue'
import ShippingOutDateDialog from '@/components/ServiceRecord/Layer3/ShippingOutDateDialog.vue'
import { loanerStatusLabel, loanerStatusOptionLabel } from '@/utils/loanerStatusLabel'
import { apiFetch } from '@/utils/apiFetch'
import { handleUnauthorizedResponse } from '@/utils/auth'
import { pickMasterVersion, PAID_LOANER_RETURN_CODES } from '@/utils/resolveServiceWorkPrice'

const SHIP_PREP_STATUS_ID = 300

const props = defineProps({
    attached: { type: Object, required: true },
    record: { type: Object, required: true },
    parentReturnCode: { type: [Number, String], default: null },
    parentRecord: { type: Object, default: null },
    loanerMaster: { type: Object, default: null },
    files: { type: Array, default: () => [] },
    notes: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    statusFlow: {
        type: Object,
        default: () => ({
            steps: [0, 100, 150, 300, 393, 396, 399, 400],
            checkStatusId: 650,
            completeStatusId: 400,
            stockStatusId: 0,
            unregisteredStatusId: 20,
            lendingStatusId: 400,
            activeListStatusMax: 400,
            laborEditableStatusId: 393,
            returnedStatusId: 393,
            acceptanceStatusId: 396,
            preCompleteStatusId: 399,
            shipPrepStatusId: 200,
            shipPrepCompleteStatusId: 300,
            shipPrepRemandStatusId: 201,
            shipRequestStatusId: 350,
            nextDisabledExactStatusIds: [396],
        }),
    },
    labors: { type: Array, default: () => [] },
    dealersMaster: { type: Array, default: () => [] },
    loanerUnits: { type: Array, default: () => [] },
    availableUnits: { type: Array, default: () => [] },
    dateFields: { type: Object, required: true },
})

const page = usePage()
const saving = ref(false)
const error = ref('')
const success = ref('')
const promotionFromCheck = ref(false)
const promotionFromLending = ref(false)
const promoting = ref(false)
const cancellingReservation = ref(false)
const cancelReservationError = ref('')
const showCancelReservationDialog = ref(false)
const promoteLoanerId = ref('')
const calendarError = ref('')
const calendarRef = ref(null)
const bottomTab = ref('notes')
const fileInput = ref(null)
const fileItems = ref([...props.files])
const noteItems = ref([...props.notes])
const selectedFileId = ref(props.files[0]?.id ?? null)
const selectedNoteId = ref(null)
const uploading = ref(false)
const deleting = ref(false)
const fileSortSaving = ref(false)
const fileError = ref('')
const noteError = ref('')
const uploadProgress = ref('')
const fileDropActive = ref(false)
const fileDragDepth = ref(0)
const filePendingDelete = ref(null)
const notePendingDelete = ref(null)
const activeSelectKind = ref(null)
const leftPaneSize = ref(49)
const rightPaneSize = ref(51)
const fileBusy = computed(() => uploading.value || deleting.value || fileSortSaving.value)
const parentReturnCode = ref(props.parentReturnCode)
const parentInfo = ref(props.parentRecord ? { ...props.parentRecord } : null)
const showPriceAdjustDialog = ref(false)
const priceAdjustSaving = ref(false)
const priceAdjustError = ref('')
const priceAdjustForm = reactive({
    amount: '',
    reason: '',
})
const showApplicationFormDialog = ref(false)
const showApplicationFormSetupDialog = ref(false)
const applicationFormSetupError = ref('')
const applicationFormSetupForm = reactive({
    chargeType: 'paid',
    enduser_SN: '',
    equipmentNameSource: 'item',
})
const applicationFormLoading = ref(false)
const applicationFormError = ref('')
const applicationFormPreviewUrl = ref('')
const applicationFormPdfUrl = ref('')
const applicationFormPdfBlob = ref(null)
const applicationFormFilename = ref('loaner_application.pdf')
const applicationFormDownloading = ref(false)
const showNoteDialog = ref(false)
const noteDialogMode = ref('create')
const noteSaving = ref(false)
const noteDeleting = ref(false)
const noteDialogError = ref('')
const promotionModalOpen = ref(false)
const promotionCandidates = ref([])
const showShippingDialog = ref(false)
const shippingConfirming = ref(false)
const statusBeforeShippingDialog = ref(null)
let suppressStatusWatch = false
const noteForm = reactive({
    note: '',
    important: false,
    tbc: false,
    done: false,
})
const editingNoteId = ref(null)

const isWaitingList = computed(() => props.record.order_type === 'waiting_list')
const isPromotionReady = computed(() => {
    const at = props.record.promotion_ready_at
    return at != null && at !== ''
})
const availableUnits = computed(() => props.availableUnits ?? [])

watch(
    availableUnits,
    (units) => {
        if (promoteLoanerId.value) return
        const source = units.find(unit => unit?.isPromotionSource)
        if (source?.loanerID != null) {
            promoteLoanerId.value = String(source.loanerID)
        }
    },
    { immediate: true },
)

const authUserName = computed(() => String(
    page.props.authUser?.kanji_name
    ?? page.props.auth?.user?.kanji_name
    ?? '',
).trim())
const sharedNotes = computed(() =>
    noteItems.value.filter(note => !(note?.personal === true || note?.personal === 1 || note?.personal === '1')),
)
const tbcNotesCount = computed(() =>
    sharedNotes.value.filter((note) => {
        const tbc = note?.tbc === true || note?.tbc === 1 || note?.tbc === '1'
        const done = note?.done === true || note?.done === 1 || note?.done === '1'
        return tbc && !done
    }).length,
)
const selectedNote = computed(() =>
    sharedNotes.value.find(note => Number(note.id) === Number(selectedNoteId.value)) || null,
)

function isTruthyFlag(value) {
    return value === true || value === 1 || value === '1'
}

function toggleNoteTbc() {
    noteForm.tbc = !noteForm.tbc
    if (!noteForm.tbc) noteForm.done = false
}
function isNoteOwner(note) {
    if (!note) return false
    const who = String(note.whoWrote ?? '').trim()
    if (!who) return false
    if (note.is_mine === true || note.is_mine === 1 || note.is_mine === '1') return true
    return authUserName.value !== '' && authUserName.value === who
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

const stringValue = value => value == null ? '' : String(value)
function toDateInputValue(value) {
    if (!value) return ''
    const text = String(value)
    return text.length >= 10 ? text.slice(0, 10) : text
}
const form = reactive({
    parentID: stringValue(props.record.parentID),
    status: stringValue(props.record.status),
    laborID: stringValue(props.record.laborID),
    productName: stringValue(props.record.productName ?? props.loanerMaster?.productName),
    SN: stringValue(props.record.SN ?? props.loanerMaster?.SN),
    loanerID: props.attached.loanerID ?? props.record.loanerID ?? null,
    assignStatus: stringValue(props.attached.assignStatus),
    quoteNum: stringValue(props.record.quoteNum),
    quoteDate: toDateInputValue(props.record.quoteDate),
    orderNum: stringValue(props.record.orderNum),
    orderDate: toDateInputValue(props.record.orderDate),
    poNum: stringValue(props.record.poNum),
    shippingOut_requiredDate: toDateInputValue(props.record.shippingOut_requiredDate),
    discount_service: props.record.discount_service ?? 0,
    sentDate: stringValue(props.attached.sentDate),
    returnedDate: stringValue(props.attached.returnedDate),
    plannedSentDate: stringValue(props.attached.plannedSentDate ?? props.attached.sentDate),
    plannedReturnedDate: stringValue(props.attached.plannedReturnedDate ?? props.attached.returnedDate),
    dealer: stringValue(props.record.dealer),
    dealer_depart: stringValue(props.record.dealer_depart),
    contactPerson: stringValue(props.record.contactPerson),
    email: stringValue(props.record.email),
    phone: stringValue(props.record.phone),
    fax: stringValue(props.record.fax),
    zipcode: stringValue(props.record.zipcode),
    address1: stringValue(props.record.address1),
    address2: stringValue(props.record.address2),
    deliveryDestination_company: stringValue(props.record.deliveryDestination_company),
    deliveryDestination_depart: stringValue(props.record.deliveryDestination_depart),
    deliveryDestination_contactPerson: stringValue(props.record.deliveryDestination_contactPerson),
    deliveryDestination_email: stringValue(props.record.deliveryDestination_email),
    deliveryDestination_phone: stringValue(props.record.deliveryDestination_phone),
    deliveryDestination_zipcode: stringValue(props.record.deliveryDestination_zipcode),
    deliveryDestination_address1: stringValue(props.record.deliveryDestination_address1),
    deliveryDestination_address2: stringValue(props.record.deliveryDestination_address2),
    endUser: stringValue(props.record.endUser),
    endUser_depart: stringValue(props.record.endUser_depart),
    endUser_contactPerson: stringValue(props.record.endUser_contactPerson),
    endUser_email: stringValue(props.record.endUser_email),
    endUser_phone: stringValue(props.record.endUser_phone),
    endUser_zipcode: stringValue(props.record.endUser_zipcode),
    endUser_address1: stringValue(props.record.endUser_address1),
    endUser_address2: stringValue(props.record.endUser_address2),
    enduser_SN: props.attached.enduser_SN != null && props.attached.enduser_SN !== ''
        ? String(props.attached.enduser_SN)
        : '',
})

const selectedUnit = computed(() => {
    const units = props.loanerUnits.filter(unit => String(unit.loanerID) === String(form.loanerID))
    if (!units.length) return null
    return pickMasterVersion(units, form.orderDate || null)
        ?? props.loanerUnits.find(unit => String(unit.loanerID) === String(form.loanerID))
        ?? null
})
const displayItemLabel = computed(() => {
    const fromUnit = String(selectedUnit.value?.item ?? '').trim()
    if (fromUnit) return fromUnit
    const fromMaster = String(props.loanerMaster?.item ?? '').trim()
    if (fromMaster) return fromMaster
    return ''
})
const displayProductNameLabel = computed(() => {
    const fromUnit = String(selectedUnit.value?.productName ?? '').trim()
    if (fromUnit) return fromUnit
    const fromForm = String(form.productName ?? '').trim()
    if (fromForm) return fromForm
    const fromMaster = String(props.loanerMaster?.productName ?? '').trim()
    if (fromMaster) return fromMaster
    return ''
})
const applicationFormItemPreview = computed(() =>
    String(displayItemLabel.value ?? '').replace(/【簿外】/g, '').trim(),
)
const sortedFiles = computed(() => {
    const list = [...(fileItems.value ?? [])]
    list.sort((a, b) => {
        const aNull = a?.sortNum == null
        const bNull = b?.sortNum == null
        if (aNull && bNull) return Number(a?.id ?? 0) - Number(b?.id ?? 0)
        if (aNull) return 1
        if (bNull) return -1
        if (Number(a.sortNum) !== Number(b.sortNum)) return Number(a.sortNum) - Number(b.sortNum)
        return Number(a?.id ?? 0) - Number(b?.id ?? 0)
    })
    return list
})
const masterPrice = computed(() => {
    const units = props.loanerUnits.filter(unit => String(unit.loanerID) === String(form.loanerID))
    if (units.length) {
        const picked = pickMasterVersion(units, form.orderDate || null)
        const fromVersion = Number(picked?.price)
        if (Number.isFinite(fromVersion)) return fromVersion
    }
    const raw = selectedUnit.value?.price ?? props.loanerMaster?.price ?? 0
    const num = Number(raw)
    return Number.isFinite(num) ? num : 0
})
const basePrice = computed(() => {
    if (!form.parentID) return 0
    const code = Number(parentReturnCode.value)
    if (!PAID_LOANER_RETURN_CODES.includes(code)) return 0
    return masterPrice.value
})
const discountAmount = computed(() => {
    const num = Number(form.discount_service)
    return Number.isFinite(num) ? num : 0
})
const displayPrice = computed(() => basePrice.value + discountAmount.value)

function tokyoTodayYmd() {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date())
}

function toYmd(value) {
    if (value == null || value === '') return null
    const raw = String(value).slice(0, 10)
    return /^\d{4}-\d{2}-\d{2}$/.test(raw) ? raw : null
}

function elapsedDaysFromSentOut(sentOut) {
    const ymd = toYmd(sentOut)
    if (!ymd) return ''
    const today = tokyoTodayYmd()
    if (ymd > today) return ''
    const [y1, m1, d1] = ymd.split('-').map(Number)
    const [y2, m2, d2] = today.split('-').map(Number)
    const diff = Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / 86400000)
    return Number.isFinite(diff) ? String(diff) : ''
}

function parentStatusFromRecord(data) {
    if (!data) return null
    const label = data.status_label
        ?? data.statusMaster?.status
        ?? data.status_master?.status
        ?? loanerStatusLabel(data.statusMasterLoaner)
        ?? loanerStatusLabel(data.status_master_loaner)
        ?? null
    const status = data.status
    if (label != null && String(label).trim() !== '' && status != null && status !== '') {
        return `${label} (${status})`
    }
    if (label != null && String(label).trim() !== '') return String(label)
    if (status != null && status !== '') return String(status)
    return null
}

function normalizeParentInfo(data) {
    if (!data) return null
    return {
        orderID: data.orderID ?? data.order_id ?? null,
        status: data.status ?? null,
        status_label: data.status_label
            ?? data.statusMaster?.status
            ?? data.status_master?.status
            ?? loanerStatusLabel(data.statusMasterLoaner)
            ?? loanerStatusLabel(data.status_master_loaner)
            ?? null,
        sentOut: toYmd(data.sentOut ?? data.sent_out) ?? null,
        returnCode: data.returnCode ?? data.return_code ?? null,
    }
}

const parentStatusDisplay = computed(() => parentStatusFromRecord(parentInfo.value) || '—')
const parentSentOutDisplay = computed(() => toYmd(parentInfo.value?.sentOut) || '—')
const parentElapsedDaysDisplay = computed(() => {
    if (!parentInfo.value) return '—'
    const days = elapsedDaysFromSentOut(parentInfo.value.sentOut)
    return days === '' ? '' : days
})

const statuses = computed(() => props.statuses ?? [])
const labors = computed(() => props.labors ?? [])
const stockStatusId = computed(() => Number(props.statusFlow?.stockStatusId ?? 0))
const unregisteredStatusId = computed(() => Number(props.statusFlow?.unregisteredStatusId ?? 20))
const quoteDoneStatusId = computed(() => 100)
const orderedStatusId = computed(() => 150)
const shipPrepStatusId = computed(() => Number(props.statusFlow?.shipPrepStatusId ?? 200))
const laborEditableStatusId = computed(() => Number(
    props.statusFlow?.laborEditableStatusId
    ?? props.statusFlow?.returnedStatusId
    ?? 393,
))
const returnedStatusId = computed(() => Number(
    props.statusFlow?.returnedStatusId ?? laborEditableStatusId.value ?? 393,
))
const acceptanceStatusId = computed(() => Number(
    props.statusFlow?.acceptanceStatusId ?? 396,
))
const preCompleteStatusId = computed(() => Number(
    props.statusFlow?.preCompleteStatusId ?? 399,
))
const completeStatusId = computed(() => Number(
    props.statusFlow?.completeStatusId ?? 400,
))
const shipPrepCompleteStatusId = computed(() => Number(
    props.statusFlow?.shipPrepCompleteStatusId ?? SHIP_PREP_STATUS_ID,
))
const shipPrepRemandStatusId = computed(() => Number(
    props.statusFlow?.shipPrepRemandStatusId ?? 201,
))

const currentStatusLabel = computed(() => {
    const id = form.status === '' ? null : Number(form.status)
    if (id == null || Number.isNaN(id)) return '未設定'
    const row = statuses.value.find(item => Number(item.processID_new) === id)
    return row ? loanerStatusOptionLabel(row) : `status ${id}`
})

const isLaborEditable = computed(() =>
    props.record.order_type === 'loaner' && Number(form.status) === laborEditableStatusId.value,
)

const laborDisplayLabel = computed(() => {
    if (!form.laborID) return '未設定'
    const row = labors.value.find(item => String(item.laborID) === String(form.laborID))
    return row ? `${row.laborName} (${row.laborID})` : String(form.laborID)
})

const isEngineerContext = computed(() => {
    if (typeof window === 'undefined') return false
    try {
        const params = new URLSearchParams(window.location.search)
        if (params.get('from') === 'engineer') return true
        const returnUrl = safeReturnUrl()
        if (returnUrl && /\/servicerecord\/engineer(?:\/|$|\?)/.test(returnUrl)) return true
    } catch {
        // ignore
    }
    return false
})

function isNextButtonDisabledStatus(status) {
    const current = Number(status)
    if (!Number.isFinite(current)) return true
    // 300以上393未満は disable
    if (current >= shipPrepCompleteStatusId.value && current < returnedStatusId.value) return true
    // 受け入れ確認中(396): admin は disable、Engineer は enable
    if (current === acceptanceStatusId.value && !isEngineerContext.value) return true
    return false
}

function resolveNextStatusId(current) {
    if (current === stockStatusId.value || current === unregisteredStatusId.value) {
        return quoteDoneStatusId.value
    }
    if (current === quoteDoneStatusId.value) {
        return orderedStatusId.value
    }
    if (
        current === orderedStatusId.value
        || current === shipPrepStatusId.value
        || current === shipPrepRemandStatusId.value
    ) {
        return shipPrepCompleteStatusId.value
    }
    if (current === returnedStatusId.value) {
        return acceptanceStatusId.value
    }
    // Engineer: 受け入れ確認中(396) → 完了前、予約確認(399)
    if (current === acceptanceStatusId.value && isEngineerContext.value) {
        return preCompleteStatusId.value
    }
    if (current === preCompleteStatusId.value) {
        return completeStatusId.value
    }
    return null
}

const nextStatusOption = computed(() => {
    if (props.record.order_type !== 'loaner') return null
    const current = form.status === '' ? null : Number(form.status)
    if (current == null || Number.isNaN(current)) return null
    if (isNextButtonDisabledStatus(current)) return null

    const nextId = resolveNextStatusId(current)
    if (nextId == null) return null

    const row = statuses.value.find(item => Number(item.processID_new) === nextId)
    return {
        id: nextId,
        label: row ? loanerStatusLabel(row) : String(nextId),
    }
})

function maybePrefillLaborForAcceptance() {
    if (Number(form.status) !== laborEditableStatusId.value) return
    const authLaborId = page.props.auth?.user?.laborID
    if ((!form.laborID || form.laborID === '') && authLaborId != null && authLaborId !== '') {
        form.laborID = String(authLaborId)
    }
}

async function advanceStatus() {
    if (!nextStatusOption.value || saving.value) return
    const previousStatus = form.status
    const fromStatus = Number(previousStatus)
    const nextId = Number(nextStatusOption.value.id)
    // status 変更前に labor を退避（次へ後に select が消えて値が欠けるのを防ぐ）
    const laborSnapshot = form.laborID == null ? '' : String(form.laborID)

    // 返却(393) から進めるときは labor 必須
    if (fromStatus === laborEditableStatusId.value) {
        if (!laborSnapshot || laborSnapshot === '0') {
            error.value = '返却担当の labor を選択してください。'
            return
        }
    }

    if (nextId === shipPrepCompleteStatusId.value) {
        suppressStatusWatch = true
        form.status = String(nextId)
        nextTick(() => {
            suppressStatusWatch = false
        })
        openShippingDateDialog(previousStatus ?? '')
        return
    }

    // status は保存成功後に更新。labor は snapshot を明示送信
    const saved = await save({
        statusOverride: nextId,
        laborOverride: fromStatus === laborEditableStatusId.value ? laborSnapshot : undefined,
    })
    if (saved) {
        form.laborID = laborSnapshot
        // Engineer: 次へで 399（完了前、予約確認）になったら詳細を閉じる
        if (isEngineerContext.value && nextId === preCompleteStatusId.value) {
            closePage()
        }
    } else if (String(form.status) !== String(previousStatus)) {
        // 失敗時に status が変わっていれば戻す
        form.status = String(previousStatus ?? '')
    }
}

function openShippingDateDialog(previousStatus = null) {
    statusBeforeShippingDialog.value = previousStatus
    showShippingDialog.value = true
}

function onShippingDialogClose() {
    if (shippingConfirming.value) return
    showShippingDialog.value = false
    if (statusBeforeShippingDialog.value != null) {
        suppressStatusWatch = true
        form.status = String(statusBeforeShippingDialog.value)
        nextTick(() => {
            suppressStatusWatch = false
        })
    }
    statusBeforeShippingDialog.value = null
}

async function onShippingConfirm({ shippingOut_requiredDate }) {
    form.shippingOut_requiredDate = shippingOut_requiredDate || ''
    suppressStatusWatch = true
    form.status = String(shipPrepCompleteStatusId.value)
    nextTick(() => {
        suppressStatusWatch = false
    })
    statusBeforeShippingDialog.value = null
    showShippingDialog.value = false
    await save()
}

watch(() => form.status, (status, previousStatus) => {
    maybePrefillLaborForAcceptance()
    if (suppressStatusWatch) return
    if (
        props.record.order_type === 'loaner'
        && Number(status) === shipPrepCompleteStatusId.value
        && Number(previousStatus) !== shipPrepCompleteStatusId.value
    ) {
        openShippingDateDialog(previousStatus ?? '')
    }
})

watch(() => form.parentID, async (parentId) => {
    if (!parentId) {
        parentReturnCode.value = null
        parentInfo.value = null
        return
    }
    if (String(parentId) === String(props.record.parentID)) {
        parentReturnCode.value = props.parentReturnCode
        parentInfo.value = props.parentRecord ? { ...props.parentRecord } : null
        return
    }
    try {
        const result = await apiFetch(`${page.props.appBaseUrl}/servicerecord/record/${parentId}`)
        if (!result) {
            parentReturnCode.value = null
            parentInfo.value = null
            return
        }
        const { response, data } = result
        if (!response.ok) {
            parentReturnCode.value = null
            parentInfo.value = null
            return
        }
        parentReturnCode.value = data.returnCode ?? null
        parentInfo.value = normalizeParentInfo(data)
    } catch {
        parentReturnCode.value = null
        parentInfo.value = null
    }
})
const loanerUnitsLatestForSelect = computed(() => {
    // 機種選択ダイアログは最新版のみ（価格計算用の全版 props.loanerUnits は残す）
    // サーバ側は validDateMin desc, id desc で渡すため、loanerID ごとに先頭が最新
    const seen = new Set()
    const latest = []
    for (const unit of props.loanerUnits ?? []) {
        const key = String(unit?.loanerID ?? '').trim()
        if (!key) continue
        if (seen.has(key)) continue
        seen.add(key)
        latest.push(unit)
    }
    return latest
})
const activeSelectItems = computed(() => {
    if (activeSelectKind.value === 'dealer') return props.dealersMaster
    if (activeSelectKind.value === 'loanerUnit') return loanerUnitsLatestForSelect.value
    return props.loanerUnits
})
const activeSelectInitialValue = computed(() => {
    if (activeSelectKind.value === 'loanerUnit') return form.loanerID
    return props.dealersMaster.find(item => item.dealerName === form.dealer)?.id ?? null
})

const calendarOptions = {
    plugins: [dayGridPlugin, listPlugin, interactionPlugin],
    initialView: ROLLING_MONTH_VIEW,
    locale: 'ja',
    firstDay: 0,
    height: '100%',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: `${ROLLING_MONTH_VIEW},listMonth`,
    },
    buttonText: { today: '今日', list: 'リスト' },
    views: {
        [ROLLING_MONTH_VIEW]: {
            ...rollingMonthViewConfig,
        },
    },
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    dayMaxEvents: true,
    dayCellClassNames: fullCalendarDayCellClassNames,
    dateClick: handleMonthCellDoubleClickToDayView,
    events: fetchCalendarEvents,
    eventDrop: updateEventPeriod,
    eventResize: updateEventPeriod,
    eventClick(info) {
        if (!info.event.id || String(info.event.id) === String(props.attached.id)) return
        const orderId = info.event.extendedProps?.associatedID || info.event.id
        window.location.href = `${page.props.appBaseUrl}/servicerecord/loaner/detail/${orderId}`
    },
}

function nullable(value) {
    return value === '' || value === undefined ? null : value
}

function numericNullable(value) {
    return value === '' || value === null || value === undefined ? null : Number(value)
}

function formatPrice(value) {
    const num = Number(value)
    if (!Number.isFinite(num)) return '0'
    return num.toLocaleString('ja-JP')
}

function formatSignedAmount(value) {
    const num = Number(value)
    if (!Number.isFinite(num) || num === 0) return '0'
    const formatted = Math.abs(num).toLocaleString('ja-JP')
    return num > 0 ? `+${formatted}` : `-${formatted}`
}

function openPriceAdjustDialog() {
    priceAdjustForm.amount = form.discount_service == null || form.discount_service === ''
        ? ''
        : String(form.discount_service)
    priceAdjustForm.reason = ''
    priceAdjustError.value = ''
    showPriceAdjustDialog.value = true
}

function closePriceAdjustDialog() {
    if (priceAdjustSaving.value) return
    showPriceAdjustDialog.value = false
    priceAdjustError.value = ''
}

async function confirmPriceAdjust() {
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
    priceAdjustSaving.value = true
    priceAdjustError.value = ''

    try {
        const result = await apiFetch(`${page.props.appBaseUrl}/servicerecord/notes`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                associatedID: props.record.orderID,
                note: `[調整理由]　${reason}`,
                important: true,
            }),
        })
        if (!result) throw new Error('Notes の追加に失敗しました。')
        const { response, data } = result
        if (!response.ok) {
            throw new Error(validationError(data, `Notes の追加に失敗しました。（HTTP ${response.status}）`))
        }
        form.discount_service = amount
        if (data?.note) {
            noteItems.value = [data.note, ...noteItems.value.filter(n => Number(n.id) !== Number(data.note.id))]
        }
        showPriceAdjustDialog.value = false
        success.value = '価格調整を反映しました。保存ボタンで確定してください。'
    } catch (e) {
        priceAdjustError.value = e.message || '価格調整に失敗しました。'
    } finally {
        priceAdjustSaving.value = false
    }
}

async function switchToCalendar() {
    bottomTab.value = 'calendar'
    await nextTick()
    calendarRef.value?.getApi?.().updateSize()
    calendarRef.value?.getApi?.().refetchEvents()
}

function openNoteCreate() {
    noteDialogMode.value = 'create'
    editingNoteId.value = null
    noteForm.note = ''
    noteForm.important = false
    noteForm.tbc = false
    noteForm.done = false
    noteDialogError.value = ''
    showNoteDialog.value = true
}

function openNoteEdit() {
    const note = selectedNote.value
    if (!note) return
    if (!isNoteOwner(note)) {
        window.alert(`自分が書いた Note のみ編集できます。\nログイン: ${authUserName.value || '不明'}\n記入者: ${note.whoWrote || '不明'}`)
        return
    }
    noteDialogMode.value = 'edit'
    editingNoteId.value = note.id
    noteForm.note = note.note ?? ''
    noteForm.important = !!note.important
    noteForm.tbc = isTruthyFlag(note.tbc)
    noteForm.done = noteForm.tbc && isTruthyFlag(note.done)
    noteDialogError.value = ''
    showNoteDialog.value = true
}

function closeNoteDialog() {
    if (noteSaving.value) return
    showNoteDialog.value = false
    noteDialogError.value = ''
}

async function saveNote() {
    const text = String(noteForm.note ?? '').trim()
    if (!text) {
        noteDialogError.value = '内容を入力してください。'
        return
    }

    noteSaving.value = true
    noteDialogError.value = ''
    try {
        const isEdit = noteDialogMode.value === 'edit'
        const url = isEdit
            ? `${page.props.appBaseUrl}/servicerecord/notes/${editingNoteId.value}`
            : `${page.props.appBaseUrl}/servicerecord/notes`
        const tbc = noteForm.tbc ? true : null
        const done = noteForm.tbc && noteForm.done ? true : null
        const body = isEdit
            ? { note: text, important: !!noteForm.important, tbc, done }
            : {
                associatedID: props.record.orderID,
                note: text,
                important: !!noteForm.important,
                personal: false,
                tbc,
                done,
            }
        const result = await apiFetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        })
        if (!result) throw new Error('Note の保存に失敗しました。')
        const { response, data } = result
        if (!response.ok) throw new Error(validationError(data, `Note の保存に失敗しました。（HTTP ${response.status}）`))

        const saved = data.note
        if (isEdit) {
            noteItems.value = noteItems.value.map(note =>
                Number(note.id) === Number(saved.id) ? saved : note,
            )
        } else {
            noteItems.value = [saved, ...noteItems.value]
            selectedNoteId.value = saved.id
        }
        showNoteDialog.value = false
        success.value = data.message || 'Note を保存しました。'
    } catch (e) {
        noteDialogError.value = e.message || 'Note の保存に失敗しました。'
    } finally {
        noteSaving.value = false
    }
}

function openNoteDelete() {
    const note = selectedNote.value
    if (!note) return
    if (!isNoteOwner(note)) {
        window.alert(`自分が書いた Note のみ削除できます。\nログイン: ${authUserName.value || '不明'}\n記入者: ${note.whoWrote || '不明'}`)
        return
    }
    noteError.value = ''
    notePendingDelete.value = note
}

async function deleteNote() {
    if (!notePendingDelete.value) return
    noteDeleting.value = true
    noteError.value = ''
    try {
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/notes/${notePendingDelete.value.id}`,
            {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            },
        )
        if (!result) throw new Error('Note の削除に失敗しました。')
        const { response, data } = result
        if (!response.ok) throw new Error(validationError(data, `Note の削除に失敗しました。（HTTP ${response.status}）`))
        const deletedId = notePendingDelete.value.id
        noteItems.value = noteItems.value.filter(note => Number(note.id) !== Number(deletedId))
        if (Number(selectedNoteId.value) === Number(deletedId)) selectedNoteId.value = null
        notePendingDelete.value = null
        success.value = data.message || 'Note を削除しました。'
    } catch (e) {
        noteError.value = e.message || 'Note の削除に失敗しました。'
    } finally {
        noteDeleting.value = false
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function validationError(data, fallback) {
    return data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || fallback)
}

function onMasterSelected(result) {
    if (activeSelectKind.value === 'loanerUnit') {
        form.loanerID = result.loanerID ?? null
        form.productName = result.productName ?? ''
        form.SN = result.SN ?? ''
    } else if (activeSelectKind.value === 'dealer') {
        ;['dealer', 'dealer_depart', 'contactPerson', 'email', 'phone', 'fax', 'zipcode', 'address1', 'address2']
            .forEach((field) => {
                form[field] = result[field] ?? ''
            })
        void lookupZip('dealer')
    }
    activeSelectKind.value = null
}

function normalizeZipcode(value) {
    return String(value || '').replace(/\D/g, '')
}

async function lookupZip(target) {
    const raw = target === 'delivery'
        ? form.deliveryDestination_zipcode
        : target === 'endUser'
            ? form.endUser_zipcode
            : form.zipcode
    const zip = normalizeZipcode(raw)
    if (zip.length !== 7) return

    try {
        const res = await fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${encodeURIComponent(zip)}`)
        if (!res.ok) return
        const data = await res.json()
        const hit = Array.isArray(data?.results) ? data.results[0] : null
        if (!hit) return

        const pref = String(hit.address1 || '')
        const cityTown = `${String(hit.address2 || '')}${String(hit.address3 || '')}`
        if (target === 'delivery') {
            form.deliveryDestination_zipcode = zip
            form.deliveryDestination_address1 = pref
            form.deliveryDestination_address2 = cityTown
        } else if (target === 'endUser') {
            form.endUser_zipcode = zip
            form.endUser_address1 = pref
            form.endUser_address2 = cityTown
        } else {
            form.zipcode = zip
            form.address1 = pref
            form.address2 = cityTown
        }
    } catch {
        // 検索失敗時は手入力のまま
    }
}

function copyDealerToDelivery() {
    form.deliveryDestination_company = form.dealer || ''
    form.deliveryDestination_depart = form.dealer_depart || ''
    form.deliveryDestination_contactPerson = form.contactPerson || ''
    form.deliveryDestination_phone = form.phone || ''
    form.deliveryDestination_email = form.email || ''
    form.deliveryDestination_zipcode = form.zipcode || ''
    form.deliveryDestination_address1 = form.address1 || ''
    form.deliveryDestination_address2 = form.address2 || ''
}

function copyEndUserToDelivery() {
    form.deliveryDestination_company = form.endUser || ''
    form.deliveryDestination_depart = form.endUser_depart || ''
    form.deliveryDestination_contactPerson = form.endUser_contactPerson || ''
    form.deliveryDestination_phone = form.endUser_phone || ''
    form.deliveryDestination_email = form.endUser_email || ''
    form.deliveryDestination_zipcode = form.endUser_zipcode || ''
    form.deliveryDestination_address1 = form.endUser_address1 || ''
    form.deliveryDestination_address2 = form.endUser_address2 || ''
}

function filesApiBase() {
    return `${page.props.appBaseUrl}/servicerecord/files`
}

async function persistFileSortNum(fileId, sortNum) {
    const result = await apiFetch(`${filesApiBase()}/${fileId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ sortNum }),
    })
    if (!result) throw new Error('順序の更新に失敗しました。')
    const { response, data } = result
    if (!response.ok) {
        throw new Error(validationError(data, `順序の更新に失敗しました。（HTTP ${response.status}）`))
    }
    return data.file ?? { id: fileId, sortNum }
}

async function updateFileSortNum(fileId, sortNum) {
    if (fileSortSaving.value) return
    fileSortSaving.value = true
    fileError.value = ''
    try {
        const updated = await persistFileSortNum(fileId, sortNum)
        fileItems.value = fileItems.value.map(file =>
            Number(file.id) === Number(fileId)
                ? { ...file, ...updated, sortNum: updated?.sortNum ?? sortNum }
                : file,
        )
    } catch (e) {
        fileError.value = e.message || '順序の更新に失敗しました。'
    } finally {
        fileSortSaving.value = false
    }
}

async function moveFile(fileId, direction) {
    if (fileSortSaving.value) return
    const list = [...sortedFiles.value]
    const index = list.findIndex(file => Number(file.id) === Number(fileId))
    if (index < 0) return
    const swapIndex = direction === 'up' ? index - 1 : index + 1
    if (swapIndex < 0 || swapIndex >= list.length) return

    ;[list[index], list[swapIndex]] = [list[swapIndex], list[index]]
    const updates = list.map((file, idx) => ({
        id: file.id,
        sortNum: (idx + 1) * 10,
    }))

    fileSortSaving.value = true
    fileError.value = ''
    try {
        const results = await Promise.all(
            updates.map(item => persistFileSortNum(item.id, item.sortNum)),
        )
        const byId = new Map(results.map(file => [String(file.id), file]))
        fileItems.value = fileItems.value.map((file) => {
            const updated = byId.get(String(file.id))
            return updated ? { ...file, ...updated } : file
        })
    } catch (e) {
        fileError.value = e.message || '表示順の変更に失敗しました。'
    } finally {
        fileSortSaving.value = false
    }
}

function openFilePicker() {
    if (fileBusy.value) return
    fileError.value = ''
    fileInput.value?.click()
}

function onFileInputChange(event) {
    const selected = [...(event.target.files ?? [])]
    event.target.value = ''
    if (selected.length) uploadFiles(selected)
}

function onFileDragEnter(event) {
    if (fileBusy.value || ![...(event.dataTransfer?.types ?? [])].includes('Files')) return
    fileDragDepth.value += 1
    fileDropActive.value = true
}

function onFileDragOver(event) {
    if (fileBusy.value) return
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy'
    fileDropActive.value = true
}

function onFileDragLeave() {
    fileDragDepth.value = Math.max(0, fileDragDepth.value - 1)
    if (fileDragDepth.value === 0) fileDropActive.value = false
}

function onFileDrop(event) {
    fileDragDepth.value = 0
    fileDropActive.value = false
    if (fileBusy.value) return
    const dropped = [...(event.dataTransfer?.files ?? [])]
    if (!dropped.length) {
        fileError.value = 'ドロップされた内容からファイルを取得できませんでした。'
        return
    }
    uploadFiles(dropped)
}

function guessDocumentType(file) {
    const name = String(file?.name || '').toLowerCase()
    const type = String(file?.type || '').toLowerCase()
    if (name.endsWith('.eml') || name.endsWith('.msg') || type.includes('message')) return 'メール'
    if (type === 'application/pdf' || name.endsWith('.pdf')) return 'PDF'
    if (type.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|tiff?)$/i.test(name)) return '画像'
    return '添付ファイル'
}

function nextFileSortNum() {
    const values = fileItems.value
        .map(file => Number(file.sortNum))
        .filter(value => Number.isFinite(value))
    return values.length ? Math.max(...values) + 10 : 10
}

async function uploadSingleFile(file, sortNum) {
    const body = new FormData()
    body.append('associatedID', String(props.record.orderID))
    body.append('file', file)
    body.append('documentName', file.name || 'untitled')
    body.append('documentType', guessDocumentType(file))
    body.append('sortNum', String(sortNum))

    const result = await apiFetch(filesApiBase(), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        body,
    })
    if (!result) throw new Error(`${file.name || 'ファイル'}のアップロードに失敗しました。`)
    const { response, data } = result
    if (!response.ok) {
        throw new Error(validationError(
            data,
            `${file.name || 'ファイル'}のアップロードに失敗しました。（HTTP ${response.status}）`,
        ))
    }
    return data.file
}

async function uploadFiles(files) {
    const list = files.filter(file => file && file.size >= 0)
    if (!list.length) return

    uploading.value = true
    fileError.value = ''
    success.value = ''
    let sortNum = nextFileSortNum()
    let lastAdded = null

    try {
        for (let index = 0; index < list.length; index += 1) {
            uploadProgress.value = `${index + 1}/${list.length}: ${list[index].name || 'untitled'}`
            const added = await uploadSingleFile(list[index], sortNum)
            fileItems.value.push(added)
            lastAdded = added
            sortNum += 10
        }
        if (lastAdded) selectedFileId.value = lastAdded.id
        success.value = `${list.length}件のファイルを追加しました。`
    } catch (e) {
        fileError.value = e.message || 'ファイルのアップロードに失敗しました。'
    } finally {
        uploading.value = false
        uploadProgress.value = ''
        fileDropActive.value = false
        fileDragDepth.value = 0
    }
}

function requestFileDelete() {
    fileError.value = ''
    filePendingDelete.value = fileItems.value.find(file => file.id === selectedFileId.value) ?? null
}

async function deleteFile() {
    const target = filePendingDelete.value
    if (!target || deleting.value) return

    deleting.value = true
    fileError.value = ''
    success.value = ''
    try {
        const result = await apiFetch(`${filesApiBase()}/${target.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        })
        if (!result) return
        const { response, data } = result
        if (!response.ok) {
            throw new Error(validationError(data, `ファイル削除に失敗しました。（HTTP ${response.status}）`))
        }
        fileItems.value = fileItems.value.filter(file => file.id !== target.id)
        selectedFileId.value = fileItems.value[0]?.id ?? null
        filePendingDelete.value = null
        success.value = data.message || 'ファイルを削除しました。'
    } catch (e) {
        fileError.value = e.message || 'ファイル削除に失敗しました。'
    } finally {
        deleting.value = false
    }
}

function revokeApplicationFormUrl() {
    if (applicationFormPreviewUrl.value) {
        URL.revokeObjectURL(applicationFormPreviewUrl.value)
        applicationFormPreviewUrl.value = ''
    }
    if (applicationFormPdfUrl.value) {
        URL.revokeObjectURL(applicationFormPdfUrl.value)
        applicationFormPdfUrl.value = ''
    }
    applicationFormPdfBlob.value = null
}

async function downloadApplicationForm() {
    if (!applicationFormPdfBlob.value || applicationFormDownloading.value) return

    applicationFormDownloading.value = true
    applicationFormError.value = ''
    fileError.value = ''
    success.value = ''

    const filename = applicationFormFilename.value || 'loaner_application.pdf'

    try {
        const url = applicationFormPdfUrl.value || URL.createObjectURL(applicationFormPdfBlob.value)
        const link = document.createElement('a')
        link.href = url
        link.download = filename
        link.rel = 'noopener'
        document.body.appendChild(link)
        link.click()
        link.remove()

        const file = new File([applicationFormPdfBlob.value], filename, { type: 'application/pdf' })
        const added = await uploadSingleFile(file, nextFileSortNum())
        fileItems.value.push(added)
        selectedFileId.value = added.id
        success.value = '申込書をダウンロードし、ファイル一覧に追加しました。'
    } catch (e) {
        applicationFormError.value = e.message || '申込書のファイル保存に失敗しました。'
    } finally {
        applicationFormDownloading.value = false
    }
}

function closeApplicationFormSetupDialog() {
    if (applicationFormLoading.value) return
    showApplicationFormSetupDialog.value = false
    applicationFormSetupError.value = ''
}

function openApplicationFormSetup() {
    if (applicationFormLoading.value) return
    applicationFormSetupError.value = ''
    applicationFormSetupForm.chargeType = 'paid'
    applicationFormSetupForm.enduser_SN = form.enduser_SN || ''
    applicationFormSetupForm.equipmentNameSource = 'item'
    showApplicationFormSetupDialog.value = true
}

function confirmApplicationFormSetup() {
    applicationFormSetupError.value = ''
    generateApplicationForm({
        chargeType: applicationFormSetupForm.chargeType,
        enduser_SN: applicationFormSetupForm.enduser_SN,
        equipmentNameSource: applicationFormSetupForm.equipmentNameSource,
    })
}

function closeApplicationFormDialog() {
    showApplicationFormDialog.value = false
    applicationFormError.value = ''
    revokeApplicationFormUrl()
}

async function generateApplicationForm({ chargeType, enduser_SN, equipmentNameSource = 'item' }) {
    if (applicationFormLoading.value) return
    applicationFormLoading.value = true
    applicationFormError.value = ''
    error.value = ''
    revokeApplicationFormUrl()

    const manageNum = String(selectedUnit.value?.manageNum || props.loanerMaster?.manageNum || '').trim()
    const equipmentName = equipmentNameSource === 'productName'
        ? displayProductNameLabel.value
        : displayItemLabel.value
    const payload = {
        chargeType,
        enduser_SN: enduser_SN === '' || enduser_SN == null ? null : String(enduser_SN).trim(),
        price: chargeType === 'paid' ? masterPrice.value : 0,
        orderDate: form.orderDate || null,
        contactPerson: form.contactPerson,
        phone: form.phone,
        fax: form.fax,
        manageNum,
        item: equipmentName,
        loanerID: form.loanerID,
        SN: form.SN,
        sentDate: form.sentDate || form.plannedSentDate,
        plannedReturnedDate: form.plannedReturnedDate,
        returnedDate: form.returnedDate,
        dealer: form.dealer,
        dealer_depart: form.dealer_depart,
        zipcode: form.zipcode,
        address1: form.address1,
        address2: form.address2,
        deliveryDestination_company: form.deliveryDestination_company,
        deliveryDestination_depart: form.deliveryDestination_depart,
        deliveryDestination_contactPerson: form.deliveryDestination_contactPerson,
        deliveryDestination_zipcode: form.deliveryDestination_zipcode,
        deliveryDestination_address1: form.deliveryDestination_address1,
        deliveryDestination_address2: form.deliveryDestination_address2,
        deliveryDestination_phone: form.deliveryDestination_phone,
        parentID: form.parentID ? Number(form.parentID) : null,
        senderName: authUserName.value,
    }

    const endpoint = `${page.props.appBaseUrl}/servicerecord/loaner/detail/${props.attached.id}/application-form`
    const common = {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
    }

    try {
        const [pngResponse, pdfResponse] = await Promise.all([
            fetch(endpoint, { ...common, headers: { ...common.headers, Accept: 'image/png' } }),
            fetch(endpoint, { ...common, headers: { ...common.headers, Accept: 'application/pdf' } }),
        ])
        if (handleUnauthorizedResponse(pngResponse) || handleUnauthorizedResponse(pdfResponse)) return

        if (!pngResponse.ok) {
            let message = `申込書の生成に失敗しました。（HTTP ${pngResponse.status}）`
            const ct = pngResponse.headers.get('Content-Type') || ''
            if (ct.includes('application/json')) {
                const data = await pngResponse.json().catch(() => ({}))
                message = data.message || data.error || message
            }
            throw new Error(message)
        }
        if (!pdfResponse.ok) {
            throw new Error(`申込書 PDF の取得に失敗しました。（HTTP ${pdfResponse.status}）`)
        }

        const pngBlob = await pngResponse.blob()
        const pdfBlob = await pdfResponse.blob()
        applicationFormPreviewUrl.value = URL.createObjectURL(pngBlob)
        applicationFormPdfUrl.value = URL.createObjectURL(pdfBlob)
        applicationFormPdfBlob.value = pdfBlob
        applicationFormFilename.value = `loaner_application_${props.record.orderID || props.attached.id}.pdf`
        showApplicationFormSetupDialog.value = false
        showApplicationFormDialog.value = true
    } catch (e) {
        applicationFormSetupError.value = e.message || '申込書の生成に失敗しました。'
        applicationFormError.value = applicationFormSetupError.value
        error.value = applicationFormSetupError.value
    } finally {
        applicationFormLoading.value = false
    }
}

async function save(options = {}) {
    // @click="save" だと MouseEvent が渡るため、素のオブジェクト以外は無視する
    const opts = (options && typeof options === 'object' && !('isTrusted' in options))
        ? options
        : {}

    error.value = ''
    success.value = ''
    const statusOverride = opts.statusOverride
    const laborOverride = opts.laborOverride
    const savingStatus = statusOverride != null && statusOverride !== ''
        ? Number(statusOverride)
        : Number(form.status)
    const laborToSave = laborOverride != null && laborOverride !== undefined
        ? String(laborOverride)
        : (form.laborID == null ? '' : String(form.laborID))
    const savingAtReturned = savingStatus === laborEditableStatusId.value
        || Number(opts.preserveLaborFromStatus) === laborEditableStatusId.value
        || Number(form.status) === laborEditableStatusId.value

    if (Number.isFinite(savingStatus) && savingStatus >= 300 && tbcNotesCount.value > 0) {
        if (!window.confirm('要確認事項があります')) return false
    }
    if (form.plannedSentDate && form.plannedReturnedDate && form.plannedReturnedDate < form.plannedSentDate) {
        error.value = '予定終了日は予定開始日以降にしてください。'
        return false
    }
    if (form.sentDate && form.returnedDate && form.returnedDate < form.sentDate) {
        error.value = '実終了日は実開始日以降にしてください。'
        return false
    }
    if (savingAtReturned && (!laborToSave || laborToSave === '0')) {
        error.value = '返却担当の labor を選択してください。'
        return false
    }
    if (savingStatus === shipPrepCompleteStatusId.value && !form.shippingOut_requiredDate) {
        error.value = 'status が「貸出機出荷準備完了＿起伝依頼」のときは発送予定日を設定してください。'
        openShippingDateDialog()
        return false
    }

    let notifyLoanerCheck = false
    if (
        Number.isFinite(savingStatus)
        && savingStatus === acceptanceStatusId.value
        && laborToSave !== ''
        && laborToSave !== '0'
    ) {
        try {
            const previewUrl = `${page.props.appBaseUrl}/servicerecord/assign-notify/targets?laborID=${encodeURIComponent(laborToSave)}`
            const preview = await apiFetch(previewUrl, {
                method: 'GET',
                headers: { Accept: 'application/json' },
            })
            const count = Number(preview?.data?.count ?? 0)
            if (Number.isFinite(count) && count > 0) {
                notifyLoanerCheck = !!window.confirm(
                    `同じ laborID の担当者 ${count} 名に機材チェック通知メールを送信しますか？\n\n[はい]=送信　[いいえ]=送信しない`,
                )
            }
        } catch (e) {
            console.warn('loaner check notify targets check failed', e)
        }
    }

    const payload = { ...form }
    payload.parentID = numericNullable(form.parentID)
    payload.loanerID = numericNullable(form.loanerID)
    payload.status = Number.isFinite(savingStatus) ? savingStatus : numericNullable(form.status)
    payload.notify_loaner_check = notifyLoanerCheck

    // laborID は常に明示送信（未選択時のみ省略）。返却時は必須。
    if (laborToSave !== '') {
        payload.laborID = Number(laborToSave)
        if (savingAtReturned && Number.isFinite(payload.laborID) && payload.laborID !== 0) {
            payload.receivedDate = new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Tokyo',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date())
        }
    } else {
        delete payload.laborID
    }

    payload.discount_service = numericNullable(form.discount_service) ?? 0
    payload.price = basePrice.value
    Object.keys(payload).forEach((key) => {
        if (typeof payload[key] === 'string') payload[key] = nullable(payload[key])
    })
    // laborID を再確定（forEach 後も数値で送る）
    if (laborToSave !== '') {
        payload.laborID = Number(laborToSave)
    }
    if (!props.dateFields.hasPlannedSent) delete payload.plannedSentDate
    if (!props.dateFields.hasPlannedReturned) delete payload.plannedReturnedDate

    saving.value = true
    try {
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/detail/${props.attached.id}`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify(payload),
            },
        )
        if (!result) return false
        const { response, data } = result
        if (!response.ok) throw new Error(validationError(data, `保存に失敗しました。（HTTP ${response.status}）`))
        syncCurrentDates(data.attached, data.record)
        if (data.record?.status != null && data.record.status !== '') {
            form.status = String(data.record.status)
        } else if (statusOverride != null && statusOverride !== '') {
            form.status = String(statusOverride)
        }
        if (laborToSave !== '') {
            // 送信した labor を優先（レスポンス欠落や 0 上書きを防ぐ）
            const savedLabor = data.record?.laborID
            if (savedLabor != null && savedLabor !== '' && Number(savedLabor) !== 0) {
                form.laborID = String(savedLabor)
            } else {
                form.laborID = laborToSave
            }
        } else if (data.record && Object.prototype.hasOwnProperty.call(data.record, 'laborID')) {
            form.laborID = data.record.laborID == null || data.record.laborID === ''
                ? ''
                : String(data.record.laborID)
        }
        if (data.record && Object.prototype.hasOwnProperty.call(data.record, 'shippingOut_requiredDate')) {
            form.shippingOut_requiredDate = toDateInputValue(data.record.shippingOut_requiredDate)
        }
        success.value = data.message || '貸出詳細を保存しました。'
        calendarRef.value?.getApi?.().refetchEvents()
        if (data.promotionTriggered) {
            promotionCandidates.value = Array.isArray(data.promotionCandidates) ? data.promotionCandidates : []
            promotionFromLending.value = Boolean(data.promotionFromLending ?? data.promotionFromCheck)
            promotionFromCheck.value = promotionFromLending.value
            promotionModalOpen.value = true
        }
        return true
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
        return false
    } finally {
        saving.value = false
    }
}

function closePromotionModal() {
    promotionModalOpen.value = false
    // 次へで完了(400)にした後、予約ダイアログを閉じたら詳細も閉じる
    if (Number(form.status) === completeStatusId.value) {
        closePage()
    }
}

function openPromotionCandidate(candidate) {
    if (!candidate?.orderID) return
    const returnUrl = typeof window !== 'undefined' ? window.location.href : ''
    const params = returnUrl ? `?returnUrl=${encodeURIComponent(returnUrl)}` : ''
    window.location.href = `${page.props.appBaseUrl}/servicerecord/loaner/detail/${candidate.orderID}${params}`
}

function closeCancelReservationDialog() {
    if (cancellingReservation.value) return
    showCancelReservationDialog.value = false
    cancelReservationError.value = ''
}

async function cancelReservation() {
    if (!isWaitingList.value || cancellingReservation.value) return
    error.value = ''
    success.value = ''
    cancelReservationError.value = ''
    cancellingReservation.value = true
    try {
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/detail/${props.attached.id}/cancel-reservation`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({}),
            },
        )
        if (!result) {
            cancelReservationError.value = '認証が必要です。再ログインしてください。'
            return
        }
        const { response, data } = result
        if (!response.ok) throw new Error(validationError(data, `予約キャンセルに失敗しました。（HTTP ${response.status}）`))
        showCancelReservationDialog.value = false
        returnToWaitingListList()
    } catch (e) {
        cancelReservationError.value = e.message || '予約キャンセルに失敗しました。'
        error.value = cancelReservationError.value
    } finally {
        cancellingReservation.value = false
    }
}

async function promoteToLoaner() {
    if (!isWaitingList.value || promoting.value) return
    error.value = ''
    success.value = ''
    if (!availableUnits.value.length) {
        error.value = '同機種の在庫がありません。在庫復帰後に再度実行してください。'
        return
    }

    promoting.value = true
    try {
        const payload = {}
        const selectedId = numericNullable(promoteLoanerId.value)
        if (selectedId != null) {
            payload.loanerID = selectedId
        }

        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/detail/${props.attached.id}/promote`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify(payload),
            },
        )
        if (!result) return
        const { response, data } = result
        if (!response.ok) throw new Error(validationError(data, `繰り上げに失敗しました。（HTTP ${response.status}）`))
        success.value = data.message || 'loaner へ繰り上げました。'
        // order_type / status / 在庫割当を反映するため詳細を再読込
        window.location.reload()
    } catch (e) {
        error.value = e.message || '繰り上げに失敗しました。'
    } finally {
        promoting.value = false
    }
}

function syncCurrentDates(attached, record = null) {
    if (attached) {
        form.sentDate = attached.sentDate || ''
        form.returnedDate = attached.returnedDate || ''
        form.plannedSentDate = attached.plannedSentDate || attached.sentDate || ''
        form.plannedReturnedDate = attached.plannedReturnedDate || attached.returnedDate || ''
        form.assignStatus = attached.assignStatus || ''
        if (Object.prototype.hasOwnProperty.call(attached, 'enduser_SN')) {
            form.enduser_SN = attached.enduser_SN != null && attached.enduser_SN !== ''
                ? String(attached.enduser_SN)
                : ''
        }
    }
    if (record) {
        form.quoteNum = stringValue(record.quoteNum)
        form.quoteDate = toDateInputValue(record.quoteDate)
        form.orderNum = stringValue(record.orderNum)
        form.orderDate = toDateInputValue(record.orderDate)
        form.poNum = stringValue(record.poNum)
        if (Object.prototype.hasOwnProperty.call(record, 'shippingOut_requiredDate')) {
            form.shippingOut_requiredDate = toDateInputValue(record.shippingOut_requiredDate)
        }
        form.discount_service = record.discount_service ?? 0
        if (record.parentID != null) form.parentID = stringValue(record.parentID)
    }
}

async function fetchCalendarEvents(info, successCallback, failureCallback) {
    calendarError.value = ''
    try {
        const params = new URLSearchParams({
            start: info.startStr.slice(0, 10),
            end: info.endStr.slice(0, 10),
        })
        if (form.loanerID) params.set('loanerID', form.loanerID)
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/calendar/events?${params.toString()}`,
        )
        if (!result) return successCallback([])
        const { response, data } = result
        if (!response.ok) throw new Error(data.message || `カレンダー取得に失敗しました。（HTTP ${response.status}）`)
        successCallback(data.events ?? [])
    } catch (e) {
        calendarError.value = e.message || 'カレンダー取得に失敗しました。'
        failureCallback(e)
    }
}

function toLocalYmd(value) {
    if (!value) return null
    if (typeof value === 'string') return value.slice(0, 10)
    const year = value.getFullYear()
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

function addLocalDays(ymd, amount) {
    if (!ymd) return null
    const [year, month, day] = ymd.split('-').map(Number)
    const date = new Date(year, month - 1, day, 12)
    date.setDate(date.getDate() + amount)
    return toLocalYmd(date)
}

function eventDates(event) {
    const start = toLocalYmd(event.startStr || event.start)
    const exclusiveEnd = toLocalYmd(event.endStr || event.end)
    return start ? { start, end: exclusiveEnd ? addLocalDays(exclusiveEnd, -1) : start } : null
}

async function updateEventPeriod(changeInfo) {
    const dates = eventDates(changeInfo.event)
    if (!dates || dates.end < dates.start) {
        changeInfo.revert()
        calendarError.value = '移動後の期間が不正です。'
        return
    }

    const ext = changeInfo.event.extendedProps ?? {}
    const payload = {
        sentDate: props.dateFields.hasPlannedSent ? (ext.sentDate || null) : dates.start,
        returnedDate: props.dateFields.hasPlannedReturned ? (ext.returnedDate || null) : dates.end,
        comment: ext.comment || null,
    }
    if (props.dateFields.hasPlannedSent) payload.plannedSentDate = dates.start
    if (props.dateFields.hasPlannedReturned) payload.plannedReturnedDate = dates.end

    calendarError.value = ''
    try {
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/loaner/period/${changeInfo.event.id}`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify(payload),
            },
        )
        if (!result) {
            changeInfo.revert()
            return
        }
        const { response, data } = result
        if (!response.ok) throw new Error(validationError(data, `期間更新に失敗しました。（HTTP ${response.status}）`))
        changeInfo.event.setExtendedProp('plannedSentDate', dates.start)
        changeInfo.event.setExtendedProp('plannedReturnedDate', dates.end)
        if (String(changeInfo.event.id) === String(props.attached.id)) syncCurrentDates(data.attached)
        success.value = `貸出期間を更新しました。（${dates.start} 〜 ${dates.end}）`
    } catch (e) {
        changeInfo.revert()
        calendarError.value = e.message || '期間更新に失敗しました。'
    }
}

function onPanesResized({ panes } = {}) {
    if (panes?.length >= 2) {
        leftPaneSize.value = panes[0].size
        rightPaneSize.value = panes[1].size
    }
    nextTick(() => calendarRef.value?.getApi?.().updateSize())
}

function safeReturnUrl() {
    const raw = new URLSearchParams(window.location.search).get('returnUrl')
    if (raw) {
        try {
            const url = new URL(raw, window.location.origin)
            if (url.origin === window.location.origin) return url.href
        } catch {
            // fall through
        }
    }
    try {
        const stored = sessionStorage.getItem('sr_list_return_url')
        if (!stored) return null
        const url = new URL(stored, window.location.origin)
        return url.origin === window.location.origin ? url.href : null
    } catch {
        return null
    }
}

function listOrderTypeForRecord() {
    return props.record.order_type === 'waiting_list' ? 'waiting_list' : 'loaner'
}

function buildListReturnUrl(orderType) {
    const base = `${page.props.appBaseUrl}/servicerecord/administrator`
    try {
        const url = new URL(base, window.location.origin)
        url.searchParams.set('orderType', orderType)
        return url.href
    } catch {
        return `${base}?orderType=${encodeURIComponent(orderType)}`
    }
}

function returnToWaitingListList() {
    if (window.opener && !window.opener.closed) {
        window.close()
        return
    }

    const returnUrl = safeReturnUrl()
    if (returnUrl) {
        try {
            const url = new URL(returnUrl)
            if (/\/servicerecord\/(administrator|engineer)\/?$/.test(url.pathname) || url.pathname.includes('/servicerecord/administrator')) {
                url.searchParams.set('orderType', 'waiting_list')
                window.location.href = url.href
                return
            }
            window.location.href = url.href
            return
        } catch {
            // fall through
        }
    }

    window.location.href = buildListReturnUrl('waiting_list')
}

function openParentServiceDetail() {
    const parentId = String(form.parentID ?? '').trim()
    if (!parentId) return

    try {
        const url = new URL(`${page.props.appBaseUrl}/servicerecord/administrator`, window.location.origin)
        url.searchParams.set('orderType', 'service')
        url.searchParams.set('arrival', 'all')
        url.searchParams.set('openOrderID', parentId)
        window.open(url.href, '_blank', 'noopener,noreferrer')
    } catch {
        const base = String(page.props.appBaseUrl || '').replace(/\/?$/, '')
        window.open(
            `${base}/servicerecord/administrator?orderType=service&arrival=all&openOrderID=${encodeURIComponent(parentId)}`,
            '_blank',
            'noopener,noreferrer',
        )
    }
}

function closePage() {
    if (window.opener && !window.opener.closed) {
        window.close()
        return
    }

    const orderType = listOrderTypeForRecord()
    const returnUrl = safeReturnUrl()
    if (returnUrl) {
        try {
            const url = new URL(returnUrl)
            // administrator / engineer 一覧へ戻す場合は loaner / waiting_list フィルターを明示
            if (/\/servicerecord\/(administrator|engineer)\/?$/.test(url.pathname) || url.pathname.includes('/servicerecord/administrator')) {
                url.searchParams.set('orderType', orderType)
                window.location.href = url.href
                return
            }
            // servicerecord_q など呼び出し元へそのまま戻る
            try {
                sessionStorage.removeItem('sr_list_return_url')
            } catch {
                // ignore
            }
            window.location.href = url.href
            return
        } catch {
            // fall through
        }
    }

    window.location.href = buildListReturnUrl(orderType)
}

function updateCalendarSize() {
    calendarRef.value?.getApi?.().updateSize()
}

onMounted(() => window.addEventListener('resize', updateCalendarSize))
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateCalendarSize)
    revokeApplicationFormUrl()
})
</script>

<style scoped>
.loaner-detail-page,
.loaner-detail-page * {
    box-sizing: border-box;
}

.loaner-detail-page {
    width: 100%;
    max-width: 100vw;
    height: 100vh;
    height: 100dvh;
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    overflow: hidden;
    background: #e2e8f0;
    color: #1e293b;
    font-size: 12px;
    font-weight: 700;
}
.loaner-detail-page :is(input, select, textarea, button, option, th, td, label, span, strong, h1, h2, h3, h4, h5, p, a, div) {
    font-weight: 700;
}

.page-header {
    min-height: 36px;
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    background: #dbeafe;
}
.header-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    justify-content: flex-start;
}
.page-order-id { font-size: 14px; }
.page-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
}
.header-actions { min-width: 0; display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
.save-message { max-width: min(42vw, 520px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.save-message.success { color: #166534; }
.save-message.error { color: #b91c1c; }

.btn,
.select-btn {
    min-height: 28px;
    padding: 4px 14px;
    border: 1px solid transparent;
    border-radius: 3px;
    cursor: pointer;
    white-space: nowrap;
}
.btn:disabled { opacity: .6; cursor: wait; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-danger { background: #dc2626; color: #fff; }
a.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    box-sizing: border-box;
}
.btn-secondary { background: #64748b; color: #fff; }
.select-btn { padding: 2px 8px; border-color: #94a3b8; background: #fff; color: #334155; font-size: 11px; }

.outer-splitpanes { flex: 1; min-width: 0; min-height: 0; overflow: hidden; }
.main-pane { min-width: 0; min-height: 0; padding: 0 5px; overflow: hidden; }
.left-pane { width: 100%; height: 100%; min-width: 0; min-height: 0; display: flex; flex-direction: column; gap: 5px; overflow-x: hidden; overflow-y: auto; padding-right: 3px; }
.panel { min-width: 0; min-height: 0; border: 1px solid #94a3b8; background: #fff; padding: 7px; overflow: visible; }
.panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 5px; }
.panel-heading h2 { margin: 0; }
.delivery-heading {
    justify-content: flex-start;
}
.delivery-copy-btn {
    margin-left: 50px;
}
.panel h2 { margin: 0; font-size: 13px; }
.panel h3 { margin: 7px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #cbd5e1; font-size: 11px; color: #475569; }

.loaner-panel { flex: 0 0 auto; }
.people-row { flex: 0 0 auto; min-width: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 5px; }
.person-panel { height: max-content; }
.period-panel { flex: 0 0 auto; min-height: 45px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.period-panel h2 { margin-right: 5px; white-space: nowrap; }
.period-panel label { display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.period-panel input { width: 116px; }
.calendar-help, .file-help { color: #64748b; font-size: 10px; }
.calendar-error { margin: 0 0 3px; color: #b91c1c; font-size: 11px; }

.tab-panel { flex: 1 0 330px; min-height: 330px; display: flex; flex-direction: column; overflow: hidden; }
.tab-heading { flex-wrap: wrap; gap: 6px; }
.tab-buttons { display: flex; align-items: center; gap: 4px; }
.notes-tbc-count {
    margin-left: 100px;
    font-size: 12px;
    font-weight: 700;
    color: #dc2626;
    white-space: nowrap;
}
.tab-btn {
    min-height: 26px;
    padding: 3px 10px;
    border: 1px solid #94a3b8;
    border-radius: 3px 3px 0 0;
    background: #e2e8f0;
    color: #475569;
    font-size: 12px;
    cursor: pointer;
}
.tab-btn.active {
    border-bottom-color: #fff;
    background: #fff;
    color: #0f172a;
    font-weight: 600;
}
.notes-actions { display: flex; align-items: center; gap: 5px; margin-left: auto; }
.notes-shell,
.calendar-shell {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.notes-table-wrap { flex: 1; min-height: 0; overflow: auto; }
.notes-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}
.notes-table th,
.notes-table td {
    border-bottom: 1px solid #cbd5e1;
    padding: 5px 6px;
    text-align: left;
    vertical-align: top;
}
.notes-table th {
    position: sticky;
    top: 0;
    background: #f1f5f9;
    color: #475569;
    font-weight: 600;
    z-index: 1;
}
.notes-table .col-note-date { width: 88px; white-space: nowrap; }
.notes-table .col-note-author { width: 88px; white-space: nowrap; }
.notes-table .col-note-body { overflow-wrap: anywhere; }
.notes-table tbody tr { cursor: pointer; }
.notes-table tbody tr:hover { background: #f8fafc; }
.notes-table tbody tr.active-row,
.notes-table tbody tr.active-row td {
    color: #fff;
    background: #7e25eb !important;
}
.notes-table tbody tr.important-row:not(.active-row) td { background: #fef08a; }
.empty-notes { margin: 12px 4px; color: #64748b; font-size: 12px; }
.confirm-checkbox {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #475569;
    font-size: 12px;
}
.confirm-toggles {
    display: flex;
    gap: 8px;
    margin: 8px 0 0;
}
.toggle-btn {
    padding: 6px 12px;
    border: 1px solid #94a3b8;
    border-radius: 999px;
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.toggle-btn.on {
    background: #dc2626;
    border-color: #b91c1c;
    color: #fff;
}
.toggle-btn-done.on {
    background: #166534;
    border-color: #14532d;
    color: #fff;
}
.note-delete-preview {
    max-height: 120px;
    overflow: auto;
    padding: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 3px;
    background: #f8fafc;
    color: #334155;
    font-size: 12px;
    white-space: pre-wrap;
}

.compact-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 6px; }
.compact-grid label { min-width: 0; display: grid; grid-template-columns: 62px minmax(0, 1fr); align-items: center; gap: 4px; }
.compact-grid label > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #475569; }
.compact-grid input,
.compact-grid select,
.master-value,
.period-panel input {
    width: 100%;
    min-width: 0;
    height: 25px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}
.compact-grid input[readonly] { background: #f1f5f9; color: #64748b; }
.compact-grid .span-2 { grid-column: span 2; }
.master-value { cursor: pointer; text-align: left; }

.loaner-top-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
    gap: 8px 12px;
    align-items: stretch;
}
.loaner-identity-col,
.loaner-commerce-col {
    min-width: 0;
    display: grid;
    gap: 4px;
    padding: 6px;
    border: 1px solid #cbd5e1;
    background: #e2e8f0;
}
.loaner-commerce-col {
    grid-template-rows: auto auto auto auto 1fr;
}
.loaner-identity-col label {
    min-width: 0;
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    align-items: center;
    gap: 4px;
}
.loaner-identity-col label.parent-id-field {
    align-items: start;
}
.loaner-identity-col label.parent-id-field > span {
    padding-top: 5px;
}
.loaner-identity-col label > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #334155;
    font-size: 11px;
}
.loaner-identity-col input,
.loaner-identity-col .master-value {
    width: 100%;
    min-width: 0;
    height: 26px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}
.loaner-identity-col .parent-id-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    flex: 1 1 auto;
}
.loaner-identity-col .parent-id-controls {
    display: flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
}
.loaner-identity-col .parent-id-controls input {
    flex: 1 1 auto;
}
.loaner-identity-col .parent-id-open-btn {
    flex: 0 0 auto;
    height: 26px;
    padding: 0 8px;
    font-size: 11px;
    line-height: 1;
    white-space: nowrap;
}
.loaner-identity-col .parent-id-open-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.loaner-identity-col .parent-id-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 4px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 2px;
    background: #f8fafc;
    font-size: 11px;
    line-height: 1.35;
    color: #334155;
}
.loaner-identity-col .parent-id-meta-row {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    gap: 6px;
    align-items: baseline;
}
.loaner-identity-col .parent-id-meta-label {
    color: #64748b;
    white-space: nowrap;
}
.loaner-identity-col .parent-id-meta-value {
    min-width: 0;
    overflow-wrap: anywhere;
}
.loaner-identity-col input[readonly] {
    background: #f8fafc;
    color: #64748b;
}
.commerce-row {
    min-width: 0;
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) 36px minmax(0, 0.9fr);
    align-items: center;
    gap: 4px;
}
.commerce-row-po {
    grid-template-columns: 72px minmax(0, 1fr);
}
.commerce-label,
.commerce-date-label {
    color: #334155;
    font-size: 11px;
    white-space: nowrap;
}
.commerce-num,
.commerce-date,
.commerce-po {
    width: 100%;
    min-width: 0;
    height: 26px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}
.shipping-date-btn {
    text-align: left;
    cursor: pointer;
}

.status-action-row {
    margin-top: 8px;
    display: grid;
    grid-template-columns: minmax(140px, 1.1fr) auto minmax(160px, 1.2fr) minmax(140px, 1fr);
    gap: 8px;
    align-items: stretch;
}
.status-action-waiting {
    grid-template-columns: minmax(140px, 1fr) minmax(160px, 1fr);
}
.status-current-box,
.status-select-box,
.labor-box {
    min-width: 0;
    display: grid;
    gap: 3px;
    padding: 4px 6px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #f8fafc;
}
.status-box-label,
.status-select-box > span,
.labor-box > span {
    color: #475569;
    font-size: 10px;
    line-height: 1.2;
}
.status-current-value {
    display: block;
    min-height: 28px;
    padding: 4px 2px;
    color: #0f172a;
    font-size: 13px;
    line-height: 1.3;
}
.status-select-box select,
.labor-box select,
.labor-box input {
    width: 100%;
    min-width: 0;
    height: 28px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}
.labor-box input[readonly] {
    background: #f1f5f9;
    color: #64748b;
}
.status-next-btn {
    min-height: 56px;
    min-width: 140px;
    padding: 10px 22px;
    font-size: 16px;
    font-weight: 700;
    border-radius: 4px;
    align-self: center;
}
.application-form-issue-btn {
    width: 100%;
    min-height: 40px;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 4px;
    white-space: nowrap;
}
.commerce-application-form-slot {
    display: flex;
    align-items: flex-end;
    justify-content: stretch;
    min-height: 48px;
    margin-top: auto;
    padding-top: 6px;
}
.labor-required-field span { color: #b45309; font-weight: 700; }
.labor-required-field select { border-color: #f59e0b; background: #fffbeb; }

.person-stack {
    display: grid;
    gap: 4px;
}
.person-stack > label {
    min-width: 0;
    display: grid;
    grid-template-columns: 96px minmax(0, 1fr);
    align-items: center;
    gap: 4px;
}
.person-stack label > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #475569;
    font-size: 11px;
}
.person-stack input {
    width: 100%;
    min-width: 0;
    height: 25px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}
.person-stack .zip-row {
    grid-template-columns: 28px 100px;
}
.person-stack .zip-mark {
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
}
.person-stack .zip-input {
    width: 100px;
    max-width: 100px;
}
.address-pair {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 6px;
    padding-left: 28px; /* 〒列分を空けて zipcode と左端を揃える */
}
.address-pair .address1-input {
    width: 100px;
    flex: 0 0 100px;
    min-width: 0;
    height: 25px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}
.address-pair .address2-input {
    flex: 1 1 160px;
    min-width: 0;
    height: 25px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}

.price-adjust-row {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 16px;
    padding: 6px 10px;
    border: 1px solid #94a3b8;
    background: #e2e8f0;
}
.price-adjust-main,
.price-adjust-actions,
.price-adjust-delta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.price-adjust-label { color: #475569; font-size: 13px; font-weight: bold; white-space: nowrap; }
.price-adjust-value { font-size: 13px; color: #0f172a; }
.price-adjust-btn { min-height: 24px; padding: 2px 10px; font-size: 11px; }
.price-adjust-delta strong { font-size: 12px; color: #0f172a; }
.price-adjust-enduser-sn {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.price-adjust-enduser-sn input {
    width: 140px;
    min-width: 0;
    height: 25px;
    padding: 2px 5px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #fff;
    color: #1e293b;
    font-size: 11px;
}

.files-panel { width: 100%; height: 100%; display: flex; flex-direction: column; overflow: hidden; }
.file-actions { display: flex; align-items: center; gap: 5px; }
.file-input { display: none; }
.add-btn { border-color: #2563eb; background: #2563eb; color: #fff; }
.delete-btn { border-color: #dc2626; background: #dc2626; color: #fff; }
.select-btn:disabled { opacity: .55; cursor: not-allowed; }
.file-dropzone {
    flex: 0 0 auto;
    margin-bottom: 7px;
    padding: 13px 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    border: 2px dashed #94a3b8;
    border-radius: 5px;
    background: #f8fafc;
    color: #475569;
    text-align: center;
    cursor: pointer;
}
.file-dropzone strong { color: #1e293b; }
.file-dropzone span { font-size: 10px; }
.file-dropzone.active { border-color: #2563eb; background: #dbeafe; }
.file-dropzone.disabled { opacity: .6; cursor: wait; }
.file-error { margin: 0 0 6px; color: #b91c1c; font-size: 11px; }
.files-list { flex: 1; min-height: 0; overflow: auto; padding-right: 3px; }
.empty { margin: 12px; color: #64748b; }

.confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 400;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, .5);
}
.confirm-panel { width: min(420px, 100%); padding: 16px; border-radius: 7px; background: #fff; box-shadow: 0 12px 32px rgba(15, 23, 42, .25); }
.application-form-overlay {
    padding: 0;
    align-items: stretch;
    justify-content: center;
}
.application-form-panel {
    /* 高さ最大。幅はプレビュー領域に A4 縦をスクロール無しで収める幅 + 200px */
    --af-chrome: 56px;
    height: 100vh;
    max-height: 100vh;
    width: calc((100vh - var(--af-chrome)) * 210 / 297 + 200px);
    max-width: 100vw;
    margin: 0;
    border-radius: 0;
    padding: 0 0 8px;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}
.application-form-panel .application-form-header {
    margin: 0;
    flex: 0 0 auto;
    padding: 8px 12px;
    gap: 12px;
}
.application-form-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}
.application-form-body {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 0;
    padding: 0 12px;
    overflow: hidden;
}
.application-form-viewport {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #1e293b;
    border-radius: 4px;
}
.application-form-image {
    display: block;
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
}
.application-form-charge-fieldset {
    margin: 0 0 12px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}
.application-form-charge-fieldset legend {
    padding: 0 6px;
    font-size: 13px;
    font-weight: bold;
    color: #334155;
}
.application-form-charge-option {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 6px 0;
    font-size: 13px;
    color: #0f172a;
}
.confirm-panel h3 { margin: 0 0 10px; font-size: 15px; }
.confirm-panel p { overflow-wrap: anywhere; }
.confirm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin: -16px -16px 0;
    padding: 10px 12px;
    border-bottom: 1px solid #cbd5e1;
}
.confirm-header h3 { margin: 0; }
.close-btn {
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
}
.confirm-body { padding: 12px 0; display: grid; gap: 8px; }
.confirm-current-price { margin: 0; color: #334155; font-size: 12px; }
.confirm-field { display: grid; gap: 4px; color: #475569; font-size: 12px; }
.confirm-input,
.confirm-textarea {
    width: 100%;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    padding: 6px 8px;
    font-size: 12px;
}
.confirm-error { margin: 0; color: #b91c1c; font-size: 12px; }
.confirm-actions { display: flex; justify-content: flex-end; gap: 7px; margin-top: 14px; }

.promotion-panel { width: min(760px, 100%); }
.promotion-lead { margin: 0 0 12px; color: #334155; font-size: 13px; line-height: 1.45; }
.promotion-table-wrap { max-height: min(50vh, 360px); overflow: auto; border: 1px solid #cbd5e1; border-radius: 6px; }
.promotion-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.promotion-table th,
.promotion-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
.promotion-table th { position: sticky; top: 0; background: #f8fafc; color: #475569; font-weight: 600; }
.promotion-table tbody tr:last-child td { border-bottom: none; }
.promotion-open-btn { min-height: 26px; padding: 2px 10px; font-size: 11px; }
.promotion-empty { margin: 0; padding: 16px; color: #64748b; font-size: 13px; text-align: center; }

.promote-banner {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin: 0 0 10px;
    padding: 12px 14px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #f8fafc;
}
.promote-banner.is-ready {
    border-color: #f59e0b;
    background: #fffbeb;
}
.promote-banner-text {
    display: grid;
    gap: 4px;
    min-width: 0;
}
.promote-banner-text strong {
    color: #92400e;
    font-size: 13px;
}
.promote-banner-text p {
    margin: 0;
    color: #475569;
    font-size: 12px;
    line-height: 1.45;
}
.promote-source { color: #64748b !important; }
.promote-banner-actions {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    flex-shrink: 0;
}
.promote-unit-select {
    display: grid;
    gap: 4px;
    color: #475569;
    font-size: 11px;
}
.promote-unit-select select {
    min-width: 220px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    padding: 6px 8px;
    font-size: 12px;
    background: #fff;
}

:deep(.splitpanes__splitter) { width: 7px !important; border-left: 1px solid #64748b; border-right: 1px solid #64748b; background: #cbd5e1 !important; }
:deep(.splitpanes__splitter:hover) { background: #60a5fa !important; }
:deep(.fc) { font-size: 10px; }
:deep(.fc .fc-toolbar) { margin-bottom: 3px; gap: 3px; }
:deep(.fc .fc-toolbar-title) { font-size: 13px; }
:deep(.fc .fc-button) { padding: 2px 5px; font-size: 10px; }
:deep(.fc .fc-daygrid-day-number) { padding: 1px 3px; }
:deep(.fc .fc-event) { cursor: move; }
:deep(.file-item) { margin-bottom: 7px; }

@media (max-width: 1050px) {
    .loaner-top-layout { grid-template-columns: 1fr; }
    .status-action-row {
        grid-template-columns: minmax(0, 1fr) auto;
    }
    .status-select-box,
    .labor-box {
        grid-column: 1 / -1;
    }
    .address-pair { grid-template-columns: 1fr; }
}

@media (max-height: 760px) {
    .tab-panel { flex-basis: 300px; min-height: 300px; }
}

@media (max-width: 720px) {
    .loaner-detail-page { padding: 3px; }
    .main-pane { padding: 0 2px; }
    .page-header {
        flex-wrap: wrap;
        align-items: flex-start;
    }
    .header-title-group {
        flex-wrap: wrap;
    }
    .header-actions {
        width: 100%;
        justify-content: flex-start;
        gap: 3px;
    }
    .btn { padding-inline: 8px; }
    .save-message { display: none; }
    .people-row { grid-template-columns: 1fr; }
    .period-panel h2, .period-panel label span { display: none; }
    .status-action-row,
    .status-action-waiting {
        grid-template-columns: 1fr;
    }
    .status-next-btn { width: 100%; }
}
</style>

<style>
html,
body,
#app {
    max-width: 100vw;
    overflow: hidden;
}
</style>
