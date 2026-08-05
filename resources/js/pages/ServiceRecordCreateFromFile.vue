<template>
    <div class="create-page">
        <div class="page-header">
            <div>
                <h1>新規案件作成</h1>
            </div>
            <div class="header-actions">
                <a :href="intakeListUrl" class="btn btn-secondary">未登録ファイル一覧へ戻る</a>
                <a :href="adminUrl" class="btn btn-secondary">既存案件一覧</a>
                <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
                    {{ saveButtonLabel }}
                </button>
            </div>
        </div>

        <p v-if="error" class="global-error">{{ error }}</p>

        <div class="create-layout">
            <Splitpanes class="default-theme create-splitpanes" @resized="syncPaneSizes">
                <Pane class="create-pane create-pane-pdf" :size="leftPaneSize" :min-size="28">
                    <section class="panel panel-pdf">
                        <div class="panel-header">
                            <div>
                                <h2>{{ hasSourceFile ? '申請フォーム' : '添付ファイル無し' }}</h2>
                                <div v-if="hasSourceFile" class="panel-meta">
                                    <span>ID: {{ sourceFile?.id }}</span>
                                    <span>{{ sourceFile?.documentName || '（名称なし）' }}</span>
                                    <span>{{ sourceFile?.fileType || '' }}</span>
                                </div>
                                <div v-else class="panel-meta">
                                    <span>情報入力のみで新規案件を作成します</span>
                                </div>
                            </div>
                            <button
                                v-if="hasSourceFile"
                                type="button"
                                class="btn btn-secondary"
                                @click="openPreview(sourceFile)"
                            >
                                拡大・回転
                            </button>
                        </div>
                        <div v-if="hasSourceFile" class="pdf-preview-shell">
                            <iframe
                                v-if="isSourcePdf"
                                :src="sourceFileUrl"
                                class="pdf-frame"
                                title="申請フォーム"
                            />
                            <img
                                v-else-if="isSourceImage"
                                :src="sourceFileUrl"
                                :alt="sourceFile?.documentName || '画像'"
                                class="pdf-frame image-frame"
                            >
                            <div v-else class="no-preview-panel">
                                <p>{{ sourceFile?.documentName || '（名称なし）' }}</p>
                                <p>{{ sourceFile?.fileType || 'この形式はプレビュー非対応です' }}</p>
                                <a :href="sourceFileUrl" target="_blank" rel="noopener" class="btn btn-secondary">別タブで開く</a>
                            </div>
                        </div>
                        <div v-else class="pdf-preview-shell no-file-shell">
                            <div class="no-preview-panel">
                                <p class="no-file-title">添付ファイル無し</p>
                                <p>必要なら「関連する未登録書類」からファイルを選択できます。</p>
                            </div>
                        </div>
                    </section>
                </Pane>

                <Pane class="create-pane create-pane-form" :size="rightPaneSize" :min-size="35">
                    <section class="panel panel-form">
                <div class="tab-bar" role="tablist">
                    <button
                        type="button"
                        class="tab-btn"
                        :class="{ active: activeTab === 'basic' }"
                        role="tab"
                        @click="activeTab = 'basic'"
                    >
                        基本情報
                    </button>
                    <button
                        type="button"
                        class="tab-btn"
                        :class="{ active: activeTab === 'related' }"
                        role="tab"
                        @click="activeTab = 'related'"
                    >
                        関連する未登録書類({{ relatedFileCount }}枚)
                    </button>
                    <button
                        v-if="hasSourceFile"
                        type="button"
                        class="tab-btn"
                        :class="{ active: activeTab === 'existing' }"
                        role="tab"
                        @click="switchToExistingTab"
                    >
                        service案件検索
                    </button>
                    <button
                        type="button"
                        class="tab-btn"
                        :class="{ active: activeTab === 'loaner' }"
                        role="tab"
                        @click="switchToLoanerTab"
                    >
                        loaner案件検索
                        <span v-if="selectedLoaners.length">（{{ selectedLoaners.length }}）</span>
                    </button>
                </div>

                <div v-show="activeTab === 'basic'" class="tab-panel">
                    <div class="form-stack">
                        <section class="info-card info-card-main">
                            <div class="form-row row-product">
                                <div class="field">
                                    <span>productName</span>
                                    <button type="button" class="field-button" @click="openSelectDialog('serviceMaster')">
                                        {{ selectedProductLabel }}
                                    </button>
                                </div>
                                <label class="field">
                                    <span>entityID</span>
                                    <input :value="form.entityID || ''" type="text" readonly>
                                </label>
                                <label class="field">
                                    <span>SN</span>
                                    <input v-model="form.SN" type="text" placeholder="SN">
                                </label>
                            </div>
                            <div class="form-row row-3">
                                <label class="field">
                                    <span>receivedDate</span>
                                    <input v-model="form.receivedDate" type="date">
                                </label>
                                <label class="field">
                                    <span>status</span>
                                    <select v-model="form.status">
                                        <option value="">選択してください</option>
                                        <option v-for="status in statuses" :key="status.processID" :value="String(status.processID)">
                                            {{ status.status }} ({{ status.processID }})
                                        </option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>returnCode</span>
                                    <select v-model="form.returnCode">
                                        <option value="">選択してください</option>
                                        <option v-for="returnCode in returnCodes" :key="returnCode.id" :value="String(returnCode.id)">
                                            {{ returnCode.description }} ({{ returnCode.id }})
                                        </option>
                                    </select>
                                </label>
                            </div>
                        </section>

                        <section class="info-card info-card-dealer">
                            <h3 class="card-title">dealer</h3>
                            <div class="form-row row-2">
                                <button
                                    type="button"
                                    class="field-button"
                                    :class="{ placeholder: !form.dealer }"
                                    @click="openSelectDialog('dealer')"
                                >
                                    {{ form.dealer || 'company' }}
                                </button>
                                <input v-model="form.dealer_depart" type="text" placeholder="depart">
                            </div>
                            <div class="form-row row-3">
                                <input v-model="form.contactPerson" type="text" placeholder="contactPerson">
                                <input v-model="form.phone" type="text" placeholder="phone">
                                <input v-model="form.email" type="text" placeholder="email">
                            </div>
                            <div class="form-row row-zip">
                                <input
                                    v-model="form.zipcode"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="8"
                                    placeholder="zipcode"
                                    @input="onZipcodeInput('dealer')"
                                >
                            </div>
                            <div class="form-row row-address">
                                <input v-model="form.address1" type="text" placeholder="address1">
                                <input v-model="form.address2" type="text" placeholder="address2">
                            </div>
                        </section>

                        <section class="info-card info-card-enduser">
                            <h3 class="card-title">endUser</h3>
                            <div class="form-row row-2">
                                <input v-model="form.endUser" type="text" placeholder="company">
                                <input v-model="form.endUser_depart" type="text" placeholder="depart">
                            </div>
                            <div class="form-row row-3">
                                <input v-model="form.endUser_contactPerson" type="text" placeholder="contactPerson">
                                <input v-model="form.endUser_phone" type="text" placeholder="phone">
                                <input v-model="form.endUser_email" type="text" placeholder="email">
                            </div>
                            <div class="form-row row-zip">
                                <input
                                    v-model="form.endUser_zipcode"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="8"
                                    placeholder="zipcode"
                                    @input="onZipcodeInput('endUser')"
                                >
                            </div>
                            <div class="form-row row-address">
                                <input v-model="form.endUser_address1" type="text" placeholder="address1">
                                <input v-model="form.endUser_address2" type="text" placeholder="address2">
                            </div>
                        </section>

                        <section class="info-card info-card-delivery">
                            <h3 class="card-title">delivery</h3>
                            <div class="form-row row-2">
                                <input v-model="form.deliveryDestination_company" type="text" placeholder="company">
                                <input v-model="form.deliveryDestination_depart" type="text" placeholder="depart">
                            </div>
                            <div class="form-row row-2">
                                <input v-model="form.deliveryDestination_contactPerson" type="text" placeholder="contactPerson">
                                <input v-model="form.deliveryDestination_phone" type="text" placeholder="phone">
                            </div>
                            <div class="form-row row-zip">
                                <input
                                    v-model="form.deliveryDestination_zipcode"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="8"
                                    placeholder="zipcode"
                                    @input="onZipcodeInput('delivery')"
                                >
                            </div>
                            <div class="form-row row-address">
                                <input v-model="form.deliveryDestination_address1" type="text" placeholder="address1">
                                <input v-model="form.deliveryDestination_address2" type="text" placeholder="address2">
                            </div>
                        </section>
                    </div>
                </div>

                <div v-show="activeTab === 'related'" class="tab-panel">
                    <div class="related-toolbar">
                        <p class="section-help">この案件に紐付けたい未登録書類にチェックを入れてください。プレビューをクリックすると拡大表示できます。</p>
                        <span class="related-selected">{{ selectedAdditionalCount }}件選択中</span>
                    </div>
                    <div class="related-files">
                        <div
                            v-for="file in additionalFileCandidates"
                            :key="file.id"
                            class="file-option"
                            :class="{ selected: form.additionalFileIds.includes(String(file.id)) }"
                        >
                            <div class="file-option-top">
                                <input
                                    v-model="form.additionalFileIds"
                                    type="checkbox"
                                    :value="String(file.id)"
                                >
                                <div class="file-option-body" />
                            </div>
                            <button type="button" class="related-preview-wrap" @click="openPreview(file)">
                                <iframe
                                    v-if="isPdf(file)"
                                    :src="buildFileUrl(file.id)"
                                    class="related-preview"
                                    :title="`related pdf ${file.id}`"
                                    tabindex="-1"
                                />
                                <img
                                    v-else-if="isImage(file)"
                                    :src="buildFileUrl(file.id)"
                                    :alt="file.documentName || '画像'"
                                    class="related-preview-image"
                                >
                                <div v-else class="related-preview-fallback">プレビュー非対応</div>
                                <span class="preview-hint">クリックで拡大</span>
                            </button>
                        </div>
                        <p v-if="!additionalFileCandidates.length" class="empty-message">関連する未登録書類はありません。</p>
                    </div>
                </div>

                <div v-show="activeTab === 'existing'" class="tab-panel tab-panel-existing">
                    <ExistingRecordSearchDialog
                        inline
                        purpose="file"
                        :records="existingSearchRecords"
                        :query-summary="existingSearchSummary"
                        :statuses="statuses"
                        :searching="existingSearchLoading"
                        :has-searched="existingHasSearched"
                        @search="openExistingRecordSearch"
                        @link-selected="linkToExistingRecord"
                    />
                </div>

                <div v-show="activeTab === 'loaner'" class="tab-panel tab-panel-existing">
                    <div class="loaner-flow-note">
                        <p>
                            loaner 紐づけでは、この画面で<strong>新規 service 案件を作成</strong>し、
                            得た orderID を選択した loaner の parentID に設定します。
                            最低限 <strong>productName / SN / dealer / contactPerson</strong> の入力が必要です。
                        </p>
                    </div>
                    <div v-if="selectedLoaners.length" class="selected-loaners">
                        <h4>紐づけ対象（保存時に新規 service 作成 → parentID 設定）</h4>
                        <div class="selected-loaner-list">
                            <div
                                v-for="loaner in selectedLoaners"
                                :key="loaner.orderID"
                                class="selected-loaner-chip"
                            >
                                <span>
                                    #{{ loaner.orderID }}
                                    {{ loaner.productName || '—' }}
                                    / {{ loaner.SN || 'SNなし' }}
                                </span>
                                <button type="button" class="chip-remove" @click="removeSelectedLoaner(loaner.orderID)">×</button>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-primary loaner-create-btn"
                            :disabled="saving"
                            @click="save"
                        >
                            {{ saving ? '作成中...' : '新規 service を作成して紐づけ' }}
                        </button>
                    </div>
                    <ExistingRecordSearchDialog
                        inline
                        purpose="loaner"
                        :records="loanerSearchRecords"
                        :query-summary="existingSearchSummary"
                        :searching="loanerSearchLoading"
                        :has-searched="loanerHasSearched"
                        @search="openLoanerRecordSearch"
                        @loaner-selected="onLoanerSelected"
                    />
                </div>
                    </section>
                </Pane>
            </Splitpanes>
        </div>

        <div v-if="showLoanerRequirementDialog" class="confirm-overlay" @click.self="cancelLoanerRequirementDialog">
            <div class="confirm-panel">
                <div class="confirm-header">
                    <h3>基本情報が不足しています</h3>
                    <button type="button" class="close-btn" @click="cancelLoanerRequirementDialog">×</button>
                </div>
                <div class="confirm-body">
                    <p>
                        loaner に紐づける新規 service 案件を作成するには、
                        productName / SN / dealer / contactPerson が必要です。
                    </p>
                    <ul class="missing-fields">
                        <li v-for="field in missingLoanerLinkFields" :key="field">{{ field }}</li>
                    </ul>
                    <p>不足項目を OCR で読み取りますか？（OCR 未実装の場合は手入力へ進みます）</p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" @click="cancelLoanerRequirementDialog">キャンセル</button>
                    <button type="button" class="btn btn-secondary" @click="chooseManualEntryForLoaner">手入力する</button>
                    <button type="button" class="btn btn-primary" @click="chooseOcrForLoaner">OCRで読み取る</button>
                </div>
            </div>
        </div>

        <IntakeMasterSelectDialog
            v-if="activeSelectKind"
            :kind="activeSelectKind"
            :items="activeSelectItems"
            :initial-value="activeSelectInitialValue"
            @close="activeSelectKind = null"
            @selected="onMasterSelected"
        />

        <IntakeFilePreviewDialog
            v-if="previewFile"
            :file="previewFile"
            :files="previewFiles"
            :selectable="true"
            :selected-file-ids="form.additionalFileIds"
            :fixed-file-ids="hasSourceFile ? [String(sourceFile?.id)] : []"
            @close="previewFile = null"
            @saved="onPreviewSaved"
            @navigate="openPreview"
            @toggle-selected="toggleAdditionalFile"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Pane, Splitpanes } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'
