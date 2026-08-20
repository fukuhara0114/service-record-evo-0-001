<template>
    <!-- パネル全画面のため backdrop click は不要。dblclick 残存 click で誤 close するのを防ぐ -->
    <div class="detail-overlay">
        <div class="detail-panel">
            <div class="detail-header">
                <template v-if="isAdminServiceDetail">
                    <div class="header-left">
                        <span class="service-detail-badge">Service詳細</span>
                        <div class="header-summary">
                            <span class="header-summary-item header-summary-orderid">OrderID: {{ record?.orderID }}</span>
                            <span class="header-summary-item">{{ headerDealer }}</span>
                            <span class="header-summary-item">{{ headerProductName }}</span>
                            <span class="header-summary-item header-summary-sn">SN: {{ headerSn }}</span>
                            <span class="header-summary-item header-summary-return">{{ headerReturnCodeLabel }}</span>
                        </div>
                    </div>
                    <div class="header-center-actions">
                        <p v-if="saveError" class="save-error">{{ saveError }}</p>
                        <button
                            type="button"
                            class="save-btn"
                            :disabled="savingRecord"
                            @click="$emit('save')"
                        >
                            {{ savingRecord ? '保存中...' : '保存' }}
                        </button>
                        <button
                            type="button"
                            class="remand-btn"
                            :class="isRemandOn ? 'remand-on' : 'remand-off'"
                            :title="isRemandOn ? '差戻 ON（クリックで OFF）' : '差戻 OFF（クリックで ON）'"
                            @click="toggleRemand"
                        >
                            差戻
                        </button>
                    </div>
                    <div class="detail-meta">
                        <button
                            type="button"
                            class="tab-btn"
                            :class="{ active: layout === 'A' }"
                            @click="$emit('switch-layout', 'A')"
                        >
                            詳細 A
                        </button>
                        <button
                            type="button"
                            class="tab-btn"
                            :class="{ active: layout === 'B' }"
                            @click="$emit('switch-layout', 'B')"
                        >
                            詳細 B
                        </button>
                        <button type="button" class="close-x-btn" aria-label="閉じる" title="閉じる" @click="$emit('close')">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                </template>
                <template v-else>
                    <div class="layout-tabs">
                        <template v-if="mode === 'engineer'">
                            <span class="engineer-title">Engineer 詳細</span>
                        </template>
                        <template v-else-if="mode === 'logistics' || layout === 'logistics'">
                            <span class="closing-title">Logistics 詳細</span>
                        </template>
                        <template v-else-if="layout === 'closing'">
                            <span class="closing-title">Closing 詳細</span>
                        </template>
                        <template v-else-if="layout === 'invoice'">
                            <span class="closing-title">Invoice 詳細</span>
                        </template>
                    </div>
                    <div class="header-summary">
                        <span class="header-summary-item header-summary-orderid">OrderID: {{ record?.orderID }}</span>
                        <span class="header-summary-item">{{ headerDealer }}</span>
                        <span class="header-summary-item">{{ headerProductName }}</span>
                        <span class="header-summary-item header-summary-sn">SN: {{ headerSn }}</span>
                        <span class="header-summary-item header-summary-return">{{ headerReturnCodeLabel }}</span>
                    </div>
                    <div class="detail-meta">
                        <p v-if="saveError" class="save-error">{{ saveError }}</p>
                        <button type="button" class="close-x-btn" aria-label="閉じる" title="閉じる" @click="$emit('close')">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                </template>
            </div>

            <div
                class="detail-body"
                :class="{
                    'detail-body-engineer': mode === 'engineer',
                    'detail-body-closing': layout === 'closing' || layout === 'invoice' || layout === 'logistics' || mode === 'logistics',
                    'detail-body-form-a': layout === 'A',
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
                <DetailFormLogistics
                    v-else-if="mode === 'logistics' || layout === 'logistics'"
                    :record="record"
                    :draft-record="draftRecord"
                    :notes="notes"
                    :files="files"
                    :captured-images="capturedImages"
                    :attachments-loading="attachmentsLoading"
                    :attachments-error="attachmentsError"
                    @open-dialog="(type, payload) => $emit('open-dialog', type, payload)"
                    @files-updated="(nextFiles) => $emit('files-updated', nextFiles)"
                    @reload-attachments="$emit('reload-attachments')"
                    @save="$emit('save')"
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
                    :current-user-kanji="currentUserKanji"
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
                    :current-user-kanji="currentUserKanji"
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
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import DetailFormA from './DetailFormA.vue'
import DetailFormB from './DetailFormB.vue'
import DetailFormC from './DetailFormC.vue'
import DetailFormClosing from './DetailFormClosing.vue'
import DetailFormInvoice from './DetailFormInvoice.vue'
import DetailFormEngineer from './DetailFormEngineer.vue'
import DetailFormLogistics from './DetailFormLogistics.vue'

