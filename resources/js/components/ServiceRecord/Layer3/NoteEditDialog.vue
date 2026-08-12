<template>
    <BaseDialog :title="title" @close="$emit('close')">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>

        <label class="form-field">
            内容
            <textarea v-model="noteText" rows="6" class="form-textarea" />
        </label>

        <label v-if="!payload?.remand" class="form-checkbox">
            <input v-model="important" type="checkbox">
            重要
        </label>
        <p v-else class="remand-important-hint">差戻理由は重要 Note として登録されます。</p>

        <div class="confirm-toggles">
            <button
                type="button"
                class="toggle-btn"
                :class="{ on: tbc }"
                @click="toggleTbc"
            >
                要
            </button>
            <button
                v-if="tbc"
                type="button"
                class="toggle-btn toggle-btn-done"
                :class="{ on: done }"
                @click="done = !done"
            >
                済
            </button>
        </div>

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
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const noteText = ref('')
const important = ref(false)
const tbc = ref(false)
const done = ref(false)
const saving = ref(false)
const error = ref('')

const isEdit = computed(() => props.payload?.mode === 'edit')
const title = computed(() => {
    if (isEdit.value) return 'Note 編集'
    if (props.payload?.remand) return '差戻理由'
    return props.payload?.personal ? 'Personal Note 新規追加' : 'Note 新規追加'
})

watch(
    () => props.payload,
    (payload) => {
        if (payload?.mode === 'edit' && payload.note) {
            noteText.value = payload.note.note ?? ''
            important.value = !!payload.note.important
            tbc.value = isTruthyFlag(payload.note.tbc)
            done.value = tbc.value && isTruthyFlag(payload.note.done)
        } else {
            noteText.value = ''
            important.value = !!payload?.remand
            tbc.value = false
            done.value = false
        }
        error.value = ''
    },
    { immediate: true },
)

function getApiBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function isTruthyFlag(value) {
    return value === true || value === 1 || value === '1'
}

function toggleTbc() {
    tbc.value = !tbc.value
    if (!tbc.value) done.value = false
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

    const noteBody = (() => {
        const text = noteText.value.trim()
        if (props.payload?.remand) {
            return text.startsWith('[差戻理由]') ? text : `[差戻理由] ${text}`
        }
        return text
    })()

    const confirmFlags = {
        tbc: tbc.value ? true : null,
        done: tbc.value && done.value ? true : null,
    }

    const body = isEdit.value
        ? { note: noteBody, important: important.value, ...confirmFlags }
        : {
            associatedID: props.record?.orderID,
            note: noteBody,
            important: props.payload?.remand ? true : important.value,
            personal: !!props.payload?.personal,
            ...confirmFlags,
        }

    try {
        const result = await apiFetch(url, {
            method: isEdit.value ? 'PUT' : 'POST',
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
            throw new Error(data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        emit('saved', {
            ...data,
            remand: !!props.payload?.remand,
        })
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

.confirm-toggles {
    display: flex;
    gap: 8px;
    margin: 0 0 12px;
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

.remand-important-hint {
    margin: 0 0 8px;
    color: #9a3412;
    font-size: 13px;
    font-weight: 700;
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
