<template>
    <div class="notes-table-root">
        <div v-if="sortedNotes.length" class="attachment-table-wrap notes-table-wrap">
            <table class="data-table notes-table" :style="tableStyleVars">
                <colgroup>
                    <col class="col-note-date-col">
                    <col class="col-note-author-col">
                    <col v-if="showConfirmStatus" class="col-note-confirm-col">
                    <col class="col-note-body-col">
                </colgroup>
                <thead>
                    <tr>
                        <th class="col-note-date">日時</th>
                        <th class="col-note-author">記入者</th>
                        <th v-if="showConfirmStatus" class="col-note-confirm"></th>
                        <th class="col-note-body">内容</th>
                    </tr>
                </thead>
                <tbody>
                        <tr
                        v-for="note in sortedNotes"
                        :key="note.id"
                        class="table-row"
                    :class="{
                        'important-row': isImportantNote(note),
                        'active-row': Number(selectedId) === Number(note.id),
                    }"
                        @click="selectNote(note.id)"
                        @dblclick="onNoteDblclick(note)"
                    >
                        <td class="col-note-date">{{ formatDate(note.whenWrote) }}</td>
                        <td class="col-note-author">{{ note.whoWrote || '—' }}</td>
                        <td v-if="showConfirmStatus" class="col-note-confirm">
                            <span
                                v-if="confirmStatusLabel(note)"
                                class="note-confirm-label"
                                :class="confirmStatusClass(note)"
                            >
                                {{ confirmStatusLabel(note) }}
                            </span>
                        </td>
                        <td
                            class="text-cell col-note-body"
                            @click.stop="selectNote(note.id)"
                            @dblclick.stop="onNoteDblclick(note)"
                            v-html="linkifyNote(displayNoteText(note))"
                        />
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="empty-message">{{ emptyMessage }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { linkifyText } from '@/utils/linkifyText'

const props = defineProps({
    notes: {
        type: Array,
        default: () => [],
    },
    selectedId: {
        type: [Number, String, null],
        default: null,
    },
    recordOrderId: {
        type: [Number, String, null],
        default: null,
    },
    emptyMessage: {
        type: String,
        default: 'Notes がありません。',
    },
    dateColumnWidth: {
        type: [Number, String, null],
        default: null,
    },
    authorColumnWidth: {
        type: [Number, String, null],
        default: null,
    },
    tableFontSize: {
        type: [Number, String, null],
        default: null,
    },
    showConfirmStatus: {
        type: Boolean,
        default: false,
    },
    currentUserName: {
        type: String,
        default: '',
    },
    allowEdit: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['update:selectedId', 'select', 'edit'])

function noteWroteTime(note) {
    const when = note?.whenWrote
    if (!when) return 0
    const time = new Date(when).getTime()
    return Number.isNaN(time) ? 0 : time
}

const sortedNotes = computed(() =>
    [...(props.notes ?? [])].sort((a, b) => {
        const diff = noteWroteTime(a) - noteWroteTime(b)
        if (diff !== 0) return diff
        return Number(a?.id ?? 0) - Number(b?.id ?? 0)
    }),
)

const tableStyleVars = computed(() => {
    const dateWidth = normalizeCssWidth(props.dateColumnWidth)
    const authorWidth = normalizeCssWidth(props.authorColumnWidth)
    const fontSize = normalizeCssWidth(props.tableFontSize)

    return {
        '--notes-date-col-width': dateWidth || '134px',
        '--notes-author-col-width': authorWidth || '66px',
        '--notes-confirm-col-width': props.showConfirmStatus ? '72px' : '0px',
        '--notes-font-size': fontSize || '12px',
    }
})

function selectNote(id) {
    emit('update:selectedId', id)
    emit('select', id)
}

function isNoteOwner(note) {
    if (!note) return false
    const who = String(note.whoWrote ?? '').trim()
    if (!who) return false
    if (note.is_mine === true || note.is_mine === 1 || note.is_mine === '1') {
        return true
    }
    const me = String(props.currentUserName ?? '').trim()
    return me !== '' && me === who
}

function onNoteDblclick(note) {
    selectNote(note?.id)
    if (!props.allowEdit) return
    if (!isNoteOwner(note)) {
        window.alert(
            `自分が書いた Note のみ編集できます。\nログイン: ${String(props.currentUserName || '').trim() || '不明'}\n記入者: ${note?.whoWrote || '不明'}`,
        )
        return
    }
    emit('edit', note)
}

function isTruthyFlag(value) {
    return value === true || value === 1 || value === '1'
}

function confirmStatusLabel(note) {
    const tbc = isTruthyFlag(note?.tbc)
    const done = isTruthyFlag(note?.done)
    if (tbc && done) return '確認済'
    if (tbc) return '要確認'
    return ''
}

function confirmStatusClass(note) {
    const label = confirmStatusLabel(note)
    if (label === '確認済') return 'is-done'
    if (label === '要確認') return 'is-tbc'
    return ''
}

function isImportantNote(note) {
    return note?.important === true
        || note?.important === 1
        || note?.important === '1'
}

function isLoanerSourceNote(note) {
    const recordOrderId = Number(props.recordOrderId)
    if (!Number.isFinite(recordOrderId) || recordOrderId <= 0) {
        return note?.note_source === 'loaner'
    }

    return note?.note_source === 'loaner'
        || (
            Number(note?.associatedID) > 0
            && Number(note?.associatedID) !== recordOrderId
            && Number(note?.source_orderID) !== recordOrderId
        )
}

function displayNoteText(note) {
    const body = String(note?.note ?? '')
    if (!isLoanerSourceNote(note)) return body
    if (body.startsWith('[貸]')) return body
    return `[貸]${body}`
}

function linkifyNote(value) {
    const html = linkifyText(value)
    return html || '—'
}

function formatDate(value) {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    const hh = String(date.getHours()).padStart(2, '0')
    const mm = String(date.getMinutes()).padStart(2, '0')
    return `${y}-${m}-${d} ${hh}:${mm}`
}

function normalizeCssWidth(value) {
    if (value === null || value === undefined || value === '') return ''
    return typeof value === 'number' ? `${value}px` : String(value)
}
</script>

<style scoped>
.notes-table-root {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.notes-table-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    border: 1px solid #94a3b8;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
}

.data-table thead th {
    background: #e2e8f0;
    color: #000;
    font-weight: 700;
}

.table-row {
    cursor: pointer;
}

.table-row:hover td {
    background: #dbeafe;
}

.active-row td {
    color: #fff !important;
    background: #7e25eb !important;
}

.table-row.active-row:hover td {
    background: #7e25eb !important;
}

.text-cell {
    white-space: pre-wrap;
    word-break: break-word;
}

:deep(.note-autolink) {
    color: #1d4ed8;
    text-decoration: underline;
    word-break: break-all;
}

:deep(.active-row .note-autolink) {
    color: #fff;
}

.notes-table {
    table-layout: fixed;
    background: #fff;
}

.notes-table th,
.notes-table td {
    font-weight: 700;
    font-size: var(--notes-font-size, 12px);
}

.notes-table tbody td {
    background: #fff;
}

.notes-table tbody tr.important-row:not(.active-row) td {
    background: #fef3c7;
}

.notes-table tbody tr.important-row:not(.active-row):hover td {
    background: #fde68a;
}

.notes-table .col-note-date-col,
.notes-table .col-note-date {
    width: var(--notes-date-col-width, 134px);
    min-width: var(--notes-date-col-width, 134px);
    max-width: var(--notes-date-col-width, 134px);
    box-sizing: border-box;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notes-table .col-note-author-col,
.notes-table .col-note-author {
    width: var(--notes-author-col-width, 66px);
    min-width: var(--notes-author-col-width, 66px);
    max-width: var(--notes-author-col-width, 66px);
    box-sizing: border-box;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notes-table .col-note-confirm-col,
.notes-table .col-note-confirm {
    width: var(--notes-confirm-col-width, 72px);
    min-width: var(--notes-confirm-col-width, 72px);
    max-width: var(--notes-confirm-col-width, 72px);
    box-sizing: border-box;
    text-align: center;
    vertical-align: middle;
    padding-left: 4px;
    padding-right: 4px;
}

.note-confirm-label {
    display: inline-block;
    font-weight: 700;
    white-space: nowrap;
}

.note-confirm-label.is-tbc {
    color: #dc2626;
}

.note-confirm-label.is-done {
    color: #1bc25b;
}

.notes-table tbody tr.active-row .note-confirm-label.is-tbc {
    color: #fca5a5 !important;
}

.notes-table tbody tr.active-row .note-confirm-label.is-done {
    color: #00ff5e !important;
}

.notes-table .col-note-body {
    width: auto;
}

.empty-message {
    margin: 0;
    color: #64748b;
}
</style>
