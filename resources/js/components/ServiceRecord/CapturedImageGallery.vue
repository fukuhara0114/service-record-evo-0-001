<template>
    <div class="gallery">
        <div class="gallery-toolbar">
            <div class="gallery-filters">
                <label class="filter-field">
                    期間
                    <select v-model="periodFilter" class="filter-select" :disabled="loading">
                        <option
                            v-for="option in periodOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>
                <label class="filter-field">
                    開始日
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="filter-input"
                        :disabled="loading"
                        @change="onCustomDateChange"
                    >
                </label>
                <label class="filter-field">
                    終了日
                    <input
                        v-model="dateTo"
                        type="date"
                        class="filter-input"
                        :disabled="loading"
                        @change="onCustomDateChange"
                    >
                </label>
                <label class="filter-field">
                    撮影者
                    <select v-model="capturedByFilter" class="filter-select" :disabled="loading">
                        <option value="">すべて</option>
                        <option
                            v-for="name in photographerOptions"
                            :key="name"
                            :value="name"
                        >
                            {{ name }}
                        </option>
                    </select>
                </label>
                <button
                    v-if="showLinkedOnlyToggle"
                    type="button"
                    class="action-btn filter-toggle-btn"
                    :class="{ active: linkedOnlyFilter }"
                    :disabled="loading || !canAssociate"
                    :title="linkedOnlyFilter
                        ? 'この案件に紐づいた画像のみ表示中（再クリックで解除）'
                        : 'この案件に紐づいた画像だけを表示'"
                    @click="toggleLinkedOnlyFilter"
                >
                    紐づけ済
                </button>
                <button type="button" class="action-btn" :disabled="loading" @click="reload">再読込</button>
            </div>
            <div class="gallery-toolbar-right">
                <p class="gallery-count">{{ items.length }} / {{ total }} 件</p>
                <p class="gallery-selected-count">選択中: {{ selectedCount }} 件</p>
            </div>
        </div>

        <div class="selection-toolbar">
            <button
                type="button"
                class="action-btn action-btn-secondary"
                :disabled="loading || !items.length"
                @click="selectAllVisible"
            >
                表示中を全選択
            </button>
            <button
                type="button"
                class="action-btn action-btn-secondary"
                :disabled="selectedCount === 0"
                @click="clearSelection"
            >
                選択解除
            </button>
            <button
                v-if="!selectionOnly"
                type="button"
                class="action-btn action-btn-danger"
                :disabled="deletableSelectedCount === 0 || deleting"
                :title="deleteButtonTitle"
                @click="deleteSelected"
            >
                {{ deleting ? '削除中...' : `削除${deletableSelectedCount > 0 ? ` (${deletableSelectedCount})` : ''}` }}
            </button>
            <button
                v-if="canAssociate && !selectionOnly"
                type="button"
                class="action-btn action-btn-primary"
                :disabled="selectedCount === 0 || associating"
                @click="associateSelected"
            >
                {{ associating ? '紐づけ中...' : 'この案件に紐づける' }}
            </button>
            <button
                v-if="selectionOnly"
                type="button"
                class="action-btn action-btn-primary"
                :disabled="selectedCount === 0"
                @click="confirmSelection"
            >
                選択した画像を使う ({{ selectedCount }})
            </button>
            <p v-if="associateMessage" class="associate-message" :class="{ error: associateError }">
                {{ associateMessage }}
            </p>
        </div>

        <p v-if="loading && !items.length" class="status-message">読み込み中...</p>
        <p v-else-if="error" class="status-message error">{{ error }}</p>
        <p v-else-if="!items.length" class="status-message">画像がありません。</p>

        <div v-else class="thumb-grid">
            <div
                v-for="item in items"
                :key="item.id"
                class="thumb-card"
                :class="{
                    previewed: previewId === item.id,
                    checked: isSelected(item.id),
                    linked: isLinked(item),
                    'linked-current': isLinkedToCurrentCase(item),
                    'linked-other': isLinkedToOtherCase(item),
                }"
            >
                <label class="thumb-check" @click.stop>
                    <input
                        type="checkbox"
                        :checked="isSelected(item.id)"
                        @change="toggleSelect(item, $event)"
                        @click.stop
                    >
                    <span class="sr-only">選択</span>
                </label>
                <span
                    v-if="isLinked(item)"
                    class="linked-badge"
                    :class="{ 'linked-badge-other': isLinkedToOtherCase(item) }"
                    :title="linkedBadgeTitle(item)"
                >
                    紐づき済
                    <small v-if="isLinkedToOtherCase(item)" class="linked-hint">
                        #{{ normalizedAssociatedId(item) }}
                    </small>
                </span>
                <button
                    type="button"
                    class="thumb-card-body"
                    @click="openPreview(item)"
                >
                    <img
                        :src="item.thumbnail_url"
                        :alt="item.title || item.file_name"
                        class="thumb-image"
                        loading="lazy"
                    >
                    <div class="thumb-meta">
                        <strong>{{ item.title || '—' }}</strong>
                        <span>{{ item.captured_at || '—' }}</span>
                        <span>{{ item.captured_by || '—' }}</span>
                    </div>
                </button>
            </div>
        </div>

        <div v-if="hasMore" class="gallery-more">
            <button type="button" class="action-btn action-btn-primary" :disabled="loading" @click="loadMore">
                {{ loading ? '読み込み中...' : 'さらに表示' }}
            </button>
        </div>

        <div v-if="previewItem" class="preview-overlay" @click.self="closePreview">
            <div class="preview-panel">
                <div class="preview-header">
                    <div>
                        <h3>{{ previewItem.title || previewItem.file_name }}</h3>
                        <p>
                            {{ previewItem.captured_at || '—' }}
                            ／ {{ previewItem.captured_by || '—' }}
                            ／ associatedID: {{ previewItem.associatedID ?? '—' }}
                        </p>
                    </div>
                    <div class="preview-header-actions">
                        <button type="button" class="action-btn action-btn-primary" @click="openEditor">
                            編集
                        </button>
                        <button type="button" class="close-btn" @click="closePreview">×</button>
                    </div>
                </div>
                <div class="preview-body">
                    <img :src="previewItem.image_url" :alt="previewItem.title || previewItem.file_name" class="preview-image">
                </div>
            </div>
        </div>

        <CapturedImageEditor
            v-if="editingItem"
            :image="editingItem"
            @close="closeEditor"
            @saved="onEditorSaved"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import CapturedImageEditor from './CapturedImageEditor.vue'

