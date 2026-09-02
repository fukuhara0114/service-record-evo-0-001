<template>
    <div class="detail-page">
        <header class="page-header">
            <div>
                <h1>Maintenance Contract 詳細</h1>
            </div>
            <div>
                <p class="subtitle">
                    ID: {{ form.id }}
                    <span v-if="form.contractTypeName"> / {{ form.contractTypeName }}</span>
                </p>
            </div>
            <div class="header-actions">
                <span v-if="success" class="msg success">{{ success }}</span>
                <span v-if="error" class="msg error">{{ error }}</span>
                <div class="header-extra-actions">
                    <button type="button" class="btn btn-dark" @click="openDuplicateDialog">複製を保存</button>
                    <button
                        type="button"
                        class="btn btn-dark"
                        :disabled="certificateLoading"
                        @click="generateCertificate"
                    >
                        {{ certificateLoading ? '生成中...' : '保守サービス保証書' }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-dark"
                        :disabled="ticketLoading"
                        @click="generateCertificationTicket"
                    >
                        {{ ticketLoading ? '生成中...' : '再校正チケット' }}
                    </button>
                </div>
                <div class="header-main-actions">
                    <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
                        {{ saving ? '保存中...' : '保存' }}
                    </button>
                    <a :href="listUrl" class="btn btn-secondary">一覧へ戻る</a>
                    <CloseToHomeButton :href="homeUrl" />
                </div>
            </div>
        </header>

        <Splitpanes class="default-theme detail-splitpanes" @resized="syncPaneSizes">
            <Pane class="detail-pane detail-pane-left" :size="leftPaneSize" :min-size="28">
            <div class="left-column">
                <section class="panel">
                    <h2>製品 </h2>
                    <div class="row row-3">
                        <label class="field">
                            <span>instrumentName</span>
                            <input v-model="form.instrumentName" type="text">
                        </label>
                        <label class="field">
                            <span>SN</span>
                            <input v-model="form.SN" type="text">
                        </label>
                        <label class="field">
                            <span>status</span>
                            <input v-model="form.status" type="text">
                        </label>
                    </div>
                </section>

                <section class="panel">
                    <h2>契約情報</h2>
                    <div class="row row-3">
                        <label class="field">
                            <span>RefNumber</span>
                            <input v-model="form.RefNumber" type="text">
                        </label>
                        <label class="field">
                            <span>契約種別</span>
                            <select v-model="form.contractType">
                                <option value="">未選択</option>
                                <option
                                    v-for="type in contractTypes"
                                    :key="type.id"
                                    :value="String(type.id)"
                                >
                                    {{ type.contractType }}（{{ type.id }}）
                                </option>
                            </select>
                        </label>
                        <label class="field">
                            <span>価格</span>
                            <input
                                type="text"
                                inputmode="decimal"
                                class="amount-input"
                                :value="amountFocused ? amountEditText : formatYen(form.amount)"
                                @focus="onAmountFocus"
                                @input="onAmountInput"
                                @blur="onAmountBlur"
                            >
                        </label>
                    </div>
                    <div class="row row-3">
                        <label class="field">
                            <span>startDate</span>
                            <DateInputWithToday v-model="form.startDate" />
                        </label>
                        <label class="field">
                            <span>expireDate</span>
                            <DateInputWithToday v-model="form.expireDate" />
                        </label>
                        <label class="field checkbox-field">
                            <span>certificationTicket</span>
                            <input v-model="form.certificationTicket" type="checkbox">
                        </label>
                    </div>
                    <div class="row row-3">
                        <label class="field">
                            <span>certificationExpireDate</span>
                            <DateInputWithToday v-model="form.certificationExpireDate" />
                        </label>
                    </div>
                </section>

                <section class="panel panel-plain">
                    <h2>受注</h2>
                    <div class="row row-3">
                        <label class="field">
                            <span>informedDate</span>
                            <DateInputWithToday v-model="form.informedDate" />
                        </label>
                        <label class="field checkbox-field">
                            <span>informed</span>
                            <input v-model="form.informed" type="checkbox">
                        </label>
                    </div>
                    <div class="row row-3">
                        <label class="field">
                            <span>renewalInformation</span>
                            <DateInputWithToday v-model="form.renewalInformation" />
                        </label>
                    </div>
                    <div class="row row-3">
                        <label class="field">
                            <span>renewedDate</span>
                            <DateInputWithToday v-model="form.renewedDate" />
                        </label>
                    </div>
                    <div class="row row-3">
                        <label class="field">
                            <span>shippingDate</span>
                            <DateInputWithToday v-model="form.shippingDate" />
                        </label>
                        <label class="field">
                            <span>orderedDate</span>
                            <DateInputWithToday v-model="form.orderedDate" />
                        </label>
                    </div>
                    <div class="row row-3">
                        <label class="field">
                            <span>yayoi_PO</span>
                            <input v-model="form.yayoi_PO" type="text">
                        </label>
                        <label class="field">
                            <span>mapics_PO</span>
                            <input v-model="form.mapics_PO" type="text">
                        </label>
                        <label class="field">
                            <span>invoice_num</span>
                            <input v-model="form.invoice_num" type="text">
                        </label>
                    </div>
                </section>

                <section class="panel panel-plain">
                    <label class="field">
                        <span>description</span>
                        <textarea v-model="form.description" rows="3"></textarea>
                    </label>
                    <label class="field">
                        <span>additional_information</span>
                        <textarea v-model="form.additional_information" rows="3"></textarea>
                    </label>
                </section>
            </div>
            </Pane>

            <Pane class="detail-pane detail-pane-right" :size="rightPaneSize" :min-size="28">
            <div class="right-column">
                <section class="panel stakeholder-panel">
                    <h2>dealer</h2>
                    <div class="row row-2">
                        <label class="field">
                            <span>dealer</span>
                            <input v-model="form.dealer" type="text">
                        </label>
                        <label class="field">
                            <span>branch</span>
                            <input v-model="form.branch" type="text">
                        </label>
                    </div>
                    <div class="row row-2">
                        <label class="field">
                            <span>contact</span>
                            <input v-model="form.contact" type="text">
                        </label>
                        <label class="field">
                            <span>phone</span>
                            <input v-model="form.phone" type="text">
                        </label>
                    </div>
                    <div class="row row-1">
                        <label class="field">
                            <span>email</span>
                            <input v-model="form.email" type="text">
                        </label>
                    </div>
                    <div class="row row-1">
                        <label class="field">
                            <span>address</span>
                            <textarea v-model="form.address" rows="3"></textarea>
                        </label>
                    </div>
                </section>

                <section class="panel stakeholder-panel">
                    <h2>endUser</h2>
                    <div class="row row-2">
                        <label class="field">
                            <span>endUser</span>
                            <input v-model="form.endUser" type="text">
                        </label>
                        <label class="field">
                            <span>endUser_depart</span>
                            <input v-model="form.endUser_depart" type="text">
                        </label>
                    </div>
                    <div class="row row-2">
                        <label class="field">
                            <span>endUser_contact</span>
                            <input v-model="form.endUser_contact" type="text">
                        </label>
                        <label class="field">
                            <span>endUser_phone</span>
                            <input v-model="form.endUser_phone" type="text">
                        </label>
                    </div>
                    <div class="row row-1">
                        <label class="field">
                            <span>endUser_email</span>
                            <input v-model="form.endUser_email" type="text">
                        </label>
                    </div>
                    <div class="row row-1">
                        <label class="field">
                            <span>endUser_address</span>
                            <textarea v-model="form.endUser_address" rows="3"></textarea>
                        </label>
                    </div>
                </section>
            </div>
            </Pane>
        </Splitpanes>

        <p class="meta-foot">
            最終更新: {{ form.lastEditPerson || '—' }} / {{ form.lastEditDate || '—' }}
        </p>

        <div
            v-if="certificateDialogOpen"
            class="dialog-overlay certificate-overlay"
            @click.self="closeCertificateDialog"
        >
            <div class="dialog-panel certificate-panel" role="dialog" aria-modal="true" aria-labelledby="certificate-dialog-title">
                <div class="certificate-dialog-header">
                    <h3 id="certificate-dialog-title">保守サービス保証書プレビュー</h3>
                    <div class="certificate-dialog-actions">
                        <button type="button" class="btn btn-secondary" @click="closeCertificateDialog">閉じる</button>
                        <button
                            v-if="certificatePdfUrl"
                            type="button"
                            class="btn btn-primary"
                            :disabled="certificateDownloading"
                            @click="downloadCertificate"
                        >
                            {{ certificateDownloading ? '保存中...' : 'ダウンロード' }}
                        </button>
                    </div>
                </div>
                <p v-if="certificateError" class="msg error certificate-error">{{ certificateError }}</p>
                <div class="certificate-viewport">
                    <img
                        v-if="certificatePreviewUrl"
                        class="certificate-image"
                        :src="certificatePreviewUrl"
                        alt="保守サービス保証書プレビュー"
                    >
                </div>
            </div>
        </div>

        <div
            v-if="ticketDialogOpen"
            class="dialog-overlay certificate-overlay"
            @click.self="closeTicketDialog"
        >
            <div class="dialog-panel certificate-panel" role="dialog" aria-modal="true" aria-labelledby="ticket-dialog-title">
                <div class="certificate-dialog-header">
                    <h3 id="ticket-dialog-title">再校正チケットプレビュー</h3>
                    <div class="certificate-dialog-actions">
                        <button type="button" class="btn btn-secondary" @click="closeTicketDialog">閉じる</button>
                        <button
                            v-if="ticketPdfUrl"
                            type="button"
                            class="btn btn-primary"
                            :disabled="ticketDownloading"
                            @click="downloadCertificationTicket"
                        >
                            {{ ticketDownloading ? '保存中...' : 'ダウンロード' }}
                        </button>
                    </div>
                </div>
                <p v-if="ticketError" class="msg error certificate-error">{{ ticketError }}</p>
                <div class="certificate-viewport ticket-preview-viewport">
                    <div
                        v-for="preview in ticketPreviewPages"
                        :key="preview.page"
                        class="ticket-preview-page"
                    >
                        <p v-if="ticketPreviewPages.length > 1" class="ticket-preview-label">
                            {{ preview.page }}年目
                        </p>
                        <img
                            class="certificate-image"
                            :src="preview.url"
                            :alt="`再校正チケットプレビュー ${preview.page}年目`"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="duplicateDialogOpen"
            class="dialog-overlay"
            @click.self="closeDuplicateDialog"
        >
            <div class="dialog-panel duplicate-dialog" role="dialog" aria-modal="true" aria-labelledby="duplicate-dialog-title">
                <div class="duplicate-dialog-header">
                    <h3 id="duplicate-dialog-title">複製を保存</h3>
                    <div class="duplicate-dialog-actions">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="duplicating"
                            @click="confirmDuplicate"
                        >
                            {{ duplicating ? '作成中...' : 'OK' }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-secondary"
                            :disabled="duplicating"
                            @click="closeDuplicateDialog"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
                <p v-if="duplicateError" class="msg error duplicate-error">{{ duplicateError }}</p>
                <div class="duplicate-toggle-list">
                    <button
                        type="button"
                        class="toggle-btn"
                        :class="{ on: duplicateSections.all }"
                        @click="toggleDuplicateAll"
                    >
                        全て
                    </button>
                    <button
                        v-for="item in duplicateSectionItems"
                        :key="item.key"
                        type="button"
                        class="toggle-btn"
                        :class="{ on: duplicateSections[item.key] }"
                        @click="toggleDuplicateSection(item.key)"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import DateInputWithToday from '@/components/DateInputWithToday.vue'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    contract: {
        type: Object,
        required: true,
    },
    contractTypes: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const saving = ref(false)
const error = ref('')
const success = ref('')
const leftPaneSize = ref(50)
const rightPaneSize = ref(50)
const amountFocused = ref(false)
const amountEditText = ref('')

const duplicateDialogOpen = ref(false)
const duplicating = ref(false)
const duplicateError = ref('')

const certificateDialogOpen = ref(false)
const certificateLoading = ref(false)
const certificateDownloading = ref(false)
const certificateError = ref('')
const certificatePreviewUrl = ref('')
const certificatePdfUrl = ref('')
const certificatePdfBlob = ref(null)
const certificateFilename = ref('maintenance_contract.pdf')

const ticketDialogOpen = ref(false)
const ticketLoading = ref(false)
const ticketDownloading = ref(false)
const ticketError = ref('')
const ticketPreviewPages = ref([])
const ticketPdfUrl = ref('')
const ticketPdfBlob = ref(null)
const ticketFilename = ref('certification_ticket.pdf')

const duplicateSectionItems = [
    { key: 'product', label: '製品' },
    { key: 'contract', label: '契約情報' },
    { key: 'order', label: '受注' },
    { key: 'dealer', label: 'dealer' },
    { key: 'endUser', label: 'endUser' },
    { key: 'description', label: 'description' },
    { key: 'additional_information', label: 'additional information' },
]

const duplicateSections = reactive({
    all: true,
    product: true,
    contract: true,
    order: true,
    dealer: true,
    endUser: true,
    description: true,
    additional_information: true,
})

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const listUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/maintenance-contracts`)

const contractTypeDescription = computed(() => {
    const id = form.contractType === '' ? null : Number(form.contractType)
    if (Number.isFinite(id)) {
        const found = props.contractTypes.find((type) => Number(type.id) === id)
        if (found?.description) return found.description
    }
    return props.contract.contractTypeDescription ?? ''
})

function syncPaneSizes({ panes } = {}) {
    if (!panes || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

function formatYen(value) {
    if (value === '' || value == null) return ''
    const num = Number(value)
    if (!Number.isFinite(num)) return String(value)
    return new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
    }).format(num)
}

function parseYenInput(value) {
    const raw = String(value ?? '').replace(/[￥¥,\s]/g, '').trim()
    if (raw === '') return ''
    const num = Number(raw)
    return Number.isFinite(num) ? num : null
}

function onAmountFocus() {
    amountFocused.value = true
    amountEditText.value = form.amount === '' || form.amount == null
        ? ''
        : String(form.amount)
}

function onAmountInput(event) {
    amountEditText.value = event.target.value
}

function onAmountBlur() {
    amountFocused.value = false
    const parsed = parseYenInput(amountEditText.value)
    if (parsed === null) {
        amountEditText.value = form.amount === '' || form.amount == null
            ? ''
            : String(form.amount)
        return
    }
    form.amount = parsed
}

const form = reactive({
    id: props.contract.id,
    dealer: props.contract.dealer ?? '',
    branch: props.contract.branch ?? '',
    contact: props.contract.contact ?? '',
    phone: props.contract.phone ?? '',
    email: props.contract.email ?? '',
    address: props.contract.address ?? '',
    endUser: props.contract.endUser ?? '',
    endUser_depart: props.contract.endUser_depart ?? '',
    endUser_contact: props.contract.endUser_contact ?? '',
    endUser_phone: props.contract.endUser_phone ?? '',
    endUser_email: props.contract.endUser_email ?? '',
    endUser_address: props.contract.endUser_address ?? '',
    instrumentName: props.contract.instrumentName ?? '',
    SN: props.contract.SN ?? '',
    shippingDate: normalizeDateFormValue(props.contract.shippingDate),
    yayoi_PO: props.contract.yayoi_PO ?? '',
    orderedDate: normalizeDateFormValue(props.contract.orderedDate),
    mapics_PO: props.contract.mapics_PO ?? '',
    invoice_num: props.contract.invoice_num ?? '',
    startDate: normalizeDateFormValue(props.contract.startDate),
    expireDate: normalizeDateFormValue(props.contract.expireDate),
    certificationTicket: !!props.contract.certificationTicket,
    certificationExpireDate: normalizeDateFormValue(props.contract.certificationExpireDate),
    renewalInformation: normalizeDateFormValue(props.contract.renewalInformation),
    informedDate: normalizeDateFormValue(props.contract.informedDate),
    renewedDate: normalizeDateFormValue(props.contract.renewedDate),
    contractType: props.contract.contractType != null ? String(props.contract.contractType) : '',
    contractTypeName: props.contract.contractTypeName ?? '',
    informed: !!props.contract.informed,
    amount: props.contract.amount ?? '',
    status: props.contract.status ?? '',
    RefNumber: props.contract.RefNumber ?? '',
    description: props.contract.description ?? '',
    additional_information: props.contract.additional_information ?? '',
    lastEditPerson: props.contract.lastEditPerson ?? '',
    lastEditDate: props.contract.lastEditDate ?? '',
})

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function nullable(value) {
    if (value === '' || value === undefined) return null
    return value
}

function normalizeDateFormValue(value) {
    if (value == null || value === '') return ''
    const text = String(value).trim()
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (!match) return ''
    const year = Number(match[1])
    if (year < 1901) return ''
    return `${match[1]}-${match[2]}-${match[3]}`
}

function nullableDate(value) {
    const normalized = normalizeDateFormValue(value)
    return normalized === '' ? null : normalized
}

function buildContractPayload() {
    return {
        dealer: nullable(form.dealer),
        branch: nullable(form.branch),
        contact: nullable(form.contact),
        phone: nullable(form.phone),
        email: nullable(form.email),
        address: nullable(form.address),
        endUser: nullable(form.endUser),
        endUser_depart: nullable(form.endUser_depart),
        endUser_contact: nullable(form.endUser_contact),
        endUser_phone: nullable(form.endUser_phone),
        endUser_email: nullable(form.endUser_email),
        endUser_address: nullable(form.endUser_address),
        instrumentName: nullable(form.instrumentName),
        SN: nullable(form.SN),
        shippingDate: nullableDate(form.shippingDate),
        yayoi_PO: nullable(form.yayoi_PO),
        orderedDate: nullableDate(form.orderedDate),
        mapics_PO: nullable(form.mapics_PO),
        invoice_num: nullable(form.invoice_num),
        startDate: nullableDate(form.startDate),
        expireDate: nullableDate(form.expireDate),
        certificationTicket: !!form.certificationTicket,
        certificationExpireDate: nullableDate(form.certificationExpireDate),
        renewalInformation: nullableDate(form.renewalInformation),
        informedDate: nullableDate(form.informedDate),
        renewedDate: nullableDate(form.renewedDate),
        contractType: form.contractType === '' ? null : Number(form.contractType),
        informed: !!form.informed,
        amount: form.amount === '' ? null : Number(form.amount),
        status: nullable(form.status),
        RefNumber: nullable(form.RefNumber),
        description: nullable(form.description),
        additional_information: nullable(form.additional_information),
    }
}

function syncDuplicateAllFromSections() {
    duplicateSections.all = duplicateSectionItems.every((item) => duplicateSections[item.key])
}

function setAllDuplicateSections(value) {
    duplicateSections.all = value
    for (const item of duplicateSectionItems) {
        duplicateSections[item.key] = value
    }
}

function openDuplicateDialog() {
    setAllDuplicateSections(true)
    duplicateError.value = ''
    duplicateDialogOpen.value = true
}

function closeDuplicateDialog() {
    if (duplicating.value) return
    duplicateDialogOpen.value = false
    duplicateError.value = ''
}

function toggleDuplicateAll() {
    setAllDuplicateSections(!duplicateSections.all)
}

function toggleDuplicateSection(key) {
    duplicateSections[key] = !duplicateSections[key]
    syncDuplicateAllFromSections()
}

async function confirmDuplicate() {
    const sections = {
        product: !!duplicateSections.product,
        contract: !!duplicateSections.contract,
        order: !!duplicateSections.order,
        dealer: !!duplicateSections.dealer,
        endUser: !!duplicateSections.endUser,
        description: !!duplicateSections.description,
        additional_information: !!duplicateSections.additional_information,
    }

    if (!Object.values(sections).some(Boolean)) {
        duplicateError.value = 'コピーする項目を1つ以上選択してください。'
        return
    }

    duplicating.value = true
    duplicateError.value = ''
    error.value = ''
    success.value = ''

    try {
        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/maintenance-contracts/${form.id}/duplicate`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    sections,
                    ...buildContractPayload(),
                }),
            },
        )
        if (!result) throw new Error('複製に失敗しました。')

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `複製に失敗しました。（HTTP ${response.status}）`)
        }

        const newId = data.contract?.id
        if (!newId) throw new Error('複製後のIDを取得できませんでした。')

        duplicateDialogOpen.value = false
        window.location.href = `${page.props.appBaseUrl}/servicerecord/maintenance-contracts/${newId}`
    } catch (e) {
        duplicateError.value = e.message || '複製に失敗しました。'
    } finally {
        duplicating.value = false
    }
}