const props = defineProps({
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
    currentUserKanji: {
        type: String,
        default: '',
    },
})

defineEmits(['close', 'switch-layout', 'open-dialog', 'save', 'files-updated', 'reload-attachments', 'workflow-done'])

const page = usePage()

const isAdminServiceDetail = computed(() => (
    props.mode !== 'engineer'
    && props.mode !== 'logistics'
    && props.layout !== 'closing'
    && props.layout !== 'invoice'
    && props.layout !== 'logistics'
))

function headerText(field) {
    const draft = props.draftRecord?.[field]
    if (draft !== undefined && draft !== null && draft !== '') return String(draft)
    const value = props.record?.[field]
    if (value !== undefined && value !== null && value !== '') return String(value)
    return '—'
}

const headerDealer = computed(() => headerText('dealer'))
const headerProductName = computed(() => headerText('productName'))
const headerSn = computed(() => headerText('SN'))

const headerReturnCodeLabel = computed(() => {
    const id = props.draftRecord?.returnCode ?? props.record?.returnCode
    const draftId = props.draftRecord?.returnCode
    const recordId = props.record?.returnCode
    const master = props.record?.return_code_master
    if (
        master?.description
        && (draftId === undefined || draftId === null || String(draftId) === String(recordId))
    ) {
        return master.description
    }
    const found = (page.props.returnCodes ?? []).find(item => String(item.id) === String(id))
    return found?.description || (id != null && id !== '' ? String(id) : '—')
})

const isRemandOn = computed(() => {
    const value = props.draftRecord?.remand ?? props.record?.remand
    return value === 1 || value === '1' || value === true
})

function toggleRemand() {
    if (!props.draftRecord) return
    props.draftRecord.remand = isRemandOn.value ? 0 : 1
}
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
    background: #888888;
    display: flex;
    flex-direction: column;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: #1e293b;
    color: white;
    border-bottom: 2px solid #3b82f6;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1 1 0;
    min-width: 0;
}

.header-center-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex: 0 0 auto;
}

.header-center-actions .remand-btn {
    margin-left: 90px;
}

.layout-tabs {
    display: flex;
    gap: 8px;
    align-items: center;
    flex: 0 0 auto;
}

.service-detail-badge {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    padding: 4px 12px;
    border-radius: 999px;
    background: #16a34a;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.engineer-title {
    font-size: 14px;
    font-weight: 700;
    color: #99f6e4;
    white-space: nowrap;
}

.header-summary {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 0 12px;
    overflow: hidden;
}

.header-summary-item {
    min-width: 0;
    font-size: inherit;
    font-weight: inherit;
    color: inherit;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.header-summary-orderid {
    flex: 0 0 auto;
    width: auto;
    min-width: 110px;
}

.header-summary-sn {
    flex: 0 0 auto;
    width: auto;
    min-width: 150px;
}

.header-summary-return {
    flex: 0 0 auto;
    width: auto;
    min-width: 100px;
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
    flex: 1 1 0;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    white-space: nowrap;
    min-width: 0;
}

.save-error {
    margin: 0;
    color: #fca5a5;
    font-size: 12px;
}

.save-btn,
.remand-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    min-width: 112px;
    padding: 6px 24px;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 700;
    text-align: center;
}

.save-btn {
    background: #2563eb;
}

.save-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.remand-btn.remand-on {
    background: #dc2626;
}

.remand-btn.remand-off {
    background: #64748b;
}

.close-btn {
    padding: 6px 12px;
    background: #64748b;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.close-x-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    width: 36px;
    height: 36px;
    padding: 0;
    margin: 0;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    color: #0f172a;
    font-size: 22px;
    font-weight: 700;
    line-height: 0;
    text-align: center;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
}

.close-x-btn > span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1em;
    height: 1em;
    line-height: 1;
    /* × グリフの視覚重心をボタン中央へ合わせる */
    transform: translate(-0.02em, -0.06em);
}

.close-x-btn:hover {
    background: #f8fafc;
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

.detail-body-form-a {
    overflow: hidden;
    padding: 0;
}
</style>
