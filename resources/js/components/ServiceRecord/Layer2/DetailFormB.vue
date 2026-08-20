<template>
    <div class="detail-form">
        <Splitpanes class="default-theme detail-splitpanes" @resized="syncPaneSizes">
            <Pane class="detail-pane detail-pane-left" :size="leftPaneSize" :min-size="30">
                <div class="files-section">
                    <section class="section-card section-card-files">
                        <div class="section-header">
                            <h3>
                                Files（書類 {{ sortedFiles.length }}件
                                ／ 撮影画像 {{ capturedImages.length }}件）
                            </h3>
                        </div>
                        <div class="files-type-label">
                            <span class="type-badge type-badge-doc">書類ファイル</span>
                        </div>

                        <div class="files-list-wrap">
                            <AttachedFileItem
                                v-for="(file, index) in sortedFiles"
                                :key="file.id"
                                :file="file"
                                :order-id="record?.orderID"
                                :can-move-up="index > 0"
                                :can-move-down="index < sortedFiles.length - 1"
                                :sorting="fileSortSaving"
                                @move="(direction) => moveFile(file.id, direction)"
                                @sort-num-change="(sortNum) => updateFileSortNum(file.id, sortNum)"
                            />
                            <p v-if="!sortedFiles.length" class="empty-message">書類ファイルがありません。</p>

                            <AssociatedCapturedImages
                                :images="capturedImages"
                                @changed="emit('reload-attachments')"
                            />
                        </div>
                    </section>
                </div>
            </Pane>

            <Pane class="detail-pane detail-pane-right" :size="rightPaneSize" :min-size="25">
                <div ref="rightScrollEl" class="info-section pane-content-scroll">
                    <div class="info-stack">
                        <section
                            data-card-nav="basic"
                            class="section-card detail-card"
                        >
                            <div class="card-scroll-nav">
                                <button
                                    type="button"
                                    class="card-scroll-nav-btn"
                                    disabled
                                    title="前のカードへ"
                                    aria-label="前のカードへ"
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    class="card-scroll-nav-btn"
                                    title="次のカードへ"
                                    aria-label="次のカードへ"
                                    @click="scrollToCard('dealer')"
                                >
                                    ↓
                                </button>
                            </div>
                            <h3 class="card-title">基本情報</h3>
                            <p class="card-meta">OrderID: {{ record?.orderID }} / receivedDate: {{ record?.receivedDate }}</p>
                            <div class="field-stack">
                                <div class="grid-cell">
                                    <span class="cell-label">productName</span>
                                    <button type="button" class="field-button" @click="openServiceMasterSelect">
                                        {{ draftRecord?.productName || record?.productName || '選択してください' }}
                                    </button>
                                </div>
                                <label class="grid-cell">
                                    <span class="cell-label">SN</span>
                                    <input type="text" class="field-input" :value="draftRecord?.SN ?? record?.SN ?? ''" @input="updateDraftValue('SN', $event.target.value)">
                                </label>
                                <label class="grid-cell">
                                    <span class="cell-label">returnCode</span>
                                    <select class="field-select" :value="draftRecord?.returnCode ?? record?.returnCode ?? ''" @change="updateNumericDraftValue('returnCode', $event.target.value)">
                                        <option value="">選択してください</option>
                                        <option v-for="returnCode in page.props.returnCodes ?? []" :key="returnCode.id" :value="returnCode.id">
                                            {{ returnCode.description }} ({{ returnCode.id }})
                                        </option>
                                    </select>
                                </label>
                                <div class="a2la-row">
                                    <label class="flag-toggle" :class="{ on: isA2laOn }">
                                        <span class="flag-name">a2la</span>
                                        <button
                                            type="button"
                                            class="switch"
                                            role="switch"
                                            :aria-checked="isA2laOn"
                                            @click="toggleA2la"
                                        >
                                            <span class="switch-thumb" />
                                        </button>
                                    </label>
                                </div>
                            </div>
                        </section>

                        <section
                            data-card-nav="dealer"
                            class="section-card detail-card"
                        >
                            <div class="card-scroll-nav">
                                <button
                                    type="button"
                                    class="card-scroll-nav-btn"
                                    title="前のカードへ"
                                    aria-label="前のカードへ"
                                    @click="scrollToCard('basic')"
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    class="card-scroll-nav-btn"
                                    title="次のカードへ"
                                    aria-label="次のカードへ"
                                    @click="scrollToCard('endUser')"
                                >
                                    ↓
                                </button>
                            </div>
                            <h3 class="card-title">dealer</h3>
                            <div class="field-stack">
                                <label class="input-field">
                                    <span>企業様名</span>
                                    <input type="text" :value="draftRecord?.dealer ?? record?.dealer ?? ''" @input="updateDraftValue('dealer', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>部署</span>
                                    <input type="text" :value="draftRecord?.dealer_depart ?? record?.dealer_depart ?? ''" @input="updateDraftValue('dealer_depart', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>担当者</span>
                                    <input type="text" :value="draftRecord?.contactPerson ?? record?.contactPerson ?? ''" @input="updateDraftValue('contactPerson', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>〒</span>
                                    <input type="text" maxlength="10" :value="draftRecord?.zipcode ?? record?.zipcode ?? ''" @input="updateDraftValue('zipcode', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>address1</span>
                                    <input type="text" maxlength="5" :value="draftRecord?.address1 ?? record?.address1 ?? ''" @input="updateDraftValue('address1', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>address2</span>
                                    <input type="text" :value="draftRecord?.address2 ?? record?.address2 ?? ''" @input="updateDraftValue('address2', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>℡</span>
                                    <input type="text" :value="draftRecord?.phone ?? record?.phone ?? ''" @input="updateDraftValue('phone', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>E-mail</span>
                                    <input type="text" :value="draftRecord?.email ?? record?.email ?? ''" @input="updateDraftValue('email', $event.target.value)">
                                </label>
                            </div>
                        </section>

                        <section
                            data-card-nav="endUser"
                            class="section-card detail-card"
                        >
                            <div class="card-scroll-nav">
                                <button
                                    type="button"
                                    class="card-scroll-nav-btn"
                                    title="前のカードへ"
                                    aria-label="前のカードへ"
                                    @click="scrollToCard('dealer')"
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    class="card-scroll-nav-btn"
                                    title="次のカードへ"
                                    aria-label="次のカードへ"
                                    @click="scrollToCard('delivery')"
                                >
                                    ↓
                                </button>
                            </div>
                            <h3 class="card-title">endUser</h3>
                            <div class="field-stack">
                                <label class="input-field">
                                    <span>E/U</span>
                                    <input type="text" :value="draftRecord?.endUser ?? record?.endUser ?? ''" @input="updateDraftValue('endUser', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>部署</span>
                                    <input type="text" :value="draftRecord?.endUser_depart ?? record?.endUser_depart ?? ''" @input="updateDraftValue('endUser_depart', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>担当者</span>
                                    <input type="text" :value="draftRecord?.endUser_contactPerson ?? record?.endUser_contactPerson ?? ''" @input="updateDraftValue('endUser_contactPerson', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>〒</span>
                                    <input type="text" maxlength="10" :value="draftRecord?.endUser_zipcode ?? record?.endUser_zipcode ?? ''" @input="updateDraftValue('endUser_zipcode', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>address1</span>
                                    <input type="text" maxlength="5" :value="draftRecord?.endUser_address1 ?? record?.endUser_address1 ?? ''" @input="updateDraftValue('endUser_address1', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>address2</span>
                                    <input type="text" :value="draftRecord?.endUser_address2 ?? record?.endUser_address2 ?? ''" @input="updateDraftValue('endUser_address2', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>℡</span>
                                    <input type="text" :value="draftRecord?.endUser_phone ?? record?.endUser_phone ?? ''" @input="updateDraftValue('endUser_phone', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>E-mail</span>
                                    <input type="text" :value="draftRecord?.endUser_email ?? record?.endUser_email ?? ''" @input="updateDraftValue('endUser_email', $event.target.value)">
                                </label>
                            </div>
                        </section>

                        <section
                            data-card-nav="delivery"
                            class="section-card detail-card"
                        >
                            <div class="card-scroll-nav">
                                <button
                                    type="button"
                                    class="card-scroll-nav-btn"
                                    title="前のカードへ"
                                    aria-label="前のカードへ"
                                    @click="scrollToCard('endUser')"
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    class="card-scroll-nav-btn"
                                    disabled
                                    title="次のカードへ"
                                    aria-label="次のカードへ"
                                >
                                    ↓
                                </button>
                            </div>
                            <h3 class="card-title">delivery</h3>
                            <div class="field-stack">
                                <label class="input-field">
                                    <span>発送先企業様名</span>
                                    <input type="text" :value="draftRecord?.deliveryDestination_company ?? record?.deliveryDestination_company ?? ''" @input="updateDraftValue('deliveryDestination_company', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>発送先部署</span>
                                    <input type="text" :value="draftRecord?.deliveryDestination_depart ?? record?.deliveryDestination_depart ?? ''" @input="updateDraftValue('deliveryDestination_depart', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>担当者</span>
                                    <input type="text" :value="draftRecord?.deliveryDestination_contactPerson ?? record?.deliveryDestination_contactPerson ?? ''" @input="updateDraftValue('deliveryDestination_contactPerson', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>〒</span>
                                    <input type="text" maxlength="10" :value="draftRecord?.deliveryDestination_zipcode ?? record?.deliveryDestination_zipcode ?? ''" @input="updateDraftValue('deliveryDestination_zipcode', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>address1</span>
                                    <input type="text" maxlength="5" :value="draftRecord?.deliveryDestination_address1 ?? record?.deliveryDestination_address1 ?? ''" @input="updateDraftValue('deliveryDestination_address1', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>address2</span>
                                    <input type="text" :value="draftRecord?.deliveryDestination_address2 ?? record?.deliveryDestination_address2 ?? ''" @input="updateDraftValue('deliveryDestination_address2', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>℡</span>
                                    <input type="text" :value="draftRecord?.deliveryDestination_phone ?? record?.deliveryDestination_phone ?? ''" @input="updateDraftValue('deliveryDestination_phone', $event.target.value)">
                                </label>
                                <label class="input-field">
                                    <span>E-mail</span>
                                    <input type="text" :value="draftRecord?.deliveryDestination_email ?? record?.deliveryDestination_email ?? ''" @input="updateDraftValue('deliveryDestination_email', $event.target.value)">
                                </label>
                            </div>
                        </section>
                    </div>
                </div>
            </Pane>
        </Splitpanes>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import AssociatedCapturedImages from '@/components/ServiceRecord/AssociatedCapturedImages.vue'
