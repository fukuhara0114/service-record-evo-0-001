<template>
    <div class="intake-page">
        <div class="page-header">
            <div>
                <h1>未登録PDF一覧</h1>
            </div>
            <div class="header-actions">
                <a :href="homeUrl" class="btn btn-secondary">Home</a>
                <a :href="adminUrl" class="btn btn-primary">既存案件一覧</a>
            </div>
        </div>

        <section class="list-card">
            <div class="list-header">
                <h2>対象ファイル（{{ files.length }}件）</h2>
            </div>

            <div class="file-scroll">
                <div v-if="files.length" class="file-grid">
                    <article
                        v-for="file in files"
                        :key="file.id"
                        class="file-card"
                        @click="openPreview(file)"
                    >
                        <div class="file-preview-wrap">
                            <iframe
                                v-if="isPdf(file)"
                                :src="fileUrl(file.id)"
                                class="file-preview"
                                :title="`PDF preview ${file.id}`"
                                tabindex="-1"
                            />
                            <img
                                v-else-if="isImage(file)"
                                :src="fileUrl(file.id)"
                                :alt="file.documentName || '画像'"
                                class="file-preview-image"
                            >
                            <div v-else class="file-preview-fallback">
                                <p>プレビュー非対応</p>
                            </div>
                        </div>

                        <div class="file-card-actions" @click.stop>
                            <a :href="createUrl(file.id)" class="btn btn-primary btn-sm">このPDFで新規登録</a>
                        </div>
                    </article>
                </div>

                <p v-else class="empty-message">未登録PDFはありません。</p>
            </div>
        </section>

        <IntakeFilePreviewDialog
            v-if="previewFile"
            :file="previewFile"
            :files="files"
            @close="previewFile = null"
            @saved="onPreviewSaved"
            @navigate="openPreview"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import IntakeFilePreviewDialog from '@/components/ServiceRecord/Intake/IntakeFilePreviewDialog.vue'

const props = defineProps({
    unregisteredFiles: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const previewFile = ref(null)
const previewCacheBust = ref(Date.now())

const files = computed(() => props.unregisteredFiles ?? [])
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)

function createUrl(fileId) {
    return `${page.props.appBaseUrl}/servicerecord/intake/${fileId}/create`
}

function fileUrl(fileId) {
    return `${page.props.appBaseUrl}/servicerecord/files/${fileId}?t=${previewCacheBust.value}`
}

function isPdf(file) {
    return file?.fileType === 'application/pdf'
}

function isImage(file) {
    return String(file?.fileType || '').startsWith('image/')
}

function openPreview(file) {
    previewFile.value = file
}

function onPreviewSaved() {
    previewCacheBust.value = Date.now()
}
</script>

<style scoped>
.intake-page {
    height: 100vh;
    padding: 24px;
    background: #e2e8f0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 20px;
    flex-shrink: 0;
}

.page-header h1 {
    margin: 0 0 8px;
    font-size: 24px;
    color: #1e293b;
}

.page-header p {
    margin: 0;
    color: #475569;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.list-card {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.list-header {
    flex-shrink: 0;
}

.list-header h2 {
    margin: 0 0 16px;
    font-size: 18px;
    color: #1e293b;
}

.file-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.file-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    padding: 6px;
    min-width: 0;
    cursor: pointer;
}

.file-card:hover {
    border-color: #2563eb;
    background: #eff6ff;
}

.file-preview-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 210 / 297;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    overflow: hidden;
}

.file-preview,
.file-preview-image {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    background: #fff;
    pointer-events: none;
}

.file-preview-image {
    object-fit: contain;
}

.file-preview-fallback {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 12px;
}

.file-card-actions {
    display: flex;
    gap: 6px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.btn-sm {
    padding: 5px 10px;
    width: 100%;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

.empty-message {
    margin: 0;
    color: #64748b;
}
</style>