function revokeCertificateUrls() {
    if (certificatePreviewUrl.value) {
        URL.revokeObjectURL(certificatePreviewUrl.value)
        certificatePreviewUrl.value = ''
    }
    if (certificatePdfUrl.value) {
        URL.revokeObjectURL(certificatePdfUrl.value)
        certificatePdfUrl.value = ''
    }
    certificatePdfBlob.value = null
}

function closeCertificateDialog() {
    if (certificateLoading.value) return
    certificateDialogOpen.value = false
    certificateError.value = ''
    revokeCertificateUrls()
}

function buildCertificatePayload() {
    return {
        RefNumber: nullable(form.RefNumber),
        instrumentName: nullable(form.instrumentName),
        SN: nullable(form.SN),
        startDate: nullableDate(form.startDate),
        expireDate: nullableDate(form.expireDate),
        endUser: nullable(form.endUser),
        endUser_depart: nullable(form.endUser_depart),
        endUser_address: nullable(form.endUser_address),
        endUser_phone: nullable(form.endUser_phone),
        dealer: nullable(form.dealer),
        branch: nullable(form.branch),
        contact: nullable(form.contact),
        phone: nullable(form.phone),
    }
}

async function generateCertificate() {
    if (certificateLoading.value) return

    certificateLoading.value = true
    certificateError.value = ''
    error.value = ''
    success.value = ''
    revokeCertificateUrls()

    const endpoint = `${page.props.appBaseUrl}/servicerecord/maintenance-contracts/${form.id}/certificate`
    const common = {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(buildCertificatePayload()),
    }

    try {
        const [pngResponse, pdfResponse] = await Promise.all([
            fetch(endpoint, { ...common, headers: { ...common.headers, Accept: 'image/png' } }),
            fetch(endpoint, { ...common, headers: { ...common.headers, Accept: 'application/pdf' } }),
        ])

        if (!pngResponse.ok) {
            let message = `保守サービス保証書の生成に失敗しました。（HTTP ${pngResponse.status}）`
            const ct = pngResponse.headers.get('Content-Type') || ''
            if (ct.includes('application/json')) {
                const data = await pngResponse.json().catch(() => ({}))
                message = data.message || data.error || message
            }
            throw new Error(message)
        }
        if (!pdfResponse.ok) {
            throw new Error(`保守サービス保証書 PDF の取得に失敗しました。（HTTP ${pdfResponse.status}）`)
        }

        const pngBlob = await pngResponse.blob()
        const pdfBlob = await pdfResponse.blob()
        certificatePreviewUrl.value = URL.createObjectURL(pngBlob)
        certificatePdfUrl.value = URL.createObjectURL(pdfBlob)
        certificatePdfBlob.value = pdfBlob

        const headerName = pdfResponse.headers.get('X-Filename')
        const ref = String(form.RefNumber || '').trim()
        certificateFilename.value = headerName
            || (ref !== '' ? `maintenance_contract-${ref}.pdf` : `maintenance_contract-${form.id}.pdf`)

        certificateDialogOpen.value = true
    } catch (e) {
        certificateError.value = e.message || '保守サービス保証書の生成に失敗しました。'
        error.value = certificateError.value
        certificateDialogOpen.value = true
    } finally {
        certificateLoading.value = false
    }
}

