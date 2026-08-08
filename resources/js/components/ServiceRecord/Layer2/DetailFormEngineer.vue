<template>
    <div class="engineer-detail">
        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <Splitpanes v-else class="default-theme engineer-splitpanes" @resized="syncPaneSizes">
            <Pane class="engineer-pane engineer-pane-files" :size="leftPaneSize" :min-size="30">
                <section class="panel panel-files">
                    <div class="panel-header">
                        <h3>
                            Files（書類 {{ sortedFiles.length }}件
                            ／ 撮影画像 {{ capturedImages.length }}件）
                        </h3>
                        <div class="panel-actions">
                            <button type="button" class="action-btn" :disabled="!selectedFileId" @click="openFileDelete">削除</button>
                            <button type="button" class="action-btn action-btn-primary" @click="openFileCreate">新規追加</button>
                        </div>
                    </div>

                    <div class="captured-images-panel">
                        <button
                            type="button"
                            class="captured-toggle"
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

                    <div class="files-list">
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
            </Pane>

            <Pane class="engineer-pane engineer-pane-right" :size="rightPaneSize" :min-size="35">
                <div class="right-stack">
                    <div class="action-bar">
                        <button type="button" class="action-btn" @click="showGalleryDialog = true">Gallery</button>
                        <div class="docs-row docs-row-inline">
                            <span class="docs-label">添付書類</span>
                            <label class="flag-toggle" :class="{ on: isA2laOn }">
                                <span class="flag-name">A2LA</span>
                                <button
                                    type="button"
                                    class="switch"
                                    role="switch"
                                    :aria-checked="isA2laOn"
                                    :disabled="flagSaving"
                                    @click="toggleFlag('a2la')"
                                >
                                    <span class="switch-thumb" />
                                </button>
                            </label>
                            <label class="flag-toggle" :class="{ on: isPreDataOn }">
                                <span class="flag-name">pre data</span>
                                <button
                                    type="button"
                                    class="switch"
                                    role="switch"
                                    :aria-checked="isPreDataOn"
                                    :disabled="flagSaving"
                                    @click="toggleFlag('preData')"
                                >
                                    <span class="switch-thumb" />
                                </button>
                            </label>
                            <label class="flag-toggle" :class="{ on: isPostDataOn }">
                                <span class="flag-name">post data</span>
                                <button
                                    type="button"
                                    class="switch"
                                    role="switch"
                                    :aria-checked="isPostDataOn"
                                    :disabled="flagSaving"
                                    @click="toggleFlag('postData')"
                                >
                                    <span class="switch-thumb" />
                                </button>
                            </label>
                        </div>
                        <div class="action-status-group">
                            <button
                                type="button"
                                class="action-btn action-btn-success action-btn-wide"
                                :disabled="statusActionSaving"
                                @click="onComplete"
                            >
                                {{ statusActionSaving ? '処理中...' : '完了' }}
                            </button>
                            <button
                                type="button"
                                class="action-btn action-btn-warn action-btn-wide"
                                :disabled="statusActionSaving"
                                @click="onRemand"
                            >
                                差戻
                            </button>
                        </div>
                    </div>
                    
                    <section class="panel panel-block panel-stocked">
                        <aside class="card-side">
                            <h3>stocked Parts（{{ stockedParts.length }}件）</h3>
                            <div class="card-side-actions">
                                <button type="button" class="action-btn action-btn-primary" @click="openStockedPartCreate">新規追加</button>
                                <button type="button" class="action-btn" :disabled="!selectedStockedPartId" @click="openStockedPartEdit">数量編集</button>
                                <button type="button" class="action-btn" :disabled="!selectedStockedPartId" @click="openStockedPartDelete">削除</button>
                            </div>
                        </aside>
                        <div class="card-main">
                            <div v-if="stockedParts.length" class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Part ID</th>
                                            <th>部品名</th>
                                            <th>説明</th>
                                            <th>使用数</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="part in stockedParts"
                                            :key="part.id"
                                            class="table-row"
                                            :class="{ 'active-row': selectedStockedPartId === part.id }"
                                            @click="selectedStockedPartId = part.id"
                                            @dblclick="openStockedPartEditFor(part)"
                                        >
                                            <td>{{ part.partID }}</td>
                                            <td>{{ part.stocked_part_master?.partName || '—' }}</td>
                                            <td class="text-cell">{{ part.stocked_part_master?.description || '—' }}</td>
                                            <td>{{ part.quantity ?? '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="empty-message">stocked Parts がありません。</p>
                        </div>
                    </section>

                    <section class="panel panel-block panel-notes">
                        <aside class="card-side">
                            <h3>Notes（{{ sharedNotes.length }}件）</h3>
                            <div class="card-side-actions">
                                <button type="button" class="action-btn action-btn-primary" @click="openNoteCreate(false)">新規追加</button>
                                <button type="button" class="action-btn" :disabled="!canModifySelectedSharedNote" @click="openNoteEdit(false)">編集</button>
                                <button type="button" class="action-btn action-btn-danger" :disabled="!canModifySelectedSharedNote" @click="openNoteDelete(false)">削除</button>
                            </div>
                        </aside>
                        <div class="card-main">
                            <div v-if="sharedNotes.length" class="table-wrap">
                                <table class="data-table notes-table">
                                    <thead>
                                        <tr>
                                            <th class="col-note-date">日時</th>
                                            <th class="col-note-author">記入者</th>
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
                                                'active-row': selectedSharedNoteId === note.id,
                                            }"
                                            @click="selectedSharedNoteId = note.id"
                                        >
                                            <td class="col-note-date">{{ formatDate(note.whenWrote) }}</td>
                                            <td class="col-note-author">{{ note.whoWrote || '—' }}</td>
                                            <td class="text-cell" @click.stop="selectedSharedNoteId = note.id" v-html="linkifyNote(note.note)" />
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="empty-message">Notes がありません。</p>
                        </div>
                    </section>

                    <section class="panel panel-block panel-notes">
                        <aside class="card-side">
                            <h3>Personal Notes（{{ personalNotes.length }}件）</h3>
                            <div class="card-side-actions">
                                <button type="button" class="action-btn action-btn-primary" @click="openNoteCreate(true)">新規追加</button>
                                <button type="button" class="action-btn" :disabled="!canModifySelectedPersonalNote" @click="openNoteEdit(true)">編集</button>
                                <button type="button" class="action-btn action-btn-danger" :disabled="!canModifySelectedPersonalNote" @click="openNoteDelete(true)">削除</button>
                            </div>
                        </aside>
                        <div class="card-main">
                            <div v-if="personalNotes.length" class="table-wrap">
                                <table class="data-table notes-table">
                                    <thead>
                                        <tr>
                                            <th class="col-note-date">日時</th>
                                            <th class="col-note-author">記入者</th>
                                            <th>内容</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="note in personalNotes"
                                            :key="note.id"
                                            class="table-row"
                                            :class="{
                                                'important-row': note.important,
                                                'active-row': selectedPersonalNoteId === note.id,
                                            }"
                                            @click="selectedPersonalNoteId = note.id"
                                        >
                                            <td class="col-note-date">{{ formatDate(note.whenWrote) }}</td>
                                            <td class="col-note-author">{{ note.whoWrote || '—' }}</td>
                                            <td class="text-cell" @click.stop="selectedPersonalNoteId = note.id" v-html="linkifyNote(note.note)" />
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="empty-message">Personal Notes がありません。</p>
                        </div>
                    </section>


                    <section class="panel panel-block panel-parts">
                        <aside class="card-side">
                            <h3>Parts（{{ parts.length }}件）</h3>
                            <div class="card-side-actions">
                                <button type="button" class="action-btn action-btn-primary" @click="openPartCreate">新規追加</button>
                                <button type="button" class="action-btn" :disabled="!selectedPartId" @click="openPartDelete">削除</button>
                            </div>
                        </aside>
                        <div class="card-main">
                            <div v-if="parts.length" class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Part ID</th>
                                            <th>部品名</th>
                                            <th>説明</th>
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
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="empty-message">Parts がありません。</p>
                        </div>
                    </section>

                </div>
            </Pane>
        </Splitpanes>

        <p v-if="actionMessage" class="action-toast">{{ actionMessage }}</p>

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
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import AssociatedCapturedImages from '@/components/ServiceRecord/AssociatedCapturedImages.vue'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import CapturedImageGalleryDialog from '@/components/ServiceRecord/CapturedImageGalleryDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { linkifyText } from '@/utils/linkifyText'