import { apiFetch } from '@/utils/apiFetch'
import IntakeMasterSelectDialog from '@/components/ServiceRecord/Intake/IntakeMasterSelectDialog.vue'
import IntakeFilePreviewDialog from '@/components/ServiceRecord/Intake/IntakeFilePreviewDialog.vue'
import ExistingRecordSearchDialog from '@/components/ServiceRecord/Intake/ExistingRecordSearchDialog.vue'

const props = defineProps({
    sourceFile: {
        type: Object,
        default: null,
    },
    unregisteredFiles: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    returnCodes: {
        type: Array,
        default: () => [],
    },
    labors: {
        type: Array,
        default: () => [],
    },
    dealersMaster: {
        type: Array,
        default: () => [],
    },
    servicesMaster: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const saving = ref(false)
const error = ref('')
const activeTab = ref('basic')
const activeSelectKind = ref(null)
const previewFile = ref(null)
const previewCacheBust = ref(Date.now())
const existingSearchLoading = ref(false)
const existingHasSearched = ref(false)
const existingSearchRecords = ref([])
const loanerSearchLoading = ref(false)
const loanerHasSearched = ref(false)
const loanerSearchRecords = ref([])
const selectedLoaners = ref([])
const showLoanerRequirementDialog = ref(false)
const pendingLoanerRecord = ref(null)
const loanerRequirementContext = ref(null) // 'select' | 'save'
const leftPaneSize = ref(42)
const rightPaneSize = ref(58)

function syncPaneSizes({ panes } = {}) {
    if (!Array.isArray(panes) || panes.length < 2) return
    leftPaneSize.value = panes[0].size
    rightPaneSize.value = panes[1].size
}

const form = reactive({
    receivedDate: '',
    status: '',
    serviceID: '',
    productName: '',
    entityID: '',
    SN: '',
    returnCode: '',
    dealer: '',
    dealer_depart: '',
    contactPerson: '',
    email: '',
    phone: '',
    zipcode: '',
    address1: '',
    address2: '',
    endUser: '',
    endUser_depart: '',
    endUser_contactPerson: '',
    endUser_phone: '',
    endUser_email: '',
    endUser_zipcode: '',
    endUser_address1: '',
    endUser_address2: '',
    deliveryDestination_company: '',
    deliveryDestination_depart: '',
    deliveryDestination_contactPerson: '',
    deliveryDestination_phone: '',
    deliveryDestination_zipcode: '',
    deliveryDestination_address1: '',
    deliveryDestination_address2: '',
    additionalFileIds: [],
})

const statuses = computed(() => props.statuses ?? [])
const returnCodes = computed(() => props.returnCodes ?? [])
const dealers = computed(() => props.dealersMaster ?? [])
const services = computed(() => props.servicesMaster ?? [])

const zipLookupTimers = {
    dealer: null,
    endUser: null,
    delivery: null,
}

const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)
const intakeListUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/intake`)
const hasSourceFile = computed(() => Boolean(props.sourceFile?.id))
const isSourcePdf = computed(() => props.sourceFile?.fileType === 'application/pdf')
const isSourceImage = computed(() => String(props.sourceFile?.fileType || '').startsWith('image/'))
const sourceFileUrl = computed(() => (
    hasSourceFile.value ? buildFileUrl(props.sourceFile.id) : ''
))

const additionalFileCandidates = computed(() =>
    (props.unregisteredFiles ?? []).filter(file => (
        !hasSourceFile.value || Number(file.id) !== Number(props.sourceFile.id)
    )),
)
const previewFiles = computed(() => (
    hasSourceFile.value
        ? [props.sourceFile, ...additionalFileCandidates.value]
        : [...additionalFileCandidates.value]
))

const selectedAdditionalCount = computed(() => form.additionalFileIds.length)
const relatedFileCount = computed(() => additionalFileCandidates.value.length)

const selectedProductLabel = computed(() => {
    if (form.productName) {
        return `${form.productName} (${form.serviceID})`
    }
    return '製品名を選択してください'
})
const existingSearchTerms = computed(() =>
    [
        form.productName,
        form.SN,
        form.dealer,
        form.contactPerson,
    ]
        .map(value => String(value ?? '').trim())
        .filter(Boolean)
)
const existingSearchSummary = computed(() => existingSearchTerms.value.join(' / '))

const missingLoanerLinkFields = computed(() => {
    const missing = []
    if (!form.serviceID || !form.productName) missing.push('productName')
    if (!String(form.SN || '').trim()) missing.push('SN')
    if (!String(form.dealer || '').trim()) missing.push('dealer')
    if (!String(form.contactPerson || '').trim()) missing.push('contactPerson')
    return missing
})

const hasLoanerLinkRequiredFields = computed(() => missingLoanerLinkFields.value.length === 0)

const saveButtonLabel = computed(() => {
    if (saving.value) {
        return selectedLoaners.value.length ? '作成中...' : '保存中...'
    }
    if (selectedLoaners.value.length) {
        return '新規 service を作成して紐づけ'
    }
    return '保存'
})

watch(hasLoanerLinkRequiredFields, (ok) => {
    if (!ok || !pendingLoanerRecord.value) return
    if (loanerRequirementContext.value !== 'select') return

    addSelectedLoaner(pendingLoanerRecord.value)
    pendingLoanerRecord.value = null
    loanerRequirementContext.value = null
    error.value = ''
    activeTab.value = 'loaner'
})

const activeSelectItems = computed(() => {
    if (activeSelectKind.value === 'dealer') return dealers.value
    if (activeSelectKind.value === 'serviceMaster') return services.value
    return []
})

const activeSelectInitialValue = computed(() => {
    if (activeSelectKind.value === 'serviceMaster') {
        const matched = services.value.find(item =>
            form.productName
            && String(item.productName) === String(form.productName),
        )
        return matched?.id ?? null
    }
    if (activeSelectKind.value === 'dealer') {
        const matched = dealers.value.find(item => item.dealerName === form.dealer)
        return matched?.id ?? null
    }
    return null
})

function openSelectDialog(kind) {
    activeSelectKind.value = kind
}

function openPreview(file) {
    previewFile.value = file
}

function onPreviewSaved() {
    previewCacheBust.value = Date.now()
}

function toggleAdditionalFile(file) {
    const fileId = String(file?.id ?? '')
    if (!fileId || fileId === String(props.sourceFile?.id)) return

    const index = form.additionalFileIds.findIndex(id => String(id) === fileId)
    if (index === -1) {
        form.additionalFileIds.push(fileId)
        return
    }

    form.additionalFileIds.splice(index, 1)
}

function onMasterSelected(result) {
    if (activeSelectKind.value === 'serviceMaster') {
        form.serviceID = result.serviceID != null ? String(result.serviceID) : ''
        form.productName = result.productName ?? ''
        form.entityID = result.entityID ?? ''
    }

    if (activeSelectKind.value === 'dealer') {
        form.dealer = result.dealer ?? ''
        form.dealer_depart = result.dealer_depart ?? ''
        form.contactPerson = result.contactPerson ?? ''
        form.email = result.email ?? ''
        form.phone = result.phone ?? ''
    }

    activeSelectKind.value = null
}

function buildFileUrl(fileId) {
    return `${page.props.appBaseUrl}/servicerecord/files/${fileId}?t=${previewCacheBust.value}#view=FitV`
}

