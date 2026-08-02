<template>
    <BaseDialog :title="title" @close="$emit('close')">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>

        <div class="part-summary">
            <div><span class="label">partID</span><strong>{{ partID }}</strong></div>
            <div><span class="label">部品名</span><strong>{{ partName || '—' }}</strong></div>
            <div><span class="label">説明</span><strong>{{ description || '—' }}</strong></div>
        </div>

        <label class="form-field">
            使用数（quantity）
            <input
                ref="quantityInputEl"
                v-model="quantityText"
                type="number"
                min="1"
                step="1"
                class="form-input"
                placeholder="1 以上の整数"
                @keydown.enter.prevent="save"
            >
        </label>

        <p class="help-text">使用数は必須です。1 以上の整数を入力してください。</p>
        <p v-if="error" class="error-message">{{ error }}</p>

        <template #footer>
            <button type="button" class="btn-secondary" :disabled="saving" @click="$emit('close')">
                キャンセル
            </button>
            <button type="button" class="btn-primary" :disabled="saving || !isQuantityValid" @click="save">
                {{ saving ? '保存中...' : (isEdit ? '数量を更新' : '追加') }}
            </button>
        </template>
    </BaseDialog>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import BaseDialog from './BaseDialog.vue'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const quantityInputEl = ref(null)
const quantityText = ref('')
const saving = ref(false)
const error = ref('')

const isEdit = computed(() => props.payload?.mode === 'edit')
const title = computed(() => (isEdit.value ? 'stocked Parts 数量編集' : 'stocked Parts 使用数入力'))

const partID = computed(() =>
    props.payload?.partID
    ?? props.payload?.stockedPart?.partID
    ?? '',
)
const partName = computed(() =>
    props.payload?.partName
    ?? props.payload?.stockedPart?.stocked_part_master?.partName
    ?? '',
)
const description = computed(() =>
    props.payload?.description
    ?? props.payload?.stockedPart?.stocked_part_master?.description
    ?? '',
)

const quantityValue = computed(() => {
    const num = Number(quantityText.value)
    if (!Number.isFinite(num) || !Number.isInteger(num)) return null
    return num
})

const isQuantityValid = computed(() => quantityValue.value != null && quantityValue.value >= 1)

watch(
    () => props.payload,
    (payload) => {
        if (payload?.mode === 'edit') {
            quantityText.value = String(payload?.stockedPart?.quantity ?? '')
        } else {
            quantityText.value = ''
        }
        error.value = ''
    },
    { immediate: true },
)

onMounted(async () => {
    await nextTick()
    quantityInputEl.value?.focus()
    quantityInputEl.value?.select?.()
})

function getApiBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer)\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function save() {
    if (!isQuantityValid.value) {
        error.value = '使用数は 1 以上の整数で入力してください。'
        return
    }

    saving.value = true
    error.value = ''

    const basePath = getApiBasePath()
    const quantity = quantityValue.value

    try {
        let result
        if (isEdit.value) {
            const id = props.payload?.stockedPart?.id ?? props.payload?.partId
            if (!id) throw new Error('編集対象が見つかりません。')
            result = await apiFetch(`${window.location.origin}${basePath}/stocked-parts/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ quantity }),
            })
        } else {
            if (!partID.value) throw new Error('部品が選択されていません。')
            result = await apiFetch(`${window.location.origin}${basePath}/stocked-parts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    associatedID: props.record?.orderID,
                    partID: partID.value,
                    quantity,
                }),
            })
        }

        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
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

.part-summary {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 14px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
}

.part-summary .label {
    display: inline-block;
    min-width: 56px;
    margin-right: 8px;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.part-summary strong {
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
}

.form-field {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    font-size: 14px;
    color: #1e293b;
}

.form-input {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    font-size: 14px;
}

.help-text {
    margin: 0 0 8px;
    color: #64748b;
    font-size: 12px;
}

.error-message {
    margin: 0;
    color: #b91c1c;
    font-size: 13px;
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

.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
