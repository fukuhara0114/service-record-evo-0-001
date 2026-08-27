<template>
    <div class="revision-page">
        <header class="page-header">
            <div>
                <h1>マスタ価格改定</h1>
                <p class="subtitle">
                    service / part / loaner の3マスタを同一改定日で同時に版切替します。
                    受注日が未設定の案件は最新版、受注日ありは期間内の版を参照します。
                </p>
            </div>
            <div class="header-actions">
                <a :href="adminUrl" class="btn btn-secondary">Admin一覧</a>
                <CloseToHomeButton :href="homeUrl" />
            </div>
        </header>

        <section class="toolbar panel">
            <label class="field">
                <span>改定日（新版の validDateMin）</span>
                <DateInputWithToday v-model="effectiveDate" />
            </label>
            <p class="hint">
                旧版の validDateMax は改定日前日になります。CSVは Excel 向け Shift_JIS で出力し、取込は UTF-8 / Shift_JIS を自動判定します。
                新規追加は serviceID / partID / loanerID を空欄にし、改定実行時に自動採番します（名称は必須）。
                loaner は一個体＝一つの loanerID（版をまたいでも同じ。servicerecord.loanerID と紐づく）。
                同一 productName でも loanerID が異なれば別個体です。新規追加だけ loanerID 空欄で自動採番します。
            </p>
            <p v-if="repairNotice" class="hint repair">{{ repairNotice }}</p>
            <div class="toolbar-actions">
                <span v-if="success" class="msg success">{{ success }}</span>
                <span v-if="error" class="msg error">{{ error }}</span>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="exportAllCsv">
                    現行価格をCSV出力（3種）
                </button>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="exportCurrentTabCsv">
                    表示タブをCSV出力
                </button>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="openImportPicker">
                    改定CSVを取込
                </button>
                <input
                    ref="csvInput"
                    type="file"
                    class="file-input"
                    accept=".csv,text/csv"
                    @change="onCsvSelected"
                >
                <button type="button" class="btn btn-primary" :disabled="saving || !effectiveDate" @click="submit">
                    {{ saving ? '保存中...' : '3マスタを同時改定' }}
                </button>
            </div>
        </section>

        <div class="tabs">
            <button type="button" class="tab" :class="{ active: tab === 'services' }" @click="tab = 'services'">
                Service（{{ filteredServices.length }}/{{ services.length }}）
            </button>
            <button type="button" class="tab" :class="{ active: tab === 'parts' }" @click="tab = 'parts'">
                Part（{{ filteredParts.length }}/{{ parts.length }}）
            </button>
            <button type="button" class="tab" :class="{ active: tab === 'loaners' }" @click="tab = 'loaners'">
                Loaner（{{ filteredLoaners.length }}/{{ loaners.length }}）
            </button>
            <input
                v-model="search"
                type="search"
                class="search"
                placeholder="キーワード検索（製品名 / partID / loanerID など）"
            >
        </div>

        <div class="list-area">
            <section v-show="tab === 'services'" class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>serviceID</th>
                                <th>製品名</th>
                                <th>現行期間</th>
                                <th>priceC_0</th>
                                <th>priceR_0</th>
                                <th>priceR_onSite</th>
                                <th>price_a2la</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredServices" :key="`s-${draftKey(row, 'serviceID')}`">
                                <td>
                                    <span v-if="row.isNew" class="new-badge">自動採番</span>
                                    <template v-else>{{ row.serviceID }}</template>
                                </td>
                                <td>{{ row.productName || '—' }}</td>
                                <td class="dates">{{ row.isNew ? '（新規）' : formatRange(row) }}</td>
                                <td><input v-model="draftServices[draftKey(row, 'serviceID')].priceC_0" type="number" step="1"></td>
                                <td><input v-model="draftServices[draftKey(row, 'serviceID')].priceR_0" type="number" step="1"></td>
                                <td><input v-model="draftServices[draftKey(row, 'serviceID')].priceR_onSite" type="number" step="1"></td>
                                <td><input v-model="draftServices[draftKey(row, 'serviceID')].price_a2la" type="number" step="1"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-show="tab === 'parts'" class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>partID</th>
                                <th>部品名</th>
                                <th>現行期間</th>
                                <th>price_discounted</th>
                                <th>price_market</th>
                                <th>price_discounted_1</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredParts" :key="`p-${draftKey(row, 'partID')}`">
                                <td>
                                    <span v-if="row.isNew" class="new-badge">自動採番</span>
                                    <template v-else>{{ row.partID }}</template>
                                </td>
                                <td>{{ row.partName || '—' }}</td>
                                <td class="dates">{{ row.isNew ? '（新規）' : formatRange(row) }}</td>
                                <td><input v-model="draftParts[draftKey(row, 'partID')].price_discounted" type="number" step="1"></td>
                                <td><input v-model="draftParts[draftKey(row, 'partID')].price_market" type="number" step="1"></td>
                                <td><input v-model="draftParts[draftKey(row, 'partID')].price_discounted_1" type="number" step="1"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-show="tab === 'loaners'" class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>id</th>
                                <th>loanerID</th>
                                <th>製品名</th>
                                <th>品目</th>
                                <th>S/N</th>
                                <th>管理番号</th>
                                <th>現行期間</th>
                                <th>price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredLoaners" :key="`l-${draftKey(row, 'loanerID')}`">
                                <td>{{ row.isNew ? '—' : (row.id ?? '—') }}</td>
                                <td>
                                    <span v-if="row.isNew" class="new-badge">自動採番</span>
                                    <template v-else>{{ row.loanerID }}</template>
                                </td>
                                <td>{{ row.productName || '—' }}</td>
                                <td>{{ row.item || '—' }}</td>
                                <td>{{ row.SN || '—' }}</td>
                                <td>{{ row.manageNum || '—' }}</td>
                                <td class="dates">{{ row.isNew ? '（新規）' : formatRange(row) }}</td>
                                <td><input v-model="draftLoaners[draftKey(row, 'loanerID')].price" type="number" step="1"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Encoding from 'encoding-japanese'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import DateInputWithToday from '@/components/DateInputWithToday.vue'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    services: { type: Array, default: () => [] },
    parts: { type: Array, default: () => [] },
    loaners: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
})

