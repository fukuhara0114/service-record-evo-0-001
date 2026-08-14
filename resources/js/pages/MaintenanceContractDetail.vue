<template>
    <div class="detail-page">
        <header class="page-header">
            <div>
                <h1>Maintenance Contract 詳細</h1>
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

        <div class="content-grid">
            <section class="panel">
                <h2>契約情報</h2>
                <div class="form-grid">
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
                        <span>status</span>
                        <input v-model="form.status" type="text">
                    </label>
                    <label class="field">
                        <span>RefNumber</span>
                        <input v-model="form.RefNumber" type="text">
                    </label>
                    <label class="field">
                        <span>amount</span>
                        <input v-model="form.amount" type="number" step="0.01">
                    </label>
                    <label class="field">
                        <span>startDate</span>
                        <input v-model="form.startDate" type="date">
                    </label>
                    <label class="field">
                        <span>expireDate</span>
                        <input v-model="form.expireDate" type="date">
                    </label>
                    <label class="field">
                        <span>certificationTicket</span>
                        <input v-model="form.certificationTicket" type="text">
                    </label>
                    <label class="field">
                        <span>certificationExpireDate</span>
                        <input v-model="form.certificationExpireDate" type="date">
                    </label>
                    <label class="field">
                        <span>informedDate</span>
                        <input v-model="form.informedDate" type="date">
                    </label>
                    <label class="field">
                        <span>renewedDate</span>
                        <input v-model="form.renewedDate" type="date">
                    </label>
                    <label class="field checkbox-field">
                        <span>informed</span>
                        <input v-model="form.informed" type="checkbox">
                    </label>
                    <label class="field span-2">
                        <span>renewalInformation</span>
                        <input v-model="form.renewalInformation" type="text">
                    </label>
                    <label class="field span-2">
                        <span>description</span>
                        <textarea v-model="form.description" rows="3"></textarea>
                    </label>
                    <label class="field span-2">
                        <span>additional_information</span>
                        <textarea v-model="form.additional_information" rows="3"></textarea>
                    </label>
                </div>
            </section>

            <section class="panel">
                <h2>製品 / 受注</h2>
                <div class="form-grid">
                    <label class="field">
                        <span>instrumentName</span>
                        <input v-model="form.instrumentName" type="text">
                    </label>
                    <label class="field">
                        <span>SN</span>
                        <input v-model="form.SN" type="text">
                    </label>
                    <label class="field">
                        <span>shippingDate</span>
                        <input v-model="form.shippingDate" type="date">
                    </label>
                    <label class="field">
                        <span>orderedDate</span>
                        <input v-model="form.orderedDate" type="date">
                    </label>
                    <label class="field">
                        <span>yayoi_PO</span>
                        <input v-model="form.yayoi_PO" type="text">
                    </label>
                    <label class="field">
                        <span>mapics_PO</span>
                        <input v-model="form.mapics_PO" type="text">
                    </label>
                    <label class="field span-2">
                        <span>invoice_num</span>
                        <input v-model="form.invoice_num" type="text">
                    </label>
                </div>
            </section>

            <section class="panel">
                <h2>dealer</h2>
                <div class="form-grid">
                    <label class="field">
                        <span>dealer</span>
                        <input v-model="form.dealer" type="text">
                    </label>
                    <label class="field">
                        <span>branch</span>
                        <input v-model="form.branch" type="text">
                    </label>
                    <label class="field">
                        <span>contact</span>
                        <input v-model="form.contact" type="text">
                    </label>
                    <label class="field">
                        <span>phone</span>
                        <input v-model="form.phone" type="text">
                    </label>
                    <label class="field span-2">
                        <span>email</span>
                        <input v-model="form.email" type="text">
                    </label>
                    <label class="field span-2">
                        <span>address</span>
                        <textarea v-model="form.address" rows="2"></textarea>
                    </label>
                </div>
            </section>

            <section class="panel">
                <h2>endUser</h2>
                <div class="form-grid">
                    <label class="field">
                        <span>endUser</span>
                        <input v-model="form.endUser" type="text">
                    </label>
                    <label class="field">
                        <span>endUser_depart</span>
                        <input v-model="form.endUser_depart" type="text">
                    </label>
                    <label class="field">
                        <span>endUser_contact</span>
                        <input v-model="form.endUser_contact" type="text">
                    </label>
                    <label class="field">
                        <span>endUser_phone</span>
                        <input v-model="form.endUser_phone" type="text">
                    </label>
                    <label class="field span-2">
                        <span>endUser_email</span>
                        <input v-model="form.endUser_email" type="text">
                    </label>
                    <label class="field span-2">
                        <span>endUser_address</span>
                        <textarea v-model="form.endUser_address" rows="2"></textarea>
                    </label>
                </div>
            </section>
        </div>

        <p class="meta-foot">
            最終更新: {{ form.lastEditPerson || '—' }} / {{ form.lastEditDate || '—' }}
        </p>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
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

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const listUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/maintenance-contracts`)

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
    background: #e2e8f0;
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
    font-size: 13px;
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

.content-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.panel {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
}

.panel h2 {
    margin: 0 0 10px;
    font-size: 15px;
    color: #0f172a;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 10px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    color: #475569;
    min-width: 0;
}

.field.span-2 {
    grid-column: 1 / -1;
}

.field.checkbox-field {
    flex-direction: row;
    align-items: center;
    gap: 8px;
    padding-top: 22px;
}

.field input,
.field select,
.field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    padding: 7px 8px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    background: #fff;
}

.field textarea {
    resize: vertical;
}

.field.checkbox-field input {
    width: auto;
}

.meta-foot {
    margin: 12px 0 0;
    color: #64748b;
    font-size: 12px;
}

@media (max-width: 960px) {
    .content-grid,
    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.span-2 {
        grid-column: auto;
    }
}
</style>