import AttachedFileItem from '@/components/ServiceRecord/AttachedFileItem.vue'
import { apiFetch } from '@/utils/apiFetch'

const page = usePage()

const props = defineProps({
    record: Object,
    draftRecord: Object,
    notes: { type: Array, default: () => [] },
    parts: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    capturedImages: { type: Array, default: () => [] },
    attachmentsLoading: { type: Boolean, default: false },
    attachmentsError: { type: String, default: '' },
})

const emit = defineEmits(['open-dialog', 'files-updated', 'reload-attachments'])
const fileSortSaving = ref(false)
const rightScrollEl = ref(null)
const leftPaneSize = ref(60)
const rightPaneSize = ref(40)

function compareFilesBySortNum(a, b) {
    const aNull = a?.sortNum == null
    const bNull = b?.sortNum == null
    if (aNull && bNull) return Number(a?.id ?? 0) - Number(b?.id ?? 0)
    if (aNull) return 1
    if (bNull) return -1
    if (Number(a.sortNum) !== Number(b.sortNum)) {
        return Number(a.sortNum) - Number(b.sortNum)
    }
    return Number(a?.id ?? 0) - Number(b?.id ?? 0)
}

const sortedFiles = computed(() =>
    [...(props.files ?? [])].sort(compareFilesBySortNum),
)

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function getFilesApiBase() {
    const basePath = window.location.pathname.replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
    return `${window.location.origin}${basePath}/files`
}

