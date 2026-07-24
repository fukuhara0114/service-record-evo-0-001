<template>
    <BaseDialog :title="title" @close="$emit('close')">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>

        <label class="form-field">
            内容
            <textarea v-model="noteText" rows="6" class="form-textarea" />
        </label>

        <label class="form-checkbox">
            <input v-model="important" type="checkbox">
            重要
        </label>

        <p v-if="error" class="error-message">{{ error }}</p>

        <template #footer>
            <button type="button" class="btn-secondary" :disabled="saving" @click="$emit('close')">
                キャンセル
            </button>
            <button type="button" class="btn-primary" :disabled="saving || !noteText.trim()" @click="save">
                {{ saving ? '保存中...' : '保存' }}
            </button>
        </template>
    </BaseDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import BaseDialog from './BaseDialog.vue'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const noteText = ref('')
const important = ref(false)
const saving = ref(false)
const error = ref('')

const isEdit = computed(() => props.payload?.mode === 'edit')
const title = computed(() => (isEdit.value ? 'Note 編集' : 'Note 新規追加'))

watch(
    () => props.payload,
    (payload) => {
        if (payload?.mode === 'edit' && payload.note) {
            noteText.value = payload.note.note ?? ''
            important.value = !!payload.note.important
        } else {
            noteText.value = ''
            important.value = false
        }
        error.value = ''
    },
    { immediate: true },
)

function getApiBasePath() {
    return window.location.pathname.replace(/\/administrator\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function save() {
    if (!noteText.value.trim()) {
        error.value = '内容を入力してください。'
        return
    }

    saving.value = true
    error.value = ''

    const basePath = getApiBasePath()
    const url = isEdit.value
        ? `${window.location.origin}${basePath}/notes/${props.payload.note.id}`
        : `${window.location.origin}${basePath}/notes`

    const body = isEdit.value
        ? { note: noteText.value.trim(), important: important.value }
        : {
            associatedID: props.record?.orderID,
            note: noteText.value.trim(),
            important: important.value,
        }

    try {
        const response = await fetch(url, {
            method: isEdit.value ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        })

        const data = await response.json().catch(() => ({}))

        if (!response.ok) {
            throw new Error(data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        emit('saved', data)
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.order-id {
    margin: 0 0 12px;
    color: #475569;
    font-size: 14px;
}

.form-field {
    display: block;
    margin-bottom: 12px;
    font-weight: bold;
    font-size: 14px;
}

.form-textarea {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 14px;
    font-weight: normal;
    box-sizing: border-box;
}

.form-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    margin-bottom: 8px;
}

.error-message {
    margin: 8px 0 0;
    color: #b91c1c;
    font-size: 14px;
}

.btn-primary,
.btn-secondary {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}
</style>
