<template>
    <div class="notes-table-root">
        <div v-if="notes.length" class="attachment-table-wrap notes-table-wrap">
            <table class="data-table notes-table" :style="tableStyleVars">
                <colgroup>
                    <col class="col-note-date-col">
                    <col class="col-note-author-col">
                    <col class="col-note-body-col">
                </colgroup>
                <thead>
                    <tr>
                        <th class="col-note-date">日時</th>
                        <th class="col-note-author">記入者</th>
                        <th class="col-note-body">内容</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="note in notes"
                        :key="note.id"
                        class="table-row"
                    :class="{
                        'important-row': isImportantNote(note),
                        'active-row': Number(selectedId) === Number(note.id),
                    }"
                        @click="selectNote(note.id)"
                    >
                        <td class="col-note-date">{{ formatDate(note.whenWrote) }}</td>
                        <td class="col-note-author">{{ note.whoWrote || '—' }}</td>
                        <td
                            class="text-cell col-note-body"
                            @click.stop="selectNote(note.id)"
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
})

const emit = defineEmits(['update:selectedId', 'select'])

const tableStyleVars = computed(() => {
    const dateWidth = normalizeCssWidth(props.dateColumnWidth)
    const authorWidth = normalizeCssWidth(props.authorColumnWidth)
    const fontSize = normalizeCssWidth(props.tableFontSize)

    return {
        '--notes-date-col-width': dateWidth || '134px',
        '--notes-author-col-width': authorWidth || '66px',
        '--notes-font-size': fontSize || '12px',
    }
})

function selectNote(id) {
    emit('update:selectedId', id)
    emit('select', id)
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

.notes-table .col-note-body {
    width: auto;
}

.empty-message {
    margin: 0;
    color: #64748b;
}
</style>
