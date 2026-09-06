<template>
    <BaseDialog title="受入完了" :show-close="!saving" @close="onClose">
        <p class="order-id">OrderID: {{ record?.orderID }}</p>
        <p class="message">この案件の受入を完了しますか？</p>
        <p v-if="productLabel" class="meta">{{ productLabel }}</p>
        <p v-if="errorMessage" class="error-message">{{ errorMessage }}</p>

        <template #footer>
            <button type="button" class="btn-secondary" :disabled="saving" @click="onClose">
                キャンセル
            </button>
            <button type="button" class="btn-primary" :disabled="saving" @click="onAccept">
                {{ saving ? '処理中...' : '受入完了' }}
            </button>
        </template>
    </BaseDialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import BaseDialog from './BaseDialog.vue'
import { apiFetch } from '@/utils/apiFetch'
import { confirmOrderTypeOriginalMismatchForRecord } from '@/utils/confirmOrderTypeOriginalMismatch'

const PRE_COMPLETE_STATUS = 399

const props = defineProps({
    record: { type: Object, required: true },
})

const emit = defineEmits(['close', 'accepted'])

const saving = ref(false)
const errorMessage = ref('')

const productLabel = computed(() => {
    const name = String(props.record?.productName ?? '').trim()
    const item = String(props.record?.item ?? '').trim()
    return [name, item].filter(Boolean).join(' / ')
})

function getBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function onClose() {
    if (saving.value) return
    emit('close')
}

async function onAccept() {
    if (saving.value) return

    if (!props.record?.orderID) {
        errorMessage.value = '案件が選択されていません。'
        return
    }

    if (!confirmOrderTypeOriginalMismatchForRecord(props.record)) {
        return
    }

    saving.value = true
    errorMessage.value = ''
    try {
        const url = `${window.location.origin}${getBasePath()}/${props.record.orderID}`
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ status: PRE_COMPLETE_STATUS }),
        })
        if (!result?.response?.ok) {
            throw new Error(result?.data?.message || `更新に失敗しました。（HTTP ${result?.response?.status ?? ''}）`)
        }
        emit('accepted', { status: PRE_COMPLETE_STATUS })
    } catch (e) {
        errorMessage.value = e.message || '受入完了の処理に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.order-id {
    margin: 0 0 8px;
    color: #475569;
    font-size: 14px;
}

.message {
    margin: 0;
    font-size: 14px;
}

.meta {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 13px;
}

.error-message {
    margin: 12px 0 0;
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
    color: white;
}

.btn-primary {
    background: #2563eb;
}

.btn-secondary {
    background: #6b7280;
}

.btn-primary:disabled,
.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
