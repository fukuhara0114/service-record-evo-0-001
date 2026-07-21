<template>
    <div class="dialog-overlay" @click.self="$emit('close')">
        <div class="dialog-panel">
            <div class="dialog-header">
                <h3>{{ title }}</h3>
                <button type="button" class="close-btn" @click="$emit('close')">×</button>
            </div>
            <div class="dialog-body">
                <slot />
            </div>
            <div v-if="$slots.footer" class="dialog-footer">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        default: 'ダイアログ',
    },
})

defineEmits(['close'])
</script>

<style scoped>
.dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 200;
    display: flex;
    justify-content: center;
    align-items: center;
}

.dialog-panel {
    width: 520px;
    max-width: 90vw;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #1e293b;
    color: white;
}

.dialog-header h3 {
    margin: 0;
    font-size: 16px;
}

.close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
}

.dialog-body {
    padding: 16px;
}

.dialog-footer {
    padding: 12px 16px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>