function downloadCertificate() {
    if (!certificatePdfBlob.value || certificateDownloading.value) return

    certificateDownloading.value = true
    try {
        const url = certificatePdfUrl.value || URL.createObjectURL(certificatePdfBlob.value)
        const link = document.createElement('a')
        link.href = url
        link.download = certificateFilename.value || 'maintenance_contract.pdf'
        link.rel = 'noopener'
        document.body.appendChild(link)
        link.click()
        link.remove()
        success.value = '保守サービス保証書をダウンロードしました。'
    } catch (e) {
        certificateError.value = e.message || 'ダウンロードに失敗しました。'
    } finally {
        certificateDownloading.value = false
    }
}

function revokeTicketUrls() {
    ticketPreviewPages.value.forEach((preview) => {
        if (preview.url) {
            URL.revokeObjectURL(preview.url)
        }
    })
    ticketPreviewPages.value = []
    if (ticketPdfUrl.value) {
        URL.revokeObjectURL(ticketPdfUrl.value)
        ticketPdfUrl.value = ''
    }
    ticketPdfBlob.value = null
}

function closeTicketDialog() {
    if (ticketLoading.value) return
    ticketDialogOpen.value = false
    ticketError.value = ''
    revokeTicketUrls()
}

function buildTicketPayload() {
    return {
        RefNumber: nullable(form.RefNumber),
        instrumentName: nullable(form.instrumentName),
        SN: nullable(form.SN),
        startDate: nullableDate(form.startDate),
        expireDate: nullableDate(form.expireDate),
        dealer: nullable(form.dealer),
        branch: nullable(form.branch),
        phone: nullable(form.phone),
        description: nullable(form.description),
        additional_information: nullable(form.additional_information),
        contractTypeDescription: nullable(contractTypeDescription.value),
    }
}