function isPdf(file) {
    return file?.fileType === 'application/pdf'
}

function isImage(file) {
    return String(file?.fileType || '').startsWith('image/')
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function fetchAddressByZipcode(zipcode) {
    const digits = String(zipcode ?? '').replace(/\D/g, '')
    if (digits.length !== 7) {
        return null
    }

    const response = await fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${digits}`)
    if (!response.ok) {
        throw new Error('郵便番号の検索に失敗しました。')
    }

    const data = await response.json()
    const result = data?.results?.[0]
    if (!result) {
        return null
    }

    return {
        address1: result.address1 || '',
        address2: `${result.address2 || ''}${result.address3 || ''}`,
    }
}

function onZipcodeInput(kind) {
    if (zipLookupTimers[kind]) {
        clearTimeout(zipLookupTimers[kind])
    }

    zipLookupTimers[kind] = setTimeout(async () => {
        const map = {
            dealer: {
                zip: 'zipcode',
                address1: 'address1',
                address2: 'address2',
            },
            endUser: {
                zip: 'endUser_zipcode',
                address1: 'endUser_address1',
                address2: 'endUser_address2',
            },
            delivery: {
                zip: 'deliveryDestination_zipcode',
                address1: 'deliveryDestination_address1',
                address2: 'deliveryDestination_address2',
            },
        }
        const fields = map[kind]
        if (!fields) return

        const digits = String(form[fields.zip] ?? '').replace(/\D/g, '')
        if (digits.length !== 7) return

        try {
            const address = await fetchAddressByZipcode(digits)
            if (!address) return
            form[fields.address1] = address.address1
            form[fields.address2] = address.address2
        } catch (e) {
            error.value = e.message || '郵便番号の検索に失敗しました。'
        }
    }, 350)
}

async function openExistingRecordSearch() {
    if (existingSearchTerms.value.length === 0) {
        error.value = 'productName / SN / dealer / contactPerson のいずれかを入力してから検索してください。'
        activeTab.value = 'basic'
        return
    }

    existingSearchLoading.value = true
    error.value = ''

    try {
        const params = new URLSearchParams({ order_type: 'service' })
        if (form.productName) params.set('productName', form.productName)
        if (form.SN) params.set('SN', form.SN)
        if (form.dealer) params.set('dealer', form.dealer)
        if (form.contactPerson) params.set('contactPerson', form.contactPerson)

        const url = `${page.props.appBaseUrl}/servicerecord/search-existing?${params.toString()}`
        const result = await apiFetch(url)

        if (!result) {
            return
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `検索に失敗しました。（HTTP ${response.status}）`)
        }

        existingSearchRecords.value = data.records ?? []
        existingHasSearched.value = true
        activeTab.value = 'existing'
    } catch (e) {
        error.value = e.message || '検索に失敗しました。'
    } finally {
        existingSearchLoading.value = false
    }
}

async function openLoanerRecordSearch() {
    if (existingSearchTerms.value.length === 0) {
        error.value = 'productName / SN / dealer / contactPerson のいずれかを入力してから検索してください。'
        activeTab.value = 'basic'
        return
    }

    loanerSearchLoading.value = true
    error.value = ''

    try {
        const params = new URLSearchParams({ order_type: 'loaner' })
        if (form.productName) params.set('productName', form.productName)
        if (form.SN) params.set('SN', form.SN)
        if (form.dealer) params.set('dealer', form.dealer)
        if (form.contactPerson) params.set('contactPerson', form.contactPerson)

        const url = `${page.props.appBaseUrl}/servicerecord/search-existing?${params.toString()}`
        const result = await apiFetch(url)

        if (!result) {
            return
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `検索に失敗しました。（HTTP ${response.status}）`)
        }

        loanerSearchRecords.value = data.records ?? []
        loanerHasSearched.value = true
        activeTab.value = 'loaner'
    } catch (e) {
        error.value = e.message || '検索に失敗しました。'
    } finally {
        loanerSearchLoading.value = false
    }
}

function switchToExistingTab() {
    activeTab.value = 'existing'
    if (!existingHasSearched.value && !existingSearchLoading.value) {
        openExistingRecordSearch()
    }
}

function switchToLoanerTab() {
    activeTab.value = 'loaner'
    if (!loanerHasSearched.value && !loanerSearchLoading.value) {
        openLoanerRecordSearch()
    }
}

function addSelectedLoaner(record) {
    if (!record?.orderID) return
    if (record.parentID) {
        error.value = `orderID ${record.orderID} は既に parentID=${record.parentID} へ紐づいています。`
        return
    }
    if (selectedLoaners.value.some(item => String(item.orderID) === String(record.orderID))) {
        return
    }
    selectedLoaners.value = [...selectedLoaners.value, record]
    error.value = ''
}

function openLoanerRequirementDialog(context, record = null) {
    loanerRequirementContext.value = context
    pendingLoanerRecord.value = record
    showLoanerRequirementDialog.value = true
}

function cancelLoanerRequirementDialog() {
    showLoanerRequirementDialog.value = false
    pendingLoanerRecord.value = null
    loanerRequirementContext.value = null
}

function chooseManualEntryForLoaner() {
    showLoanerRequirementDialog.value = false
    activeTab.value = 'basic'
    error.value = `不足項目を入力してください: ${missingLoanerLinkFields.value.join(', ')}（入力後、loaner案件検索から再度追加できます）`
}

function chooseOcrForLoaner() {
    showLoanerRequirementDialog.value = false
    activeTab.value = 'basic'
    // OCR 未実装: 手入力へ誘導。実装後はここで OCR API を呼び結果を form に流し込む。
    error.value = 'OCR機能はまだ未実装です。productName / SN / dealer / contactPerson を手入力してください。'
}

function onLoanerSelected(payload) {
    const record = payload?.record ?? payload
    if (!record?.orderID) return
    if (record.parentID) {
        error.value = `orderID ${record.orderID} は既に parentID=${record.parentID} へ紐づいています。`
        return
    }
    if (!hasLoanerLinkRequiredFields.value) {
        openLoanerRequirementDialog('select', record)
        return
    }
    addSelectedLoaner(record)
}

function removeSelectedLoaner(orderID) {
    selectedLoaners.value = selectedLoaners.value.filter(item => String(item.orderID) !== String(orderID))
}

async function linkToExistingRecord(payload) {
    const record = payload?.record ?? payload
    if (!record?.orderID) return
    if (!hasSourceFile.value) {
        error.value = '添付ファイル無しの場合は既存案件へのファイル紐づけはできません。新規保存を利用してください。'
        return
    }

    existingSearchLoading.value = true
    error.value = ''

    try {
        const url = `${page.props.appBaseUrl}/servicerecord/intake/link-existing`
        const body = {
            orderID: Number(record.orderID),
            sourceFileId: Number(props.sourceFile.id),
            additionalFileIds: form.additionalFileIds.map(id => Number(id)),
        }
        if (Object.prototype.hasOwnProperty.call(payload ?? {}, 'receivedDate')) {
            body.receivedDate = payload.receivedDate
        }
        if (payload?.status != null) {
            body.status = Number(payload.status)
        }

        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        })

        if (!result) {
            return
        }

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `紐付けに失敗しました。（HTTP ${response.status}）`)
        }

        window.location.href = adminUrl.value
    } catch (e) {
        error.value = e.message || '紐付けに失敗しました。'
    } finally {
        existingSearchLoading.value = false
    }
}

onBeforeUnmount(() => {
    Object.keys(zipLookupTimers).forEach((key) => {
        if (zipLookupTimers[key]) {
            clearTimeout(zipLookupTimers[key])
            zipLookupTimers[key] = null
        }
    })
})

async function save() {
    if (selectedLoaners.value.length > 0) {
        if (!hasLoanerLinkRequiredFields.value) {
            openLoanerRequirementDialog('save')
            return
        }
    } else if (!form.serviceID) {
        error.value = 'productName を選択してください。'
        return
    }

    saving.value = true
    error.value = ''

    const url = `${page.props.appBaseUrl}/servicerecord/intake/store`

    try {
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                sourceFileId: hasSourceFile.value ? props.sourceFile.id : null,
                additionalFileIds: form.additionalFileIds.map(id => Number(id)),
                receivedDate: form.receivedDate || null,
                status: form.status === '' ? null : Number(form.status),
                serviceID: Number(form.serviceID),
                SN: form.SN || null,
                returnCode: form.returnCode === '' ? null : Number(form.returnCode),
                dealer: form.dealer || null,
                dealer_depart: form.dealer_depart || null,
                contactPerson: form.contactPerson || null,
                email: form.email || null,
                phone: form.phone || null,
                zipcode: form.zipcode || null,
                address1: form.address1 || null,
                address2: form.address2 || null,
                endUser: form.endUser || null,
                endUser_depart: form.endUser_depart || null,
                endUser_contactPerson: form.endUser_contactPerson || null,
                endUser_phone: form.endUser_phone || null,
                endUser_email: form.endUser_email || null,
                endUser_zipcode: form.endUser_zipcode || null,
                endUser_address1: form.endUser_address1 || null,
                endUser_address2: form.endUser_address2 || null,
                deliveryDestination_company: form.deliveryDestination_company || null,
                deliveryDestination_depart: form.deliveryDestination_depart || null,
                deliveryDestination_contactPerson: form.deliveryDestination_contactPerson || null,
                deliveryDestination_phone: form.deliveryDestination_phone || null,
                deliveryDestination_zipcode: form.deliveryDestination_zipcode || null,
                deliveryDestination_address1: form.deliveryDestination_address1 || null,
                deliveryDestination_address2: form.deliveryDestination_address2 || null,
                loanerOrderIds: selectedLoaners.value.map(item => Number(item.orderID)),
            }),
        })

        if (!result) {
            return
        }

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        window.location.href = adminUrl.value
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.create-page {
    height: 100vh;
    min-height: 100vh;
    padding: 12px 16px;
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
    margin-bottom: 12px;
    flex: 0 0 auto;
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

.global-error {
    margin: 0 0 16px;
    padding: 10px 14px;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    background: #fef2f2;
    color: #b91c1c;
}

.create-layout {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.create-splitpanes {
    flex: 1;
    min-height: 0;
    width: 100%;
}

.create-pane {
    min-width: 0;
    min-height: 0;
    display: flex;
    height: 100%;
}

.create-pane-pdf,
.create-pane-form {
    flex-direction: column;
}

.panel {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
    width: 100%;
    overflow: hidden;
    box-sizing: border-box;
}

.panel-pdf {
    min-width: 0;
}

.panel-form {
    min-width: 0;
    gap: 0;
    overflow: hidden;
    padding-top: 8px;
}

:deep(.splitpanes__splitter) {
    background: #cbd5e1;
    min-width: 8px;
}

:deep(.splitpanes__splitter:hover) {
    background: #94a3b8;
}

.tab-bar {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
    border-bottom: 1px solid #cbd5e1;
    margin-bottom: 12px;
    overflow-x: auto;
}

.tab-btn {
    appearance: none;
    border: none;
    background: transparent;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    white-space: nowrap;
}

.tab-btn:hover {
    color: #1e293b;
    background: #f8fafc;
}

.tab-btn.active {
    color: #1d4ed8;
    border-bottom-color: #2563eb;
}

.tab-panel {
    flex: 1;
    min-height: 0;
    overflow: auto;
    display: flex;
    flex-direction: column;
}

.tab-panel-existing {
    overflow: hidden;
}

.selected-loaners {
    flex-shrink: 0;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #93c5fd;
    border-radius: 6px;
    background: #eff6ff;
}

.selected-loaners h4 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #1e40af;
}

.selected-loaner-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.selected-loaner-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #dbeafe;
    color: #1e293b;
    font-size: 12px;
}

.chip-remove {
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 14px;
    cursor: pointer;
    line-height: 1;
}

.loaner-flow-note {
    flex-shrink: 0;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    background: #fffbeb;
}

.loaner-flow-note p {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: #92400e;
}

.loaner-create-btn {
    margin-top: 10px;
}

.confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 400;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.45);
}

.confirm-panel {
    width: min(480px, 100%);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.2);
}

.confirm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
}

.confirm-header h3 {
    margin: 0;
    font-size: 16px;
    color: #0f172a;
}

.close-btn {
    border: none;
    background: transparent;
    font-size: 22px;
    line-height: 1;
    color: #64748b;
    cursor: pointer;
}

.confirm-body {
    padding: 14px 16px;
    color: #334155;
    font-size: 14px;
    line-height: 1.55;
}

.confirm-body p {
    margin: 0 0 10px;
}

.missing-fields {
    margin: 0 0 12px;
    padding-left: 1.2em;
    color: #b91c1c;
}

.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px 16px;
}

.form-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-bottom: 8px;
}

.info-card {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 10px 10px;
    background: #fff;
}

.info-card-main {
    border-color: #000000;
    background: #cdcdcd;
}

.info-card-dealer {
    border-color: #000000;
    background: #aaaaaa;
}

.info-card-enduser {
    border-color: #000000;
    background: #aaaaaa;
}

.info-card-delivery {
    border-color: #000000;
    background: #aaaaaa;
}

.card-title {
    margin: 0 0 6px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    letter-spacing: 0.02em;
}

.form-row {
    display: grid;
    gap: 6px;
    align-items: stretch;
    margin-top: 6px;
}

.info-card > .form-row:first-of-type,
.info-card-main > .form-row:first-child {
    margin-top: 0;
}

.row-product {
    grid-template-columns: minmax(0, 1.3fr) minmax(90px, 0.7fr) minmax(0, 1.3fr);
}

.row-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.row-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.row-zip {
    grid-template-columns: minmax(88px, 120px);
}

.row-address {
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
}

.field {
    display: flex;
    flex-direction: column;
    gap: 3px;
    font-size: 11px;
    color: #64748b;
    min-width: 0;
}

.info-card input,
.info-card select,
.info-card .field-button,
.field input,
.field select,
.field-button {
    width: 100%;
    min-width: 0;
    padding: 6px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    background: #fff;
    color: #1e293b;
    font-size: 13px;
}

.field input[readonly] {
    background: #f8fafc;
    color: #475569;
}

.field-button {
    text-align: left;
    cursor: pointer;
}

.field-button:hover {
    background: #f8fafc;
}

.field-button.placeholder {
    color: #94a3b8;
}

.info-card input::placeholder {
    color: #94a3b8;
}

.related-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.related-selected {
    flex-shrink: 0;
    font-size: 13px;
    color: #475569;
    font-weight: 700;
}

.empty-message {
    margin: 0;
    padding: 16px;
    color: #64748b;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    flex: 0 0 auto;
}

.panel-header h2 {
    margin: 0 0 8px;
    font-size: 18px;
    color: #1e293b;
}

.panel-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    color: #64748b;
    font-size: 13px;
}

.pdf-preview-shell {
    flex: 1;
    min-height: 0;
    width: 100%;
    display: flex;
    justify-content: stretch;
    align-items: stretch;
    overflow: hidden;
}

.no-file-shell {
    align-items: center;
}

.no-preview-panel {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 16px;
    box-sizing: border-box;
    background: #f8fafc;
    border: 1px dashed #94a3b8;
    border-radius: 6px;
    color: #64748b;
    text-align: center;
}

.no-preview-panel p {
    margin: 0;
}

.no-file-title {
    font-size: 18px;
    font-weight: 700;
    color: #334155;
}

.image-frame {
    object-fit: contain;
    background: #fff;
}

.pdf-frame {
    width: 100%;
    height: 100%;
    max-width: none;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
    display: block;
}

.section-help {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.related-files {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
    flex: 1;
    min-height: 0;
    overflow: auto;
    align-content: start;
}

.file-option {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    cursor: pointer;
}

.file-option.selected {
    border-color: #2563eb;
    background: #eff6ff;
}

.file-option-top {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    min-height: 18px;
}

.file-option-body {
    flex: 1;
    min-width: 0;
}

.related-preview-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 210 / 297;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;
    padding: 0;
    cursor: zoom-in;
}

.related-preview,
.related-preview-image {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    pointer-events: none;
}

.related-preview-image {
    object-fit: contain;
}

.related-preview-fallback {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 12px;
}

.preview-hint {
    position: absolute;
    left: 8px;
    bottom: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    background: rgba(15, 23, 42, 0.75);
    color: #fff;
    font-size: 12px;
    pointer-events: none;
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

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 1280px) {
    .create-page {
        height: auto;
        min-height: 100vh;
        overflow: auto;
    }

    .create-layout {
        flex: none;
        min-height: 70vh;
    }

    .create-splitpanes {
        min-height: 70vh;
    }

    .panel-form {
        overflow: visible;
    }

    .tab-panel {
        overflow: visible;
    }

    .pdf-preview-shell {
        min-height: 360px;
    }

    .pdf-frame {
        width: 100%;
        height: 100%;
    }

    .row-product,
    .row-3 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .row-address {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr);
    }
}

@media (max-width: 720px) {
    .row-product,
    .row-3,
    .row-2,
    .row-address {
        grid-template-columns: 1fr;
    }

    .row-zip {
        grid-template-columns: minmax(100px, 160px);
    }
}
</style>
