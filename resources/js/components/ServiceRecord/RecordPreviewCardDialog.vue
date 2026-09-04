<template>
    <div v-if="open" class="preview-overlay" @click.self="emit('close')" @contextmenu.prevent>
        <div class="preview-card" role="dialog" aria-modal="true" :aria-label="titleText">
            <header class="preview-header">
                <div>
                    <h3>{{ titleText }}</h3>
                    <p v-if="subtitleText" class="preview-subtitle">{{ subtitleText }}</p>
                </div>
                <button type="button" class="close-btn" aria-label="閉じる" @click="emit('close')">×</button>
            </header>

            <div class="preview-body">
                <p v-if="loading" class="preview-status">読み込み中...</p>
                <p v-else-if="error" class="preview-status error">{{ error }}</p>
                <template v-else-if="record">
                    <section class="preview-section preview-section-priority">
                        <div
                            v-for="block in priorityBlocks"
                            :key="block.title"
                            class="preview-block"
                        >
                            <h4>{{ block.title }}</h4>
                            <dl class="preview-grid preview-grid-3">
                                <div
                                    v-for="item in block.items"
                                    :key="item.key"
                                >
                                    <dt>{{ item.label }}</dt>
                                    <dd>{{ item.value }}</dd>
                                </div>
                            </dl>
                            <dl v-if="block.addressItems?.length" class="preview-grid preview-grid-address">
                                <div
                                    v-for="item in block.addressItems"
                                    :key="item.key"
                                >
                                    <dt>{{ item.label }}</dt>
                                    <dd>{{ item.value }}</dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                    <section class="preview-section preview-section-last-edit">
                        <h4>最終編集</h4>
                        <dl class="preview-grid preview-grid-2">
                            <div>
                                <dt>最終編集日</dt>
                                <dd>{{ display(record.lastEditDate) }}</dd>
                            </div>
                            <div>
                                <dt>最終編集者</dt>
                                <dd>{{ display(record.lastEditPerson) }}</dd>
                            </div>
                        </dl>
                    </section>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    record: { type: Object, default: null },
})

const emit = defineEmits(['close'])

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close')
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown)
})

const PRIORITY_BLOCKS = [
    {
        title: '製品',
        keys: [
            ['productName', 'productName'],
            ['SN', 'SN'],
        ],
    },
    {
        title: 'Dealer',
        keys: [
            ['dealer', 'dealer'],
            ['dealer_depart', 'dealer_depart'],
            ['contactPerson', 'contactPerson'],
        ],
    },
    {
        title: 'E/U',
        keys: [
            ['endUser', 'endUser'],
            ['endUser_depart', 'endUser_depart'],
            ['endUser_contactPerson', 'endUser_contactPerson'],
            ['endUser_phone', 'endUser_phone'],
            ['endUser_email', 'endUser_email'],
        ],
        addressKeys: [
            ['endUser_address1', 'endUser_address1'],
            ['endUser_address2', 'endUser_address2'],
        ],
    },
]

function display(value) {
    if (value == null || value === '') return '—'
    if (typeof value === 'boolean') return value ? 'true' : 'false'
    if (typeof value === 'object') {
        try {
            return JSON.stringify(value)
        } catch {
            return String(value)
        }
    }
    return String(value)
}

function statusLabel(record) {
    if (!record) return ''
    if (record.order_type === 'waiting_list') return ''
    if (record.order_type === 'loaner') {
        return record.status_master_loaner?.status_new
            || record.status_master_loaner?.status
            || ''
    }
    return record.status_master?.status || ''
}

function buildItems(record, keys) {
    return keys.map(([key, label, span2 = false]) => ({
        key,
        label,
        span2,
        value: display(record[key]),
    }))
}

const titleText = computed(() => {
    const id = props.record?.orderID
    return id != null ? `案件カード #${id}` : '案件カード'
})

const subtitleText = computed(() => {
    if (!props.record) return ''
    const parts = [
        props.record.productName,
        props.record.SN,
        statusLabel(props.record) || props.record.status,
    ].filter(Boolean)
    return parts.join(' / ')
})

const priorityBlocks = computed(() => {
    const record = props.record
    if (!record) return []
    return PRIORITY_BLOCKS.map((block) => ({
        title: block.title,
        items: buildItems(record, block.keys),
        addressItems: block.addressKeys ? buildItems(record, block.addressKeys) : [],
    }))
})
</script>

<style scoped>
.preview-overlay {
    position: fixed;
    inset: 0;
    z-index: 12000;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.preview-card {
    width: min(920px, 100%);
    max-height: min(88vh, 900px);
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 18px 48px rgba(15, 23, 42, 0.28);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.preview-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: #1e293b;
    color: #fff;
}

.preview-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
}

.preview-subtitle {
    margin: 4px 0 0;
    font-size: 12px;
    color: #cbd5e1;
}

.close-btn {
    border: none;
    background: #475569;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
}

.preview-body {
    padding: 14px 16px 18px;
    overflow: auto;
    background: #f8fafc;
}

.preview-status {
    margin: 12px 4px;
    color: #475569;
    font-size: 13px;
}

.preview-status.error {
    color: #b91c1c;
}

.preview-section {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 10px;
}

.preview-section-priority {
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #eef2ff;
    border-color: #c7d2fe;
}

.preview-block {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 10px 12px;
}

.preview-section h4,
.preview-block h4 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #0f172a;
    font-weight: 800;
}

.preview-grid {
    margin: 0;
    display: grid;
    gap: 8px 12px;
}

.preview-grid-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.preview-grid-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.preview-grid-address {
    margin-top: 8px;
    display: grid;
    grid-template-columns: max-content max-content;
    justify-content: start;
    align-items: start;
    gap: 8px 10px;
    width: fit-content;
    max-width: 100%;
}

.preview-grid-address > div:first-child {
    text-align: right;
}

.preview-grid-address > div:first-child dt,
.preview-grid-address > div:first-child dd {
    text-align: right;
}

.preview-grid-address > div:last-child {
    text-align: left;
}

.preview-grid-address > div:last-child dt,
.preview-grid-address > div:last-child dd {
    text-align: left;
}

.preview-grid > div {
    min-width: 0;
}

.preview-grid > div.span2 {
    grid-column: span 2;
}

.preview-grid dt {
    font-size: 11px;
    color: #64748b;
    font-weight: 700;
}

.preview-grid dd {
    margin: 2px 0 0;
    font-size: 13px;
    color: #0f172a;
    font-weight: 700;
    white-space: pre-wrap;
    word-break: break-word;
}

@media (max-width: 720px) {
    .preview-grid-3 {
        grid-template-columns: 1fr;
    }

    .preview-grid-address {
        grid-template-columns: 1fr;
        width: 100%;
    }

    .preview-grid-address > div:first-child,
    .preview-grid-address > div:first-child dt,
    .preview-grid-address > div:first-child dd {
        text-align: left;
    }

    .preview-grid > div.span2 {
        grid-column: auto;
    }
}
</style>
