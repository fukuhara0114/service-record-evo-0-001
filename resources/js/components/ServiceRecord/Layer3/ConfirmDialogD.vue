<template>
    <BaseDialog title="確認ダイアログ D" @close="$emit('close')">
        <p>OrderID: {{ record?.orderID }}</p>
        <p>この内容で保存してよろしいですか？</p>

        <template #footer>
            <button type="button" @click="$emit('close')">キャンセル</button>
            <button type="button" @click="save">保存</button>
        </template>
    </BaseDialog>
</template>

<script setup>
import BaseDialog from './BaseDialog.vue'

defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

function save() {
    emit('saved', { confirmed: true })
}
</script>