async function generateCertificationTicket() {
    if (ticketLoading.value) return

    ticketLoading.value = true
    ticketError.value = ''
    error.value = ''
    success.value = ''
    revokeTicketUrls()

    const endpoint = `${page.props.appBaseUrl}/servicerecord/maintenance-contracts/${form.id}/certification-ticket`
    const common = {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(buildTicketPayload()),
    }

    try {
        const [previewResponse, pdfResponse] = await Promise.all([
            fetch(`${endpoint}?format=preview`, {
                ...common,
                headers: { ...common.headers, Accept: 'application/json' },
            }),
            fetch(endpoint, {
                ...common,
                headers: { ...common.headers, Accept: 'application/pdf' },
            }),
        ])

        if (!previewResponse.ok) {
            let message = `再校正チケットの生成に失敗しました。（HTTP ${previewResponse.status}）`
            const ct = previewResponse.headers.get('Content-Type') || ''
            if (ct.includes('application/json')) {
                const data = await previewResponse.json().catch(() => ({}))
                message = data.message || data.error || message
            }
            throw new Error(message)
        }
        if (!pdfResponse.ok) {
            throw new Error(`再校正チケット PDF の取得に失敗しました。（HTTP ${pdfResponse.status}）`)
        }

        const previewData = await previewResponse.json()
        const pages = Array.isArray(previewData.pages) ? previewData.pages : []
        if (pages.length === 0) {
            throw new Error('再校正チケットのプレビュー画像を取得できませんでした。')
        }

        ticketPreviewPages.value = pages.map((pageData) => {
            const binary = atob(pageData.image)
            const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0))
            const blob = new Blob([bytes], { type: 'image/png' })
            return {
                page: pageData.page,
                url: URL.createObjectURL(blob),
            }
        })

        const pdfBlob = await pdfResponse.blob()
        ticketPdfUrl.value = URL.createObjectURL(pdfBlob)
        ticketPdfBlob.value = pdfBlob

        const headerName = pdfResponse.headers.get('X-Filename')
        const ref = String(form.RefNumber || '').trim()
        ticketFilename.value = headerName
            || (ref !== '' ? `maintenance_contract-${ref}.pdf` : `certification_ticket-${form.id}.pdf`)

        ticketDialogOpen.value = true
    } catch (e) {
        ticketError.value = e.message || '再校正チケットの生成に失敗しました。'
        error.value = ticketError.value
        ticketDialogOpen.value = true
    } finally {
        ticketLoading.value = false
    }
}

