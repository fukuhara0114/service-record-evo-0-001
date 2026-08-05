<template>
    <div class="detail-form">
        <Splitpanes horizontal class="default-theme detail-splitpanes" @resized="syncOuterPaneSizes">
            <Pane class="detail-pane detail-pane-top" :size="topPaneSize" :min-size="30">
                <div class="detail-top-section pane-content-scroll">
                    <div class="detail-top-stack">
                        <section class="section-card detail-card">
                            <p class="card-meta">OrderID: {{ record?.orderID }} / receivedDate: {{ record?.receivedDate }}</p>
                            <div class="first-card-row">
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
                                    <span class="cell-label">poNum</span>
                                    <input type="text" class="field-input" :value="draftRecord?.poNum ?? record?.poNum ?? ''" @input="updateDraftValue('poNum', $event.target.value)">
                                </label>
                            </div>
                            <div class="first-card-row first-card-row-second">
                                <label class="grid-cell">
                                    <!-- <span class="cell-label">returnCode</span> -->
                                    <select class="field-select" :value="draftRecord?.returnCode ?? record?.returnCode ?? ''" @change="updateNumericDraftValue('returnCode', $event.target.value)">
                                        <option value="">選択してください</option>
                                        <option v-for="returnCode in page.props.returnCodes ?? []" :key="returnCode.id" :value="returnCode.id">
                                            {{ returnCode.description }} ({{ returnCode.id }})
                                        </option>
                                    </select>
                                </label>
                                <label class="grid-cell">
                                    <span class="cell-label">a2la</span>
                                    <input type="text" class="field-input" :value="draftRecord?.a2la ?? record?.a2la ?? ''" @input="updateDraftValue('a2la', $event.target.value)">
                                </label>
                            </div>
                        </section>

                        <section class="section-card detail-card">
                            <div class="address-card-rows">
                                <div class="address-row address-row-3col">
                                    企業様名
                                    <label class="input-field">
                                        <!-- <span>企業様名</span> -->
                                        <input type="text" :value="draftRecord?.dealer ?? record?.dealer ?? ''" @input="updateDraftValue('dealer', $event.target.value)">
                                    </label>
                                    部署
                                    <label class="input-field">
                                        <!-- <span>部署</span> -->
                                        <input type="text" :value="draftRecord?.dealer_depart ?? record?.dealer_depart ?? ''" @input="updateDraftValue('dealer_depart', $event.target.value)">
                                    </label>
                                    担当者
                                    <label class="input-field">
                                        <!-- <span>担当者</span> -->
                                        <input type="text" :value="draftRecord?.contactPerson ?? record?.contactPerson ?? ''" @input="updateDraftValue('contactPerson', $event.target.value)">
                                    </label>
                                </div>
                                <div class="address-row address-row-address">
                                    〒
                                    <label class="input-field field-zipcode">
                                        <!-- <span>zipcode</span> -->
                                        <input type="text" maxlength="10" :value="draftRecord?.zipcode ?? record?.zipcode ?? ''" @input="updateDraftValue('zipcode', $event.target.value)">
                                    </label>
                                    <label class="input-field field-address1">
                                        <!-- <span>address1</span> -->
                                        <input type="text" maxlength="5" :value="draftRecord?.address1 ?? record?.address1 ?? ''" @input="updateDraftValue('address1', $event.target.value)">
                                    </label>
                                    <label class="input-field field-address2">
                                        <!-- <span>address2</span> -->
                                        <input type="text" :value="draftRecord?.address2 ?? record?.address2 ?? ''" @input="updateDraftValue('address2', $event.target.value)">
                                    </label>
                                </div>
                                <div class="address-row address-row-contact">
                                    ℡
                                    <label class="input-field field-phone">
                                        <!-- <span>phone</span> -->
                                        <input type="text" :value="draftRecord?.phone ?? record?.phone ?? ''" @input="updateDraftValue('phone', $event.target.value)">
                                    </label>
                                    E-mail
                                    <label class="input-field field-email">
                                        <!-- <span>email</span> -->
                                        <input type="text" :value="draftRecord?.email ?? record?.email ?? ''" @input="updateDraftValue('email', $event.target.value)">
                                    </label>
                                </div>
                            </div>
                        </section>

                        <section class="section-card detail-card">
                            <div class="address-card-rows">
                                <div class="address-row address-row-3col">
                                    E/U
                                    <label class="input-field">
                                        <!-- <span>endUser</span> -->
                                        <input type="text" :value="draftRecord?.endUser ?? record?.endUser ?? ''" @input="updateDraftValue('endUser', $event.target.value)">
                                    </label>
                                    部署
                                    <label class="input-field">
                                        <!-- <span>endUser_depart</span> -->
                                        <input type="text" :value="draftRecord?.endUser_depart ?? record?.endUser_depart ?? ''" @input="updateDraftValue('endUser_depart', $event.target.value)">
                                    </label>
                                    担当者
                                    <label class="input-field">
                                        <!-- <span>endUser_contactPerson</span> -->
                                        <input type="text" :value="draftRecord?.endUser_contactPerson ?? record?.endUser_contactPerson ?? ''" @input="updateDraftValue('endUser_contactPerson', $event.target.value)">
                                    </label>
                                </div>
                                <div class="address-row address-row-address">
                                    〒
                                    <label class="input-field field-zipcode">
                                        <!-- <span>endUser_zipcode</span> -->
                                        <input type="text" maxlength="10" :value="draftRecord?.endUser_zipcode ?? record?.endUser_zipcode ?? ''" @input="updateDraftValue('endUser_zipcode', $event.target.value)">
                                    </label>
                                    <label class="input-field field-address1">
                                        <!-- <span>endUser_address1</span> -->
                                        <input type="text" maxlength="5" :value="draftRecord?.endUser_address1 ?? record?.endUser_address1 ?? ''" @input="updateDraftValue('endUser_address1', $event.target.value)">
                                    </label>
                                    <label class="input-field field-address2">
                                        <!-- <span>endUser_address2</span> -->
                                        <input type="text" :value="draftRecord?.endUser_address2 ?? record?.endUser_address2 ?? ''" @input="updateDraftValue('endUser_address2', $event.target.value)">
                                    </label>
                                </div>
                                <div class="address-row address-row-contact">
                                    ℡
                                    <label class="input-field field-phone">
                                        <!-- <span>endUser_phone</span> -->
                                        <input type="text" :value="draftRecord?.endUser_phone ?? record?.endUser_phone ?? ''" @input="updateDraftValue('endUser_phone', $event.target.value)">
                                    </label>
                                    E-mail
                                    <label class="input-field field-email">
                                        <!-- <span>endUser_email</span> -->
                                        <input type="text" :value="draftRecord?.endUser_email ?? record?.endUser_email ?? ''" @input="updateDraftValue('endUser_email', $event.target.value)">
                                    </label>
                                </div>
                            </div>
                        </section>

                        <section class="section-card detail-card">
                            <div class="address-card-rows">
                                <div class="address-row address-row-3col">
                                    発送先企業様名
                                    <label class="input-field">
                                        <!-- <span>deliveryDestination_company</span> -->
                                        <input type="text" :value="draftRecord?.deliveryDestination_company ?? record?.deliveryDestination_company ?? ''" @input="updateDraftValue('deliveryDestination_company', $event.target.value)">
                                    </label>
                                    発送先部署
                                    <label class="input-field">
                                        <!-- <span>deliveryDestination_depart</span> -->
                                        <input type="text" :value="draftRecord?.deliveryDestination_depart ?? record?.deliveryDestination_depart ?? ''" @input="updateDraftValue('deliveryDestination_depart', $event.target.value)">
                                    </label>
                                    担当者
                                    <label class="input-field">
                                        <!-- <span>deliveryDestination_contactPerson</span> -->
                                        <input type="text" :value="draftRecord?.deliveryDestination_contactPerson ?? record?.deliveryDestination_contactPerson ?? ''" @input="updateDraftValue('deliveryDestination_contactPerson', $event.target.value)">
                                    </label>
                                </div>
                                <div class="address-row address-row-address">
                                    〒
                                    <label class="input-field field-zipcode">
                                        <!-- <span>deliveryDestination_zipcode</span> -->
                                        <input type="text" maxlength="10" :value="draftRecord?.deliveryDestination_zipcode ?? record?.deliveryDestination_zipcode ?? ''" @input="updateDraftValue('deliveryDestination_zipcode', $event.target.value)">
                                    </label>
                                    <label class="input-field field-address1">
                                        <!-- <span>deliveryDestination_address1</span> -->
                                        <input type="text" maxlength="5" :value="draftRecord?.deliveryDestination_address1 ?? record?.deliveryDestination_address1 ?? ''" @input="updateDraftValue('deliveryDestination_address1', $event.target.value)">
                                    </label>
                                    <label class="input-field field-address2">
                                        <!-- <span>deliveryDestination_address2</span> -->
                                        <input type="text" :value="draftRecord?.deliveryDestination_address2 ?? record?.deliveryDestination_address2 ?? ''" @input="updateDraftValue('deliveryDestination_address2', $event.target.value)">
                                    </label>
                                </div>
                                <div class="address-row address-row-contact">
                                    ℡
                                    <label class="input-field field-phone">
                                        <!-- <span>deliveryDestination_phone</span> -->
                                        <input type="text" :value="draftRecord?.deliveryDestination_phone ?? record?.deliveryDestination_phone ?? ''" @input="updateDraftValue('deliveryDestination_phone', $event.target.value)">
                                    </label>
                                    E-mail
                                    <label class="input-field field-email">
                                        <!-- <span>deliveryDestination_email</span> -->
                                        <input type="text" :value="draftRecord?.deliveryDestination_email ?? record?.deliveryDestination_email ?? ''" @input="updateDraftValue('deliveryDestination_email', $event.target.value)">
                                    </label>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </Pane>

            <Pane class="detail-pane detail-pane-bottom" :size="bottomPaneSize" :min-size="15">
                <div class="detail-bottom-section pane-content-scroll">
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
    const basePath = window.location.pathname.replace(/\/(administrator|engineer)\/?$/, '')
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
const topPaneSize = ref(72)
const bottomPaneSize = ref(28)

function syncOuterPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    topPaneSize.value = panes[0].size
    bottomPaneSize.value = panes[1].size
}

function updateDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value
}

function updateNumericDraftValue(field, value) {
    if (!props.draftRecord) return
    props.draftRecord[field] = value === '' ? null : Number(value)
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

.detail-splitpanes::before {
    content: '';
    position: absolute;
    background-color:rgb(255, 0, 0);
    border-radius: 2px;
    transition: all 0.2s;
}

.detail-pane {
    min-width: 0;
    min-height: 0;
    display: flex;
}

.detail-top-section {
    width: 100%;
    min-height: 0;
    height: 100%;
    padding: 12px;
    box-sizing: border-box;
}

.detail-bottom-section {
    width: 100%;
    min-height: 0;
    height: 100%;
    padding: 12px;
    box-sizing: border-box;
    background-color:rgb(80, 80, 80);
}

.pane-content-scroll {
    overflow: auto;
    height: 100%;
}

.detail-top-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.detail-card {
    min-height: 0;
}

.card-meta {
    margin: -4px 0 12px;
    color: #64748b;
    font-size: 13px;
}

.first-card-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px 12px;
}

.first-card-row-second {
    margin-top: 10px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    max-width: 66%;
}

.grid-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cell-label {
    font-size: 13px;
    color: #475569;
    font-weight: 600;
}

