<template>
    <div class="create-page">
        <div class="page-header">
            <div>
                <h1>貸出機登録</h1>
            </div>
            <div class="header-actions">
                <a :href="homeUrl" class="btn btn-secondary">Home</a>
                <a :href="adminUrl" class="btn btn-secondary">既存案件一覧</a>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="saving || !form.productName || activeTab !== 'register'"
                    @click="save"
                >
                    {{ saving ? '保存中...' : '保存' }}
                </button>
            </div>
        </div>

        <p v-if="error" class="global-error">{{ error }}</p>

        <section class="panel panel-main">
            <div class="tab-bar" role="tablist">
                <button
                    type="button"
                    class="tab-btn"
                    :class="{ active: activeTab === 'register' }"
                    role="tab"
                    @click="activeTab = 'register'"
                >
                    登録情報
                </button>
                <button
                    type="button"
                    class="tab-btn"
                    :class="{ active: activeTab === 'all' }"
                    role="tab"
                    @click="activeTab = 'all'"
                >
                    全リスト（{{ loaners.length }}台）
                </button>
            </div>

            <div v-show="activeTab === 'register'" class="tab-panel">
                <div class="form-stack">
                    <section class="info-card info-card-main">
                        <div class="form-row row-2">
                            <div class="field">
                                <span>productName</span>
                                <button type="button" class="field-button" @click="openSelectDialog('loanerProduct')">
                                    {{ form.productName || '機種を選択してください' }}
                                </button>
                            </div>
                            <label class="field">
                                <span>SN</span>
                                <input
                                    v-model="form.SN"
                                    type="text"
                                    placeholder="SN（在庫あり時は自動）"
                                    :readonly="Boolean(availability?.loaner?.SN)"
                                >
                            </label>
                        </div>
                        <div class="form-row row-1">
                            <label class="field">
                                <span>order_type</span>
                                <input :value="availabilityLabel" type="text" readonly>
                            </label>
                        </div>
                        <p v-if="availabilityChecking" class="availability-hint">在庫確認中...</p>
                        <p v-else-if="showWaitingWarning" class="warning-banner">
                            在庫が無いので予約リスト（waiting_list）に登録します。
                        </p>
                        <p v-else-if="availability?.order_type === 'loaner'" class="availability-hint ok">
                            在庫あり → loaner（loanerID: {{ availability.loaner?.loanerID }}）
                        </p>
                    </section>

                    <section v-if="showLoanerMetaFields" class="info-card info-card-status">
                        <label class="field">
                            <span>status</span>
                            <select v-model="form.status">
                                <option value="">選択してください</option>
                                <option
                                    v-for="status in statuses"
                                    :key="status.processID"
                                    :value="String(status.processID)"
                                >
                                    {{ status.status }} ({{ status.processID }})
                                </option>
                            </select>
                        </label>
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

            <div v-show="activeTab === 'all'" class="tab-panel tab-panel-list">
                <label class="list-search">
                    <input
                        v-model="listSearch"
                        type="text"
                        placeholder="productName / item / SN / loanerID で検索"
                    >
                </label>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>productName</th>
                                <th>item</th>
                                <th>loanerID</th>
                                <th>SN</th>
                                <th>manageNum</th>
                                <th>status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="unit in filteredLoaners" :key="`${unit.loanerID}-${unit.SN}`">
                                <td>{{ unit.productName || '—' }}</td>
                                <td>{{ unit.item || '—' }}</td>
                                <td>{{ unit.loanerID || '—' }}</td>
                                <td>{{ unit.SN || '—' }}</td>
                                <td>{{ unit.manageNum || '—' }}</td>
                                <td>
                                    <span class="badge" :class="isAvailableUnit(unit) ? 'badge-ok' : 'badge-wait'">
                                        {{ isAvailableUnit(unit) ? '在庫' : '貸出中等' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!filteredLoaners.length" class="empty-message">該当する貸出機がありません。</p>
                </div>
            </div>
        </section>

        <IntakeMasterSelectDialog
            v-if="activeSelectKind"
            :kind="activeSelectKind"
            :items="activeSelectItems"
            :initial-value="activeSelectInitialValue"
            @close="activeSelectKind = null"
            @selected="onMasterSelected"
        />

        <div v-if="showWaitingConfirm" class="confirm-overlay" @click.self="cancelWaitingList">
            <div class="confirm-panel">
                <div class="confirm-header">
                    <h3>在庫なし</h3>
                </div>
                <div class="confirm-body">
                    <p class="confirm-warning">在庫が無いので予約リストに追加しますか？</p>
                    <p class="confirm-detail">機種: {{ form.productName || '—' }}</p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" @click="cancelWaitingList">キャンセル</button>
                    <button type="button" class="btn btn-warning" @click="acceptWaitingList">予約リストに追加</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { apiFetch } from '@/utils/apiFetch'
import IntakeMasterSelectDialog from '@/components/ServiceRecord/Intake/IntakeMasterSelectDialog.vue'

const props = defineProps({
    loanerProducts: {
        type: Array,
        default: () => [],
    },
    loaners: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    dealersMaster: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const saving = ref(false)
const error = ref('')
const activeTab = ref('register')
const listSearch = ref('')
const activeSelectKind = ref(null)
const availability = ref(null)
const availabilityChecking = ref(false)
const showWaitingConfirm = ref(false)
const waitingListAccepted = ref(false)

const form = reactive({
    productName: '',
    status: '',
    SN: '',
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
})

const zipLookupTimers = {
    dealer: null,
    endUser: null,
    delivery: null,
}

const statuses = computed(() => props.statuses ?? [])
const dealers = computed(() => props.dealersMaster ?? [])
const loaners = computed(() => props.loaners ?? [])
const loanerProductOptions = computed(() =>
    (props.loanerProducts ?? []).map(item => ({
        productName: item.productName,
        availableCount: item.availableCount,
        totalCount: item.totalCount,
        order_type: item.order_type,
    })),
)

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)

const availabilityLabel = computed(() => {
    if (!form.productName) return ''
    if (availabilityChecking.value) return '確認中...'
    if (!availability.value) return ''
    return availability.value.order_type || ''
})

const showLoanerMetaFields = computed(() => availability.value?.order_type === 'loaner')
const showWaitingWarning = computed(() =>
    availability.value?.order_type === 'waiting_list' && waitingListAccepted.value,
)

const filteredLoaners = computed(() => {
    const tokens = listSearch.value
        .toLowerCase()
        .trim()
        .split(/\s+/)
        .filter(Boolean)

    if (tokens.length === 0) return loaners.value

    return loaners.value.filter((unit) => {
        const text = [
            unit.productName,
            unit.item,
            unit.loanerID,
            unit.SN,
            unit.manageNum,
            unit.groupName,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
        return tokens.every(token => text.includes(token))
    })
})

const activeSelectItems = computed(() => {
    if (activeSelectKind.value === 'dealer') return dealers.value
    if (activeSelectKind.value === 'loanerProduct') return loanerProductOptions.value
    return []
})

const activeSelectInitialValue = computed(() => {
    if (activeSelectKind.value === 'loanerProduct') return form.productName || null
    if (activeSelectKind.value === 'dealer') {
        const matched = dealers.value.find(item => item.dealerName === form.dealer)
        return matched?.id ?? null
    }
    return null
})

function unitStatusValue(unit) {
    if (unit.currentStatus != null) return Number(unit.currentStatus)
    if (unit.current_status != null) return Number(unit.current_status)
    return null
}

function isAvailableUnit(unit) {
    return unitStatusValue(unit) === 0
}

function openSelectDialog(kind) {
    activeSelectKind.value = kind
}

function clearProductSelection() {
    form.productName = ''
    form.SN = ''
    form.status = ''
    availability.value = null
    waitingListAccepted.value = false
    showWaitingConfirm.value = false
}

function acceptWaitingList() {
    waitingListAccepted.value = true
    showWaitingConfirm.value = false
}

function cancelWaitingList() {
    clearProductSelection()
}

function onMasterSelected(result) {
    if (activeSelectKind.value === 'loanerProduct') {
        form.productName = result.productName ?? ''
        form.SN = ''
        form.status = ''
        availability.value = null
        waitingListAccepted.value = false
        showWaitingConfirm.value = false
        checkAvailability()
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

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function checkAvailability() {
    if (!form.productName) {
        availability.value = null
        return
    }

    availabilityChecking.value = true
    error.value = ''

    try {
        const params = new URLSearchParams({ productName: form.productName })
        const url = `${page.props.appBaseUrl}/servicerecord/loaner/availability?${params.toString()}`
        const result = await apiFetch(url)
        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `在庫確認に失敗しました。（HTTP ${response.status}）`)
        }

        availability.value = data
        if (data.order_type === 'waiting_list') {
            form.status = ''
            waitingListAccepted.value = false
            showWaitingConfirm.value = true
        } else {
            waitingListAccepted.value = false
            showWaitingConfirm.value = false
            if (data.loaner?.SN) {
                form.SN = data.loaner.SN
            }
        }
    } catch (e) {
        availability.value = null
        error.value = e.message || '在庫確認に失敗しました。'
    } finally {
        availabilityChecking.value = false
    }
}

async function fetchAddressByZipcode(zipcode) {
    const digits = String(zipcode ?? '').replace(/\D/g, '')
    if (digits.length !== 7) return null

    const response = await fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${digits}`)
    if (!response.ok) {
        throw new Error('郵便番号の検索に失敗しました。')
    }

    const data = await response.json()
    const result = data?.results?.[0]
    if (!result) return null

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
            dealer: { zip: 'zipcode', address1: 'address1', address2: 'address2' },
            endUser: { zip: 'endUser_zipcode', address1: 'endUser_address1', address2: 'endUser_address2' },
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

async function save() {
    if (!form.productName) {
        error.value = 'productName を選択してください。'
        return
    }

    if (availability.value?.order_type === 'waiting_list' && !waitingListAccepted.value) {
        showWaitingConfirm.value = true
        return
    }

    saving.value = true
    error.value = ''

    try {
        const url = `${page.props.appBaseUrl}/servicerecord/loaner/store`
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                productName: form.productName,
                receivedDate: null,
                status: availability.value?.order_type === 'loaner' && form.status !== ''
                    ? Number(form.status)
                    : null,
                returnCode: null,
                SN: form.SN || null,
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
            }),
        })

        if (!result) return

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

onBeforeUnmount(() => {
    Object.keys(zipLookupTimers).forEach((key) => {
        if (zipLookupTimers[key]) {
            clearTimeout(zipLookupTimers[key])
            zipLookupTimers[key] = null
        }
    })
})
</script>

<style scoped>
.create-page {
    height: 100vh;
    padding: 12px 16px;
    background: #e2e8f0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow: hidden;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-shrink: 0;
}

.page-header h1 {
    margin: 0;
    font-size: 24px;
    color: #1e293b;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.global-error {
    margin: 0;
    padding: 10px 14px;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    background: #fef2f2;
    color: #b91c1c;
    flex-shrink: 0;
}

.panel-main {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.tab-bar {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
    border-bottom: 1px solid #cbd5e1;
    margin-bottom: 12px;
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
}

.tab-panel-list {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.list-search {
    display: block;
    margin-bottom: 10px;
    flex-shrink: 0;
}

.list-search input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    font-size: 13px;
}

.table-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 12px;
    color: #1e293b;
    white-space: nowrap;
}

.data-table th {
    position: sticky;
    top: 0;
    background: #e2e8f0;
    z-index: 1;
}

.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.badge-ok {
    background: #dcfce7;
    color: #166534;
}

.badge-wait {
    background: #ffedd5;
    color: #9a3412;
}

.empty-message {
    margin: 0;
    padding: 16px;
    color: #64748b;
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
    border-color: #c1c1c1;
    background: #dedede;
}

.info-card-status {
    border-color: #c1c1c1;
    background: #dedede;
}

.info-card-dealer {
    border-color: #c1c1c1;
    background: #dedede;
}

.info-card-enduser {
    border-color: #c1c1c1;
    background: #dedede;
}

.info-card-delivery {
    border-color: #c1c1c1;
    background: #dedede;
}

.card-title {
    margin: 0 0 6px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
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
    grid-template-columns: minmax(0, 1.4fr) minmax(120px, 0.8fr) minmax(120px, 0.8fr);
}

.row-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.row-1 {
    grid-template-columns: minmax(0, 1fr);
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

.availability-hint {
    margin: 0;
    align-self: center;
    font-size: 12px;
    color: #64748b;
}

.availability-hint.ok {
    color: #15803d;
    font-weight: 700;
}

.availability-hint.wait {
    color: #b45309;
    font-weight: 700;
}

.warning-banner {
    margin: 8px 0 0;
    padding: 10px 12px;
    border: 1px solid #f59e0b;
    border-radius: 6px;
    background: #fffbeb;
    color: #b45309;
    font-size: 13px;
    font-weight: 700;
}

.confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 400;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 16px;
}

.confirm-panel {
    width: min(440px, 96vw);
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.confirm-header {
    padding: 12px 16px;
    background: #b45309;
    color: #fff;
}

.confirm-header h3 {
    margin: 0;
    font-size: 16px;
}

.confirm-body {
    padding: 16px;
}

.confirm-warning {
    margin: 0 0 8px;
    color: #9a3412;
    font-size: 15px;
    font-weight: 700;
}

.confirm-detail {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #e2e8f0;
}

.btn-warning {
    background: #d97706;
    color: #fff;
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

@media (max-width: 900px) {
    .create-page {
        height: auto;
        min-height: 100vh;
        overflow: auto;
    }

    .row-product,
    .row-3,
    .row-2,
    .row-address {
        grid-template-columns: 1fr;
    }
}
</style>