const PERIOD_OPTIONS = [
    { value: 'today', label: '今日' },
    { value: '1d', label: '1日前から' },
    { value: '3d', label: '3日前から' },
    { value: '7d', label: '7日前から' },
    { value: '1m', label: '1月前から' },
    { value: '3m', label: '3か月前から' },
    { value: 'all', label: '全て' },
    { value: 'custom', label: 'カスタム' },
]

const props = defineProps({
    // Target orderID for「この案件に紐づける」(does not filter the gallery list)
    // Accept both casings: Vue kebab `associated-id` maps to associatedId, not associatedID.
    associatedID: {
        type: [Number, String],
        default: null,
    },
    associatedId: {
        type: [Number, String],
        default: null,
    },
    // When true, list API filters by associatedID
    filterByAssociated: {
        type: Boolean,
        default: false,
    },
    initialCapturedBy: {
        type: String,
        default: '',
    },
    /** true のとき削除・紐づけを隠し、選択確定ボタンを表示 */
    selectionOnly: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['select', 'selection-change', 'associated', 'confirm-selection'])

const page = usePage()
const items = ref([])
const total = ref(0)
const currentPage = ref(0)
const lastPage = ref(1)
const loading = ref(false)
const error = ref('')
const previewItem = ref(null)
const editingItem = ref(null)
const previewId = ref(null)
const photographerOptions = ref([])
const capturedByFilter = ref(props.initialCapturedBy || '')
const periodFilter = ref('today')
const dateFrom = ref('')
const dateTo = ref('')
const selectedMap = ref(new Map())
const suppressPeriodWatch = ref(false)
const suppressDateWatch = ref(false)
const associating = ref(false)
const associateMessage = ref('')
const associateError = ref(false)
const deleting = ref(false)
const linkedOnlyFilter = ref(false)
const periodBeforeLinkedOnly = ref(null)

const periodOptions = PERIOD_OPTIONS
const hasMore = computed(() => currentPage.value < lastPage.value)
const selectedCount = computed(() => selectedMap.value.size)
const selectedImages = computed(() => Array.from(selectedMap.value.values()))
const currentUserKanji = computed(() => {
    const fromPage = String(page.props.authUser?.kanji_name ?? '').trim()
    if (fromPage) return fromPage.slice(0, 8)
    if (typeof document !== 'undefined') {
        return String(document.querySelector('meta[name="auth-kanji-name"]')?.content ?? '').trim().slice(0, 8)
    }
    return ''
})
const deletableSelectedImages = computed(() => {
    const me = currentUserKanji.value
    if (!me) return []
    return selectedImages.value.filter((item) => String(item.captured_by ?? '') === me)
})
const deletableSelectedCount = computed(() => deletableSelectedImages.value.length)
const deleteButtonTitle = computed(() => {
    if (!currentUserKanji.value) return 'ログインユーザー情報を確認できません'
    if (selectedCount.value === 0) return '画像を選択してください'
    if (deletableSelectedCount.value === 0) return '自分がアップロードした画像のみ削除できます'
    if (deletableSelectedCount.value < selectedCount.value) {
        return `選択中 ${selectedCount.value} 件のうち、自分の ${deletableSelectedCount.value} 件のみ削除します`
    }
    return '選択した画像を削除します'
})
/** Resolved case orderID from either associatedID or associatedId prop. */
const caseAssociatedId = computed(() => {
    const raw = props.associatedID ?? props.associatedId
    if (raw == null || raw === '') return null
    return raw
})
const canAssociate = computed(() => {
    const id = Number(caseAssociatedId.value)
    return Number.isFinite(id) && id > 0
})
/** Email からの選択モード時に「紐づけ済」フィルターボタンを表示 */
const showLinkedOnlyToggle = computed(() => props.selectionOnly && canAssociate.value)
const shouldFilterByAssociated = computed(() => (
    props.filterByAssociated || linkedOnlyFilter.value
))

function tokyoTodayYmd() {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date())
}

