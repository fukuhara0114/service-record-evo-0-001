<template>
    <div class="draft-overlay" @click.self="onCancel">
        <div class="draft-panel" @click.stop>
            <div class="draft-header">
                <h3>メールドラフト作成</h3>
                <button type="button" class="draft-close" :disabled="creating" @click="onCancel">×</button>
            </div>
            <div class="draft-body">
                <p class="draft-help">
                    定型文に案件情報（dealer / contactPerson / productName / SN）を差し込み、
                    元の .eml へ返信ドラフトとして合成してダウンロードします。
                </p>
                <div class="draft-options">
                    <label
                        v-for="option in draftOptions"
                        :key="option.value"
                        class="draft-option"
                        :class="{ active: selectedType === option.value }"
                    >
                        <input v-model="selectedType" type="radio" :value="option.value" :disabled="creating">
                        <span>{{ option.label }}</span>
                    </label>
                </div>
                <p v-if="error" class="draft-error">{{ error }}</p>
            </div>
            <div class="draft-actions">
                <button type="button" class="action-btn" :disabled="creating" @click="onCancel">キャンセル</button>
                <button
                    type="button"
                    class="action-btn action-btn-primary"
                    :disabled="creating || !selectedType"
                    @click="onConfirm"
                >
                    {{ creating ? '作成中...' : confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    creating: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'プレビュー',
    },
    initialType: {
        type: String,
        default: 'receipt',
    },
})

const emit = defineEmits(['close', 'confirm'])

const draftOptions = [
    { value: 'receipt', label: '① 受領メール' },
    { value: 'quote', label: '② 見積添付メール' },
    { value: 'work_change', label: '③ 作業内容変更メール' },
]

const selectedType = ref(props.initialType || 'receipt')

watch(
    () => props.initialType,
    (value) => {
        if (value) selectedType.value = value
    },
)

function onCancel() {
    if (props.creating) return
    emit('close')
}

function onConfirm() {
    if (!selectedType.value || props.creating) return
    emit('confirm', selectedType.value)
}
</script>

<style scoped>
.draft-overlay {
    position: fixed;
    inset: 0;
    z-index: 430;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.45);
    padding: 16px;
}

.draft-panel {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.draft-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: #1e293b;
    color: #fff;
}

.draft-header h3 {
    margin: 0;
    font-size: 16px;
}

.draft-close {
    border: none;
    background: transparent;
    color: #fff;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.draft-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.draft-help {
    margin: 0;
    font-size: 13px;
    color: #475569;
    line-height: 1.5;
}

.draft-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.draft-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    cursor: pointer;
    background: #f8fafc;
}

.draft-option.active {
    border-color: #2563eb;
    background: #eff6ff;
}

.draft-option input {
    margin: 0;
}

.draft-error {
    margin: 0;
    color: #b91c1c;
    font-size: 13px;
}

.draft-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px 16px;
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
