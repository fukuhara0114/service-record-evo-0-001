<template>
    <div class="dialog-overlay" @click.self="$emit('close')">
        <div
            class="dialog-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="logistics-loaner-return-btn"
        >
            <header class="dialog-header">
                <div class="header-spacer" />
                <button
                    id="logistics-loaner-return-btn"
                    type="button"
                    class="return-btn"
                    :disabled="saving"
                    @click="onReturn"
                >
                    {{ saving ? '処理中...' : '返却' }}
                </button>
                <button
                    type="button"
                    class="close-btn"
                    aria-label="閉じる"
                    :disabled="saving"
                    @click="$emit('close')"
                >
                    X
                </button>
            </header>

            <div class="dialog-body">
                <section class="meta-row">
                    <div class="meta-item">
                        <span class="meta-label">出荷日</span>
                        <span class="meta-value">{{ displayDate(record?.sentOut) }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">製品名</span>
                        <span class="meta-value">{{ displayText(record?.productName) }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">item</span>
                        <span class="meta-value">{{ displayText(record?.item) }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">SN</span>
                        <span class="meta-value">{{ displayText(record?.SN) }}</span>
                    </div>
                </section>

                <section class="info-grid">
                    <div class="info-card">
                        <h4>dealer</h4>
                        <p>{{ displayText(record?.dealer) }}</p>
                        <p>{{ displayText(record?.dealer_depart) }}</p>
                        <p>{{ displayText(record?.contactPerson) }}</p>
                        <p>〒 {{ displayText(record?.zipcode) }}</p>
                        <p>{{ displayText(record?.address1) }}</p>
                        <p>{{ displayText(record?.address2) }}</p>
                        <p>Phone: {{ displayText(record?.phone) }}</p>
                        <p>E-mail: {{ displayText(record?.email) }}</p>
                    </div>
                    <div class="info-card">
                        <h4>enduser</h4>
                        <p>{{ displayText(record?.endUser) }}</p>
                        <p>{{ displayText(record?.endUser_depart) }}</p>
                        <p>{{ displayText(record?.endUser_contactPerson) }}</p>
                        <p>〒 {{ displayText(record?.endUser_zipcode) }}</p>
                        <p>{{ displayText(record?.endUser_address1) }}</p>
                        <p>{{ displayText(record?.endUser_address2) }}</p>
                        <p>Phone: {{ displayText(record?.endUser_phone) }}</p>
                        <p>E-mail: {{ displayText(record?.endUser_email) }}</p>
                    </div>
                    <div class="info-card">
                        <h4>delivery</h4>
                        <p>{{ displayText(record?.deliveryDestination_company) }}</p>
                        <p>{{ displayText(record?.deliveryDestination_depart) }}</p>
                        <p>{{ displayText(record?.deliveryDestination_contactPerson) }}</p>
                        <p>〒 {{ displayText(record?.deliveryDestination_zipcode) }}</p>
                        <p>{{ displayText(record?.deliveryDestination_address1) }}</p>
                        <p>{{ displayText(record?.deliveryDestination_address2) }}</p>
                        <p>Phone: {{ displayText(record?.deliveryDestination_phone) }}</p>
                        <p>E-mail: {{ displayText(record?.deliveryDestination_email) }}</p>
                    </div>
                </section>

                <p v-if="errorMessage" class="error-message">{{ errorMessage }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { apiFetch } from '@/utils/apiFetch'

const LOANER_RETURNED_STATUS = 393

const props = defineProps({
    record: { type: Object, required: true },
})

const emit = defineEmits(['close', 'returned'])

const saving = ref(false)
const errorMessage = ref('')

function displayText(value) {
    const text = String(value ?? '').trim()
    return text || '—'
}

function displayDate(value) {
    if (value == null || value === '') return '—'
    if (typeof value === 'string') return value.slice(0, 10)
    if (value instanceof Date) {
        const pad = (n) => String(n).padStart(2, '0')
        return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`
    }
    return String(value).slice(0, 10) || '—'
}

function getBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function onReturn() {
    if (saving.value) return
    if (!window.confirm('status を「返却」(393) に変更しますか？')) return

    if (!props.record?.orderID) {
        errorMessage.value = '案件が選択されていません。'
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
            body: JSON.stringify({ status: LOANER_RETURNED_STATUS }),
        })
        if (!result?.response?.ok) {
            throw new Error(result?.data?.message || `更新に失敗しました。（HTTP ${result?.response?.status ?? ''}）`)
        }
        emit('returned', { status: LOANER_RETURNED_STATUS })
    } catch (e) {
        errorMessage.value = e.message || '返却処理に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 220;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 16px;
}

.dialog-panel {
    width: min(1100px, 96vw);
    max-height: 92vh;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
}

.dialog-header {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    padding: 10px 12px;
    background: #1e293b;
    color: #fff;
}

.header-spacer {
    min-width: 0;
}

.return-btn {
    justify-self: center;
    min-width: 96px;
    padding: 8px 28px;
    border: none;
    border-radius: 6px;
    background: #dc2626;
    color: #fff;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
}

.return-btn:hover:not(:disabled) {
    background: #b91c1c;
}

.return-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.close-btn {
    justify-self: end;
    width: 36px;
    height: 36px;
    padding: 0;
    border: none;
    background: transparent;
    color: #fff;
    font-size: 22px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
}

.close-btn:hover:not(:disabled) {
    color: #fecaca;
}

.close-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.dialog-body {
    padding: 16px 18px 18px;
    overflow: auto;
}

.meta-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

.meta-item {
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
}

.meta-label {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
}

.meta-value {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    word-break: break-word;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.info-card {
    min-width: 0;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
}

.info-card h4 {
    margin: 0 0 8px;
    font-size: 14px;
    font-weight: 800;
    color: #334155;
}

.info-card p {
    margin: 0 0 3px;
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    word-break: break-word;
}

.error-message {
    margin: 12px 0 0;
    color: #b91c1c;
    font-size: 13px;
    font-weight: 700;
}

@media (max-width: 900px) {
    .meta-row,
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
