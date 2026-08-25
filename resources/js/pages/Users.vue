<template>
    <div class="users-page">
        <header class="page-header">
            <h1>Users</h1>
            <div class="header-actions">
                <span v-if="success" class="msg success">{{ success }}</span>
                <span v-if="error" class="msg error">{{ error }}</span>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="addRow">
                    新規追加
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="saving || (existingRows.length === 0 && newRows.length === 0)"
                    @click="save"
                >
                    {{ saving ? '保存中...' : '保存' }}
                </button>
                <CloseToHomeButton :href="homeUrl" />
            </div>
        </header>

        <section v-if="newRows.length" class="panel new-panel">
            <div class="panel-title">新規追加</div>
            <div class="new-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th>名前</th>
                            <th>漢字氏名</th>
                            <th>メールアドレス</th>
                            <th>権限</th>
                            <th>laborID</th>
                            <th class="col-receive">receive_info</th>
                            <th>signature</th>
                            <th>パスワード</th>
                            <th class="col-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in newRows" :key="row._key" class="is-new">
                            <td class="col-id">新規</td>
                            <td>
                                <input v-model="row.name" type="text" autocomplete="off">
                            </td>
                            <td>
                                <input v-model="row.kanji_name" type="text" autocomplete="off">
                            </td>
                            <td>
                                <input v-model="row.email" type="email" autocomplete="off">
                            </td>
                            <td>
                                <select v-model="row.permission">
                                    <option value="">（未設定）</option>
                                    <option
                                        v-for="opt in permissionSelectOptions(row.permission)"
                                        :key="opt"
                                        :value="opt"
                                    >
                                        {{ opt }}
                                    </option>
                                </select>
                            </td>
                            <td>
                                <select v-model="row.laborID">
                                    <option value="-1">（未割当）</option>
                                    <option
                                        v-for="labor in laborSelectOptions(row.laborID)"
                                        :key="labor.laborID"
                                        :value="String(labor.laborID)"
                                    >
                                        {{ labor.laborID }} / {{ labor.laborName }}
                                    </option>
                                </select>
                            </td>
                            <td class="col-receive">
                                <input v-model="row.receive_info" type="checkbox" class="receive-check">
                            </td>
                            <td>
                                <input v-model="row.signature" type="text" autocomplete="off">
                            </td>
                            <td>
                                <input
                                    v-model="row.password"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="必須"
                                >
                            </td>
                            <td class="col-actions">
                                <button
                                    type="button"
                                    class="btn-remove"
                                    :disabled="saving"
                                    title="この新規行を削除"
                                    @click="removeNewRow(index)"
                                >
                                    取消
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel list-panel">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th>名前</th>
                            <th>漢字氏名</th>
                            <th>メールアドレス</th>
                            <th>権限</th>
                            <th>laborID</th>
                            <th class="col-receive">receive_info</th>
                            <th>signature</th>
                            <th>パスワード</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="existingRows.length === 0">
                            <td colspan="9" class="empty">データがありません。「新規追加」でユーザーを作成できます。</td>
                        </tr>
                        <tr v-for="row in existingRows" :key="row._key">
                            <td class="col-id">{{ row.userID }}</td>
                            <td>
                                <input v-model="row.name" type="text" autocomplete="off">
                            </td>
                            <td>
                                <input v-model="row.kanji_name" type="text" autocomplete="off">
                            </td>
                            <td>
                                <input v-model="row.email" type="email" autocomplete="off">
                            </td>
                            <td>
                                <select v-model="row.permission">
                                    <option value="">（未設定）</option>
                                    <option
                                        v-for="opt in permissionSelectOptions(row.permission)"
                                        :key="opt"
                                        :value="opt"
                                    >
                                        {{ opt }}
                                    </option>
                                </select>
                            </td>
                            <td>
                                <select v-model="row.laborID">
                                    <option value="-1">（未割当）</option>
                                    <option
                                        v-for="labor in laborSelectOptions(row.laborID)"
                                        :key="labor.laborID"
                                        :value="String(labor.laborID)"
                                    >
                                        {{ labor.laborID }} / {{ labor.laborName }}
                                    </option>
                                </select>
                            </td>
                            <td class="col-receive">
                                <input v-model="row.receive_info" type="checkbox" class="receive-check">
                            </td>
                            <td>
                                <input v-model="row.signature" type="text" autocomplete="off">
                            </td>
                            <td>
                                <input
                                    v-model="row.password"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="変更時のみ入力"
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    users: { type: Array, default: () => [] },
    labors: { type: Array, default: () => [] },
    permissionOptions: { type: Array, default: () => ['administrator', 'admin', 'limited', 'guest'] },
})

const page = usePage()
const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)

const existingRows = ref([])
const newRows = ref([])
const saving = ref(false)
const success = ref('')
const error = ref('')
let rowSeq = 0

