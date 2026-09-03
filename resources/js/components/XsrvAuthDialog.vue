<template>
    <div
        v-if="open"
        class="xsrv-auth-dialog-overlay"
        @click.self="$emit('close')"
    >
        <div class="xsrv-auth-dialog" role="dialog" aria-modal="true" aria-labelledby="xsrv-auth-dialog-title">
            <h3 id="xsrv-auth-dialog-title">認証エラー</h3>
            <p class="xsrv-auth-dialog-message">{{ message }}</p>
            <div class="xsrv-auth-dialog-actions">
                <button type="button" class="xsrv-auth-dialog-ok" @click="$emit('close')">OK</button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    message: {
        type: String,
        default: 'このシステムを利用する権限がありません、または有効期限が切れています。',
    },
})

defineEmits(['close'])
</script>

<style scoped>
.xsrv-auth-dialog-overlay {
    position: fixed;
    inset: 0;
    z-index: 120;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.45);
}

.xsrv-auth-dialog {
    width: min(92vw, 440px);
    background: #fff;
    border-radius: 8px;
    padding: 20px 20px 16px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.25);
}

.xsrv-auth-dialog h3 {
    margin: 0 0 10px;
    font-size: 16px;
    font-weight: 700;
    color: #b91c1c;
}

.xsrv-auth-dialog-message {
    margin: 0 0 16px;
    font-size: 14px;
    line-height: 1.5;
    color: #334155;
    white-space: pre-wrap;
}

.xsrv-auth-dialog-actions {
    display: flex;
    justify-content: flex-end;
}

.xsrv-auth-dialog-ok {
    min-width: 72px;
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    background: #0d9488;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.xsrv-auth-dialog-ok:hover {
    background: #0f766e;
}
</style>
