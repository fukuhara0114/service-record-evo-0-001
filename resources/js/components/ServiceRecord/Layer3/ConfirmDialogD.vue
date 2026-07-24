<template>
    <BaseDialog :title="title" @close="$emit('close')">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>
        <p>{{ message }}</p>

        <div v-if="isDeleteNote && payload?.note" class="note-preview">
            {{ payload.note.note }}
        </div>

        <div v-if="isDeleteFile && payload?.file" class="file-preview">
            <span class="file-name">{{ payload.file.documentName || '（名称なし）' }}</span>
            <span v-if="payload.file.fileType" class="file-type">{{ payload.file.fileType }}</span>
        </div>

        <div v-if="isDeleteFile" class="delete-mode-options">
            <label class="delete-mode-option">
                <input v-model="fileDeleteMode" type="radio" value="unlink">
                <span>
                    <strong>関連付けを削除</strong>
                    <small>案件からの関連付けのみ解除します（associatedID を -1 に変更、ファイルデータは残ります）</small>
                </span>
            </label>
            <label class="delete-mode-option">
                <input v-model="fileDeleteMode" type="radio" value="delete">
                <span>
                    <strong>データベースから削除</strong>
                    <small>ファイルを完全に削除します（元に戻せません）</small>
                </span>
            </label>
        </div>

        <p v-if="error" class="error-message">{{ error }}</p>

        <template #footer>
            <button type="button" class="btn-secondary" :disabled="processing" @click="$emit('close')">
                キャンセル
            </button>
            <template v-if="isDeleteFile">
                <button
                    type="button"
                    class="btn-warning"
                    :disabled="processing"
                    @click="deleteFile('unlink')"
                >
                    {{ processing && fileDeleteMode === 'unlink' ? '処理中...' : '関連付けを削除' }}
                </button>
                <button
                    type="button"
                    class="btn-danger"
                    :disabled="processing"
                    @click="deleteFile('delete')"
                >
                    {{ processing && fileDeleteMode === 'delete' ? '処理中...' : 'DBから削除' }}
                </button>
            </template>
            <button
                v-else
                type="button"
                :class="isDestructive ? 'btn-danger' : 'btn-primary'"
                :disabled="processing"
                @click="confirm"
            >
                {{ processing ? '処理中...' : confirmLabel }}
            </button>
        </template>
    </BaseDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import BaseDialog from './BaseDialog.vue'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const processing = ref(false)
const error = ref('')
const fileDeleteMode = ref('unlink')

watch(
    () => props.payload,
    (payload) => {
        fileDeleteMode.value = 'unlink'
        if (payload?.action !== 'delete-file') {
            error.value = ''
        }
    },
    { immediate: true },
)

const isDeleteNote = computed(() => props.payload?.action === 'delete-note')
const isDeleteFile = computed(() => props.payload?.action === 'delete-file')
const isDestructive = computed(() => isDeleteNote.value || isDeleteFile.value)

const title = computed(() => {
    if (isDeleteNote.value) return 'Note 削除確認'
    if (isDeleteFile.value) return 'ファイル削除確認'
    return '確認'
})

const message = computed(() => {
    if (isDeleteNote.value) {
        return '選択した Note を削除してよろしいですか？'
    }
    if (isDeleteFile.value) {
        return '削除方法を選択してください。'
    }
    return 'この内容で保存してよろしいですか？'
})

const confirmLabel = computed(() => (isDestructive.value ? '削除' : '保存'))

function getApiBasePath() {
    return window.location.pathname.replace(/\/administrator\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function confirm() {
    if (isDeleteNote.value) {
        await deleteNote()
        return
    }

    if (isDeleteFile.value) {
        await deleteFile(fileDeleteMode.value)
        return
    }

    emit('saved', { confirmed: true })
}

async function deleteNote() {
    const noteId = props.payload?.noteId ?? props.payload?.note?.id
    if (!noteId) {
        error.value = '削除対象の Note が見つかりません。'
        return
    }

    processing.value = true
    error.value = ''

    const basePath = getApiBasePath()
    const url = `${window.location.origin}${basePath}/notes/${noteId}`

    try {
        const result = await apiFetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        })

        if (!result) {
            return
        }

        const { response, data } = result

        if (!response.ok) {
            throw new Error(data.message || `削除に失敗しました。（HTTP ${response.status}）`)
        }

        emit('saved', data)
    } catch (e) {
        error.value = e.message || '削除に失敗しました。'
    } finally {
        processing.value = false
    }
}

async function deleteFile(mode = 'delete') {
    const fileId = props.payload?.fileId ?? props.payload?.file?.id
    if (!fileId) {
        error.value = '削除対象のファイルが見つかりません。'
        return
    }

    processing.value = true
    fileDeleteMode.value = mode
    error.value = ''

    const basePath = getApiBasePath()
    const params = new URLSearchParams({ mode })
    if (mode === 'unlink' && props.record?.orderID != null) {
        params.set('orderID', props.record.orderID)
    }
    const url = `${window.location.origin}${basePath}/files/${fileId}?${params.toString()}`

    try {
        const result = await apiFetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        })

        if (!result) {
            return
        }

        const { response, data } = result

        if (!response.ok) {
            throw new Error(data.message || `削除に失敗しました。（HTTP ${response.status}）`)
        }

        emit('saved', data)
    } catch (e) {
        error.value = e.message || '削除に失敗しました。'
    } finally {
        processing.value = false
    }
}
</script>

<style scoped>
.order-id {
    margin: 0 0 12px;
    color: #475569;
    font-size: 14px;
}

.note-preview {
    margin-top: 12px;
    padding: 10px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 14px;
}

.file-preview {
    margin-top: 12px;
    padding: 10px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    font-size: 14px;
}

.file-name {
    font-weight: bold;
}

.file-type {
    color: #64748b;
}

.delete-mode-options {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.delete-mode-option {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.delete-mode-option input {
    margin-top: 3px;
}

.delete-mode-option small {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-weight: normal;
}

.error-message {
    margin: 12px 0 0;
    color: #b91c1c;
    font-size: 14px;
}

.btn-primary,
.btn-secondary,
.btn-danger,
.btn-warning {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    color: white;
}

.btn-primary {
    background: #2563eb;
}

.btn-secondary {
    background: #6b7280;
}

.btn-danger {
    background: #dc2626;
}

.btn-warning {
    background: #d97706;
}

.btn-primary:disabled,
.btn-secondary:disabled,
.btn-danger:disabled,
.btn-warning:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
