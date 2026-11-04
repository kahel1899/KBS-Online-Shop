<template>
    <div ref="root" class="ds">
        <div v-if="label" class="ds-label">{{ label }}</div>

        <button
            type="button"
            class="ds-btn"
            :class="{ 'is-open': open }"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="open = !open"
            @keydown.esc="open = false"
        >
            <span :class="selected ? 'ds-value' : 'ds-placeholder'">
                {{ selected ? selected.title : placeholder }}
            </span>
            <v-icon size="22">{{ open ? 'mdi-menu-up' : 'mdi-menu-down' }}</v-icon>
        </button>

        <!-- The list opens in the page flow right under the button, so it can
             never end up in a different part of the screen. -->
        <ul v-if="open" class="ds-list" role="listbox" :aria-label="label">
            <li v-for="item in items" :key="item.value" role="presentation">
                <button
                    type="button"
                    role="option"
                    class="ds-option"
                    :class="{ 'is-selected': item.value === modelValue }"
                    :aria-selected="item.value === modelValue"
                    :disabled="item.disabled"
                    @click="choose(item)"
                    @keydown.esc="open = false"
                >
                    <span>{{ item.title }}</span>
                    <small v-if="item.subtitle">{{ item.subtitle }}</small>
                </button>
            </li>
            <li v-if="!items.length" class="ds-empty">Nothing to choose yet</li>
        </ul>

        <div v-if="hint" class="ds-hint">{{ hint }}</div>
    </div>
</template>

<script>
export default {
    name: 'DropSelect',

    props: {
        modelValue: { default: null },
        items: { type: Array, default: () => [] }, // { value, title, subtitle?, disabled? }
        label: { type: String, default: '' },
        placeholder: { type: String, default: 'Select' },
        hint: { type: String, default: '' },
    },

    emits: ['update:modelValue'],

    data() {
        return { open: false }
    },

    computed: {
        selected() {
            return this.items.find(i => i.value === this.modelValue) || null
        },
    },

    mounted() {
        document.addEventListener('click', this.onOutside)
    },

    beforeUnmount() {
        document.removeEventListener('click', this.onOutside)
    },

    methods: {
        choose(item) {
            if (item.disabled) return
            this.$emit('update:modelValue', item.value)
            this.open = false
        },

        onOutside(event) {
            if (this.open && this.$refs.root && !this.$refs.root.contains(event.target)) {
                this.open = false
            }
        },
    },
}
</script>

<style scoped>
.ds {
    width: 100%;
}

.ds-label {
    font-size: 13px;
    color: var(--kbs-muted);
    margin-bottom: 6px;
}

.ds-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    height: 48px;
    padding: 0 12px 0 14px;
    border: 1px solid rgba(43, 27, 38, 0.38);
    border-radius: 8px;
    background: #fff;
    font: inherit;
    font-size: 16px;
    color: var(--kbs-ink);
    text-align: left;
    cursor: pointer;
}

.ds-btn:hover {
    border-color: var(--kbs-ink);
}

.ds-btn:focus-visible,
.ds-btn.is-open {
    outline: 2px solid var(--kbs-brand);
    outline-offset: -1px;
    border-color: var(--kbs-brand);
}

.ds-placeholder {
    color: var(--kbs-muted);
}

.ds-list {
    list-style: none;
    margin: 6px 0 0;
    padding: 4px;
    max-height: 220px;
    overflow-y: auto;
    border: 1px solid var(--kbs-line);
    border-radius: 12px;
    background: #fff;
}

.ds-option {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
    padding: 8px 12px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    font: inherit;
    color: var(--kbs-ink);
    text-align: left;
    cursor: pointer;
}

.ds-option small {
    color: var(--kbs-muted);
    font-size: 12px;
}

.ds-option:hover:not(:disabled),
.ds-option:focus-visible {
    background: var(--kbs-paper);
    outline: none;
}

.ds-option.is-selected {
    background: #ffeaf4;
    font-weight: 600;
}

.ds-option:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ds-empty {
    padding: 10px 12px;
    color: var(--kbs-muted);
}

.ds-hint {
    margin-top: 4px;
    padding: 0 2px;
    font-size: 12px;
    color: var(--kbs-muted);
}
</style>
