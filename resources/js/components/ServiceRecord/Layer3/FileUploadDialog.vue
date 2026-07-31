<template>
    <BaseDialog title="ファイル追加" @close="$emit('close')">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>

        <label class="form-field">
            ファイル
            <input
                ref="fileInput"
                type="file"
                class="form-input"
                multiple
                @change="onFileChange"
            >
        </label>

        <label class="form-field">
            表示名（任意）
            <input v-model="documentName" type="text" class="form-input" placeholder="未入力時はファイル名を使用">
        </label>

        <label class="form-field">
            ドキュメント種別（必須）
            <input v-model="documentType" type="text" class="form-input" placeholder="例: メール、見積書、修理報告書" required>
        </label>

        <label class="form-field">
            表示順（任意）
            <input v-model.number="sortNum" type="number" class="form-input" placeholder="数値が小さいほど先に表示">
        </label>

        <p v-if="selectedFileNames.length" class="file-info">選択中: {{ selectedFileNames.join(', ') }}</p>
        <p v-if="error" class="error-message">{{ error }}</p>

        <template #footer>
            <button type="button" class="btn-secondary" :disabled="saving" @click="$emit('close')">
                キャンセル
            </button>
            <button type="button" class="btn-primary" :disabled="saving || !selectedFiles.length || !documentType.trim()" @click="save">
                {{ saving ? 'アップロード中...' : '追加' }}
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

const fileInput = ref(null)
const selectedFiles = ref([])
const documentName = ref('')
const documentType = ref('')
const sortNum = ref(null)
const saving = ref(false)
const error = ref('')

const selectedFileNames = computed(() => selectedFiles.value.map(file => file.name).filter(Boolean))

watch(
    () => props.payload,
    () => {
        selectedFiles.value = []
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

function guessDocumentType(file) {
    const name = String(file?.name || '').toLowerCase()
    const type = String(file?.type || '').toLowerCase()
    if (name.endsWith('.eml') || name.endsWith('.msg') || type.includes('message') || type.includes('ms-outlook')) {
        return 'メール'
    }
    if (type === 'application/pdf' || name.endsWith('.pdf')) {
        return 'PDF'
    }
    if (type.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|tiff?)$/i.test(name)) {
        return '画像'
    }
    return '添付ファイル'
}

function onFileChange(event) {
    const files = [...(event.target.files ?? [])]
    selectedFiles.value = files
    if (files[0] && !documentName.value) {
        documentName.value = files.length === 1 ? files[0].name : ''
    }
    if (files[0] && !documentType.value.trim()) {
        documentType.value = guessDocumentType(files[0])
    }
}

function getApiBasePath() {
    return window.location.pathname.replace(/\/administrator\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function uploadOne(file, options = {}) {
    const formData = new FormData()
    formData.append('associatedID', props.record?.orderID)
    formData.append('file', file)
    if (options.documentName) {
        formData.append('documentName', options.documentName)
    }
    formData.append('documentType', options.documentType)
    if (options.sortNum != null && options.sortNum !== '') {
        formData.append('sortNum', options.sortNum)
    }

    const basePath = getApiBasePath()
    const url = `${window.location.origin}${basePath}/files`

    const result = await apiFetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: formData,
    })

    if (!result) {
        throw new Error('アップロードに失敗しました。')
    }

    const { response, data } = result
    if (!response.ok) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : null
        throw new Error(validationMessage || data.message || `アップロードに失敗しました。（HTTP ${response.status}）`)
    }

    return data
}

async function save() {
    if (!selectedFiles.value.length) {
        error.value = 'ファイルを選択してください。'
        return
    }

    if (!documentType.value.trim()) {
        error.value = 'ドキュメント種別を入力してください。'
        return
    }

    saving.value = true
    error.value = ''

    try {
        let lastResult = null
        let currentSort = sortNum.value
        for (let i = 0; i < selectedFiles.value.length; i += 1) {
            const file = selectedFiles.value[i]
            lastResult = await uploadOne(file, {
                documentName: selectedFiles.value.length === 1
                    ? (documentName.value.trim() || file.name)
                    : file.name,
                documentType: documentType.value.trim(),
                sortNum: currentSort,
            })
            if (currentSort != null && currentSort !== '') {
                currentSort = Number(currentSort) + 10
            }
        }
        emit('saved', lastResult)
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