const page = usePage()
const tab = ref('services')
const search = ref('')
const effectiveDate = ref('')
const saving = ref(false)
const error = ref('')
const success = ref('')
const csvInput = ref(null)

const services = ref([...props.services])
const parts = ref([...props.parts])
const loaners = ref([...props.loaners])

const draftServices = reactive({})
const draftParts = reactive({})
const draftLoaners = reactive({})
let tempSeq = 0

function nextTempKey(prefix) {
    tempSeq += 1
    return `${prefix}_new_${tempSeq}`
}

function canonicalBusinessKey(value) {
    const text = String(value ?? '').trim()
    if (text === '') return ''
    if (/^[+-]?\d+\.0+$/.test(text)) return text.replace(/\.0+$/, '')
    return text
}

function canonicalHeader(raw) {
    const text = String(raw ?? '').replace(/^\uFEFF/, '').trim()
    const compact = text.toLowerCase().replace(/[\s_\-]/g, '')
    const aliases = {
        serviceid: 'serviceID',
        productname: 'productName',
        pricec0: 'priceC_0',
        pricer0: 'priceR_0',
        priceronsite: 'priceR_onSite',
        pricea2la: 'price_a2la',
        partid: 'partID',
        partname: 'partName',
        pricediscounted: 'price_discounted',
        pricemarket: 'price_market',
        pricediscounted1: 'price_discounted_1',
        loanerid: 'loanerID',
        id: 'id',
        sn: 'SN',
        managenum: 'manageNum',
        item: 'item',
        price: 'price',
        producttype: 'productType',
        entityid: 'entityID',
        groupname: 'groupName',
    }
    return aliases[compact] || text
}

function parseCsvNumber(value) {
    if (value == null) return ''
    let text = String(value).trim()
    if (text === '') return ''
    text = text.replace(/[¥￥,\s]/g, '')
    text = text.replace(/[０-９]/g, ch => String(ch.charCodeAt(0) - 0xFF10))
    if (text === '') return ''
    const num = Number(text)
    return Number.isFinite(num) ? String(num) : String(value).trim()
}

function applyCsvPrices(draft, row, fields) {
    if (!draft) return
    fields.forEach((field) => {
        if (row[field] === undefined || row[field] === null || String(row[field]).trim() === '') return
        draft[field] = parseCsvNumber(row[field])
    })
}

function draftKey(row, businessKey) {
    if (row?.isNew || row?._tempKey) return row._tempKey
    return canonicalBusinessKey(row?.[businessKey]) || String(row?.[businessKey] ?? '')
}

