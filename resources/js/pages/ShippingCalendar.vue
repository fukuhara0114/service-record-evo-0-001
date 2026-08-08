<template>
    <div class="shipping-calendar-page">
        <header class="page-header">
            <div>
                <h1>出荷予定カレンダー</h1>
                <p class="subtitle">shippingOut_requiredDate の予定を確認・ドラッグで変更できます。</p>
            </div>
            <div class="header-actions">
                <a :href="listUrl" class="btn btn-secondary">一覧へ戻る</a>
                <CloseToHomeButton :href="homeUrl" />
            </div>
        </header>
        <div class="board-host">
            <ShippingOutDateDialog mode="browse" plain />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import ShippingOutDateDialog from '@/components/ServiceRecord/Layer3/ShippingOutDateDialog.vue'
import { getServiceRecordBasePath, serviceRecordUrl } from '@/utils/serviceRecordPath'

const listUrl = computed(() => serviceRecordUrl('/administrator'))
const homeUrl = computed(() => {
    const appRoot = getServiceRecordBasePath().replace(/\/servicerecord$/, '')
    return `${window.location.origin}${appRoot}/home`
})
</script>

<style scoped>
.shipping-calendar-page {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #e2e8f0;
    overflow: hidden;
}

.page-header {
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    background: #1e293b;
    color: #fff;
}

.page-header h1 {
    margin: 0;
    font-size: 18px;
}

.subtitle {
    margin: 4px 0 0;
    font-size: 12px;
    color: #cbd5e1;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.btn {
    padding: 7px 12px;
    border-radius: 4px;
    border: 1px solid #94a3b8;
    background: #334155;
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.btn:hover {
    background: #475569;
}

.board-host {
    flex: 1;
    min-height: 0;
}
</style>
