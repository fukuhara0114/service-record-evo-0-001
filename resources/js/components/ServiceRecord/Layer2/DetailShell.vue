<template>
    <div class="detail-overlay" @click.self="$emit('close')">
        <div class="detail-panel">
            <div class="detail-header">
                <div class="layout-tabs">
                    <template v-if="mode === 'engineer'">
                        <span class="engineer-title">Engineer 詳細</span>
                    </template>
                    <template v-else-if="layout === 'closing'">
                        <span class="closing-title">Closing 詳細</span>
                    </template>
                    <template v-else-if="layout === 'invoice'">
                        <span class="closing-title">Invoice 詳細</span>
                    </template>
                    <template v-else>
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
                    </template>
                </div>
                <div class="detail-meta">
                    <span>OrderID: {{ record?.orderID }}</span>
                    <p v-if="saveError" class="save-error">{{ saveError }}</p>
                    <button
                        v-if="mode !== 'engineer' && layout !== 'closing' && layout !== 'invoice'"
                        type="button"
                        class="save-btn"
                        :disabled="savingRecord"
                        @click="$emit('save')"
                    >
                        {{ savingRecord ? '保存中...' : '保存' }}
                    </button>
                    <button type="button" class="close-btn" @click="$emit('close')">× 閉じる</button>
                </div>
            </div>

            <div
                class="detail-body"
                :class="{
                    'detail-body-engineer': mode === 'engineer',
                    'detail-body-closing': layout === 'closing' || layout === 'invoice',
                }"
            >
                <DetailFormEngineer
                    v-if="mode === 'engineer'"
                    :record="record"
                    :draft-record="draftRecord"
                    :notes="notes"
                    :files="files"
                    :captured-images="capturedImages"
                    :parts="parts"
                    :stocked-parts="stockedParts"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                    @files-updated="(nextFiles) => $emit('files-updated', nextFiles)"
                    @reload-attachments="$emit('reload-attachments')"
                    @workflow-done="(payload) => $emit('workflow-done', payload)"
                />
                <DetailFormClosing
                    v-else-if="layout === 'closing'"
                    :record="record"
                    :draft-record="draftRecord"
                    :notes="notes"
                    :files="files"
                    :captured-images="capturedImages"
                    :parts="parts"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                    @files-updated="(nextFiles) => $emit('files-updated', nextFiles)"
                    @reload-attachments="$emit('reload-attachments')"
                    @save="$emit('save')"
                    @workflow-done="(payload) => $emit('workflow-done', payload)"
                />
                <DetailFormInvoice
                    v-else-if="layout === 'invoice'"
                    :record="record"
                    :draft-record="draftRecord"
                    :notes="notes"
                    :files="files"
                    :captured-images="capturedImages"
                    :parts="parts"
                    :loaners="loaners"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                    @files-updated="(nextFiles) => $emit('files-updated', nextFiles)"
                    @reload-attachments="$emit('reload-attachments')"
                    @save="$emit('save')"
                    @workflow-done="(payload) => $emit('workflow-done', payload)"
                />
                <DetailFormA
                    v-else-if="layout === 'A'"
                    :record="record"
                    :draft-record="draftRecord"
                    :notes="notes"
                    :files="files"
                    :captured-images="capturedImages"
                    :parts="parts"
                    :loaners="loaners"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                    @files-updated="(nextFiles) => $emit('files-updated', nextFiles)"
                    @reload-attachments="$emit('reload-attachments')"
                />
                <DetailFormB
                    v-else-if="layout === 'B'"
                    :record="record"
                    :draft-record="draftRecord"
                    :notes="notes"
                    :files="files"
                    :captured-images="capturedImages"
                    :parts="parts"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                    @files-updated="(nextFiles) => $emit('files-updated', nextFiles)"
                    @reload-attachments="$emit('reload-attachments')"
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
import DetailFormClosing from './DetailFormClosing.vue'
import DetailFormInvoice from './DetailFormInvoice.vue'
import DetailFormEngineer from './DetailFormEngineer.vue'

defineProps({
    record: Object,
    draftRecord: Object,
    notes: {
        type: Array,
        default: () => [],
    },
    files: {
        type: Array,
        default: () => [],
    },
    capturedImages: {
        type: Array,
        default: () => [],
    },
    parts: {
        type: Array,
        default: () => [],
    },
    stockedParts: {
        type: Array,
        default: () => [],
    },
    loaners: {
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
    savingRecord: {
        type: Boolean,
        default: false,
    },
    saveError: {
        type: String,
        default: '',
    },
    layout: {
        type: String,
        default: 'A',
    },
    mode: {
        type: String,
        default: 'admin',
    },
})

defineEmits(['close', 'switch-layout', 'open-dialog', 'save', 'files-updated', 'reload-attachments', 'workflow-done'])
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
    align-items: center;
}

.engineer-title {
    font-size: 14px;
    font-weight: 700;
    color: #99f6e4;
}

.closing-title {
    font-size: 14px;
    font-weight: 700;
    color: #5eead4;
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

.save-error {
    margin: 0;
    color: #fca5a5;
    font-size: 12px;
}

.save-btn {
    padding: 6px 12px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.save-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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
    position: relative;
}

.detail-body-engineer {
    overflow: hidden;
    padding: 0;
}

.detail-body-closing {
    overflow: hidden;
    padding: 0;
}
</style>