function ymdFromParts({ year, month, day }) {
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

function addDaysYmd(ymd, days) {
    const [y, m, d] = ymd.split('-').map(Number)
    const utc = new Date(Date.UTC(y, m - 1, d + days))
    return ymdFromParts({
        year: utc.getUTCFullYear(),
        month: utc.getUTCMonth() + 1,
        day: utc.getUTCDate(),
    })
}

function addMonthsYmd(ymd, months) {
    const [y, m, d] = ymd.split('-').map(Number)
    const utc = new Date(Date.UTC(y, m - 1 + months, 1))
    const lastDay = new Date(Date.UTC(utc.getUTCFullYear(), utc.getUTCMonth() + 1, 0)).getUTCDate()
    utc.setUTCDate(Math.min(d, lastDay))
    return ymdFromParts({
        year: utc.getUTCFullYear(),
        month: utc.getUTCMonth() + 1,
        day: utc.getUTCDate(),
    })
}

function rangeForPeriod(period) {
    const today = tokyoTodayYmd()
    switch (period) {
        case '1d':
            return { from: addDaysYmd(today, -1), to: today }
        case '3d':
            return { from: addDaysYmd(today, -3), to: today }
        case '7d':
            return { from: addDaysYmd(today, -7), to: today }
        case '1m':
            return { from: addMonthsYmd(today, -1), to: today }
        case '3m':
            return { from: addMonthsYmd(today, -3), to: today }
        case 'all':
            return { from: '', to: '' }
        case 'today':
        default:
            return { from: today, to: today }
    }
}

function applyPeriodToDates(period) {
    const range = rangeForPeriod(period)
    suppressDateWatch.value = true
    dateFrom.value = range.from
    dateTo.value = range.to
    queueMicrotask(() => {
        suppressDateWatch.value = false
    })
}

function selectionPayload(item) {
    return {
        id: item.id,
        file_name: item.file_name,
        title: item.title,
        image_url: item.image_url,
        thumbnail_url: item.thumbnail_url,
        captured_at: item.captured_at,
        captured_by: item.captured_by,
        associatedID: item.associatedID,
    }
}

function emitSelectionChange() {
    emit('selection-change', selectedImages.value)
}

function isSelected(id) {
    return selectedMap.value.has(id)
}

function toggleSelect(item, event) {
    const next = new Map(selectedMap.value)
    if (event.target.checked) {
        next.set(item.id, selectionPayload(item))
    } else {
        next.delete(item.id)
    }
    selectedMap.value = next
    emitSelectionChange()
}

function selectAllVisible() {
    const next = new Map(selectedMap.value)
    for (const item of items.value) {
        next.set(item.id, selectionPayload(item))
    }
    selectedMap.value = next
    emitSelectionChange()
}

function clearSelection() {
    selectedMap.value = new Map()
    emitSelectionChange()
}

function confirmSelection() {
    emit('confirm-selection', selectedImages.value)
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

/** Unassigned: -1 (null/empty treated as unassigned). Linked: anything else. */
function normalizedAssociatedId(item) {
    const raw = item?.associatedID
    if (raw == null || raw === '') return -1
    const n = Number(raw)
    return Number.isFinite(n) ? n : -1
}

function isLinked(item) {
    return normalizedAssociatedId(item) !== -1
}

function isLinkedToCurrentCase(item) {
    if (!canAssociate.value) return false
    return normalizedAssociatedId(item) === Number(caseAssociatedId.value)
}

function isLinkedToOtherCase(item) {
    return isLinked(item) && !isLinkedToCurrentCase(item)
}

function linkedBadgeTitle(item) {
    const id = normalizedAssociatedId(item)
    if (isLinkedToCurrentCase(item)) return 'この案件に紐づき済'
    return `他案件に紐づき済（orderID: ${id}）`
}

function listUrl(pageNum) {
    const base = `${page.props.appBaseUrl}/servicerecord/camera/images`
    const params = new URLSearchParams()
    params.set('page', String(pageNum))
    params.set('per_page', '48')

    if (
        shouldFilterByAssociated.value
        && caseAssociatedId.value != null
        && caseAssociatedId.value !== ''
    ) {
        params.set('associatedID', String(caseAssociatedId.value))
    }

    if (capturedByFilter.value) {
        params.set('captured_by', capturedByFilter.value)
    }

    if (periodFilter.value && periodFilter.value !== 'custom') {
        params.set('period', periodFilter.value)
    }

    if (dateFrom.value) {
        params.set('date_from', dateFrom.value)
    }
    if (dateTo.value) {
        params.set('date_to', dateTo.value)
    }

    return `${base}?${params.toString()}`
}

async function deleteSelected() {
    if (deletableSelectedCount.value === 0 || deleting.value) return

    const targets = deletableSelectedImages.value
    const skipped = selectedCount.value - targets.length
    const confirmMsg = skipped > 0
        ? `自分の画像 ${targets.length} 件を削除しますか？（本人以外の ${skipped} 件は削除しません）`
        : `選択した ${targets.length} 件の画像を削除しますか？`
    if (!window.confirm(confirmMsg)) return

    deleting.value = true
    associateMessage.value = ''
    associateError.value = false

    try {
        const response = await fetch(`${page.props.appBaseUrl}/servicerecord/camera/delete`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                ids: targets.map((item) => item.id),
            }),
        })

        let data = {}
        try {
            data = await response.json()
        } catch {
            // ignore
        }

        if (!response.ok) {
            throw new Error(data.message || `削除に失敗しました。（HTTP ${response.status}）`)
        }

        associateMessage.value = data.message || '画像を削除しました。'
        if (previewItem.value && targets.some((item) => item.id === previewItem.value.id)) {
            closePreview()
        }
        if (editingItem.value && targets.some((item) => item.id === editingItem.value.id)) {
            closeEditor()
        }
        await reload({ clearSelected: true })
    } catch (e) {
        associateError.value = true
        associateMessage.value = e.message || '削除に失敗しました。'
    } finally {
        deleting.value = false
    }
}