function downloadCertificationTicket() {
    if (!ticketPdfBlob.value || ticketDownloading.value) return

    ticketDownloading.value = true
    try {
        const url = ticketPdfUrl.value || URL.createObjectURL(ticketPdfBlob.value)
        const link = document.createElement('a')
        link.href = url
        link.download = ticketFilename.value || 'certification_ticket.pdf'
        link.rel = 'noopener'
        document.body.appendChild(link)
        link.click()
        link.remove()
        success.value = '再校正チケットをダウンロードしました。'
    } catch (e) {
        ticketError.value = e.message || 'ダウンロードに失敗しました。'
    } finally {
        ticketDownloading.value = false
    }
}

async function save() {
    saving.value = true
    error.value = ''
    success.value = ''

    try {
        const body = buildContractPayload()

        const result = await apiFetch(
            `${page.props.appBaseUrl}/servicerecord/maintenance-contracts/${form.id}`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(body),
            },
        )
        if (!result) throw new Error('保存に失敗しました。')

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        success.value = data.message || '保存しました。'
        if (data.contract) {
            Object.assign(form, {
                ...data.contract,
                shippingDate: normalizeDateFormValue(data.contract.shippingDate),
                orderedDate: normalizeDateFormValue(data.contract.orderedDate),
                startDate: normalizeDateFormValue(data.contract.startDate),
                expireDate: normalizeDateFormValue(data.contract.expireDate),
                certificationTicket: !!data.contract.certificationTicket,
                certificationExpireDate: normalizeDateFormValue(data.contract.certificationExpireDate),
                renewalInformation: normalizeDateFormValue(data.contract.renewalInformation),
                informedDate: normalizeDateFormValue(data.contract.informedDate),
                renewedDate: normalizeDateFormValue(data.contract.renewedDate),
                contractType: data.contract.contractType != null ? String(data.contract.contractType) : '',
                informed: !!data.contract.informed,
                amount: data.contract.amount ?? '',
            })
        }
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.detail-page {
    min-height: 100vh;
    padding: 12px 16px 24px;
    background: #dbe4ee;
    box-sizing: border-box;
    color: #1e293b;
    font-weight: 700;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 12px;
}

.page-header h1 {
    margin: 0 0 4px;
    font-size: 22px;
}

.subtitle {
    margin: 0;
    color: #64748b;
    font-size: 18px;
    font-weight: 900;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0;
    flex-wrap: wrap;
}

.header-actions .msg {
    margin-right: 8px;
}

.header-extra-actions {
    display: flex;
    align-items: center;
    gap: 50px;
    margin-right: 100px;
}

.header-main-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-dark {
    background: #4a4a4a;
}

.msg {
    font-size: 12px;
}

.msg.success {
    color: #166534;
}

.msg.error {
    color: #b91c1c;
}

.btn {
    min-height: 34px;
    padding: 6px 14px;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    color: #fff;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn:disabled {
    opacity: 0.6;
    cursor: wait;
}

.btn-primary {
    background: #2563eb;
}

.btn-secondary {
    background: #64748b;
}

.detail-splitpanes {
    height: auto;
    min-height: calc(100vh - 110px);
    background: transparent;
}

.detail-pane {
    overflow: auto;
    padding: 0 4px;
}

.left-column,
.right-column {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
    height: 100%;
}

:deep(.splitpanes__splitter) {
    background: #94a3b8;
    min-width: 6px;
}

:deep(.splitpanes__splitter:hover) {
    background: #64748b;
}

.panel {
    background: #e0e0e0;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 12px 14px;
}

.panel-plain {
    border-color: #d8e0ea;
}

.panel h2 {
    margin: 0 0 10px;
    font-size: 15px;
    font-weight: 700;
    color: #000;
}

.stakeholder-panel h2 {
    margin-bottom: 12px;
}

.row {
    display: grid;
    gap: 8px 12px;
    margin-bottom: 8px;
}

.row:last-child {
    margin-bottom: 0;
}

.row-1 {
    grid-template-columns: minmax(0, 1fr);
}

.row-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.row-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
    color: #000;
    font-weight: 700;
    min-width: 0;
}