const props = defineProps({
    record: Object,
    draftRecord: Object,
    notes: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    capturedImages: { type: Array, default: () => [] },
    parts: { type: Array, default: () => [] },
    stockedParts: { type: Array, default: () => [] },
    attachmentsLoading: { type: Boolean, default: false },
    attachmentsError: { type: String, default: '' },
})

const emit = defineEmits(['open-dialog', 'files-updated', 'reload-attachments', 'save', 'workflow-done'])

const page = usePage()
const leftPaneSize = ref(48)
const rightPaneSize = ref(52)
const selectedFileId = ref(null)
const selectedPartId = ref(null)
const selectedStockedPartId = ref(null)
const selectedSharedNoteId = ref(null)
const selectedPersonalNoteId = ref(null)
const fileSortSaving = ref(false)
const flagSaving = ref(false)
const statusActionSaving = ref(false)
const showGalleryDialog = ref(false)
const capturedImagesOpen = ref(true)
const galleryAssociatedId = computed(() => props.record?.orderID ?? null)
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
const personalNotes = computed(() =>
    (props.notes ?? []).filter(note => (
        isPersonalNote(note)
        && String(note.whoWrote || '') === String(currentUserName.value || '')
    )),
)

function isPersonalNote(note) {
    return note?.personal === true || note?.personal === 1 || note?.personal === '1'
}