async function associateSelected() {
    if (!canAssociate.value || selectedCount.value === 0 || associating.value) return

    associating.value = true
    associateMessage.value = ''
    associateError.value = false

    try {
        const response = await fetch(`${page.props.appBaseUrl}/servicerecord/camera/associate`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                ids: selectedImages.value.map((item) => item.id),
                associatedID: Number(caseAssociatedId.value),
            }),
        })

        let data = {}
        try {
            data = await response.json()
        } catch {
            // ignore
        }

        if (!response.ok) {
            throw new Error(data.message || `紐づけに失敗しました。（HTTP ${response.status}）`)
        }

        associateMessage.value = data.message || '案件に紐づけました。'
        await reload({ clearSelected: true })
        emit('associated', {
            associatedID: Number(caseAssociatedId.value),
            updated: data.updated ?? 0,
        })
    } catch (e) {
        associateError.value = true
        associateMessage.value = e.message || '紐づけに失敗しました。'
    } finally {
        associating.value = false
    }
}

let fetchSeq = 0
let reloadTimer = null
let pendingReloadOptions = null

async function fetchPage(pageNum, { append = false, clearSelected = false } = {}) {
    const seq = ++fetchSeq
    loading.value = true
    error.value = ''

    try {
        const response = await fetch(listUrl(pageNum), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        })

        // 後から始まった検索があれば、古い結果は捨てる（重複検索の結果競合を防ぐ）
        if (seq !== fetchSeq) return

        let data = {}
        try {
            data = await response.json()
        } catch {
            // ignore
        }

        if (!response.ok) {
            throw new Error(data.message || `画像一覧の取得に失敗しました。（HTTP ${response.status}）`)
        }

        const nextItems = Array.isArray(data.data) ? data.data : []
        items.value = append ? [...items.value, ...nextItems] : nextItems
        total.value = Number(data.total ?? nextItems.length)
        currentPage.value = Number(data.current_page ?? pageNum)
        lastPage.value = Number(data.last_page ?? 1)

        if (Array.isArray(data.captured_by_options)) {
            photographerOptions.value = data.captured_by_options.filter(Boolean)
        }

        if (clearSelected) {
            clearSelection()
        }
    } catch (e) {
        if (seq !== fetchSeq) return
        error.value = e.message || '画像一覧の取得に失敗しました。'
        if (!append) {
            items.value = []
            total.value = 0
            currentPage.value = 0
            lastPage.value = 1
        }
    } finally {
        if (seq === fetchSeq) {
            loading.value = false
        }
    }
}

