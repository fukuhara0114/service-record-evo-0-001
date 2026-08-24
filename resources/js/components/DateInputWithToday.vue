<template>
    <span class="date-input-with-today" :class="mergedWrapperClass">
        <input
            type="date"
            v-bind="inputAttrs"
            :value="normalizedValue"
            :class="mergedInputClass"
            :disabled="disabled"
            @input="onInput"
            @change="onChange"
        >
        <button
            type="button"
            class="date-today-btn"
            :disabled="disabled"
            title="今日の日付を入れる"
            aria-label="今日の日付を入れる"
            @click="setToday"
        />
    </span>
</template>

<script setup>
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    inputClass: {
        type: [String, Object, Array],
        default: '',
    },
    wrapperClass: {
        type: [String, Object, Array],
        default: '',
    },
})

const emit = defineEmits(['update:modelValue', 'input', 'change'])

const attrs = useAttrs()

const inputAttrs = computed(() => {
    const { class: _class, ...rest } = attrs
    return rest
})

const mergedWrapperClass = computed(() => props.wrapperClass)
const mergedInputClass = computed(() => [props.inputClass, attrs.class])

const normalizedValue = computed(() => {
    const value = props.modelValue
    if (value == null || value === '') return ''
    return String(value).slice(0, 10)
})

function todayYmd() {
    const d = new Date()
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function commit(value, originalEvent = null) {
    emit('update:modelValue', value)
    emit('input', originalEvent ?? { target: { value } })
    emit('change', originalEvent ?? { target: { value } })
}

function onInput(event) {
    commit(event.target.value, event)
}

function onChange(event) {
    emit('change', event)
}

function setToday() {
    if (props.disabled) return
    commit(todayYmd())
}
</script>

<style scoped>
.date-input-with-today {
    display: inline-flex;
    align-items: stretch;
    gap: 2px;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    vertical-align: middle;
    box-sizing: border-box;
}

.date-input-with-today > input[type="date"] {
    flex: 1 1 auto;
    min-width: 0;
    box-sizing: border-box;
    background-color: #fff;
    background-image: none;
    color: #1e293b;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    color-scheme: light;
}

.date-input-with-today > input[type="date"]:disabled {
    background-color: #f1f5f9;
    color: #64748b;
}

.date-input-with-today > input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0.7;
}

.date-today-btn {
    box-sizing: border-box;
    flex: 0 0 auto;
    align-self: stretch;
    width: auto;
    aspect-ratio: 1 / 1;
    min-width: 22px;
    min-height: 22px;
    padding: 0;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    background: #94a3b8;
    cursor: pointer;
}

.date-today-btn:hover:not(:disabled) {
    background: #64748b;
    border-color: #64748b;
}

.date-today-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
</style>
