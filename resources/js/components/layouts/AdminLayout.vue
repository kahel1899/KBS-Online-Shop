<template>
    <div>
        <v-navigation-drawer v-model="drawer" width="248" class="kbs-drawer">
            <div class="kbs-brand">
                <span class="kbs-brand__name">Kooku</span>
                <span class="kbs-brand__sub">Admin panel</span>
            </div>

            <v-list nav density="comfortable" class="px-3">
                <v-list-item
                    v-for="item in items"
                    :key="item.path"
                    :to="item.path"
                    :prepend-icon="item.icon"
                    :title="item.title"
                    color="primary"
                    rounded="lg"
                >
                    <template v-if="item.badge" #append>
                        <v-chip v-if="badgeValue(item.badge)" size="x-small" color="primary" variant="flat">
                            {{ badgeValue(item.badge) }}
                        </v-chip>
                    </template>
                </v-list-item>
            </v-list>

            <template #append>
                <v-list nav density="comfortable" class="px-3 pb-3">
                    <v-list-item
                        to="/"
                        prepend-icon="mdi-storefront-outline"
                        title="View shop"
                        rounded="lg"
                    />
                    <v-list-item
                        prepend-icon="mdi-logout"
                        title="Log out"
                        rounded="lg"
                        @click="logout"
                    />
                </v-list>
            </template>
        </v-navigation-drawer>

        <v-app-bar flat border="b" color="surface" height="56">
            <v-app-bar-nav-icon
                aria-label="Toggle menu"
                @click.stop="drawer = !drawer"
            />
            <v-toolbar-title class="kbs-bar-title">
                {{ $route.name }}
            </v-toolbar-title>
        </v-app-bar>

        <v-main>
            <router-view></router-view>
        </v-main>
    </div>
</template>

<script>
import axios from 'axios'
import { API } from '../../utils.js'
import { useAuthStore } from '../../stores/auth.js'

export default {
    data: () => ({
        // null = open on desktop, closed on mobile (Vuetify decides)
        drawer: null,
        summary: { pending_orders: 0, to_verify: 0, low_stock: 0 },
        timer: null,
        items: [
            { title: 'Dashboard', path: '/admin/dashboard', icon: 'mdi-view-dashboard-outline' },
            { title: 'Orders', path: '/admin/orders', icon: 'mdi-receipt-text-outline', badge: 'orders' },
            { title: 'Inventory', path: '/admin/inventory', icon: 'mdi-archive-outline', badge: 'stock' },
            { title: 'Categories', path: '/admin/categories', icon: 'mdi-shape-outline' },
        ],
    }),

    mounted() {
        this.loadSummary()
        // keep the order counter fresh while the admin leaves the page open
        this.timer = setInterval(this.loadSummary, 60000)
        window.addEventListener('kbs-summary', this.loadSummary)
    },

    beforeUnmount() {
        clearInterval(this.timer)
        window.removeEventListener('kbs-summary', this.loadSummary)
    },

    watch: {
        '$route.path'() {
            this.loadSummary()
        },
    },

    methods: {
        async loadSummary() {
            try {
                const { data } = await axios.get(`${API}/admin/summary`)
                this.summary = data
            } catch (e) { /* the counters are optional */ }
        },

        badgeValue(kind) {
            if (kind === 'orders') return this.summary.pending_orders + this.summary.to_verify
            if (kind === 'stock') return this.summary.low_stock
            return 0
        },

        async logout() {
            await useAuthStore().logout()
            this.$router.push('/')
        },
    },
}
</script>

<style scoped>
.kbs-brand {
    padding: 22px 24px 14px;
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}

.kbs-brand__name {
    font-family: var(--kbs-font-display);
    font-weight: 700;
    font-size: 28px;
    color: var(--kbs-brand);
    letter-spacing: -0.02em;
}

.kbs-brand__sub {
    font-size: 13px;
    color: var(--kbs-muted);
    margin-top: 2px;
}

.kbs-bar-title {
    font-family: var(--kbs-font-display);
    font-weight: 600;
    font-size: 18px;
}
</style>