function linkifyNote(value) {
    const html = linkifyText(value)
    return html || '—'
}

const selectedSharedNote = computed(() =>
    sharedNotes.value.find(note => Number(note.id) === Number(selectedSharedNoteId.value)) || null,
)
const selectedPersonalNote = computed(() =>
    personalNotes.value.find(note => Number(note.id) === Number(selectedPersonalNoteId.value)) || null,
)

const canModifySelectedSharedNote = computed(() => canModifyNote(selectedSharedNote.value))
const canModifySelectedPersonalNote = computed(() => canModifyNote(selectedPersonalNote.value))

watch(
    () => props.record?.orderID,
    () => {
        selectedFileId.value = null
        selectedPartId.value = null
        selectedStockedPartId.value = null
        selectedSharedNoteId.value = null
        selectedPersonalNoteId.value = null
        actionMessage.value = ''
    },
)

function isFlagOn(field) {
    const value = props.draftRecord?.[field] ?? props.record?.[field]
    return value === true || value === 1 || value === '1'
}

const isA2laOn = computed(() => isFlagOn('a2la'))
const isPreDataOn = computed(() => isFlagOn('preData'))
const isPostDataOn = computed(() => isFlagOn('postData'))

function syncPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

function canModifyNote(note) {
    if (!note) return false
    if (!currentUserName.value) return false
    return String(note.whoWrote || '') === String(currentUserName.value)
}

function formatDate(value) {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)
    return date.toLocaleString('ja-JP')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function getRecordApiUrl() {
    const basePath = window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
    return `${window.location.origin}${basePath}/${props.record?.orderID}`
}

function getFilesApiBase() {
    const basePath = window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
    return `${window.location.origin}${basePath}/files`
}

async function toggleFlag(field) {
    if (!props.draftRecord || !props.record?.orderID || flagSaving.value) return

    const next = isFlagOn(field) ? 0 : 1
    const previous = props.draftRecord[field]
    props.draftRecord[field] = next
    if (props.record) props.record[field] = next

    flagSaving.value = true
    actionMessage.value = ''
    try {
        const result = await apiFetch(getRecordApiUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ [field]: next }),
        })
        if (!result?.response?.ok) {
            throw new Error(result?.data?.message || 'フラグの更新に失敗しました。')
        }
    } catch (e) {
        props.draftRecord[field] = previous
        if (props.record) props.record[field] = previous
        actionMessage.value = e.message || 'フラグの更新に失敗しました。'
    } finally {
        flagSaving.value = false
    }
}

