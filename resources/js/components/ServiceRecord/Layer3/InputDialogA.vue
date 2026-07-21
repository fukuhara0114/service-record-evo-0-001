<template>
    <BaseDialog title="入力ダイアログ A" @close="$emit('close')">
        <p>OrderID: {{ record?.orderID }}</p>
        <label>
            メモ
            <textarea v-model="memo" rows="4" style="width: 100%;"></textarea>
        </label>

        <template #footer>
            <button type="button" @click="$emit('close')">キャンセル</button>
            <button type="button" @click="save">保存</button>
        </template>
    </BaseDialog>
</template>

<script setup>
import { ref } from 'vue'
import BaseDialog from './BaseDialog.vue'

const props = defineProps({
    record: Object,
    payload: Object,
})

const emit = defineEmits(['close', 'saved'])

const memo = ref('')

function save() {
    // 本来は API 呼び出し
    emit('saved', { memo: memo.value })
}
</script>