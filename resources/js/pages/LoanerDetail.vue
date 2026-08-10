<template>
    <div class="loaner-detail-page">
        <header class="page-header">
            <strong>OrderID: {{ record.orderID }}</strong>
            <div class="header-actions">
                <span v-if="success" class="save-message success">{{ success }}</span>
                <span v-if="error" class="save-message error">{{ error }}</span>
                <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
                    {{ saving ? '保存中...' : '保存' }}
                </button>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="closePage">閉じる</button>
            </div>
        </header>

        <Splitpanes class="default-theme outer-splitpanes" @resized="onPanesResized">
            <Pane :size="leftPaneSize" :min-size="32" class="main-pane">
                <div class="left-pane">
                    <section class="panel loaner-panel">
                        <div class="panel-heading">
                            <h2>貸出情報</h2>
                            <button type="button" class="select-btn" @click="activeSelectKind = 'loanerUnit'">
                                貸出機を選択
                            </button>
                        </div>
                        <div class="loaner-info-layout">
                            <div class="loaner-id-col">
                                <label><span>親OrderID</span><input v-model="form.parentID" type="number"></label>
                                <label><span>loanerID</span><input :value="form.loanerID" type="text" readonly></label>
                                <label><span>管理番号</span><input :value="selectedUnit?.manageNum || loanerMaster?.manageNum || ''" type="text" readonly></label>
                            </div>

                            <div class="loaner-detail-col">
                                <label>
                                    <span>製品名</span>
                                    <button type="button" class="master-value" @click="activeSelectKind = 'loanerUnit'">
                                        {{ form.productName || '選択してください' }}
                                    </button>
                                </label>
                                <div class="detail-pair">
                                    <label><span>品目</span><input :value="selectedUnit?.item || loanerMaster?.item || ''" type="text" readonly></label>
                                    <label><span>S/N</span><input v-model="form.SN" type="text"></label>
                                </div>
                                <div class="detail-pair">
                                    <label><span>グループ</span><input :value="selectedUnit?.groupName || loanerMaster?.groupName || ''" type="text" readonly></label>
                                    <label><span>案件種別</span><input :value="record.order_type || ''" type="text" readonly></label>
                                </div>
                                <div class="detail-pair">
                                    <label><span>割当状態</span><input v-model="form.assignStatus" type="text"></label>
                                    <label v-if="record.order_type === 'loaner'">
                                        <span>status</span>
                                        <select v-model="form.status">
                                            <option value="">選択してください</option>
                                            <option v-for="status in statuses" :key="status.processID_new" :value="String(status.processID_new)">
                                                {{ status.status }} ({{ status.processID_new }})
                                            </option>
                                        </select>
                                    </label>
                                    <label v-else class="detail-spacer" aria-hidden="true"><span></span><span></span></label>
                                </div>
                            </div>

                            <div class="loaner-commerce-col">
                                <label>
                                    <span>見積 #</span>
                                    <div class="num-date-pair">
                                        <input v-model="form.quoteNum" type="text">
                                        <input v-model="form.quoteDate" type="date" title="見積日">
                                    </div>
                                </label>
                                <label>
                                    <span>受注 #</span>
                                    <div class="num-date-pair">
                                        <input v-model="form.orderNum" type="text">
                                        <input v-model="form.orderDate" type="date" title="受注日">
                                    </div>
                                </label>
                                <label>
                                    <span>注文 #</span>
                                    <input v-model="form.poNum" type="text">
                                </label>
                            </div>
                        </div>

                        <div class="price-adjust-row">
                            <div class="price-adjust-main">
                                <span class="price-adjust-label">価格</span>
                                <strong class="price-adjust-value">{{ formatPrice(displayPrice) }}</strong>
                            </div>
                            <div class="price-adjust-actions">
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
                            </div>
                        </div>
                    </section>

                    <div class="people-row">
                        <section class="panel person-panel">
                            <div class="panel-heading">
                                <h2>依頼者</h2>
                                <button type="button" class="select-btn" @click="activeSelectKind = 'dealer'">マスター選択</button>
                            </div>
                            <div class="compact-grid">
                                <label><span>会社名</span><input v-model="form.dealer" type="text"></label>
                                <label><span>部署名</span><input v-model="form.dealer_depart" type="text"></label>
                                <label><span>担当者</span><input v-model="form.contactPerson" type="text"></label>
                                <label><span>電話</span><input v-model="form.phone" type="text"></label>
                                <label><span>E-mail</span><input v-model="form.email" type="text"></label>
                                <label><span>FAX</span><input v-model="form.fax" type="text"></label>
                                <label><span>郵便番号</span><input v-model="form.zipcode" type="text"></label>
                                <label><span>住所1</span><input v-model="form.address1" type="text"></label>
                                <label class="span-2"><span>住所2</span><input v-model="form.address2" type="text"></label>
                            </div>
                        </section>

                        <section class="panel person-panel">
                            <div class="panel-heading">
                                <h2>発送先</h2>
                            </div>
                            <div class="compact-grid">
                                <label><span>会社名</span><input v-model="form.deliveryDestination_company" type="text"></label>
                                <label><span>部署名</span><input v-model="form.deliveryDestination_depart" type="text"></label>
                                <label><span>担当者</span><input v-model="form.deliveryDestination_contactPerson" type="text"></label>
                                <label><span>電話</span><input v-model="form.deliveryDestination_phone" type="text"></label>
                                <label><span>E-mail</span><input v-model="form.deliveryDestination_email" type="text"></label>
                                <label><span>郵便番号</span><input v-model="form.deliveryDestination_zipcode" type="text"></label>
                                <label><span>住所1</span><input v-model="form.deliveryDestination_address1" type="text"></label>
                                <label class="span-2"><span>住所2</span><input v-model="form.deliveryDestination_address2" type="text"></label>
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
                            <div v-if="sharedNotes.length" class="notes-table-wrap">
                                <table class="notes-table">
                                    <thead>
                                        <tr>
                                            <th class="col-note-date">日時</th>
                                            <th class="col-note-author">記入者</th>
                                            <th class="col-note-body">内容</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="note in sharedNotes"
                                            :key="note.id"
                                            :class="{
                                                'important-row': note.important,
                                                'active-row': Number(selectedNoteId) === Number(note.id),
                                            }"
                                            @click="selectedNoteId = note.id"
                                        >
                                            <td class="col-note-date">{{ formatNoteDate(note.whenWrote) }}</td>
                                            <td class="col-note-author">{{ note.whoWrote || '—' }}</td>
                                            <td
                                                class="col-note-body"
                                                @click.stop="selectedNoteId = note.id"
                                                v-html="linkifyNote(note.note)"
                                            />
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="empty-notes">Notes がありません。</p>
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
                    <div v-if="fileItems.length" class="files-list">
                        <AttachedFileItem
                            v-for="file in fileItems"
                            :key="file.id"
                            :file="file"
                            :order-id="record.orderID"
                            :file-base-url="`${page.props.appBaseUrl}/servicerecord/files`"
                            :selected="selectedFileId === file.id"
                            :can-move-up="false"
                            :can-move-down="false"
                            :sorting="false"
                            @select="selectedFileId = file.id"
                        />
                    </div>
                    <p v-else class="empty">関連ファイルはありません。</p>
                </section>
            </Pane>
        </Splitpanes>

        <IntakeMasterSelectDialog
            v-if="activeSelectKind"
            :kind="activeSelectKind"
            :items="activeSelectItems"
            :initial-value="activeSelectInitialValue"
            @close="activeSelectKind = null"
            @selected="onMasterSelected"
        />

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
                            placeholder="例: 5000（表示は 元価格 - 調整額）"
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
                    status が「在庫有り」になったため、同機種（{{ record.productName }}）の waiting_list に繰り上がり候補があります。
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
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import IntakeMasterSelectDialog from '@/components/ServiceRecord/Intake/IntakeMasterSelectDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { linkifyText } from '@/utils/linkifyText'
import { pickMasterVersion, PAID_LOANER_RETURN_CODES } from '@/utils/resolveServiceWorkPrice'