async function persistFileSortNum(fileId, sortNum) {
    const result = await apiFetch(`${getFilesApiBase()}/${fileId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ sortNum }),
    })

    if (!result) {
        throw new Error('順序の更新に失敗しました。')
    }

    const { response, data } = result
    if (!response.ok) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : null
        throw new Error(validationMessage || data.message || `順序の更新に失敗しました。（HTTP ${response.status}）`)
    }

    return data.file
}

async function updateFileSortNum(fileId, sortNum) {
    if (fileSortSaving.value) return

    fileSortSaving.value = true
    try {
        const updated = await persistFileSortNum(fileId, sortNum)
        const nextFiles = (props.files ?? []).map((file) => (
            String(file.id) === String(fileId)
                ? { ...file, ...updated, sortNum: updated?.sortNum ?? sortNum }
                : file
        ))
        emit('files-updated', nextFiles.sort(compareFilesBySortNum))
    } catch (e) {
        window.alert(e.message || '順序の更新に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

async function moveFile(fileId, direction) {
    if (fileSortSaving.value) return

    const list = [...sortedFiles.value]
    const index = list.findIndex(file => String(file.id) === String(fileId))
    if (index < 0) return

    const swapIndex = direction === 'up' ? index - 1 : index + 1
    if (swapIndex < 0 || swapIndex >= list.length) return

    ;[list[index], list[swapIndex]] = [list[swapIndex], list[index]]

    const updates = list.map((file, idx) => ({
        id: file.id,
        sortNum: (idx + 1) * 10,
    }))

    fileSortSaving.value = true
    try {
        const results = await Promise.all(
            updates.map(item => persistFileSortNum(item.id, item.sortNum)),
        )
        const byId = new Map(results.map(file => [String(file.id), file]))
        const nextFiles = (props.files ?? []).map((file) => {
            const updated = byId.get(String(file.id))
            return updated ? { ...file, ...updated } : file
        })
        emit('files-updated', nextFiles.sort(compareFilesBySortNum))
    } catch (e) {
        window.alert(e.message || '表示順の変更に失敗しました。')
    } finally {
        fileSortSaving.value = false
    }
}

function syncPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

function scrollToCard(cardKey) {
    const scrollParent = rightScrollEl.value
    if (!scrollParent) return
    const target = scrollParent.querySelector(`[data-card-nav="${cardKey}"]`)
    if (!target) return

    const parentRect = scrollParent.getBoundingClientRect()
    const targetRect = target.getBoundingClientRect()
    const nextTop = scrollParent.scrollTop + (targetRect.top - parentRect.top)
    scrollParent.scrollTo({ top: Math.max(0, nextTop), behavior: 'smooth' })
}

function updateDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value
}

function updateNumericDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value === '' ? null : Number(value)
}

const isA2laOn = computed(() => {
    const value = props.draftRecord?.a2la ?? props.record?.a2la
    return value === true || value === 1 || value === '1'
})

function toggleA2la() {
    if (!props.draftRecord) return
    props.draftRecord.a2la = isA2laOn.value ? 0 : 1
}

function openServiceMasterSelect() {
    emit('open-dialog', 'MASTER_SELECT', {
        kind: 'serviceMaster',
        serviceID: props.draftRecord?.serviceID ?? props.record?.serviceID,
        productName: props.draftRecord?.productName ?? props.record?.productName,
    })
}
</script>

<style scoped>
.detail-form {
    height: 100%;
    min-height: 0;
}

.detail-splitpanes {
    height: 100%;
}

.detail-pane {
    min-width: 0;
    min-height: 0;
    display: flex;
}

.files-section,
.info-section {
    width: 100%;
    min-height: 0;
    height: 100%;
    padding: 12px;
    box-sizing: border-box;
}

/* 左 Files のヘッダー＋書類バッジ＋ファイル操作バー下端（プレビュー開始位置）に揃える */
.info-section {
    padding-top: 108px;
}

.pane-content-scroll {
    overflow: auto;
    height: 100%;
}

.info-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.section-card {
    padding: 16px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}

.section-card-files {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    flex: 0 0 auto;
}

.section-header h3,
.card-title {
    margin: 0 0 10px;
    font-size: 16px;
    color: #1e293b;
}

.section-header h3 {
    margin: 0;
}

.card-scroll-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.card-scroll-nav-btn {
    width: 32px;
    height: 28px;
    padding: 0;
    border: 1px solid #64748b;
    border-radius: 4px;
    background: #f1f5f9;
    color: #0f172a;
    font-size: 14px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
}

.card-scroll-nav-btn:hover:not(:disabled) {
    background: #e2e8f0;
    border-color: #475569;
}

.card-scroll-nav-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.card-meta {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 13px;
}

.field-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.grid-cell {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 8px;
}

.cell-label {
    flex: 0 0 7.5em;
    font-size: 16px;
    color: #475569;
    font-weight: 700;
}

.field-button,
.field-select,
.field-input {
    flex: 1 1 auto;
    min-width: 0;
    width: auto;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    box-sizing: border-box;
    font-size: 16px;
    font-weight: 700;
}

.field-button {
    text-align: left;
    cursor: pointer;
}

.input-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 700;
    color: #475569;
}

.input-field > span {
    flex: 0 0 7.5em;
    font-size: 16px;
    font-weight: 700;
}

.input-field input {
    flex: 1 1 auto;
    min-width: 0;
    width: auto;
    padding: 6px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    background: #fff;
    font-size: 16px;
    font-weight: 700;
}

.a2la-row {
    display: flex;
    align-items: center;
}

.flag-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    user-select: none;
}

.flag-toggle.on {
    background: #1d4ed8;
    border-color: #1e3a8a;
    color: #fff;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.15);
}

.flag-name {
    font-size: 16px;
    font-weight: 700;
    min-width: 3em;
}

.switch {
    position: relative;
    width: 38px;
    height: 22px;
    padding: 0;
    border: none;
    border-radius: 999px;
    background: #94a3b8;
    cursor: pointer;
    flex: 0 0 auto;
}

.flag-toggle.on .switch {
    background: #0f172a;
}

.switch-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    transition: transform 0.15s ease;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.35);
}

.flag-toggle.on .switch-thumb {
    transform: translateX(16px);
}

.files-list-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
    display: grid;
    gap: 12px;
    align-content: start;
}

.files-type-label {
    margin: 0 0 8px;
    flex: 0 0 auto;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.type-badge-doc {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #93c5fd;
}

.empty-message {
    margin: 0;
    color: #64748b;
}
</style>