const CSV_SPECS = {
    services: {
        filename: 'master_prices_services.csv',
        key: 'serviceID',
        headers: ['serviceID', 'productName', 'priceC_0', 'priceR_0', 'priceR_onSite', 'price_a2la'],
        buildRows: () => services.value.map(row => {
            const key = draftKey(row, 'serviceID')
            return {
                serviceID: row.isNew ? '' : row.serviceID,
                productName: row.productName ?? '',
                priceC_0: draftServices[key]?.priceC_0 ?? row.priceC_0 ?? '',
                priceR_0: draftServices[key]?.priceR_0 ?? row.priceR_0 ?? '',
                priceR_onSite: draftServices[key]?.priceR_onSite ?? row.priceR_onSite ?? '',
                price_a2la: draftServices[key]?.price_a2la ?? row.price_a2la ?? '',
            }
        }),
        applyRow(row) {
            const keyFromId = canonicalBusinessKey(row.serviceID)
            let key = keyFromId && draftServices[keyFromId] ? keyFromId : ''
            if (!key) {
                const productName = String(row.productName ?? '').trim()
                if (productName) {
                    const found = services.value.find(item => !item.isNew && String(item.productName ?? '').trim() === productName)
                    if (found) key = draftKey(found, 'serviceID')
                }
            }
            if (key && draftServices[key]) {
                applyCsvPrices(draftServices[key], row, ['priceC_0', 'priceR_0', 'priceR_onSite', 'price_a2la'])
                return true
            }
            const productName = String(row.productName ?? '').trim()
            if (!productName) return false
            const tempKey = nextTempKey('service')
            const created = {
                id: null,
                serviceID: keyFromId || null,
                productName,
                priceC_0: parseCsvNumber(row.priceC_0),
                priceR_0: parseCsvNumber(row.priceR_0),
                priceR_onSite: parseCsvNumber(row.priceR_onSite),
                price_a2la: parseCsvNumber(row.price_a2la),
                validDateMin: null,
                validDateMax: null,
                isNew: true,
                _tempKey: tempKey,
            }
            services.value.push(created)
            draftServices[tempKey] = {
                serviceID: keyFromId || null,
                productName,
                isNew: true,
                priceC_0: created.priceC_0,
                priceR_0: created.priceR_0,
                priceR_onSite: created.priceR_onSite,
                price_a2la: created.price_a2la,
            }
            return true
        },
    },
    parts: {
        filename: 'master_prices_parts.csv',
        key: 'partID',
        headers: ['partID', 'partName', 'price_discounted', 'price_market', 'price_discounted_1'],
        buildRows: () => parts.value.map(row => {
            const key = draftKey(row, 'partID')
            return {
                partID: row.isNew ? '' : row.partID,
                partName: row.partName ?? '',
                price_discounted: draftParts[key]?.price_discounted ?? row.price_discounted ?? '',
                price_market: draftParts[key]?.price_market ?? row.price_market ?? '',
                price_discounted_1: draftParts[key]?.price_discounted_1 ?? row.price_discounted_1 ?? '',
            }
        }),
        applyRow(row) {
            const keyFromId = canonicalBusinessKey(row.partID)
            let key = keyFromId && draftParts[keyFromId] ? keyFromId : ''
            if (!key) {
                const partName = String(row.partName ?? '').trim()
                if (partName) {
                    const found = parts.value.find(item => !item.isNew && String(item.partName ?? '').trim() === partName)
                    if (found) key = draftKey(found, 'partID')
                }
            }
            if (key && draftParts[key]) {
                applyCsvPrices(draftParts[key], row, ['price_discounted', 'price_market', 'price_discounted_1'])
                if (row.partName) draftParts[key].partName = String(row.partName).trim()
                return true
            }
            const partName = String(row.partName ?? '').trim()
            if (!partName) return false
            const tempKey = nextTempKey('part')
            const created = {
                id: null,
                partID: keyFromId || null,
                partName,
                price_discounted: parseCsvNumber(row.price_discounted),
                price_market: parseCsvNumber(row.price_market),
                price_discounted_1: parseCsvNumber(row.price_discounted_1),
                validDateMin: null,
                validDateMax: null,
                isNew: true,
                _tempKey: tempKey,
            }
            parts.value.push(created)
            draftParts[tempKey] = {
                partID: keyFromId || null,
                partName,
                isNew: true,
                price_discounted: created.price_discounted,
                price_market: created.price_market,
                price_discounted_1: created.price_discounted_1,
            }
            return true
        },
    },
    loaners: {
        filename: 'master_prices_loaners.csv',
        key: 'loanerID',
        headers: ['id', 'loanerID', 'productName', 'item', 'SN', 'manageNum', 'price'],
        buildRows: () => loaners.value.map(row => {
            const key = draftKey(row, 'loanerID')
            return {
                id: row.isNew ? '' : (row.id ?? ''),
                loanerID: row.isNew ? '' : row.loanerID,
                productName: row.productName ?? '',
                item: row.item ?? '',
                SN: row.SN ?? '',
                manageNum: row.manageNum ?? '',
                price: draftLoaners[key]?.price ?? row.price ?? '',
            }
        }),
        applyRow(row) {
            const keyFromId = canonicalBusinessKey(row.loanerID)
            const surrogateId = canonicalBusinessKey(row.id)
            let key = keyFromId && draftLoaners[keyFromId] ? keyFromId : ''
            if (!key && surrogateId) {
                const found = loaners.value.find(item => !item.isNew && canonicalBusinessKey(item.id) === surrogateId)
                if (found) key = draftKey(found, 'loanerID')
            }
            if (!key) {
                const sn = String(row.SN ?? '').trim()
                if (sn) {
                    const found = loaners.value.find(item => !item.isNew && String(item.SN ?? '').trim() === sn)
                    if (found) key = draftKey(found, 'loanerID')
                }
            }
            if (!key) {
                const manageNum = String(row.manageNum ?? '').trim()
                if (manageNum) {
                    const found = loaners.value.find(item => !item.isNew && String(item.manageNum ?? '').trim() === manageNum)
                    if (found) key = draftKey(found, 'loanerID')
                }
            }
            if (key && draftLoaners[key]) {
                applyCsvPrices(draftLoaners[key], row, ['price'])
                return true
            }
            const productName = String(row.productName ?? '').trim()
            if (!productName) return false
            const tempKey = nextTempKey('loaner')
            const created = {
                id: null,
                loanerID: keyFromId || null,
                productName,
                item: String(row.item ?? '').trim(),
                SN: String(row.SN ?? '').trim(),
                manageNum: String(row.manageNum ?? '').trim(),
                price: parseCsvNumber(row.price),
                validDateMin: null,
                validDateMax: null,
                isNew: true,
                _tempKey: tempKey,
            }
            loaners.value.push(created)
            draftLoaners[tempKey] = {
                id: null,
                loanerID: keyFromId || null,
                productName,
                item: created.item,
                SN: created.SN,
                manageNum: created.manageNum,
                isNew: true,
                price: created.price,
            }
            return true
        },
    },
}

