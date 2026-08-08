<template>
    <div
        class="dialog-overlay"
        :class="{ plain }"
        @click.self="onOverlayClick"
    >
        <div class="dialog-panel" :class="{ large, plain }">
            <div v-if="!plain" class="dialog-header">
                <h3>{{ title }}</h3>
                <button v-if="showClose" type="button" class="close-btn" @click="$emit('close')">×</button>
            </div>
            <div class="dialog-body" :class="{ plain }">
                <slot />
            </div>
            <div v-if="$slots.footer && !plain" class="dialog-footer">
                <slot name="footer" />
            </div>
            <div v-else-if="$slots.footer && plain && showFooter" class="dialog-footer plain">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    title: {
        type: String,
        default: 'ダイアログ',
    },
    large: {
        type: Boolean,
        default: false,
    },
    plain: {
        type: Boolean,
        default: false,
    },
    showClose: {
        type: Boolean,
        default: true,
    },
    showFooter: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['close'])

function onOverlayClick() {
    if (props.plain) return
    emit('close')
}
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

.dialog-overlay.plain {
    position: relative;
    inset: auto;
    background: transparent;
    z-index: auto;
    display: block;
    width: 100%;
    height: 100%;
    min-height: 0;
}

.dialog-panel {
    width: 520px;
    max-width: 90vw;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.dialog-panel.large {
    width: min(96vw, 1600px);
    height: 96vh;
    max-width: 96vw;
    display: flex;
    flex-direction: column;
}

.dialog-panel.plain {
    width: 100%;
    max-width: none;
    height: 100%;
    border-radius: 0;
    box-shadow: none;
    display: flex;
    flex-direction: column;
    background: transparent;
    overflow: hidden;
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

.dialog-body.plain {
    padding: 8px;
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.dialog-panel.large .dialog-body {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: auto;
}

.dialog-panel.plain.large .dialog-body {
    overflow: hidden;
}

.dialog-footer {
    padding: 12px 16px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.dialog-footer.plain {
    background: #fff;
}
</style>
