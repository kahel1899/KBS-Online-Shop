<template>
    <div class="shop-wrap ac">
        <header class="ac__head">
            <h1 class="kbs-h2">Hi, {{ auth.firstName }}</h1>
            <p class="kbs-muted">{{ auth.user.email }}</p>
        </header>

        <div class="ac__tabs" role="tablist" aria-label="Account sections">
            <button
                v-for="t in tabs"
                :id="`tab-${t.key}`"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                :class="{ 'is-on': tab === t.key }"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </div>

        <section role="tabpanel" :aria-labelledby="`tab-${tab}`" class="ac__panel">
            <OrdersTab v-if="tab === 'orders'" />
            <AddressesTab v-else-if="tab === 'addresses'" />
            <ProfileTab v-else />
        </section>
    </div>
</template>

<script>
import OrdersTab from '../../account/OrdersTab.vue'
import AddressesTab from '../../account/AddressesTab.vue'
import ProfileTab from '../../account/ProfileTab.vue'
import { useAuthStore } from '../../../stores/auth.js'

export default {
    components: { OrdersTab, AddressesTab, ProfileTab },

    data() {
        return {
            auth: useAuthStore(),
            tab: 'orders',
            tabs: [
                { key: 'orders', label: 'My orders' },
                { key: 'addresses', label: 'Addresses' },
                { key: 'profile', label: 'Profile' },
            ],
        }
    },
}
</script>

<style scoped>
.ac {
    padding-top: 28px;
    max-width: 900px;
}

.ac__head p {
    margin: 2px 0 0;
}

.ac__tabs {
    display: flex;
    gap: 6px;
    margin: 20px 0 16px;
    padding: 4px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 999px;
    width: fit-content;
    max-width: 100%;
    overflow-x: auto;
}

.ac__tabs button {
    padding: 9px 18px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    font: inherit;
    font-weight: 600;
    color: var(--kbs-muted);
    cursor: pointer;
    white-space: nowrap;
}

.ac__tabs button.is-on {
    background: var(--kbs-brand);
    color: #fff;
}

.ac__tabs button:focus-visible {
    outline: 2px solid var(--kbs-ink);
    outline-offset: 1px;
}
</style>
