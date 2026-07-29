<template>
    <div class="detail-form">
        <!-- <div class="detail-form-header">
            <h2>詳細フォーム A</h2>
            <button type="button" class="reset-layout-btn" @click="resetPaneSizes">デフォルト比率に戻す</button>
        </div> -->

        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <Splitpanes v-else class="default-theme detail-splitpanes" @resized="syncOuterPaneSizes">
            <Pane class="detail-pane detail-pane-left" :size="leftPaneSize" :min-size="35">
                <Splitpanes class="default-theme detail-splitpanes detail-splitpanes-left" horizontal @resized="syncLeftPaneSizes">
                    <Pane class="detail-pane detail-pane-left-top" :size="leftTopPaneSize" :min-size="20">
                        <div class="pane-content pane-content-scroll">
                            <div class="detail-top-grid">
                                <section class="section-card detail-card">
                                    <dl class="info-grid compact-info-grid">
                                        <dt>受領日</dt>
                                        <dd>
                                            <input
                                                type="date"
                                                class="field-input"
                                                :value="toDateInputValue(draftRecord?.receivedDate ?? record?.receivedDate)"
                                                @input="updateDraftDateValue('receivedDate', $event.target.value)"
                                            >
                                        </dd>
                                        <dt>status</dt>
                                        <dd>
                                            <template v-if="isWaitingListRecord">
                                                <span class="status-empty">—（waiting_list）</span>
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
                                                    :key="status.processID"
                                                    :value="status.processID"
                                                >
                                                    {{ status.status }} ({{ status.processID }})
                                                </option>
                                            </select>
                                        </dd>
                                        <dt>製品名</dt>
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
                                        <dt>価格</dt>
                                        <dd>
                                                <input type="text" :value="draftRecord?.price ?? record?.price ?? ''" @input="updateDraftValue('price', $event.target.value)">
                                        </dd>
                                    </dl>
                                </section>

                                <section class="section-card detail-card">
                                    <dl class="info-grid compact-info-grid">
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
                                        <dt>見積番号</dt>
                                        <dd class="dd-inline-fields">
                                            <input type="text" class="field-input" :value="draftRecord?.quoteNum ?? record?.quoteNum ?? ''" @input="updateDraftValue('quoteNum', $event.target.value)">
                                            <input
                                                type="date"
                                                class="field-input field-date"
                                                title="quoteDate"
                                                :value="toDateInputValue(draftRecord?.quoteDate ?? record?.quoteDate)"
                                                @input="updateDraftDateValue('quoteDate', $event.target.value)"
                                            >
                                        </dd>
                                        <dt>受注番号</dt>
                                        <dd class="dd-inline-fields">
                                            <input type="text" class="field-input" :value="draftRecord?.orderNum ?? record?.orderNum ?? ''" @input="updateDraftValue('orderNum', $event.target.value)">
                                            <input
                                                type="date"
                                                class="field-input field-date"
                                                title="orderDate"
                                                :value="toDateInputValue(draftRecord?.orderDate ?? record?.orderDate)"
                                                @input="updateDraftDateValue('orderDate', $event.target.value)"
                                            >
                                        </dd>
                                        <dt>発注番号</dt>
                                        <dd>
                                            <input type="text" class="field-input" :value="draftRecord?.poNum ?? record?.poNum ?? ''" @input="updateDraftValue('poNum', $event.target.value)">
                                        </dd>
                                    </dl>
                                </section>

                                <section class="section-card detail-card detail-card-input">
                                    <div class="input-grid">
                                        <label class="input-field">
                                            <span>price</span>
                                            <input type="text" :value="draftRecord?.price ?? record?.price ?? ''" @input="updateDraftValue('price', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <span>a2la</span>
                                            <input type="text" :value="draftRecord?.a2la ?? record?.a2la ?? ''" @input="updateDraftValue('a2la', $event.target.value)">
                                        </label>
                                        <label class="input-field">
                                            <span>sentOut</span>
                                            <input type="text" :value="draftRecord?.sentOut ?? record?.sentOut ?? ''" @input="updateDraftValue('sentOut', $event.target.value)">
                                        </label>
                                        <label class="input-field input-field-span2">
                                            <span>rmaNumOverSea</span>
                                            <input type="text" :value="draftRecord?.rmaNumOverSea ?? record?.rmaNumOverSea ?? ''" @input="updateDraftValue('rmaNumOverSea', $event.target.value)">
                                        </label>
                                    </div>
                                </section>

                            </div>

                            <div class="detail-bottom-grid">
                                <section class="section-card detail-card detail-card-input">
                                    <div class="section-header">
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
                                                <input type="text" placeholder="〒" :value="draftRecord?.zipcode ?? record?.zipcode ?? ''" @input="updateDraftValue('zipcode', $event.target.value)">
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
                                    <h3>E/U</H3>
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
                                                <input type="text" placeholder="〒" :value="draftRecord?.endUser_zipcode ?? record?.endUser_zipcode ?? ''" @input="updateDraftValue('endUser_zipcode', $event.target.value)">
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
                                    <h3>発送先</H3>
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
                                                <input type="text" placeholder="〒" :value="draftRecord?.deliveryDestination_zipcode ?? record?.deliveryDestination_zipcode ?? ''" @input="updateDraftValue('deliveryDestination_zipcode', $event.target.value)">
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

                    <Pane class="detail-pane detail-pane-left-bottom" :size="leftBottomPaneSize" :min-size="30">
                        <Splitpanes class="default-theme detail-splitpanes detail-splitpanes-bottom" @resized="syncBottomPaneSizes">
                            <Pane class="detail-pane detail-pane-notes" :size="notesPaneSize" :min-size="25">
                                <div class="pane-content pane-content-scroll">
                                    <section class="section-card section-card-compact section-card-fill">
                                        <div class="section-header">
                                            <h3>Notes（{{ notes.length }}件）</h3>
                                            <div class="section-actions">
                                                <button type="button" class="action-btn" :disabled="!canModifySelectedNote" :title="noteEditDeleteTitle" @click="openNoteEdit">編集</button>
                                                <button type="button" class="action-btn action-btn-danger" :disabled="!canModifySelectedNote" :title="noteEditDeleteTitle" @click="openNoteDelete">削除</button>
                                                <button type="button" class="action-btn action-btn-primary" @click="openNoteCreate">新規追加</button>
                                            </div>
                                        </div>
                                        <div v-if="notes.length" class="attachment-table-wrap">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <th>日時</th>
                                                        <th>記入者</th>
                                                        <th>内容</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="note in notes"
                                                        :key="note.id"
                                                        class="table-row"
                                                        :class="{ 'important-row': note.important, 'active-row': selectedNoteId === note.id }"
                                                        @click="selectedNoteId = note.id"
                                                    >
                                                        <td>{{ formatDate(note.whenWrote) }}</td>
                                                        <td>{{ note.whoWrote || '—' }}</td>
                                                        <td class="text-cell">{{ note.note || '—' }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p v-else class="empty-message">Notes がありません。</p>
                                    </section>
                                </div>
                            </Pane>

                            <Pane class="detail-pane detail-pane-parts" :size="partsPaneSize" :min-size="25">
                                <div class="pane-content pane-content-scroll">
                                    <section class="section-card section-card-compact section-card-fill">
                                        <div class="section-header">
                                            <h3>Parts（{{ parts.length }}件）</h3>
                                            <div class="section-actions">
                                                <span class="parts-total-inline">合計 {{ formatPrice(partsPriceTotal) }}</span>
                                                <button type="button" class="action-btn action-btn-danger" :disabled="!selectedPartId" :title="selectedPartId ? '' : '部品を選択してください'" @click="openPartDelete">削除</button>
                                                <button type="button" class="action-btn action-btn-primary" @click="openPartCreate">新規追加</button>
                                            </div>
                                        </div>
                                        <div v-if="parts.length" class="attachment-table-wrap">
                                            <table class="data-table">
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
                                                        <td>{{ formatPrice(part.part_master?.price_discounted) }}</td>
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
            </Pane>

            <Pane class="detail-pane detail-pane-files" :size="rightPaneSize" :min-size="28">
                <div class="pane-content">
                    <section class="section-card section-card-files">
                        <div class="section-header">
                            <h3>Files（{{ files.length }}件）</h3>
                            <div class="section-actions">
                                <button type="button" class="action-btn action-btn-danger" :disabled="!selectedFileId" :title="selectedFileId ? '' : 'ファイルを選択してください'" @click="openFileDelete">削除</button>
                                <button type="button" class="action-btn action-btn-primary" @click="openFileCreate">新規追加</button>
                            </div>
                        </div>

                        <div v-if="files.length" class="files-list-wrap">
                            <AttachedFileItem
                                v-for="file in files"
                                :key="file.id"
                                :file="file"
                                :selected="selectedFileId === file.id"
                                @select="selectedFileId = file.id"
                            />
                        </div>
                        <p v-else class="empty-message">Files がありません。</p>
                    </section>
                </div>
            </Pane>
        </Splitpanes>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'

const page = usePage()

const props = defineProps({
    record: Object,
    draftRecord: Object,
    notes: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    parts: { type: Array, default: () => [] },
    attachmentsLoading: { type: Boolean, default: false },
    attachmentsError: { type: String, default: '' },
})

const emit = defineEmits(['open-dialog'])

const leftPaneSize = ref(64)
const rightPaneSize = ref(36)
const leftTopPaneSize = ref(70)
const leftBottomPaneSize = ref(30)
const notesPaneSize = ref(70)
const partsPaneSize = ref(30)
const selectedNoteId = ref(null)
const selectedPartId = ref(null)
const selectedFileId = ref(null)

const authUserName = computed(() => page.props.authUser?.kanji_name ?? '')
const selectedNote = computed(() => props.notes.find(n => n.id === selectedNoteId.value))

const recordOrderType = computed(() =>
    props.draftRecord?.order_type ?? props.record?.order_type ?? null,
)
const isLoanerRecord = computed(() => recordOrderType.value === 'loaner')
const isWaitingListRecord = computed(() => recordOrderType.value === 'waiting_list')
const statusOptions = computed(() => {
    if (isLoanerRecord.value) {
        return page.props.statusesLoaner ?? []
    }
    return page.props.statuses ?? []
})

function isNoteOwner(note) {
    return note?.whoWrote === authUserName.value
}

const canModifySelectedNote = computed(() => !!selectedNote.value && isNoteOwner(selectedNote.value))

const noteEditDeleteTitle = computed(() => {
    if (!selectedNoteId.value) return 'Note を選択してください'
    if (!canModifySelectedNote.value) return '自分が書いた Note のみ編集・削除できます'
    return ''
})

const displayEntityId = computed(() => {
    const entityId = props.draftRecord?.entityID ?? props.record?.entityID
    if (entityId != null && entityId !== '') {
        return entityId
    }

    const serviceId = props.draftRecord?.serviceID ?? props.record?.serviceID
    const service = page.props.servicesMaster?.find(item => String(item.serviceID) === String(serviceId))
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
    leftTopPaneSize.value = 70
    leftBottomPaneSize.value = 30
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

watch(() => props.notes, (newNotes) => {
    if (selectedNoteId.value && !newNotes.some(n => n.id === selectedNoteId.value)) {
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
    if (!note || !isNoteOwner(note)) return
    emit('open-dialog', 'NOTE', { mode: 'edit', note })
}

function openNoteCreate() {
    emit('open-dialog', 'NOTE', { mode: 'create' })
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

function updateDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value
}

function updateNumericDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value === '' ? null : Number(value)
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
    if (!note || !isNoteOwner(note)) return
    emit('open-dialog', 'D', { action: 'delete-note', note, noteId: note.id })
}

const selectedFile = computed(() => props.files.find(f => f.id === selectedFileId.value))
const selectedPart = computed(() => props.parts.find(p => p.id === selectedPartId.value))

const partsPriceTotal = computed(() =>
    props.parts.reduce((sum, part) => {
        const value = Number(part.part_master?.price_discounted)
        return sum + (Number.isNaN(value) ? 0 : value)
    }, 0),
)

function openFileCreate() {
    emit('open-dialog', 'FILE', { mode: 'create' })
}

function openFileDelete() {
    const file = selectedFile.value
    if (!file) return
    emit('open-dialog', 'D', { action: 'delete-file', file, fileId: file.id })
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
</script>

<style scoped>
.detail-form {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
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
    font-size: 13px;
    cursor: pointer;
}

.detail-splitpanes {
    flex: 1;
    min-height: 0;
}

.detail-splitpanes-left {
    min-height: 0;
}

.detail-splitpanes-bottom {
    min-height: 0;
    height: 100%;
    width: 100%;
}

.detail-pane {
    min-width: 0;
    min-height: 0;
    display: flex;
}

.detail-pane-left {
    flex-direction: column;
}

.detail-pane-left-top,
.detail-pane-left-bottom,
.detail-pane-notes,
.detail-pane-parts {
    min-height: 0;
}

.detail-pane-notes,
.detail-pane-parts {
    min-width: 0;
}

.detail-pane-left-top {
    font-size: 13px;
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
.detail-pane-left-top .entity-id-value {
    font-size: 13px;
}

.detail-pane-left-top .info-grid dd input:not(.field-input) {
    font-size: 13px;
}

.pane-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: 100%;
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow: hidden;
    box-sizing: border-box;
    padding-right: 4px;
}

.pane-content-scroll {
    overflow: auto;
}

.section-card {
    padding: 16px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}

.detail-top-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    align-items: start;
}

.detail-bottom-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    align-items: start;
    margin-top: 12px;
}

.detail-card {
    min-height: 0;
}

.detail-card-wide {
    grid-column: 1 / -1;
}

.section-card h3 {
    margin: 0 0 12px;
    font-size: 16px;
    color: #1e293b;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
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
    font-size: 13px;
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

.detail-top-grid {
    /* 例: ①広め / ②狭め / ⑦⑧は標準 */
    grid-template-columns: 1.4fr 1.1fr 0.9fr ;
}

.compact-info-grid {
    grid-template-columns: 80px 1fr;
    gap: 6px 12px;
}


.info-grid dt {
    font-weight: bold;
    color: #475569;
}

.info-grid dd {
    min-width: 0;
}

.info-grid dd .field-input {
    width: 100%;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    background: #fff;
    color: #1e293b;
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

.dd-inline-fields .field-date {
    flex: 0 0 140px;
    width: 140px;
}

.dd-product-name {
    display: flex;
    gap: 8px;
    align-items: center;
}

.dd-product-name .field-button {
    flex: 1 1 auto;
    min-width: 0;
}

.entity-id-display {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 0 0 auto;
    min-width: 72px;
    font-size: 12px;
    color: #475569;
}

.entity-id-label {
    font-weight: 600;
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
    gap: 10px 12px;
}

.input-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
    color: #475569;
}

.input-field input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    color: #1e293b;
    background: white;
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
}

.status-empty {
    color: #64748b;
    font-size: 13px;
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
    font-size: 13px;
}

.section-card-compact .section-header {
    margin-bottom: 8px;
}

.section-card-compact .data-table th,
.section-card-compact .data-table td {
    font-size: 12px;
    padding: 4px 6px;
}

.section-card-compact .empty-message {
    font-size: 12px;
}

.section-card-compact .action-btn {
    font-size: 12px;
    padding: 4px 10px;
}

.parts-total-inline {
    font-size: 12px;
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

.section-card-fill {
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
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

:deep(.splitpanes__splitter) {
    background: #cbd5e1;
}

:deep(.splitpanes__splitter:hover) {
    background: #94a3b8;
}
</style>