function toRow(user = null) {
    rowSeq += 1
    return {
        _key: user?.userID ? `id-${user.userID}` : `new-${rowSeq}`,
        userID: user?.userID ?? null,
        name: user?.name ?? '',
        kanji_name: user?.kanji_name ?? '',
        email: user?.email ?? '',
        permission: user?.permission ?? '',
        laborID: String(user?.laborID ?? -1),
        receive_info: Number(user?.receive_info ?? 0) === 1,
        signature: user?.signature ?? '',
        password: '',
    }
}

function syncExistingFromProps(list) {
    existingRows.value = (list ?? []).map((user) => toRow(user))
    newRows.value = []
}

watch(
    () => props.users,
    (list) => syncExistingFromProps(list),
    { immediate: true },
)

function permissionSelectOptions(current) {
    const base = [...props.permissionOptions]
    if (current && !base.includes(current)) {
        base.push(current)
    }
    return base
}

function laborSelectOptions(currentLaborId) {
    const list = [...props.labors]
    const current = Number(currentLaborId)
    if (Number.isFinite(current) && current !== -1 && !list.some((l) => Number(l.laborID) === current)) {
        list.push({ laborID: current, laborName: '（マスタ外）' })
    }
    return list
}

function addRow() {
    newRows.value.push(toRow())
    success.value = ''
    error.value = ''
}

function removeNewRow(index) {
    newRows.value.splice(index, 1)
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function nullableTrim(value) {
    const text = String(value ?? '').trim()
    return text === '' ? null : text
}

function serializeRow(row) {
    return {
        userID: row.userID,
        name: String(row.name ?? '').trim(),
        kanji_name: String(row.kanji_name ?? '').trim(),
        email: String(row.email ?? '').trim(),
        permission: nullableTrim(row.permission),
        laborID: row.laborID === '' || row.laborID == null ? -1 : Number(row.laborID),
        receive_info: !!row.receive_info,
        signature: String(row.signature ?? '').trim(),
        password: String(row.password ?? ''),
    }
}

async function save() {
    saving.value = true
    success.value = ''
    error.value = ''

    try {
        const payload = {
            users: [...existingRows.value, ...newRows.value].map(serializeRow),
        }

        const result = await apiFetch(`${page.props.appBaseUrl}/users`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        })
        if (!result) throw new Error('保存に失敗しました。')

        const { response, data } = result
        if (!response.ok) {
            const validationMessage = data.errors
                ? [...new Set(Object.values(data.errors).flat())].join(' ')
                : null
            throw new Error(validationMessage || data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        syncExistingFromProps(data.users ?? [])
        success.value = data.message || '保存しました。'
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.users-page {
    height: 100vh;
    box-sizing: border-box;
    padding: 16px 20px;
    background: #e2e8f0;
    color: #0f172a;
    font-family: sans-serif;
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow: hidden;
}
.page-header {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.page-header h1 {
    margin: 0;
    font-size: 22px;
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    padding: 0 14px;
    border: 1px solid transparent;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.btn-primary {
    background: #2563eb;
    color: #fff;
}
.btn-primary:hover:not(:disabled) {
    background: #1d4ed8;
}
.btn-secondary {
    background: #64748b;
    color: #fff;
}
.btn-secondary:hover:not(:disabled) {
    background: #475569;
}
.msg {
    font-size: 13px;
}
.msg.success { color: #047857; }
.msg.error { color: #b91c1c; }
.panel {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    min-width: 0;
}
.new-panel {
    flex: 0 0 auto;
    max-height: 28vh;
    display: flex;
    flex-direction: column;
}
.panel-title {
    flex: 0 0 auto;
    padding: 8px 12px;
    background: #eff6ff;
    border-bottom: 1px solid #bfdbfe;
    color: #1e40af;
    font-size: 13px;
    font-weight: 700;
}
.new-table-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
}
.list-panel {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
}
.table-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
th,
td {
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}
th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    white-space: nowrap;
}
.col-id {
    width: 64px;
    white-space: nowrap;
    color: #64748b;
}
.col-actions {
    width: 64px;
}
.col-receive {
    width: 96px;
    text-align: center;
}
.receive-check {
    width: 16px;
    min-width: 16px;
    height: 16px;
    margin: 0 auto;
    display: block;
    cursor: pointer;
}
.empty {
    text-align: center;
    color: #94a3b8;
    padding: 28px 12px;
}
tr.is-new {
    background: #f0f9ff;
}
input,
select {
    width: 100%;
    min-width: 110px;
    box-sizing: border-box;
    height: 32px;
    padding: 2px 8px;
    border: 1px solid #94a3b8;
    border-radius: 3px;
    background: #fff;
    color: #0f172a;
    font-size: 13px;
}
.btn-remove {
    height: 28px;
    padding: 0 8px;
    border: 1px solid #cbd5e1;
    border-radius: 3px;
    background: #fff;
    color: #b91c1c;
    font-size: 12px;
    cursor: pointer;
}
.btn-remove:hover:not(:disabled) {
    background: #fef2f2;
}
</style>
