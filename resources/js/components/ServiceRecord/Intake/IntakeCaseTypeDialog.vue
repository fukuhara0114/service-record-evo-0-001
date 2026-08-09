<template>
    <div class="case-type-overlay" @click.self="$emit('close')">
        <div class="case-type-panel" @click.stop>
            <div class="case-type-header">
                <div>
                    <h3>作成する案件の種類</h3>
                    <p class="case-type-sub">{{ subtitle }}</p>
                </div>
                <button type="button" class="case-type-close" @click="$emit('close')">×</button>
            </div>

            <div class="case-type-body">
                <button
                    type="button"
                    class="case-type-option"
                    :class="{ active: selectedType === 'service' }"
                    @click="selectedType = 'service'"
                >
                    <strong>サービス案件</strong>
                    <span>servicemaster から対象製品を選んで作成します</span>
                </button>
                <button
                    type="button"
                    class="case-type-option"
                    :class="{ active: selectedType === 'loaner' }"
                    @click="selectedType = 'loaner'"
                >
                    <strong>Loaner案件</strong>
                    <span>loanermaster から貸出機を選んで作成します</span>
                </button>
            </div>

            <div class="case-type-actions">
                <button type="button" class="action-btn" @click="$emit('close')">キャンセル</button>
                <button
                    type="button"
                    class="action-btn action-btn-primary"
                    :disabled="!selectedType"
                    @click="confirm"
                >
                    次へ
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
    /** blank | file | upload */
    mode: {
        type: String,
        default: 'blank',
    },
    fileName: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['close', 'confirm'])

const selectedType = ref('service')

const subtitle = computed(() => {
    if (props.mode === 'file') {
        return props.fileName
            ? `選択ファイル: ${props.fileName}`
            : '選択したファイルで新規登録します'
    }
    if (props.mode === 'upload') {
        return 'ファイル追加後、選択した種類の案件作成へ進みます'
    }
    return '添付なしで情報入力のみの新規登録へ進みます'
})

watch(
    () => props.mode,
    () => {
        selectedType.value = 'service'
    },
)

function confirm() {
    if (!selectedType.value) return
    emit('confirm', selectedType.value)
}
</script>

<style scoped>
.case-type-overlay {
    position: fixed;
    inset: 0;
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.45);
}

.case-type-panel {
    width: min(480px, 100%);
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.case-type-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: #1e293b;
    color: #fff;
}

.case-type-header h3 {
    margin: 0;
    font-size: 16px;
}

.case-type-sub {
    margin: 4px 0 0;
    font-size: 12px;
    color: #cbd5e1;
    word-break: break-all;
}

.case-type-close {
    border: none;
    background: transparent;
    color: #fff;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.case-type-body {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px;
}

.case-type-option {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    text-align: left;
    cursor: pointer;
}

.case-type-option strong {
    font-size: 15px;
    color: #0f172a;
}

.case-type-option span {
    font-size: 12px;
    color: #64748b;
}

.case-type-option.active {
    border-color: #2563eb;
    background: #eff6ff;
}

.case-type-option.active strong {
    color: #1d4ed8;
}

.case-type-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 0 16px 16px;
}

.action-btn {
    padding: 8px 14px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.action-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.action-btn-primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}
</style>
