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
                <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
                    {{ saving ? '保存中...' : '保存' }}
                </button>
                <a :href="listUrl" class="btn btn-secondary">一覧へ戻る</a>
                <CloseToHomeButton :href="homeUrl" />
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
                        <label class="field">
                            <span>certificationTicket</span>
                            <input v-model="form.certificationTicket" type="text">
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
                            <input v-model="form.renewalInformation" type="text">
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

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const listUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/maintenance-contracts`)

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
    shippingDate: props.contract.shippingDate ?? '',
    yayoi_PO: props.contract.yayoi_PO ?? '',
    orderedDate: props.contract.orderedDate ?? '',
    mapics_PO: props.contract.mapics_PO ?? '',
    invoice_num: props.contract.invoice_num ?? '',
    startDate: props.contract.startDate ?? '',
    expireDate: props.contract.expireDate ?? '',
    certificationTicket: props.contract.certificationTicket ?? '',
    certificationExpireDate: props.contract.certificationExpireDate ?? '',
    renewalInformation: props.contract.renewalInformation ?? '',
    informedDate: props.contract.informedDate ?? '',
    renewedDate: props.contract.renewedDate ?? '',
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

async function save() {
    saving.value = true
    error.value = ''
    success.value = ''

    try {
        const body = {
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
            shippingDate: nullable(form.shippingDate),
            yayoi_PO: nullable(form.yayoi_PO),
            orderedDate: nullable(form.orderedDate),
            mapics_PO: nullable(form.mapics_PO),
            invoice_num: nullable(form.invoice_num),
            startDate: nullable(form.startDate),
            expireDate: nullable(form.expireDate),
            certificationTicket: nullable(form.certificationTicket),
            certificationExpireDate: nullable(form.certificationExpireDate),
            renewalInformation: nullable(form.renewalInformation),
            informedDate: nullable(form.informedDate),
            renewedDate: nullable(form.renewedDate),
            contractType: form.contractType === '' ? null : Number(form.contractType),
            informed: !!form.informed,
            amount: form.amount === '' ? null : Number(form.amount),
            status: nullable(form.status),
            RefNumber: nullable(form.RefNumber),
            description: nullable(form.description),
            additional_information: nullable(form.additional_information),
        }

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
    gap: 8px;
    flex-wrap: wrap;
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
</style>
