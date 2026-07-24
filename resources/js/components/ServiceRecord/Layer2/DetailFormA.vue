<template>
    <div class="detail-form">
        <h2>詳細フォーム A</h2>

        <section class="section-card">
            <h3>基本情報</h3>
            <dl class="info-grid">
                <dt>OrderID</dt><dd>{{ record?.orderID }}</dd>
                <dt>受領日</dt><dd>{{ record?.receivedDate }}</dd>
                <dt>製品名</dt><dd>{{ record?.productName }}</dd>
                <dt>S/N</dt><dd>{{ record?.SN }}</dd>
                <dt>ステータス</dt><dd>{{ record?.status_master?.status }}</dd>
                <dt>作業担当</dt><dd>{{ record?.labor_master?.laborName }}</dd>
                <dt>作業内容</dt><dd>{{ record?.return_code_master?.description }}</dd>
                <dt>依頼者</dt><dd>{{ record?.dealer }}</dd>
                <dt>部署</dt><dd>{{ record?.dealer_depart }}</dd>
                <dt>担当者</dt><dd>{{ record?.contactPerson }}</dd>
                <dt>Email</dt><dd>{{ record?.email }}</dd>
                <dt>Phone</dt><dd>{{ record?.phone }}</dd>
            </dl>
        </section>

        <p v-if="attachmentsLoading" class="status-message">添付データを読み込み中...</p>
        <p v-else-if="attachmentsError" class="status-message error">{{ attachmentsError }}</p>

        <template v-else>
            <section class="section-card">
                <div class="section-header">
                    <h3>Notes（{{ notes.length }}件）</h3>
                    <div class="section-actions">
                        <button
                            type="button"
                            class="action-btn"
                            :disabled="!canModifySelectedNote"
                            :title="noteEditDeleteTitle"
                            @click="openNoteEdit"
                        >
                            編集
                        </button>
                        <button
                            type="button"
                            class="action-btn action-btn-danger"
                            :disabled="!canModifySelectedNote"
                            :title="noteEditDeleteTitle"
                            @click="openNoteDelete"
                        >
                            削除
                        </button>
                        <button type="button" class="action-btn action-btn-primary" @click="openNoteCreate">
                            新規追加
                        </button>
                    </div>
                </div>
                <table v-if="notes.length" class="data-table">
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
                            :class="{
                                'important-row': note.important,
                                'active-row': selectedNoteId === note.id,
                            }"
                            @click="selectedNoteId = note.id"
                        >
                            <td>{{ formatDateTime(note.whenWrote) }}</td>
                            <td>{{ note.whoWrote || '—' }}</td>
                            <td class="text-cell">{{ note.note || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="empty-message">Notes がありません。</p>
            </section>

            <section class="section-card">
                <div class="section-header">
                    <h3>Files（{{ files.length }}件）</h3>
                    <div class="section-actions">
                        <button
                            type="button"
                            class="action-btn action-btn-danger"
                            :disabled="!selectedFileId"
                            :title="selectedFileId ? '' : 'ファイルを選択してください'"
                            @click="openFileDelete"
                        >
                            削除
                        </button>
                        <button type="button" class="action-btn action-btn-primary" @click="openFileCreate">
                            新規追加
                        </button>
                    </div>
                </div>
                <div v-if="files.length" class="files-list">
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

            <section class="section-card">
                <h3>Parts（{{ parts.length }}件）</h3>
                <table v-if="parts.length" class="data-table">
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
                <p v-else class="empty-message">Parts がありません。</p>
            </section>
        </template>

        <div class="action-row">
            <button type="button" @click="$emit('open-dialog', 'A', { source: 'formA' })">
                入力ダイアログ A
            </button>
            <button type="button" @click="$emit('open-dialog', 'D', { action: 'confirm' })">
                確認ダイアログ D
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'

const page = usePage()

const props = defineProps({
    record: Object,
    notes: {
        type: Array,
        default: () => [],
    },
    files: {
        type: Array,
        default: () => [],
    },
    parts: {
        type: Array,
        default: () => [],
    },
    attachmentsLoading: {
        type: Boolean,
        default: false,
    },
    attachmentsError: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['open-dialog'])

const authUserName = computed(() => page.props.authUser?.kanji_name ?? '')

const selectedNoteId = ref(null)
const selectedPartId = ref(null)
const selectedFileId = ref(null)

const selectedNote = computed(() => props.notes.find(n => n.id === selectedNoteId.value))

function isNoteOwner(note) {
    return note?.whoWrote === authUserName.value
}

const canModifySelectedNote = computed(() => {
    return !!selectedNote.value && isNoteOwner(selectedNote.value)
})

const noteEditDeleteTitle = computed(() => {
    if (!selectedNoteId.value) {
        return 'Note を選択してください'
    }
    if (!canModifySelectedNote.value) {
        return '自分が書いた Note のみ編集・削除できます'
    }
    return ''
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
})

watch(() => props.record?.orderID, () => {
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

function openNoteDelete() {
    const note = selectedNote.value
    if (!note || !isNoteOwner(note)) return
    emit('open-dialog', 'D', { action: 'delete-note', note, noteId: note.id })
}

const selectedFile = computed(() => props.files.find(f => f.id === selectedFileId.value))

function openFileCreate() {
    emit('open-dialog', 'FILE', { mode: 'create' })
}

function openFileDelete() {
    const file = selectedFile.value
    if (!file) return
    emit('open-dialog', 'D', { action: 'delete-file', file, fileId: file.id })
}

function formatDateTime(value) {
    if (!value) return '—'
    const normalized = String(value).replace(' ', 'T')
    const date = new Date(normalized)
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleString('ja-JP')
}
</script>

<style scoped>
.detail-form h2 {
    margin-bottom: 16px;
}

.section-card {
    margin-bottom: 20px;
    padding: 16px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}

.section-card h3 {
    margin: 0;
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
}

.action-btn {
    padding: 6px 12px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: white;
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
    color: white;
}

.action-btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: white;
}

.info-grid {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 8px 16px;
}

.info-grid dt {
    font-weight: bold;
    color: #475569;
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
    white-space: nowrap;
}

.table-row {
    cursor: pointer;
}

.table-row:hover td {
    background-color: #dbeafe;
}

.active-row td {
    color: rgb(255, 255, 255) !important;
    background-color: #7e25eb !important;
}

.table-row.active-row:hover td {
    background-color: #7e25eb !important;
}

.text-cell {
    white-space: pre-wrap;
    word-break: break-word;
}

.important-row td {
    background: #fef3c7;
}

.files-list {
    display: flex;
    flex-direction: column;
}

.empty-message,
.status-message {
    margin: 0;
    color: #64748b;
}

.status-message.error {
    color: #b91c1c;
}

.action-row {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.action-row button {
    padding: 8px 16px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
</style>