function resetDrafts() {
    Object.keys(draftServices).forEach(key => delete draftServices[key])
    Object.keys(draftParts).forEach(key => delete draftParts[key])
    Object.keys(draftLoaners).forEach(key => delete draftLoaners[key])

    services.value.forEach((row) => {
        const key = draftKey(row, 'serviceID')
        draftServices[key] = {
            serviceID: row.isNew ? null : row.serviceID,
            productName: row.productName ?? '',
            isNew: !!row.isNew,
            priceC_0: row.priceC_0 ?? '',
            priceR_0: row.priceR_0 ?? '',
            priceR_onSite: row.priceR_onSite ?? '',
            price_a2la: row.price_a2la ?? '',
        }
    })
    parts.value.forEach((row) => {
        const key = draftKey(row, 'partID')
        draftParts[key] = {
            partID: row.isNew ? null : row.partID,
            partName: row.partName ?? '',
            isNew: !!row.isNew,
            price_discounted: row.price_discounted ?? '',
            price_market: row.price_market ?? '',
            price_discounted_1: row.price_discounted_1 ?? '',
        }
    })
    loaners.value.forEach((row) => {
        const key = draftKey(row, 'loanerID')
        draftLoaners[key] = {
            id: row.isNew ? null : row.id,
            loanerID: row.isNew ? null : row.loanerID,
            productName: row.productName ?? '',
            item: row.item ?? '',
            SN: row.SN ?? '',
            manageNum: row.manageNum ?? '',
            isNew: !!row.isNew,
            price: row.price ?? '',
        }
    })
}