const props = defineProps({
    attached: { type: Object, required: true },
    record: { type: Object, required: true },
    parentReturnCode: { type: [Number, String], default: null },
    loanerMaster: { type: Object, default: null },
    files: { type: Array, default: () => [] },
    notes: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    dealersMaster: { type: Array, default: () => [] },
    loanerUnits: { type: Array, default: () => [] },
    dateFields: { type: Object, required: true },
})

const page = usePage()
const saving = ref(false)
const error = ref('')
const success = ref('')
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
const fileBusy = computed(() => uploading.value || deleting.value)
const parentReturnCode = ref(props.parentReturnCode)
const showPriceAdjustDialog = ref(false)
const priceAdjustSaving = ref(false)
const priceAdjustError = ref('')
const priceAdjustForm = reactive({
    amount: '',
    reason: '',
})
const showNoteDialog = ref(false)
const noteDialogMode = ref('create')
const noteSaving = ref(false)
const noteDeleting = ref(false)
const noteDialogError = ref('')
const promotionModalOpen = ref(false)
const promotionCandidates = ref([])
const noteForm = reactive({
    note: '',
    important: false,
})
const editingNoteId = ref(null)

const authUserName = computed(() => String(page.props.auth?.user?.kanji_name ?? '').trim())
const sharedNotes = computed(() =>
    noteItems.value.filter(note => !(note?.personal === true || note?.personal === 1 || note?.personal === '1')),
)
const selectedNote = computed(() =>
    sharedNotes.value.find(note => Number(note.id) === Number(selectedNoteId.value)) || null,
)
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
    productName: stringValue(props.record.productName ?? props.loanerMaster?.productName),
    SN: stringValue(props.record.SN ?? props.loanerMaster?.SN),
    loanerID: props.attached.loanerID ?? props.record.loanerID ?? null,
    assignStatus: stringValue(props.attached.assignStatus),
    quoteNum: stringValue(props.record.quoteNum),
    quoteDate: toDateInputValue(props.record.quoteDate),
    orderNum: stringValue(props.record.orderNum),
    orderDate: toDateInputValue(props.record.orderDate),
    poNum: stringValue(props.record.poNum),
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
})

