<template>
    <span class="chip" :style="{ color: meta.color, background: meta.bg }">{{ meta.label }}</span>
</template>

<script>
import { ORDER_STATUS, PAYMENT_STATUS } from '../utils.js'

export default {
    name: 'StatusChip',

    props: {
        kind: { type: String, default: 'order' }, // 'order' or 'payment'
        value: { type: String, default: '' },
    },

    computed: {
        meta() {
            const table = this.kind === 'payment' ? PAYMENT_STATUS : ORDER_STATUS
            return table[this.value] || { label: this.value, color: '#2B1B26', bg: '#F3E8EE' }
        },
    },
}
</script>

<style scoped>
.chip {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 700;
    white-space: nowrap;
}
</style>