.field > span {
    color: #000;
    font-size: 13px;
    font-weight: 700;
}

.field input,
.field select,
.field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #94a3b8;
    border-radius: 3px;
    padding: 7px 8px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    background: #fff;
}

.field textarea {
    resize: vertical;
    line-height: 1.4;
}

.amount-input {
    text-align: left;
    font-variant-numeric: tabular-nums;
}

.field.checkbox-field {
    flex-direction: row;
    align-items: flex-end;
    gap: 8px;
    padding-bottom: 8px;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
}

.field.checkbox-field input {
    width: auto;
    margin: 0;
}

.meta-foot {
    margin: 12px 0 0;
    color: #64748b;
    font-size: 12px;
}

@media (max-width: 720px) {
    .row-2,
    .row-3 {
        grid-template-columns: 1fr;
    }

    .field.checkbox-field {
        padding-bottom: 0;
    }
}

.dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 200;
    display: flex;
    justify-content: center;
    align-items: center;
}

.dialog-panel {
    width: min(420px, calc(100vw - 32px));
    background: #f8fafc;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.28);
    padding: 16px;
    box-sizing: border-box;
}

.duplicate-dialog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.duplicate-dialog-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.duplicate-dialog-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.duplicate-error {
    margin: 0 0 10px;
}

.duplicate-toggle-list {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
}

