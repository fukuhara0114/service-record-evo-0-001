<template>
    <BaseDialog title="ファイル追加" @close="$emit('close')">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>

        <label class="form-field">
            ファイル
            <input
                ref="fileInput"
                type="file"
                class="form-input"
                @change="onFileChange"
            >
        </label>

        <label class="form-field">
            表示名（任意）
            <input v-model="documentName" type="text" class="form-input" placeholder="未入力時はファイル名を使用">
        </label>

        <label class="form-field">
            ドキュメント種別（必須）
            <input v-model="documentType" type="text" class="form-input" placeholder="例: 見積書、修理報告書" required>
        </label>

        <label class="form-field">
            表示順（任意）
            <input v-model.number="sortNum" type="number" class="form-input" placeholder="数値が小さいほど先に表示">
        </label>

        <p v-if="selectedFileName" class="file-info">選択中: {{ selectedFileName }}</p>
        <p v-if="error" class="error-message">{{ error }}</p>

        <template #footer>
            <button type="button" class="btn-secondary" :disabled="saving" @click="$emit('close')">
                キャンセル
            </button>
            <button type="button" class="btn-primary" :disabled="saving || !selectedFile || !documentType.trim()" @click="save">
                {{ saving ? 'アップロード中...' : '追加' }}
            </button>
        </template>
    </BaseDialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import BaseDialog from './BaseDialog.vue'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const fileInput = ref(null)
const selectedFile = ref(null)
const selectedFileName = ref('')
const documentName = ref('')
const documentType = ref('')
const sortNum = ref(null)
const saving = ref(false)
const error = ref('')

watch(
    () => props.payload,
    () => {
        selectedFile.value = null
        selectedFileName.value = ''
        documentName.value = ''
        documentType.value = ''
        sortNum.value = null
        error.value = ''
        if (fileInput.value) {
            fileInput.value.value = ''
        }
    },
    { immediate: true },
)

function onFileChange(event) {
    const file = event.target.files?.[0] ?? null
    selectedFile.value = file
    selectedFileName.value = file?.name ?? ''
    if (file && !documentName.value) {
        documentName.value = file.name
    }
}

function getApiBasePath() {
    return window.location.pathname.replace(/\/administrator\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function save() {
    if (!selectedFile.value) {
        error.value = 'ファイルを選択してください。'
        return
    }

    if (!documentType.value.trim()) {
        error.value = 'ドキュメント種別を入力してください。'
        return
    }

    saving.value = true
    error.value = ''

    const formData = new FormData()
    formData.append('associatedID', props.record?.orderID)
    formData.append('file', selectedFile.value)
    if (documentName.value.trim()) {
        formData.append('documentName', documentName.value.trim())
    }
    formData.append('documentType', documentType.value.trim())
    if (sortNum.value != null && sortNum.value !== '') {
        formData.append('sortNum', sortNum.value)
    }

    const basePath = getApiBasePath()
    const url = `${window.location.origin}${basePath}/files`

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: formData,
        })

        const data = await response.json().catch(() => ({}))

        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `アップロードに失敗しました。（HTTP ${response.status}）`)
        }

        emit('saved', data)
    } catch (e) {
        error.value = e.message || 'アップロードに失敗しました。'
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

.form-input {
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

.file-info {
    margin: 0 0 8px;
    font-size: 13px;
    color: #475569;
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
