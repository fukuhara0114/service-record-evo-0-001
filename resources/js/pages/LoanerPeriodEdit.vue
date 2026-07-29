<template>
    <div class="period-page">
        <div class="page-header">
            <div>
                <h1>貸出期間の編集</h1>
                <p class="subtitle">attachedloaners #{{ attached.id }} / orderID: {{ attached.associatedID }}</p>
            </div>
            <div class="header-actions">
                <a :href="calendarUrl" class="btn btn-secondary">カレンダー</a>
                <a :href="adminUrl" class="btn btn-secondary">既存案件一覧</a>
                <a :href="homeUrl" class="btn btn-secondary">Home</a>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="saving"
                    @click="save"
                >
                    {{ saving ? '保存中...' : '保存' }}
                </button>
            </div>
        </div>

        <p v-if="error" class="global-error">{{ error }}</p>
        <p v-if="success" class="global-success">{{ success }}</p>

        <div class="content-grid">
            <section class="info-card">
                <h2 class="card-title">貸出情報</h2>
                <dl class="meta-grid">
                    <div><dt>productName</dt><dd>{{ attachedLocal.productName || '—' }}</dd></div>
                    <div><dt>item</dt><dd>{{ attachedLocal.item || '—' }}</dd></div>
                    <div><dt>loanerID</dt><dd>{{ attachedLocal.loanerID || '—' }}</dd></div>
                    <div><dt>SN</dt><dd>{{ attachedLocal.SN || '—' }}</dd></div>
                    <div><dt>order_type</dt><dd>{{ attachedLocal.order_type || '—' }}</dd></div>
                    <div><dt>dealer</dt><dd>{{ attachedLocal.dealer || '—' }}</dd></div>
                    <div><dt>dealer_depart</dt><dd>{{ attachedLocal.dealer_depart || '—' }}</dd></div>
                    <div><dt>contactPerson</dt><dd>{{ attachedLocal.contactPerson || '—' }}</dd></div>
                    <div><dt>assignStatus</dt><dd>{{ attachedLocal.assignStatus || '—' }}</dd></div>
                    <div>
                        <dt>parentID</dt>
                        <dd>
                            <template v-if="attachedLocal.parentID">
                                {{ attachedLocal.parentID }}
                                <span v-if="parentLocal" class="parent-note">
                                    （{{ parentLocal.productName || '—' }} / {{ parentLocal.dealer || '—' }}）
                                </span>
                            </template>
                            <template v-else>なし（service 案件待ち）</template>
                        </dd>
                    </div>
                </dl>

                <div v-if="!attachedLocal.parentID" class="parent-link-section">
                    <h3 class="section-title">service 案件への後追い紐づけ</h3>
                    <p class="field-hint">
                        紐づけ無しで登録した貸出は、後から作成された service 案件を親として紐づけできます。
                    </p>
                    <div class="parent-search-row">
                        <input
                            v-model="parentSearchQuery"
                            type="text"
                            placeholder="productName / SN / dealer / contactPerson / orderID"
                            @keydown.enter.prevent="openParentSearch"
                        >
                        <button
                            type="button"
                            class="btn btn-secondary"
                            :disabled="parentSearching || linkingParent"
                            @click="openParentSearch"
                        >
                            {{ parentSearching ? '検索中...' : 'service 案件を検索' }}
                        </button>
                    </div>

                    <div v-if="pendingParent" class="pending-parent">
                        <div class="parent-summary">
                            <strong>選択中 orderID: {{ pendingParent.orderID }}</strong>
                            <span>{{ pendingParent.productName || '—' }}</span>
                            <span>SN: {{ pendingParent.SN || '—' }}</span>
                            <span>{{ pendingParent.dealer || '—' }}</span>
                        </div>
                        <label v-if="attachedLocal.order_type === 'loaner'" class="field status-field">
                            <span>紐づけ後の status（任意）</span>
                            <select v-model="linkStatus">
                                <option value="">変更しない（案件未登録のまま）</option>
                                <option
                                    v-for="status in statuses"
                                    :key="status.processID"
                                    :value="String(status.processID)"
                                >
                                    {{ status.status }} ({{ status.processID }})
                                </option>
                            </select>
                        </label>
                        <div class="pending-actions">
                            <button type="button" class="btn btn-secondary" @click="pendingParent = null">クリア</button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="linkingParent"
                                @click="confirmLinkParent"
                            >
                                {{ linkingParent ? '紐づけ中...' : 'この案件に紐づけ' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="info-card">
                <h2 class="card-title">貸出期間 / status</h2>
                <div class="form-stack">
                    <label v-if="attachedLocal.order_type === 'loaner'" class="field">
                        <span>status（StatusLoaner）</span>
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
                    <p v-else-if="attachedLocal.order_type === 'waiting_list'" class="field-hint">
                        waiting_list 案件のため status リレーションはありません（DB上は -1 固定）。
                    </p>
                    <div v-if="dateFields.hasPlannedSent || dateFields.hasPlannedReturned" class="form-row">
                        <label v-if="dateFields.hasPlannedSent" class="field">
                            <span>plannedSentDate（予定開始）</span>
                            <input v-model="form.plannedSentDate" type="date">
                        </label>
                        <label v-if="dateFields.hasPlannedReturned" class="field">
                            <span>plannedReturnedDate（予定終了）</span>
                            <input v-model="form.plannedReturnedDate" type="date">
                        </label>
                    </div>
                    <div class="form-row">
                        <label class="field">
                            <span>sentDate</span>
                            <input v-model="form.sentDate" type="date">
                        </label>
                        <label class="field">
                            <span>returnedDate</span>
                            <input v-model="form.returnedDate" type="date">
                        </label>
                    </div>
                    <label class="field">
                        <span>comment</span>
                        <textarea v-model="form.comment" rows="3"></textarea>
                    </label>
                </div>
            </section>
        </div>

        <ExistingRecordSearchDialog
            v-if="showParentSearch"
            purpose="parent"
            :records="parentSearchRecords"
            :query-summary="parentSearchQuerySummary"
            :searching="parentSearching"
            :has-searched="parentHasSearched"
            @close="showParentSearch = false"
            @search="openParentSearch"
            @parent-selected="onParentSelected"
        />
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { apiFetch } from '@/utils/apiFetch'
import ExistingRecordSearchDialog from '@/components/ServiceRecord/Intake/ExistingRecordSearchDialog.vue'

const props = defineProps({
    attached: {
        type: Object,
        required: true,
    },
    parentRecord: {
        type: Object,
        default: null,
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    dateFields: {
        type: Object,
        default: () => ({
            hasPlannedSent: false,
            hasPlannedReturned: false,
        }),
    },
})

const page = usePage()
const saving = ref(false)
const linkingParent = ref(false)
const error = ref('')
const success = ref('')

const attachedLocal = reactive({ ...props.attached })
const parentLocal = ref(props.parentRecord ? { ...props.parentRecord } : null)

const form = reactive({
    plannedSentDate: props.attached.plannedSentDate || '',
    plannedReturnedDate: props.attached.plannedReturnedDate || '',
    sentDate: props.attached.sentDate || '',
    returnedDate: props.attached.returnedDate || '',
    comment: props.attached.comment || '',
    status: props.attached.status != null ? String(props.attached.status) : '',
})

const showParentSearch = ref(false)
const parentSearching = ref(false)
const parentHasSearched = ref(false)
const parentSearchRecords = ref([])
const parentSearchQuery = ref([
    props.attached.productName,
    props.attached.SN,
    props.attached.dealer,
].filter(Boolean).join(' '))
const pendingParent = ref(null)
const linkStatus = ref('')

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)
const calendarUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/loaner/calendar`)
const parentSearchQuerySummary = computed(() => parentSearchQuery.value.trim() || '検索キーワードなし')
const statuses = computed(() => props.statuses ?? [])

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function openParentSearch() {
    const tokens = parentSearchQuery.value
        .trim()
        .split(/\s+/)
        .filter(Boolean)

    if (tokens.length === 0) {
        error.value = '検索キーワードを入力してください。'
        return
    }

    parentSearching.value = true
    error.value = ''

    try {
        const params = new URLSearchParams({ for: 'loaner_parent' })
        const keys = ['productName', 'SN', 'dealer', 'contactPerson']
        tokens.slice(0, 4).forEach((token, index) => {
            params.set(keys[index], token)
        })

        const url = `${page.props.appBaseUrl}/servicerecord/search-existing?${params.toString()}`
        const result = await apiFetch(url)
        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `検索に失敗しました。（HTTP ${response.status}）`)
        }

        parentSearchRecords.value = data.records ?? []
        parentHasSearched.value = true
        showParentSearch.value = true
    } catch (e) {
        error.value = e.message || '検索に失敗しました。'
    } finally {
        parentSearching.value = false
    }
}

function onParentSelected(payload) {
    const record = payload?.record ?? payload
    if (!record?.orderID) return
    pendingParent.value = record
    showParentSearch.value = false
    error.value = ''
}

async function confirmLinkParent() {
    if (!pendingParent.value?.orderID) return

    linkingParent.value = true
    error.value = ''
    success.value = ''

    try {
        const body = {
            parentID: Number(pendingParent.value.orderID),
        }
        if (linkStatus.value !== '') {
            body.status = Number(linkStatus.value)
        }

        const url = `${page.props.appBaseUrl}/servicerecord/loaner/period/${props.attached.id}/parent`
        const result = await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        })

        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `紐づけに失敗しました。（HTTP ${response.status}）`)
        }

        attachedLocal.parentID = data.parentID
        attachedLocal.status = data.status
        parentLocal.value = data.parentRecord ?? pendingParent.value
        pendingParent.value = null
        success.value = data.message || 'service 案件に紐づけました。'
    } catch (e) {
        error.value = e.message || '紐づけに失敗しました。'
    } finally {
        linkingParent.value = false
    }
}

async function save() {
    error.value = ''
    success.value = ''

    if (form.sentDate && form.returnedDate && form.returnedDate < form.sentDate) {
        error.value = 'returnedDate は sentDate 以降にしてください。'
        return
    }
    if (
        form.plannedSentDate
        && form.plannedReturnedDate
        && form.plannedReturnedDate < form.plannedSentDate
    ) {
        error.value = 'plannedReturnedDate は plannedSentDate 以降にしてください。'
        return
    }

    saving.value = true

    try {
        const body = {
            sentDate: form.sentDate || null,
            returnedDate: form.returnedDate || null,
            comment: form.comment || null,
        }
        if (props.dateFields.hasPlannedSent) {
            body.plannedSentDate = form.plannedSentDate || null
        }
        if (props.dateFields.hasPlannedReturned) {
            body.plannedReturnedDate = form.plannedReturnedDate || null
        }
        if (attachedLocal.order_type === 'loaner') {
            body.status = form.status === '' ? null : Number(form.status)
        }

        const url = `${page.props.appBaseUrl}/servicerecord/loaner/period/${props.attached.id}`
        const result = await apiFetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        })

        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        success.value = data.message || '貸出期間を更新しました。'
        if (data.attached) {
            form.sentDate = data.attached.sentDate || ''
            form.returnedDate = data.attached.returnedDate || ''
            form.plannedSentDate = data.attached.plannedSentDate || ''
            form.plannedReturnedDate = data.attached.plannedReturnedDate || ''
            form.comment = data.attached.comment || ''
            if (Object.prototype.hasOwnProperty.call(data.attached, 'status')) {
                form.status = data.attached.status != null ? String(data.attached.status) : ''
                attachedLocal.status = data.attached.status
            }
        }
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.period-page {
    min-height: 100vh;
    padding: 12px 16px 24px;
    background: #e2e8f0;
    box-sizing: border-box;
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
    color: #1e293b;
}

.subtitle {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
    color: #fff;
    display: inline-flex;
    align-items: center;
}

.btn-primary {
    background: #2563eb;
}

.btn-secondary {
    background: #64748b;
}

.btn-primary:disabled,
.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.global-error,
.global-success {
    margin: 0 0 12px;
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 13px;
}

.global-error {
    border: 1px solid #fca5a5;
    background: #fef2f2;
    color: #b91c1c;
}

.global-success {
    border: 1px solid #86efac;
    background: #f0fdf4;
    color: #166534;
}

.content-grid {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) minmax(320px, 1.2fr);
    gap: 12px;
}

@media (max-width: 900px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

.info-card {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 14px 16px;
}

.card-title {
    margin: 0 0 12px;
    font-size: 15px;
    color: #1e293b;
}

.meta-grid {
    margin: 0;
    display: grid;
    gap: 8px;
}

.meta-grid div {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 8px;
    padding: 8px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.meta-grid dt {
    margin: 0;
    font-size: 12px;
    color: #64748b;
}

.meta-grid dd {
    margin: 0;
    font-size: 13px;
    color: #1e293b;
    word-break: break-word;
}

.parent-note {
    color: #64748b;
    font-size: 12px;
}

.parent-link-section {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #e2e8f0;
}

.section-title {
    margin: 0 0 6px;
    font-size: 14px;
    color: #1e293b;
}

.field-hint {
    margin: 0 0 10px;
    font-size: 12px;
    color: #64748b;
}

.parent-search-row {
    display: flex;
    gap: 8px;
}

.parent-search-row input {
    flex: 1;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
}

.pending-parent {
    margin-top: 10px;
    padding: 12px;
    border: 1px solid #93c5fd;
    border-radius: 6px;
    background: #eff6ff;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.parent-summary {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 12px;
    color: #334155;
}

.parent-summary strong {
    color: #1e40af;
    font-size: 13px;
}

.status-field {
    margin: 0;
}

.pending-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.form-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
    color: #475569;
}

.field input,
.field select,
.field textarea {
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    font: inherit;
}
</style>
