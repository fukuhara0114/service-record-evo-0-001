<template>
    <a
        :href="resolvedUrl"
        class="close-to-home-btn"
        aria-label="閉じる"
        title="閉じる"
    >
        <span aria-hidden="true">×</span>
    </a>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    href: {
        type: String,
        default: '',
    },
})

const page = usePage()

const resolvedUrl = computed(() => {
    if (props.href) return props.href
    return page.props.homeUrl ?? `${page.props.appBaseUrl}/home`
})
</script>

<style scoped>
.close-to-home-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    width: 36px;
    height: 36px;
    padding: 0;
    margin: 0;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    color: #0f172a;
    text-decoration: none;
    font-size: 22px;
    font-weight: 700;
    line-height: 0;
    text-align: center;
    cursor: pointer;
    flex: 0 0 auto;
}

.close-to-home-btn > span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1em;
    height: 1em;
    line-height: 1;
    /* × グリフの視覚重心をボタン中央へ合わせる */
    transform: translate(-0.02em, -0.06em);
}

.close-to-home-btn:hover {
    background: #f8fafc;
    border-color: #64748b;
}
</style>
