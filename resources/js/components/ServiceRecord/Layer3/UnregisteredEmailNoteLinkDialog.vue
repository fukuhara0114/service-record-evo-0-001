<template>
    <BaseDialog title="未登録メール Note を紐づけ" large @close="$emit('close')">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>
        <p class="help-text">選択したメールリンクをこの案件の Notes に追加します（日付はメール側、記入者はログイン中のユーザー）。</p>

        <label class="search-field">
            検索
            <input
                v-model="searchQuery"
                type="text"
                class="search-input"
                placeholder="mailLink / subject / whoWrote で検索"
            >
        </label>

        <p v-if="loadError" class="error-message">{{ loadError }}</p>
        <p v-if="error" class="error-message">{{ error }}</p>
        <p v-if="loading" class="status-message">読み込み中...</p>

        <div class="dialog-actions">
            <button type="button" class="btn-secondary" :disabled="saving" @click="$emit('close')">
                キャンセル
            </button>
            <button
                type="button"
                class="btn-primary"
                :disabled="saving || loading || !selectedId"
                @click="linkSelected"
            >
                {{ saving ? '紐づけ中...' : 'この案件に紐づけ' }}
            </button>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>日時</th>
                        <th>記入者</th>
                        <th>件名</th>
                        <th>mailLink</th>
                        <th>From</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="note in filteredNotes"
                        :key="note.id"
                        class="table-row"
                        :class="{ selected: selectedId === note.id }"
                        @click="selectedId = note.id"
                        @dblclick="linkSelected"
                    >
                        <td class="col-date">{{ formatDateTime(note.whenWrote) }}</td>
                        <td class="col-author">{{ note.whoWrote || '—' }}</td>
                        <td class="col-subject" :title="note.subject || ''">{{ note.subject || '—' }}</td>
                        <td class="col-mail-link" @click.stop v-html="linkifyNote(note.mailLink)" />
                        <td class="col-from" :title="note.fromAddress || ''">{{ note.fromAddress || '—' }}</td>
                        <td class="col-actions" @click.stop>
                            <button
                                type="button"
                                class="btn-danger btn-sm"
                                :disabled="saving || deletingId === note.id"
                                @click="deleteNote(note)"
                            >
                                {{ deletingId === note.id ? '削除中...' : '削除' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!loading && !filteredNotes.length" class="empty-message">
                {{ notes.length ? '検索条件に一致する未登録メールはありません。' : '未登録メール Notes はありません。' }}
            </p>
        </div>
    </BaseDialog>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import BaseDialog from './BaseDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { linkifyText } from '@/utils/linkifyText'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const notes = ref([])
const selectedId = ref(null)
const searchQuery = ref('')
const loading = ref(false)
const saving = ref(false)
const deletingId = ref(null)
const loadError = ref('')
const error = ref('')

const filteredNotes = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return notes.value
    return notes.value.filter((note) => {
        const haystack = [
            note.mailLink,
            note.subject,
            note.whoWrote,
            note.fromAddress,
        ].map((v) => String(v || '').toLowerCase()).join(' ')
        return haystack.includes(q)
    })
})

function getApiBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer)\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function formatDateTime(value) {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)
    const pad = (n) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function linkifyNote(value) {
    const html = linkifyText(value)
    return html || '—'
}

async function loadNotes() {
    loading.value = true
    loadError.value = ''
    error.value = ''
    selectedId.value = null

    try {
        const url = `${window.location.origin}${getApiBasePath()}/unregistered-email-notes`
        const result = await apiFetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!result) {
            throw new Error('未登録メール Notes の取得に失敗しました。')
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `取得に失敗しました。（HTTP ${response.status}）`)
        }

        notes.value = Array.isArray(data.notes) ? data.notes : []
    } catch (e) {
        notes.value = []
        loadError.value = e.message || '取得に失敗しました。'
    } finally {
        loading.value = false
    }
}

async function linkSelected() {
    if (!selectedId.value || saving.value) return
    if (!props.record?.orderID) {
        error.value = '案件が選択されていません。'
        return
    }

    saving.value = true
    error.value = ''

    try {
        const url = `${window.location.origin}${getApiBasePath()}/unregistered-email-notes/${selectedId.value}/link`
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ orderID: props.record.orderID }),
        })

        if (!result) {
            throw new Error('紐づけに失敗しました。')
        }

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `紐づけに失敗しました。（HTTP ${response.status}）`)
        }

        emit('saved', data.note ?? true)
    } catch (e) {
        error.value = e.message || '紐づけに失敗しました。'
    } finally {
        saving.value = false
    }
}

async function deleteNote(note) {
    if (!note?.id || deletingId.value || saving.value) return

    const label = note.subject || note.mailLink || `ID ${note.id}`
    if (!window.confirm(`「${label}」を削除しますか？\nこの操作は取り消せません。`)) {
        return
    }

    deletingId.value = note.id
    error.value = ''

    try {
        const url = `${window.location.origin}${getApiBasePath()}/unregistered-email-notes/${note.id}`
        const result = await apiFetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!result) {
            throw new Error('削除に失敗しました。')
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `削除に失敗しました。（HTTP ${response.status}）`)
        }

        notes.value = notes.value.filter((item) => Number(item.id) !== Number(note.id))
        if (Number(selectedId.value) === Number(note.id)) {
            selectedId.value = null
        }
    } catch (e) {
        error.value = e.message || '削除に失敗しました。'
    } finally {
        deletingId.value = null
    }
}

onMounted(() => {
    loadNotes()
})
</script>

<style scoped>
.order-id {
    margin: 0 0 8px;
    font-weight: 700;
    color: #334155;
}

.help-text {
    margin: 0 0 12px;
    font-size: 13px;
    color: #64748b;
}

.search-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
}

.search-input {
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 400;
}

.dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-bottom: 12px;
}

.table-wrap {
    max-height: calc(96vh - 280px);
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.data-table th,
.data-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: middle;
}

.data-table th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    white-space: nowrap;
    z-index: 1;
}

.table-row {
    cursor: pointer;
}

.table-row:hover {
    background: #eff6ff;
}

.table-row.selected {
    background: #dbeafe;
}

.col-date,
.col-author {
    white-space: nowrap;
}

.col-subject,
.col-mail-link,
.col-from {
    max-width: 240px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.col-actions {
    width: 88px;
    white-space: nowrap;
}

:deep(.note-autolink) {
    color: #1d4ed8;
    text-decoration: underline;
}

.error-message {
    margin: 0 0 10px;
    color: #b91c1c;
    font-size: 13px;
}

.status-message,
.empty-message {
    margin: 0 0 10px;
    color: #64748b;
    font-size: 13px;
}

.btn-primary,
.btn-secondary,
.btn-danger {
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.btn-sm {
    padding: 5px 10px;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

.btn-danger {
    background: #dc2626;
    color: #fff;
}

.btn-primary:disabled,
.btn-secondary:disabled,
.btn-danger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
