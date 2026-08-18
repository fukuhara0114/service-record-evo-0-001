<template>
    <div class="detail-form">
        <h2>詳細フォーム C</h2>
        <dl class="info-grid">
            <dt>OrderID</dt><dd>{{ record?.orderID }}</dd>
            <dt>製品名</dt><dd>{{ record?.productName }}</dd>
            <dt>S/N</dt><dd>{{ record?.SN }}</dd>
            <dt>ステータス</dt><dd>{{ resolvedStatusLabel }}</dd>
            <dt>販売店</dt><dd>{{ record?.dealer }}</dd>
        </dl>

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
import { computed } from 'vue'
import { loanerStatusLabel } from '@/utils/loanerStatusLabel'

const props = defineProps({
    record: Object,
})

defineEmits(['open-dialog'])

const resolvedStatusLabel = computed(() => {
    if (props.record?.order_type === 'waiting_list') return ''
    if (props.record?.order_type === 'loaner') {
        return loanerStatusLabel(props.record?.status_master_loaner)
            || loanerStatusLabel(props.record?.resolved_status_master)
            || ''
    }
    return props.record?.status_master?.status || ''
})
</script>

<style scoped>
.detail-form h2 {
    margin-bottom: 16px;
}

.info-grid {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 8px 16px;
    margin-bottom: 24px;
}

.info-grid dt {
    font-weight: bold;
    color: #475569;
}

.action-row {
    display: flex;
    gap: 12px;
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