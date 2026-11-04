<template>
    <div>
        <div v-if="loading" class="muted">Loading your orders…</div>

        <v-alert v-else-if="error" type="error" variant="tonal">
            {{ error }}
            <template #append><v-btn variant="text" @click="load">Try again</v-btn></template>
        </v-alert>

        <div v-else-if="!orders.length" class="empty">
            <v-icon size="40" color="secondary">mdi-package-variant-closed</v-icon>
            <p class="empty__title">No orders yet</p>
            <p class="muted">When you place an order it will show up here.</p>
            <v-btn to="/shop" color="primary" variant="flat" class="mt-3">Start shopping</v-btn>
        </div>

        <ul v-else class="list">
            <li v-for="o in orders" :key="o.id">
                <button type="button" class="row" @click="open(o)">
                    <span class="row__main">
                        <strong>{{ o.order_number }}</strong>
                        <small>{{ formatDate(o.created_at) }}, {{ count(o) }}</small>
                    </span>
                    <span class="row__chips">
                        <StatusChip kind="order" :value="o.status" />
                        <StatusChip kind="payment" :value="o.payment_status" />
                    </span>
                    <strong class="row__total">{{ peso(o.total) }}</strong>
                    <v-icon size="20" class="row__go">mdi-chevron-right</v-icon>
                </button>
            </li>
        </ul>

        <v-dialog v-model="dialog" max-width="640" scrollable>
            <v-card v-if="current" class="dlg">
                <v-card-title class="dlg__title">
                    <span>Order details</span>
                    <v-btn icon variant="text" size="small" aria-label="Close" @click="dialog = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-card-text>
                    <v-alert v-if="message" type="success" variant="tonal" density="compact" class="mb-3">{{ message }}</v-alert>
                    <v-alert v-if="actionError" type="error" variant="tonal" density="compact" class="mb-3">{{ actionError }}</v-alert>
                    <OrderDetail
                        :order="current"
                        :settings="shop.settings"
                        :busy="busy"
                        @pay="pay"
                        @cancel="cancelOrder"
                    />
                </v-card-text>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios'
import OrderDetail from '../OrderDetail.vue'
import StatusChip from '../StatusChip.vue'
import { useShopStore } from '../../stores/shop.js'
import { API, apiError, formatDate, peso } from '../../utils.js'

export default {
    components: { OrderDetail, StatusChip },

    data() {
        return {
            shop: useShopStore(),
            orders: [],
            loading: true,
            error: '',
            dialog: false,
            current: null,
            busy: false,
            message: '',
            actionError: '',
        }
    },

    mounted() {
        this.shop.load()
        this.load()
    },

    methods: {
        peso,
        formatDate,

        count(order) {
            const n = order.items.reduce((sum, i) => sum + i.quantity, 0)
            return `${n} ${n === 1 ? 'item' : 'items'}`
        },

        async load() {
            this.loading = true
            this.error = ''
            try {
                const { data } = await axios.get(`${API}/account/orders`)
                this.orders = data
            } catch (e) {
                this.error = apiError(e)
            } finally {
                this.loading = false
            }
        },

        open(order) {
            this.current = order
            this.message = ''
            this.actionError = ''
            this.dialog = true
        },

        replace(order) {
            this.current = order
            const i = this.orders.findIndex(o => o.id === order.id)
            if (i !== -1) this.orders.splice(i, 1, order)
        },

        async pay(reference) {
            await this.act(`${API}/shop/orders/pay`, { order_number: this.current.order_number, reference_number: reference })
        },

        async cancelOrder() {
            await this.act(`${API}/account/orders/${this.current.id}/cancel`, {})
        },

        async act(url, body) {
            this.busy = true
            this.message = ''
            this.actionError = ''
            try {
                const { data } = await axios.post(url, body)
                this.replace(data.order)
                this.message = data.message
            } catch (e) {
                this.actionError = apiError(e)
            } finally {
                this.busy = false
            }
        },
    },
}
</script>

<style scoped>
.muted {
    color: var(--kbs-muted);
}

.empty {
    padding: 48px 24px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.empty__title {
    margin: 8px 0 2px;
    font-weight: 700;
}

.list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.row {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto 20px;
    gap: 14px;
    align-items: center;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 14px;
    font: inherit;
    color: var(--kbs-ink);
    text-align: left;
    cursor: pointer;
}

.row:hover {
    border-color: var(--kbs-brand);
}

.row:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: 2px;
}

.row__main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.row__main small {
    color: var(--kbs-muted);
}

.row__chips {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.row__total {
    font-variant-numeric: tabular-nums;
}

.row__go {
    color: var(--kbs-muted);
}

.dlg {
    border-radius: 16px;
}

.dlg__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: var(--kbs-font-display);
    font-weight: 600;
    padding: 16px 20px 4px;
}

@media (max-width: 640px) {
    .row {
        grid-template-columns: minmax(0, 1fr) auto 20px;
    }

    .row__chips {
        grid-column: 1 / span 3;
        grid-row: 2;
        justify-content: flex-start;
    }
}
</style>