function reload({ clearSelected = true } = {}) {
    // 連続 watch / mount からの重複 reload をまとめる
    pendingReloadOptions = {
        clearSelected: (pendingReloadOptions?.clearSelected ?? false) || clearSelected,
    }
    if (reloadTimer != null) return Promise.resolve()
    return new Promise((resolve) => {
        reloadTimer = window.setTimeout(async () => {
            const options = pendingReloadOptions || { clearSelected: true }
            pendingReloadOptions = null
            reloadTimer = null
            await fetchPage(1, { append: false, clearSelected: options.clearSelected })
            resolve()
        }, 0)
    })
}

function loadMore() {
    if (!hasMore.value || loading.value) return
    return fetchPage(currentPage.value + 1, { append: true, clearSelected: false })
}

function openPreview(item) {
    previewId.value = item.id
    previewItem.value = item
    emit('select', item)
}

function closePreview() {
    previewItem.value = null
}

function openEditor() {
    if (!previewItem.value) return
    editingItem.value = previewItem.value
}

function closeEditor() {
    editingItem.value = null
}

async function onEditorSaved() {
    editingItem.value = null
    previewItem.value = null
    await reload({ clearSelected: false })
}

function onCustomDateChange() {
    if (suppressDateWatch.value) return
    suppressPeriodWatch.value = true
    periodFilter.value = 'custom'
    queueMicrotask(() => {
        suppressPeriodWatch.value = false
    })
    reload()
}

function toggleLinkedOnlyFilter() {
    if (!canAssociate.value) return

    const next = !linkedOnlyFilter.value
    linkedOnlyFilter.value = next

    if (next) {
        // 紐づけ済画像は撮影日が今日以外のことも多いため、期間を「すべて」に広げる
        if (periodFilter.value !== 'all') {
            periodBeforeLinkedOnly.value = periodFilter.value
            suppressPeriodWatch.value = true
            periodFilter.value = 'all'
            applyPeriodToDates('all')
            queueMicrotask(() => {
                suppressPeriodWatch.value = false
            })
        } else {
            periodBeforeLinkedOnly.value = null
        }
    } else if (periodBeforeLinkedOnly.value) {
        const restore = periodBeforeLinkedOnly.value
        periodBeforeLinkedOnly.value = null
        suppressPeriodWatch.value = true
        periodFilter.value = restore
        applyPeriodToDates(restore)
        queueMicrotask(() => {
            suppressPeriodWatch.value = false
        })
    }

    reload()
}

watch(capturedByFilter, () => {
    reload()
})

watch(periodFilter, (period) => {
    if (suppressPeriodWatch.value) return
    if (period === 'custom') return
    applyPeriodToDates(period)
    reload()
})

watch(
    caseAssociatedId,
    () => {
        reload()
    },
)

