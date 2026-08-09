<template>
    <div class="logistics-detail">
        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <div v-else class="logistics-grid">
            <section class="panel panel-files">
                <div class="panel-header">
                    <h3>
                        Files（書類 {{ sortedFiles.length }}件
                        ／ 撮影画像 {{ capturedImages.length }}件）
                    </h3>
                    <div class="panel-actions">
                        <button
                            type="button"
                            class="action-btn"
                            :disabled="!selectedFileId"
                            @click="openFileDelete"
                        >
                            削除
                        </button>
                        <button type="button" class="action-btn" @click="openFileCreate">新規追加</button>
                    </div>
                </div>
                <div class="panel-body files-body">
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
                </div>
            </section>

            <div class="right-column">
                <div class="action-bar">
                    <button
                        type="button"
                        class="workflow-btn"
                        :disabled="statusActionSaving"
                        @click="onComplete"
                    >
                        {{ statusActionSaving ? '処理中...' : '出荷完了' }}
                    </button>
                    <button
                        type="button"
                        class="workflow-btn"
                        :disabled="statusActionSaving"
                        @click="onRemand"
                    >
                        差戻
                    </button>
                </div>
                <p v-if="actionMessage" class="action-message">{{ actionMessage }}</p>

                <section class="panel panel-summary">
                    <div class="panel-body summary-row">
                        <span class="summary-item">{{ fieldValue('dealer') || 'dealer' }}</span>
                        <span class="summary-item">{{ fieldValue('productName') || 'ProductName' }}</span>
                        <span class="summary-item">SN:{{ fieldValue('SN') || 'xxxxxxx' }}</span>
                    </div>
                </section>

                <section class="panel panel-delivery">
                    <div class="panel-header">
                        <h3>発送先</h3>
                    </div>
                    <div class="panel-body delivery-form">
                        <label class="field field-zip">
                            <span>郵便番号</span>
                            <input
                                type="text"
                                placeholder="zipcode"
                                :value="fieldValue('deliveryDestination_zipcode')"
                                @input="updateDraftValue('deliveryDestination_zipcode', $event.target.value)"
                            >
                        </label>
                        <div class="address-row">
                            <label class="field field-address1">
                                <input
                                    type="text"
                                    placeholder="address1"
                                    :value="fieldValue('deliveryDestination_address1')"
                                    @input="updateDraftValue('deliveryDestination_address1', $event.target.value)"
                                >
                            </label>
                            <label class="field field-address2">
                                <input
                                    type="text"
                                    placeholder="address2"
                                    :value="fieldValue('deliveryDestination_address2')"
                                    @input="updateDraftValue('deliveryDestination_address2', $event.target.value)"
                                >
                            </label>
                        </div>
                        <label class="field field-company">
                            <input
                                type="text"
                                placeholder="deliveryCompany"
                                :value="fieldValue('deliveryDestination_company')"
                                @input="updateDraftValue('deliveryDestination_company', $event.target.value)"
                            >
                        </label>
                        <label class="field field-full">
                            <input
                                type="text"
                                placeholder="deliveryCompany_depart"
                                :value="fieldValue('deliveryDestination_depart')"
                                @input="updateDraftValue('deliveryDestination_depart', $event.target.value)"
                            >
                        </label>
                        <label class="field field-contact">
                            <input
                                type="text"
                                placeholder="deliveryCompany_contactPerson"
                                :value="fieldValue('deliveryDestination_contactPerson')"
                                @input="updateDraftValue('deliveryDestination_contactPerson', $event.target.value)"
                            >
                        </label>
                        <label class="field field-phone">
                            <input
                                type="text"
                                placeholder="deliveryCompany_Phone"
                                :value="fieldValue('deliveryDestination_phone')"
                                @input="updateDraftValue('deliveryDestination_phone', $event.target.value)"
                            >
                        </label>
                    </div>
                </section>

                <section class="panel panel-notes">
                    <div class="panel-header">
                        <h3>Notes（{{ sharedNotes.length }}件）</h3>
                        <div class="panel-actions">
                            <button type="button" class="action-btn" @click="openNoteCreate">新規追加</button>
                            <button
                                type="button"
                                class="action-btn"
                                :disabled="!canModifySelectedNote"
                                @click="openNoteEdit"
                            >
                                編集
                            </button>
                            <button
                                type="button"
                                class="action-btn"
                                :disabled="!canModifySelectedNote"
                                @click="openNoteDelete"
                            >
                                削除
                            </button>
                        </div>
                    </div>
                    <div class="panel-body notes-body">
                        <div v-if="sharedNotes.length" class="table-wrap">
                            <table class="data-table notes-table">
                                <thead>
                                    <tr>
                                        <th class="col-date">日時</th>
                                        <th class="col-author">記入者</th>
                                        <th>内容</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="note in sharedNotes"
                                        :key="note.id"
                                        class="table-row"
                                        :class="{
                                            'important-row': note.important,
                                            'active-row': selectedNoteId === note.id,
                                        }"
                                        @click="selectedNoteId = note.id"
                                    >
                                        <td class="col-date">{{ formatDate(note.whenWrote) }}</td>
                                        <td class="col-author">{{ note.whoWrote || '—' }}</td>
                                        <td class="text-cell" v-html="linkifyNote(note.note)" />
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="empty-message notes-empty">Notes</p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AssociatedCapturedImages from '@/components/ServiceRecord/AssociatedCapturedImages.vue'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import { apiFetch } from '@/utils/apiFetch'
import { linkifyText } from '@/utils/linkifyText'

