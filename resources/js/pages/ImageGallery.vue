<template>
    <div class="gallery-page">
        <header class="page-header">
            <div>
                <h1>Gallery</h1>
                <p class="subtitle">撮影画像のサムネイル一覧です。クリックで本体を表示します。</p>
            </div>
            <div class="header-actions">
                <a :href="homeUrl" class="btn btn-secondary">Home</a>
                <a :href="cameraUrl" class="btn btn-primary">カメラ</a>
            </div>
        </header>

        <section class="card">
            <CapturedImageGallery />
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import CapturedImageGallery from '@/components/ServiceRecord/CapturedImageGallery.vue'

const page = usePage()
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const cameraUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/camera`)
</script>

<style scoped>
.gallery-page {
    min-height: 100vh;
    padding: 20px;
    box-sizing: border-box;
    background: #e2e8f0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 22px;
    color: #1e293b;
}

.subtitle {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.card {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    min-height: calc(100vh - 120px);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 14px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

@media (max-width: 640px) {
    .page-header {
        flex-direction: column;
    }
}
</style>