.address-card-rows {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.address-row {
    display: flex;
    gap: 12px;
    align-items: flex-end;
}

.address-row-3col .input-field {
    flex: 1 1 0;
    min-width: 0;
}

.field-zipcode {
    flex: 0 0 auto;
}

.field-address1 {
    flex: 0 0 auto;
}

.field-address2 {
    flex: 1 1 auto;
    min-width: 0;
}

.field-phone {
    flex: 0 0 auto;
}

.field-email {
    flex: 0 0 auto;
}

.field-zipcode input {
    width: 12ch;
    max-width: 12ch;
}

.field-address1 input {
    width: 10ch;
    max-width: 10ch;
}

.field-phone input {
    width: 20ch;
    max-width: 20ch;
}

.field-email input {
    width: 32ch;
    max-width: 32ch;
}

.section-card {
    padding: 16px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}

.section-card h3 {
    margin: 0 0 12px;
    font-size: 16px;
    color: #1e293b;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.section-header h3 {
    margin: 0;
}

.field-button,
.field-select,
.field-input {
    width: 100%;
    padding: 4px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    box-sizing: border-box;
}

.field-button {
    text-align: left;
    cursor: pointer;
}

.input-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
    color: #475569;
}

.input-field input {
    padding: 6px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    background: #fff;
}

.input-field:not(.field-zipcode):not(.field-address1):not(.field-phone):not(.field-email) input {
    width: 100%;
}

.section-card-files {
    height: 100%;
    min-height: 120px;
}

.files-list-wrap {
    display: grid;
    gap: 12px;
}

.files-type-label {
    margin: 0 0 8px;
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
