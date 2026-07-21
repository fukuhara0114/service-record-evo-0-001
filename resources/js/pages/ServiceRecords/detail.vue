<template>
    <div class="detail-page">
        <p>OrderID: {{ initialRecord?.orderID }}</p>
        <a href="/servicerecords">一覧に戻る</a>
    </div>
</template>

<script setup>
defineProps({
    initialRecord: Object,
    statuses: Array,
    returnCodes: Array,
    labors: Array,
    notes: Array,
    files: Array,
    parts: Array,
    mode: String,
})
</script>

<style scoped>
.detail-page {
    padding: 20px;
}
</style>