.toggle-btn {
    min-height: 36px;
    padding: 8px 14px;
    border: 1px solid #94a3b8;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-align: left;
}

.toggle-btn.on {
    background: #2563eb;
    border-color: #1d4ed8;
    color: #fff;
}

.certificate-overlay {
    padding: 0;
    align-items: stretch;
    justify-content: center;
}

.certificate-panel {
    --cert-chrome: 64px;
    height: 100vh;
    max-height: 100vh;
    width: calc((100vh - var(--cert-chrome)) * 210 / 297 + 200px);
    max-width: 100vw;
    margin: 0;
    border-radius: 0;
    padding: 0 0 8px;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

.certificate-dialog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 12px;
    flex: 0 0 auto;
}

.certificate-dialog-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.certificate-dialog-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.certificate-error {
    margin: 0 12px 6px;
}

.certificate-viewport {
    flex: 1 1 auto;
    min-height: 0;
    margin: 0 12px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #1e293b;
    border-radius: 4px;
}

.certificate-image {
    display: block;
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
}

.ticket-preview-viewport {
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    overflow-y: auto;
    padding: 12px 0;
}

.ticket-preview-page {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.ticket-preview-page + .ticket-preview-page {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #475569;
}

.ticket-preview-label {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 700;
    color: #e2e8f0;
}
</style>