/** Logistics 完了時の status（一覧の 350 から外れる値） */
const LOGISTICS_COMPLETE_STATUS = 360

const props = defineProps({
    record: Object,
    draftRecord: Object,
    notes: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    capturedImages: { type: Array, default: () => [] },
    attachmentsLoading: { type: Boolean, default: false },
    attachmentsError: { type: String, default: '' },
})

const emit = defineEmits(['open-dialog', 'files-updated', 'reload-attachments', 'workflow-done', 'save'])

const page = usePage()
const selectedFileId = ref(null)
const selectedNoteId = ref(null)
const capturedImagesOpen = ref(false)
const fileSortSaving = ref(false)
const statusActionSaving = ref(false)
const actionMessage = ref('')

const currentUserName = computed(() => page.props.authUser?.kanji_name || '')

const sortedFiles = computed(() => {
    const list = [...(props.files ?? [])]
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

const sharedNotes = computed(() =>
    (props.notes ?? []).filter(note => !isPersonalNote(note)),
)

const selectedNote = computed(() =>
    sharedNotes.value.find(note => Number(note.id) === Number(selectedNoteId.value)),
)

const canModifySelectedNote = computed(() => {
    const note = selectedNote.value
    if (!note) return false
    return String(note.whoWrote || '') === String(currentUserName.value || '')
})

watch(() => props.files, (files) => {
    if (selectedFileId.value && !files.some(f => Number(f.id) === Number(selectedFileId.value))) {
        selectedFileId.value = null
    }
}, { immediate: true })

watch(() => props.notes, () => {
    if (
        selectedNoteId.value != null
        && !sharedNotes.value.some(n => Number(n.id) === Number(selectedNoteId.value))
    ) {
        selectedNoteId.value = null
    }
})

function isPersonalNote(note) {
    return note?.personal === true || note?.personal === 1 || note?.personal === '1'
}

function fieldValue(field) {
    const draft = props.draftRecord?.[field]
    if (draft !== undefined && draft !== null) return draft
    return props.record?.[field] ?? ''
}

function updateDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value
}

function formatDate(value) {
    if (!value) return '—'
    return String(value).replace('T', ' ').slice(0, 16)
}

function linkifyNote(text) {
    return linkifyText(text || '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function getBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
}

function getRecordApiUrl() {
    return `${window.location.origin}${getBasePath()}/${props.record?.orderID}`
}

function getFilesApiBase() {
    return `${window.location.origin}${getBasePath()}/files`
}

async function updateRecord(payload) {
    if (!props.record?.orderID) {
        throw new Error('案件が選択されていません。')
    }

    const result = await apiFetch(getRecordApiUrl(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
    })

    if (!result?.response?.ok) {
        throw new Error(result?.data?.message || `更新に失敗しました。（HTTP ${result?.response?.status ?? ''}）`)
    }

    Object.assign(props.record, payload)
    if (props.draftRecord) Object.assign(props.draftRecord, payload)
    return result.data
}

function deliveryPayload() {
    return {
        deliveryDestination_company: fieldValue('deliveryDestination_company') || null,
        deliveryDestination_depart: fieldValue('deliveryDestination_depart') || null,
        deliveryDestination_contactPerson: fieldValue('deliveryDestination_contactPerson') || null,
        deliveryDestination_phone: fieldValue('deliveryDestination_phone') || null,
        deliveryDestination_email: fieldValue('deliveryDestination_email') || null,
        deliveryDestination_zipcode: fieldValue('deliveryDestination_zipcode') || null,
        deliveryDestination_address1: fieldValue('deliveryDestination_address1') || null,
        deliveryDestination_address2: fieldValue('deliveryDestination_address2') || null,
    }
}

async function onComplete() {
    if (statusActionSaving.value) return
    if (!window.confirm(`この案件を完了（status=${LOGISTICS_COMPLETE_STATUS}）にしますか？`)) return

    statusActionSaving.value = true
    actionMessage.value = ''
    try {
        await updateRecord({
            ...deliveryPayload(),
            status: LOGISTICS_COMPLETE_STATUS,
        })
        emit('workflow-done', { action: 'complete', status: LOGISTICS_COMPLETE_STATUS })
    } catch (e) {
        actionMessage.value = e.message || '完了処理に失敗しました。'
    } finally {
        statusActionSaving.value = false
    }
}

function onRemand() {
    if (statusActionSaving.value) return
    emit('open-dialog', 'NOTE', {
        mode: 'create',
        personal: false,
        remand: true,
    })
}

function openFileCreate() {
    emit('open-dialog', 'FILE', { mode: 'create' })
}

function openFileDelete() {
    const file = sortedFiles.value.find(item => Number(item.id) === Number(selectedFileId.value))
    if (!file) return
    emit('open-dialog', 'D', { action: 'delete-file', file, fileId: file.id })
}

function openNoteCreate() {
    emit('open-dialog', 'NOTE', { mode: 'create', personal: false })
}

function openNoteEdit() {
    const note = selectedNote.value
    if (!note || !canModifySelectedNote.value) return
    emit('open-dialog', 'NOTE', { mode: 'edit', note, personal: false })
}

function openNoteDelete() {
    const note = selectedNote.value
    if (!note || !canModifySelectedNote.value) return
    emit('open-dialog', 'D', { action: 'delete-note', note, noteId: note.id })
}

async function moveFile(fileId, direction) {
    if (fileSortSaving.value) return
    const list = [...sortedFiles.value]
    const index = list.findIndex(file => Number(file.id) === Number(fileId))
    if (index < 0) return
    const swapIndex = direction === 'up' ? index - 1 : index + 1
    if (swapIndex < 0 || swapIndex >= list.length) return

    const current = list[index]
    const target = list[swapIndex]
    const currentSort = current.sortNum ?? (index + 1) * 10
    const targetSort = target.sortNum ?? (swapIndex + 1) * 10

    fileSortSaving.value = true
    try {
        await Promise.all([
            patchFileSort(current.id, targetSort),
            patchFileSort(target.id, currentSort),
        ])
        const nextFiles = (props.files ?? []).map((file) => {
            if (Number(file.id) === Number(current.id)) return { ...file, sortNum: targetSort }
            if (Number(file.id) === Number(target.id)) return { ...file, sortNum: currentSort }
            return file
        })
        emit('files-updated', nextFiles)
    } catch (e) {
        window.alert(e.message || '表示順の変更に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

async function updateFileSortNum(fileId, sortNum) {
    if (fileSortSaving.value) return
    fileSortSaving.value = true
    try {
        await patchFileSort(fileId, sortNum)
        const nextFiles = (props.files ?? []).map(file => (
            Number(file.id) === Number(fileId) ? { ...file, sortNum } : file
        ))
        emit('files-updated', nextFiles)
    } catch (e) {
        window.alert(e.message || '表示順の変更に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

async function patchFileSort(fileId, sortNum) {
    const result = await apiFetch(`${getFilesApiBase()}/${fileId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ sortNum }),
    })
    if (!result?.response?.ok) {
        throw new Error(result?.data?.message || '表示順の更新に失敗しました。')
    }
}
</script>

<style scoped>
.logistics-detail {
    height: 100%;
    min-height: 0;
    background: #bbbbbb;
    padding: 10px;
    box-sizing: border-box;
}

.status-message {
    margin: 16px;
    color: #334155;
}

.status-message.error {
    color: #b91c1c;
}

.logistics-grid {
    height: 100%;
    min-height: 0;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
    gap: 10px;
}

.panel-files {
    grid-column: 1;
    min-height: 0;
}

.right-column {
    grid-column: 2;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.right-column > .action-bar,
.right-column > .action-message,
.right-column > .panel-summary,
.right-column > .panel-delivery {
    flex: 0 0 auto;
}

.right-column > .panel-notes {
    flex: 1 1 auto;
    min-height: 160px;
}

.panel {
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    border: 2px solid #0f172a;
    border-radius: 8px;
    background: #eff6ff;
    overflow: hidden;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-bottom: 1px solid #94a3b8;
    background: #dbeafe;
    flex: 0 0 auto;
}

.panel-header h3 {
    margin: 0;
    font-size: 15px;
    color: #0f172a;
}

.panel-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.panel-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    padding: 8px;
    background: #fff;
}

.files-body,
.notes-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.captured-images-panel {
    flex: 0 0 auto;
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

.files-list-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.action-bar {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    flex: 0 0 auto;
}

.workflow-btn {
    min-height: 48px;
    border: 2px solid #0f172a;
    border-radius: 0;
    background: #fff;
    color: #0f172a;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
}

.workflow-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.action-message {
    margin: 0;
    color: #b91c1c;
    font-size: 13px;
}

.action-btn {
    padding: 4px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #0f172a;
    font-size: 12px;
    cursor: pointer;
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.panel-summary .panel-body {
    background: #eff6ff;
    padding: 12px 14px;
}

.summary-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
    gap: 20px;
}

.summary-item {
    min-width: 0;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.delivery-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #eff6ff;
}

.address-row {
    display: grid;
    grid-template-columns: minmax(100px, 0.45fr) minmax(0, 1.55fr);
    gap: 8px;
}

.field {
    display: block;
    min-width: 0;
}

.field-zip,
.field-company,
.field-contact,
.field-phone {
    width: min(240px, 100%);
}

.field-full {
    width: 100%;
}

.field input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #0f172a;
    border-radius: 2px;
    box-sizing: border-box;
    background: #fff;
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
}

.field input::placeholder {
    color: #94a3b8;
    font-weight: 500;
}

.notes-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 120px;
    font-size: 18px;
    font-weight: 700;
    color: #64748b;
}

.table-wrap {
    min-height: 0;
    overflow: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
    vertical-align: top;
}

.data-table th {
    position: sticky;
    top: 0;
    background: #e2e8f0;
    z-index: 1;
}

.notes-table th,
.notes-table td {
    font-weight: 700;
}

.table-row {
    cursor: pointer;
}

.table-row.active-row {
    background: #dbeafe;
}

.table-row.important-row {
    background: #fef3c7;
}

.col-date {
    width: 120px;
    white-space: nowrap;
}

.col-author {
    width: 90px;
}

.text-cell {
    word-break: break-word;
}

.empty-message {
    margin: 0;
    padding: 12px;
    color: #64748b;
    font-size: 13px;
}
</style>