resetDrafts()

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)
const repairNotice = computed(() => {
    const repair = props.meta?.loanerIdRepair
    if (!repair) return ''
    const assigned = Number(repair.assignedNull || 0)
    if (assigned < 1) return ''
    return `loanerID 未採番の個体に ID を付与しました（${assigned}件）。同一個体の価格版では loanerID を維持します。`
})

function tokensOf(value) {
    return String(value || '')
        .toLowerCase()
        .split(/\s+/)
        .map(token => token.trim())
        .filter(Boolean)
}

function matchesSearch(row, fields) {
    const tokens = tokensOf(search.value)
    if (!tokens.length) return true
    const haystack = fields.map(field => String(row[field] ?? '').toLowerCase()).join(' ')
    return tokens.every(token => haystack.includes(token))
}

const filteredServices = computed(() =>
    services.value.filter(row => matchesSearch(row, ['serviceID', 'productName', 'entityID'])),
)
const filteredParts = computed(() =>
    parts.value.filter(row => matchesSearch(row, ['partID', 'partName', 'description', 'type'])),
)
const filteredLoaners = computed(() =>
    loaners.value.filter(row => matchesSearch(row, ['id', 'loanerID', 'productName', 'item', 'SN', 'manageNum', 'groupName'])),
)

function formatRange(row) {
    const min = row.validDateMin || '—'
    const max = row.validDateMax || '—'
    return `${min} ～ ${max}`
}

