<template>
    <div class="detail-overlay" @click.self="$emit('close')">
        <div class="detail-panel">
            <div class="detail-header">
                <div class="layout-tabs">
                    <button
                        v-for="tab in ['A', 'B', 'C']"
                        :key="tab"
                        type="button"
                        class="tab-btn"
                        :class="{ active: layout === tab }"
                        @click="$emit('switch-layout', tab)"
                    >
                        詳細 {{ tab }}
                    </button>
                </div>
                <div class="detail-meta">
                    <span>OrderID: {{ record?.orderID }}</span>
                    <button type="button" class="close-btn" @click="$emit('close')">× 閉じる</button>
                </div>
            </div>

            <div class="detail-body">
                <DetailFormA
                    v-if="layout === 'A'"
                    :record="record"
                    :notes="notes"
                    :files="files"
                    :parts="parts"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                />
                <DetailFormB
                    v-else-if="layout === 'B'"
                    :record="record"
                    :notes="notes"
                    :files="files"
                    :parts="parts"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                />
                <DetailFormC
                    v-else-if="layout === 'C'"
                    :record="record"
                    :notes="notes"
                    :files="files"
                    :parts="parts"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import DetailFormA from './DetailFormA.vue'
import DetailFormB from './DetailFormB.vue'
import DetailFormC from './DetailFormC.vue'

defineProps({
    record: Object,
    notes: {
        type: Array,
        default: () => [],
    },
    files: {
        type: Array,
        default: () => [],
    },
    parts: {
        type: Array,
        default: () => [],
    },
    attachmentsLoading: {
        type: Boolean,
        default: false,
    },
    attachmentsError: {
        type: String,
        default: '',
    },
    layout: {
        type: String,
        default: 'A',
    },
})

defineEmits(['close', 'switch-layout', 'open-dialog'])
</script>

<style scoped>
.detail-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 100;
    display: flex;
    justify-content: center;
    align-items: stretch;
}

.detail-panel {
    width: 100%;
    height: 100%;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    background: #1e293b;
    color: white;
    border-bottom: 2px solid #3b82f6;
}

.layout-tabs {
    display: flex;
    gap: 8px;
}

.tab-btn {
    padding: 6px 14px;
    border: 1px solid #64748b;
    border-radius: 4px;
    background: #334155;
    color: white;
    cursor: pointer;
}

.tab-btn.active {
    background: #2563eb;
    border-color: #2563eb;
}

.detail-meta {
    display: flex;
    align-items: center;
    gap: 16px;
}

.close-btn {
    padding: 6px 12px;
    background: #64748b;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.detail-body {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 20px;
}
</style>