onMounted(() => {
    // periodFilter 初期値 today の日付範囲だけ整え、reload は1回に限定
    suppressPeriodWatch.value = true
    applyPeriodToDates(periodFilter.value || 'today')
    queueMicrotask(() => {
        suppressPeriodWatch.value = false
    })
    reload()
})

onBeforeUnmount(() => {
    fetchSeq += 1
    if (reloadTimer != null) {
        clearTimeout(reloadTimer)
        reloadTimer = null
    }
    pendingReloadOptions = null
})

defineExpose({
    reload,
    selectedImages,
    selectedCount,
    clearSelection,
    selectAllVisible,
    getSelectedImages: () => selectedImages.value,
})
</script>

<style scoped>
.gallery {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 0;
    height: 100%;
}

.gallery-toolbar {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.gallery-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.gallery-toolbar-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.selection-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.associate-message {
    margin: 0;
    font-size: 13px;
    color: #166534;
}

.associate-message.error {
    color: #b91c1c;
}

.linked-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    max-width: calc(100% - 40px);
    padding: 2px 6px;
    border-radius: 4px;
    background: #ecfeff;
    color: #0e7490;
    border: 1px solid #67e8f9;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.2;
}

.linked-badge-other {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fdba74;
}

.linked-hint {
    font-size: 9px;
    font-weight: 600;
    opacity: 0.9;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.thumb-card.linked {
    border-color: #22d3ee;
}

.thumb-card.linked-other {
    border-color: #fb923c;
}

.filter-field {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.filter-select,
.filter-input {
    padding: 6px 8px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-weight: 500;
}

.gallery-count,
.gallery-selected-count {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.gallery-selected-count {
    font-weight: 700;
    color: #1e40af;
}

.status-message {
    margin: 0;
    color: #475569;
    font-size: 14px;
}

.status-message.error {
    color: #b91c1c;
}

.thumb-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
    overflow: auto;
    min-height: 0;
    flex: 1;
    padding-right: 2px;
}

.thumb-card {
    position: relative;
    display: flex;
    flex-direction: column;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}

.thumb-card:hover,
.thumb-card.previewed {
    border-color: #2563eb;
    box-shadow: 0 0 0 1px #2563eb;
}

.thumb-card.checked {
    border-color: #1d4ed8;
    box-shadow: 0 0 0 2px #93c5fd;
    background: #eff6ff;
}

.thumb-check {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid #94a3b8;
    cursor: pointer;
}

.thumb-check input {
    width: 16px;
    height: 16px;
    margin: 0;
    cursor: pointer;
}

.thumb-card-body {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    width: 100%;
}

.thumb-image {
    width: 100%;
    aspect-ratio: 1 / 1;
    object-fit: cover;
    border-radius: 4px;
    background: #e2e8f0;
}

.thumb-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 11px;
    color: #64748b;
}

.thumb-meta strong {
    color: #1e293b;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.gallery-more {
    display: flex;
    justify-content: center;
}

.action-btn {
    padding: 8px 12px;
    border: none;
    border-radius: 4px;
    background: #64748b;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.action-btn-primary {
    background: #2563eb;
}

.action-btn-secondary {
    background: #475569;
}

.action-btn-danger {
    background: #dc2626;
}

.action-btn-danger:hover:not(:disabled) {
    background: #b91c1c;
}

.filter-toggle-btn {
    background: #fff;
    color: #0e7490;
    border: 1px solid #67e8f9;
}

.filter-toggle-btn:hover:not(:disabled) {
    background: #ecfeff;
}

.filter-toggle-btn.active {
    background: #0891b2;
    border-color: #0891b2;
    color: #fff;
}

.preview-overlay {
    position: fixed;
    inset: 0;
    z-index: 300;
    background: rgba(15, 23, 42, 0.72);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.preview-panel {
    width: min(96vw, 1100px);
    max-height: 94vh;
    background: #0f172a;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 16px;
    color: #fff;
}

.preview-header h3 {
    margin: 0 0 4px;
    font-size: 16px;
}

.preview-header p {
    margin: 0;
    font-size: 12px;
    color: #cbd5e1;
}

.preview-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.close-btn {
    background: none;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
}

.preview-body {
    flex: 1;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 16px 16px;
    overflow: auto;
}

.preview-image {
    max-width: 100%;
    max-height: calc(94vh - 90px);
    object-fit: contain;
    background: #1e293b;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