function escapeCsvCell(value) {
    const text = value == null ? '' : String(value)
    if (/[",\r\n]/.test(text)) {
        return `"${text.replace(/"/g, '""')}"`
    }
    return text
}

function toCsvString(headers, rows) {
    const lines = [headers.join(',')]
    rows.forEach((row) => {
        lines.push(headers.map(header => escapeCsvCell(row[header])).join(','))
    })
    return `${lines.join('\r\n')}\r\n`
}

/**
 * Excel（日本語Windows）向けに Shift_JIS でCSV出力する。
 */
function downloadCsv(filename, headers, rows) {
    const unicode = toCsvString(headers, rows)
    const sjisArray = Encoding.convert(Encoding.stringToCode(unicode), {
        to: 'SJIS',
        from: 'UNICODE',
        type: 'array',
    })
    const blob = new Blob([new Uint8Array(sjisArray)], { type: 'text/csv;charset=Shift_JIS;' })
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
}

function exportKind(kind) {
    const spec = CSV_SPECS[kind]
    if (!spec) return
    downloadCsv(spec.filename, spec.headers, spec.buildRows())
}

function exportCurrentTabCsv() {
    error.value = ''
    exportKind(tab.value)
    success.value = `${tab.value} の現行価格CSV（Shift_JIS）を出力しました。`
}

function exportAllCsv() {
    error.value = ''
    exportKind('services')
    exportKind('parts')
    exportKind('loaners')
    success.value = 'service / part / loaner の現行価格CSV（Shift_JIS）を出力しました。Excelでそのまま開けます。'
}

function openImportPicker() {
    error.value = ''
    success.value = ''
    csvInput.value?.click()
}

/**
 * UTF-8 / UTF-16LE / Shift_JIS を自動判定して文字列化する。
 */
async function readCsvText(file) {
    const buffer = new Uint8Array(await file.arrayBuffer())
    if (buffer.length >= 3 && buffer[0] === 0xEF && buffer[1] === 0xBB && buffer[2] === 0xBF) {
        return new TextDecoder('utf-8').decode(buffer.subarray(3))
    }
    if (buffer.length >= 2 && buffer[0] === 0xFF && buffer[1] === 0xFE) {
        return new TextDecoder('utf-16le').decode(buffer)
    }
    if (buffer.length >= 2 && buffer[0] === 0xFE && buffer[1] === 0xFF) {
        return new TextDecoder('utf-16be').decode(buffer)
    }

    const detected = Encoding.detect(buffer) || 'SJIS'
    // Excel保存CSVは多くの場合 SJIS。UTF8 と誤判定された場合のフォールバックも持つ。
    try {
        return Encoding.convert(buffer, {
            to: 'UNICODE',
            from: detected,
            type: 'string',
        })
    } catch {
        return Encoding.convert(buffer, {
            to: 'UNICODE',
            from: 'SJIS',
            type: 'string',
        })
    }
}

function detectDelimiter(text) {
    const firstLine = String(text || '').split(/\r?\n/).find(line => String(line).trim() !== '') || ''
    const counts = {
        ',': (firstLine.match(/,/g) || []).length,
        ';': (firstLine.match(/;/g) || []).length,
        '\t': (firstLine.match(/\t/g) || []).length,
    }
    const ranked = Object.entries(counts).sort((a, b) => b[1] - a[1])
    return ranked[0][1] > 0 ? ranked[0][0] : ','
}

function parseCsv(text) {
    const delimiter = detectDelimiter(text)
    const normalized = String(text || '').replace(/^\uFEFF/, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n')
    const rows = []
    let current = ''
    let inQuotes = false
    const pushCell = (row, cell) => {
        row.push(cell)
    }
    let row = []

    for (let i = 0; i < normalized.length; i += 1) {
        const ch = normalized[i]
        const next = normalized[i + 1]
        if (inQuotes) {
            if (ch === '"' && next === '"') {
                current += '"'
                i += 1
            } else if (ch === '"') {
                inQuotes = false
            } else {
                current += ch
            }
            continue
        }
        if (ch === '"') {
            inQuotes = true
            continue
        }
        if (ch === delimiter) {
            pushCell(row, current)
            current = ''
            continue
        }
        if (ch === '\n') {
            pushCell(row, current)
            current = ''
            if (row.some(cell => String(cell).trim() !== '')) rows.push(row)
            row = []
            continue
        }
        current += ch
    }
    pushCell(row, current)
    if (row.some(cell => String(cell).trim() !== '')) rows.push(row)
    if (!rows.length) return []

    const headerIndex = rows.findIndex((cells) => {
        const names = cells.map(cell => canonicalHeader(cell).toLowerCase())
        return names.includes('serviceid') || names.includes('partid') || names.includes('loanerid')
    })
    const start = headerIndex >= 0 ? headerIndex : 0
    const headers = rows[start].map(cell => canonicalHeader(cell))
    return rows.slice(start + 1).map((cells) => {
        const obj = {}
        headers.forEach((header, index) => {
            if (!header) return
            obj[header] = cells[index] ?? ''
        })
        return obj
    })
}

function detectCsvKind(rows) {
    if (!rows.length) return null
    const headers = Object.keys(rows[0]).map(header => String(header).toLowerCase())
    if (headers.includes('serviceid')) return 'services'
    if (headers.includes('partid')) return 'parts'
    if (headers.includes('loanerid')) return 'loaners'
    return tab.value
}

async function onCsvSelected(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    error.value = ''
    success.value = ''
    try {
        const text = await readCsvText(file)
        const rows = parseCsv(text)
        if (!rows.length) throw new Error('CSVにデータ行がありません。')

        const kind = detectCsvKind(rows)
        const spec = CSV_SPECS[kind]
        if (!spec) throw new Error('CSVの種類を判定できませんでした（serviceID / partID / loanerID が必要です）。')

        let updated = 0
        let created = 0
        let skipped = 0
        const beforeServices = services.value.length
        const beforeParts = parts.value.length
        const beforeLoaners = loaners.value.length
        rows.forEach((row) => {
            if (spec.applyRow(row)) updated += 1
            else skipped += 1
        })
        if (kind === 'services') created = services.value.length - beforeServices
        if (kind === 'parts') created = parts.value.length - beforeParts
        if (kind === 'loaners') created = loaners.value.length - beforeLoaners
        const matchedUpdates = Math.max(0, updated - created)

        tab.value = kind
        success.value = `${kind} CSVを取り込みました（更新 ${matchedUpdates}件 / 新規 ${created}件 / 未一致 ${skipped}件）。文字コードは自動判定です。内容を確認して「3マスタを同時改定」を実行してください。`
    } catch (e) {
        error.value = e.message || 'CSVの取り込みに失敗しました。'
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function nullableNumber(value) {
    if (value === '' || value === null || value === undefined) return null
    const parsed = parseCsvNumber(value)
    if (parsed === '') return null
    const num = Number(parsed)
    return Number.isFinite(num) ? num : null
}

async function submit() {
    error.value = ''
    success.value = ''
    if (!effectiveDate.value) {
        error.value = '改定日を入力してください。'
        return
    }
    if (!window.confirm(`改定日 ${effectiveDate.value} で service / part / loaner を同時に版切替します。よろしいですか？`)) {
        return
    }

    saving.value = true
    try {
        const result = await apiFetch(`${page.props.appBaseUrl}/servicerecord/master-price-revision`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                effectiveDate: effectiveDate.value,
                services: Object.values(draftServices).map(row => ({
                    serviceID: row.serviceID ?? null,
                    productName: row.productName ?? null,
                    priceC_0: nullableNumber(row.priceC_0),
                    priceR_0: nullableNumber(row.priceR_0),
                    priceR_onSite: nullableNumber(row.priceR_onSite),
                    price_a2la: nullableNumber(row.price_a2la),
                })),
                parts: Object.values(draftParts).map(row => ({
                    partID: row.partID ?? null,
                    partName: row.partName ?? null,
                    price_discounted: nullableNumber(row.price_discounted),
                    price_market: nullableNumber(row.price_market),
                    price_discounted_1: nullableNumber(row.price_discounted_1),
                })),
                loaners: Object.values(draftLoaners).map(row => ({
                    loanerID: row.loanerID ?? null,
                    productName: row.productName ?? null,
                    item: row.item ?? null,
                    SN: row.SN ?? null,
                    manageNum: row.manageNum ?? null,
                    price: nullableNumber(row.price),
                })),
            }),
        })
        if (!result) throw new Error('保存に失敗しました。')
        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data?.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            throw new Error(validationMessage || data?.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        services.value = data.services ?? services.value
        parts.value = data.parts ?? parts.value
        loaners.value = data.loaners ?? loaners.value
        resetDrafts()
        success.value = data.message || '価格改定を保存しました。'
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.revision-page {
    height: 100vh;
    max-height: 100vh;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    background: #e2e8f0;
    box-sizing: border-box;
    overflow: hidden;
}
.page-header {
    flex: 0 0 auto;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 14px;
    background: #1e293b;
    color: #fff;
    border-radius: 4px;
}
.page-header h1 { margin: 0; font-size: 18px; }
.subtitle { margin: 4px 0 0; font-size: 12px; color: #cbd5e1; max-width: 70ch; }
.header-actions, .toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.btn {
    min-height: 30px;
    padding: 5px 12px;
    border: 1px solid transparent;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
    color: #fff;
}
.btn:disabled { opacity: .6; cursor: wait; }
.btn-primary { background: #2563eb; }
.btn-secondary { background: #475569; border-color: #64748b; }
.file-input { display: none; }
.panel {
    background: #fff;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    padding: 10px;
}
.toolbar {
    flex: 0 0 auto;
    display: flex;
    flex-wrap: wrap;
    gap: 12px 18px;
    align-items: end;
}
.field { display: grid; gap: 4px; font-size: 12px; color: #475569; }
.field input, .search, table input {
    height: 28px;
    padding: 2px 6px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    font-size: 12px;
}
.hint { margin: 0; flex: 1 1 280px; color: #64748b; font-size: 12px; }
.hint.repair { flex: 1 1 100%; color: #1d4ed8; background: #eff6ff; border: 1px solid #93c5fd; padding: 6px 8px; border-radius: 2px; }
.msg { font-size: 12px; }
.msg.success { color: #166534; }
.msg.error { color: #b91c1c; }
.tabs {
    flex: 0 0 auto;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
.tab {
    min-height: 30px;
    padding: 4px 10px;
    border: 1px solid #94a3b8;
    border-radius: 3px;
    background: #e2e8f0;
    color: #334155;
    cursor: pointer;
    font-size: 12px;
}
.tab.active { background: #fff; font-weight: 700; color: #0f172a; }
.search { flex: 1 1 220px; min-width: 180px; }
.list-area {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.table-panel {
    flex: 1 1 auto;
    min-height: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.table-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    overscroll-behavior: contain;
}
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th, td { border-bottom: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; vertical-align: middle; }
th { position: sticky; top: 0; background: #f1f5f9; z-index: 1; }
td.dates { white-space: nowrap; color: #64748b; }
table input { width: 100%; min-width: 88px; }
.warn { flex: 0 0 auto; margin: 0 0 8px; color: #92400e; background: #fef3c7; border: 1px solid #fcd34d; padding: 6px 8px; font-size: 12px; }
.new-badge {
    display: inline-block;
    padding: 1px 6px;
    border: 1px solid #93c5fd;
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 2px;
    font-size: 11px;
    white-space: nowrap;
}
</style>

<style>
html,
body,
#app {
    height: 100%;
    max-height: 100vh;
    overflow: hidden;
}
</style>