async function updateRecordStatus(status) {
    if (!props.record?.orderID) {
        throw new Error('案件が選択されていません。')
    }

    const result = await apiFetch(getRecordApiUrl(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ status }),
    })

    if (!result?.response?.ok) {
        throw new Error(result?.data?.message || `status の更新に失敗しました。（HTTP ${result?.response?.status ?? ''}）`)
    }

    if (props.draftRecord) props.draftRecord.status = status
    if (props.record) props.record.status = status

    return result.data
}

async function onComplete() {
    if (statusActionSaving.value) return
    if (!window.confirm('この案件を完了（status=190）にしますか？')) return

    statusActionSaving.value = true
    actionMessage.value = ''
    try {
        await updateRecordStatus(190)
        emit('workflow-done', { action: 'complete', status: 190 })
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

function openPartCreate() {
    emit('open-dialog', 'PART', {
        mode: 'create',
        attachedPartIds: props.parts.map(part => part.partID),
    })
}

function openPartDelete() {
    const part = props.parts.find(item => Number(item.id) === Number(selectedPartId.value))
    if (!part) return
    emit('open-dialog', 'D', { action: 'delete-part', part, partId: part.id })
}

function openStockedPartCreate() {
    emit('open-dialog', 'STOCKED_PART', {
        mode: 'create',
        attachedPartIds: props.stockedParts.map(part => part.partID),
    })
}

function openStockedPartEdit() {
    const part = props.stockedParts.find(item => Number(item.id) === Number(selectedStockedPartId.value))
    if (!part) return
    openStockedPartEditFor(part)
}

function openStockedPartEditFor(part) {
    if (!part) return
    selectedStockedPartId.value = part.id
    emit('open-dialog', 'STOCKED_PART_QTY', {
        mode: 'edit',
        stockedPart: part,
        partId: part.id,
    })
}

function openStockedPartDelete() {
    const part = props.stockedParts.find(item => Number(item.id) === Number(selectedStockedPartId.value))
    if (!part) return
    emit('open-dialog', 'D', { action: 'delete-stocked-part', part, partId: part.id })
}

function openNoteCreate(personal) {
    emit('open-dialog', 'NOTE', { mode: 'create', personal: !!personal })
}

function openNoteEdit(personal) {
    const note = personal ? selectedPersonalNote.value : selectedSharedNote.value
    if (!note) return
    emit('open-dialog', 'NOTE', { mode: 'edit', note, personal: !!personal })
}

function openNoteDelete(personal) {
    const note = personal ? selectedPersonalNote.value : selectedSharedNote.value
    if (!note) return
    emit('open-dialog', 'D', { action: 'delete-note', note, noteId: note.id })
}

async function moveFile(fileId, direction) {
    const list = sortedFiles.value
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
        await updateFileSortNum(current.id, targetSort, false)
        await updateFileSortNum(target.id, currentSort, false)
        emit('reload-attachments')
    } finally {
        fileSortSaving.value = false
    }
}

async function updateFileSortNum(fileId, sortNum, reload = true) {
    fileSortSaving.value = true
    try {
        const result = await apiFetch(`${getFilesApiBase()}/${fileId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ sortNum }),
        })
        if (!result?.response?.ok) {
            throw new Error(result?.data?.message || '順序の更新に失敗しました。')
        }
        const nextFiles = (props.files ?? []).map(file => (
            Number(file.id) === Number(fileId)
                ? { ...file, sortNum: result.data.file?.sortNum ?? sortNum }
                : file
        ))
        emit('files-updated', nextFiles)
        if (reload) emit('reload-attachments')
    } catch (e) {
        actionMessage.value = e.message || '順序の更新に失敗しました。'
    } finally {
        fileSortSaving.value = false
    }
}
</script>

<style scoped>
.engineer-detail {
    width: 100%;
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 8px;
    box-sizing: border-box;
    background: #e2e8f0;
    position: relative;
    overflow: hidden;
}

.status-message {
    margin: 0;
    padding: 10px 12px;
    border-radius: 6px;
    background: #fff;
    color: #334155;
}

.status-message.error {
    color: #b91c1c;
    background: #fef2f2;
}

.engineer-splitpanes {
    flex: 1;
    min-height: 0;
}

.engineer-pane {
    min-width: 0;
    min-height: 0;
    display: flex;
    height: 100%;
}

.panel {
    width: 100%;
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    overflow: hidden;
    box-sizing: border-box;
}

.panel-files {
    padding: 8px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    flex: 0 0 auto;
}

.panel-header h3 {
    margin: 0;
    font-size: 14px;
    color: #0f172a;
}

.panel-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
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

.captured-toggle-icon {
    font-size: 11px;
    color: #475569;
}

.captured-images-body {
    max-height: 180px;
    overflow: auto;
    padding: 8px;
}

.files-list {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.right-stack {
    width: 100%;
    height: 100%;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-right: 2px;
}

.action-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    flex: 0 0 auto;
    flex-wrap: wrap;
}

.action-status-group {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-left: auto;
}

.action-btn-wide {
    min-width: 96px;
    padding-left: 18px;
    padding-right: 18px;
}

.docs-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.docs-row-inline {
    flex: 1 1 auto;
    min-width: 0;
    justify-content: center;
}

.docs-label {
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    margin-right: 2px;
    white-space: nowrap;
}

.flag-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    user-select: none;
}

.flag-toggle.on {
    background: #1d4ed8;
    border-color: #1e3a8a;
    color: #fff;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.15);
}

.flag-name {
    font-size: 12px;
    font-weight: 700;
    min-width: 56px;
}

.switch {
    position: relative;
    width: 38px;
    height: 22px;
    padding: 0;
    border: none;
    border-radius: 999px;
    background: #94a3b8;
    cursor: pointer;
    flex: 0 0 auto;
}

.flag-toggle.on .switch {
    background: #0f172a;
}

.switch:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.switch-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    transition: transform 0.15s ease;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.35);
}

.flag-toggle.on .switch-thumb {
    transform: translateX(16px);
}

.panel-block {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    min-height: 0;
    padding: 0;
    overflow: hidden;
}

.panel-parts {
    flex: 0.9 1 0;
}

.panel-notes {
    flex: 1.35 1 0;
}

.panel-stocked {
    flex: 1.1 1 0;
}

.card-side {
    flex: 0 0 132px;
    width: 132px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px;
    border-right: 1px solid #cbd5e1;
    background: #f8fafc;
    box-sizing: border-box;
}

.card-side h3 {
    margin: 0;
    font-size: 13px;
    line-height: 1.25;
    color: #0f172a;
}

.card-side-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.card-side-actions .action-btn {
    width: 100%;
    text-align: center;
}

.card-main {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 6px 8px;
}

.table-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.data-table th,
.data-table td {
    border-bottom: 1px solid #e2e8f0;
    padding: 5px 6px;
    text-align: left;
    vertical-align: top;
}

.data-table th {
    position: sticky;
    top: 0;
    background: #f1f5f9;
    z-index: 1;
}

.table-row {
    cursor: pointer;
}

.table-row:hover {
    background: #f8fafc;
}

.active-row {
    background: #eff6ff;
}

.important-row {
    background: #fff7ed;
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

.col-note-date,
.col-note-author {
    width: 100px;
    white-space: nowrap;
}

.empty-message {
    margin: 0;
    padding: 10px 4px;
    color: #64748b;
    font-size: 13px;
}

.action-btn {
    padding: 5px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    font-size: 12px;
    font-weight: 700;
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

.action-btn-success {
    background: #15803d;
    border-color: #15803d;
    color: #fff;
}

.action-btn-warn {
    background: #c2410c;
    border-color: #c2410c;
    color: #fff;
}

.action-btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}

.action-toast {
    position: absolute;
    left: 16px;
    bottom: 16px;
    margin: 0;
    padding: 8px 12px;
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.9);
    color: #fff;
    font-size: 12px;
    z-index: 5;
}

:deep(.splitpanes__splitter) {
    background: #cbd5e1;
    min-width: 8px;
}

:deep(.splitpanes__splitter:hover) {
    background: #94a3b8;
}
</style>