const selectedUnit = computed(() => {
    const units = props.loanerUnits.filter(unit => String(unit.loanerID) === String(form.loanerID))
    if (!units.length) return null
    return pickMasterVersion(units, form.orderDate || null)
        ?? props.loanerUnits.find(unit => String(unit.loanerID) === String(form.loanerID))
        ?? null
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
const displayPrice = computed(() => basePrice.value - discountAmount.value)

watch(() => form.parentID, async (parentId) => {
    if (!parentId) {
        parentReturnCode.value = null
        return
    }
    if (String(parentId) === String(props.record.parentID)) {
        parentReturnCode.value = props.parentReturnCode
        return
    }
    try {
        const result = await apiFetch(`${page.props.appBaseUrl}/servicerecord/record/${parentId}`)
        if (!result) {
            parentReturnCode.value = null
            return
        }
        const { response, data } = result
        if (!response.ok) {
            parentReturnCode.value = null
            return
        }
        parentReturnCode.value = data.returnCode ?? null
    } catch {
        parentReturnCode.value = null
    }
})
const activeSelectItems = computed(() =>
    activeSelectKind.value === 'dealer' ? props.dealersMaster : props.loanerUnits,
)
const activeSelectInitialValue = computed(() => {
    if (activeSelectKind.value === 'loanerUnit') return form.loanerID
    return props.dealersMaster.find(item => item.dealerName === form.dealer)?.id ?? null
})

const calendarOptions = {
    plugins: [dayGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'ja',
    height: '100%',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
    buttonText: { today: '今日', month: '月', list: 'リスト' },
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    dayMaxEvents: true,
    events: fetchCalendarEvents,
    eventDrop: updateEventPeriod,
    eventResize: updateEventPeriod,
    eventClick(info) {
        if (!info.event.id || String(info.event.id) === String(props.attached.id)) return
        window.location.href = `${page.props.appBaseUrl}/servicerecord/loaner/detail/${info.event.id}`
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
                note: `[調整理由]${reason}`,
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

function linkifyNote(value) {
    return linkifyText(value) || '—'
}

function formatNoteDate(value) {
    if (!value) return '—'
    const date = new Date(String(value).replace(' ', 'T'))
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleDateString('ja-JP')
}

function openNoteCreate() {
    noteDialogMode.value = 'create'
    editingNoteId.value = null
    noteForm.note = ''
    noteForm.important = false
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
        const body = isEdit
            ? { note: text, important: !!noteForm.important }
            : {
                associatedID: props.record.orderID,
                note: text,
                important: !!noteForm.important,
                personal: false,
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
    }
    activeSelectKind.value = null
}

function filesApiBase() {
    return `${page.props.appBaseUrl}/servicerecord/files`
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

async function save() {
    error.value = ''
    success.value = ''
    if (form.plannedSentDate && form.plannedReturnedDate && form.plannedReturnedDate < form.plannedSentDate) {
        error.value = '予定終了日は予定開始日以降にしてください。'
        return
    }
    if (form.sentDate && form.returnedDate && form.returnedDate < form.sentDate) {
        error.value = '実終了日は実開始日以降にしてください。'
        return
    }

    const payload = { ...form }
    payload.parentID = numericNullable(form.parentID)
    payload.loanerID = numericNullable(form.loanerID)
    payload.status = numericNullable(form.status)
    payload.discount_service = numericNullable(form.discount_service) ?? 0
    payload.price = basePrice.value
    Object.keys(payload).forEach((key) => {
        if (typeof payload[key] === 'string') payload[key] = nullable(payload[key])
    })
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
        if (!result) return
        const { response, data } = result
        if (!response.ok) throw new Error(validationError(data, `保存に失敗しました。（HTTP ${response.status}）`))
        syncCurrentDates(data.attached, data.record)
        if (data.record?.status != null && data.record.status !== '') {
            form.status = String(data.record.status)
        }
        success.value = data.message || '貸出詳細を保存しました。'
        calendarRef.value?.getApi?.().refetchEvents()
        if (data.promotionTriggered) {
            promotionCandidates.value = Array.isArray(data.promotionCandidates) ? data.promotionCandidates : []
            promotionModalOpen.value = true
        }
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}

function closePromotionModal() {
    promotionModalOpen.value = false
}

function openPromotionCandidate(candidate) {
    if (!candidate?.orderID) return
    const returnUrl = typeof window !== 'undefined' ? window.location.href : ''
    const params = returnUrl ? `?returnUrl=${encodeURIComponent(returnUrl)}` : ''
    window.location.href = `${page.props.appBaseUrl}/servicerecord/loaner/detail/${candidate.orderID}${params}`
}

function syncCurrentDates(attached, record = null) {
    if (attached) {
        form.sentDate = attached.sentDate || ''
        form.returnedDate = attached.returnedDate || ''
        form.plannedSentDate = attached.plannedSentDate || attached.sentDate || ''
        form.plannedReturnedDate = attached.plannedReturnedDate || attached.returnedDate || ''
        form.assignStatus = attached.assignStatus || ''
    }
    if (record) {
        form.quoteNum = stringValue(record.quoteNum)
        form.quoteDate = toDateInputValue(record.quoteDate)
        form.orderNum = stringValue(record.orderNum)
        form.orderDate = toDateInputValue(record.orderDate)
        form.poNum = stringValue(record.poNum)
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
    if (!raw) return null
    try {
        const url = new URL(raw, window.location.origin)
        return url.origin === window.location.origin ? url.href : null
    } catch {
        return null
    }
}

function closePage() {
    if (window.opener && !window.opener.closed) {
        window.close()
        return
    }
    const returnUrl = safeReturnUrl()
    if (returnUrl) return void (window.location.href = returnUrl)
    if (props.record.parentID) {
        window.location.href = `${page.props.appBaseUrl}/servicerecords/detail/${props.record.parentID}`
        return
    }
    window.location.href = `${page.props.appBaseUrl}/servicerecord/administrator`
}

function updateCalendarSize() {
    calendarRef.value?.getApi?.().updateSize()
}

onMounted(() => window.addEventListener('resize', updateCalendarSize))
onBeforeUnmount(() => window.removeEventListener('resize', updateCalendarSize))
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

.page-header strong { font-size: 14px; }
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
.btn-secondary { background: #64748b; color: #fff; }
.select-btn { padding: 2px 8px; border-color: #94a3b8; background: #fff; color: #334155; font-size: 11px; }

.outer-splitpanes { flex: 1; min-width: 0; min-height: 0; overflow: hidden; }
.main-pane { min-width: 0; min-height: 0; padding: 0 5px; overflow: hidden; }
.left-pane { width: 100%; height: 100%; min-width: 0; min-height: 0; display: flex; flex-direction: column; gap: 5px; overflow-x: hidden; overflow-y: auto; padding-right: 3px; }
.panel { min-width: 0; min-height: 0; border: 1px solid #94a3b8; background: #fff; padding: 7px; overflow: visible; }
.panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 5px; }
.panel h2 { margin: 0; font-size: 13px; }
.panel h3 { margin: 7px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #cbd5e1; font-size: 11px; color: #475569; }

.loaner-panel { flex: 0 0 auto; }
.people-row { flex: 0 0 auto; min-width: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px; }
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

.loaner-info-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr) minmax(0, 2fr);
    gap: 4px 8px;
    align-items: start;
}
.loaner-id-col,
.loaner-detail-col,
.loaner-commerce-col {
    min-width: 0;
    display: grid;
    gap: 4px;
}
.loaner-id-col label,
.loaner-detail-col > label,
.loaner-detail-col .detail-pair > label,
.loaner-commerce-col > label {
    min-width: 0;
    display: grid;
    grid-template-columns: 62px minmax(0, 1fr);
    align-items: center;
    gap: 4px;
}
.loaner-id-col label > span,
.loaner-detail-col label > span,
.loaner-commerce-col label > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #475569;
}
.loaner-id-col input,
.loaner-detail-col input,
.loaner-detail-col select,
.loaner-detail-col .master-value,
.loaner-commerce-col input {
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
.loaner-id-col input[readonly],
.loaner-detail-col input[readonly] {
    background: #f1f5f9;
    color: #64748b;
}
.detail-pair {
    min-width: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px 6px;
}
.detail-spacer { visibility: hidden; pointer-events: none; }
.num-date-pair {
    min-width: 0;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 4px;
}
.price-adjust-row {
    margin-top: 6px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 16px;
    padding: 4px 8px;
    border: 1px solid #93c5fd;
    background: rgb(210, 210, 220);
}
.price-adjust-main,
.price-adjust-actions,
.price-adjust-delta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.price-adjust-label { color: #475569; font-size: 11px; white-space: nowrap; }
.price-adjust-value { font-size: 13px; color: #0f172a; }
.price-adjust-btn { min-height: 24px; padding: 2px 10px; font-size: 11px; }
.price-adjust-delta strong { font-size: 12px; color: #0f172a; }

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
    .loaner-info-layout { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); }
    .loaner-commerce-col { grid-column: 1 / -1; }
    .compact-grid { grid-template-columns: 1fr; }
    .compact-grid .span-2 { grid-column: auto; }
}

@media (max-height: 760px) {
    .tab-panel { flex-basis: 300px; min-height: 300px; }
}

@media (max-width: 720px) {
    .loaner-detail-page { padding: 3px; }
    .main-pane { padding: 0 2px; }
    .header-actions { gap: 3px; }
    .btn { padding-inline: 8px; }
    .save-message { display: none; }
    .people-row { grid-template-columns: 1fr; }
    .period-panel h2, .period-panel label span { display: none; }
    .loaner-info-layout { grid-template-columns: 1fr; }
    .detail-pair { grid-template-columns: 1fr; }
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
