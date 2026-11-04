<template>
    <div class="ao">
        <v-container fluid class="ao__wrap">
            <header class="ao__head">
                <div>
                    <h1 class="ao__title">Orders</h1>
                    <p class="ao__lede" aria-live="polite">{{ lede }}</p>
                </div>
            </header>

            <!-- Filters -->
            <div class="ao__filters">
                <div class="ao__status" role="group" aria-label="Filter by status">
                    <button
                        v-for="s in statusFilters"
                        :key="s.value"
                        type="button"
                        :class="{ 'is-on': status === s.value }"
                        :aria-pressed="status === s.value"
                        @click="setStatus(s.value)"
                    >
                        {{ s.label }}
                    </button>
                </div>

                <div class="ao__row2">
                    <label class="ao__search">
                        <v-icon size="20">mdi-magnify</v-icon>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search order no., name or phone"
                            aria-label="Search orders"
                            @input="debounced"
                        />
                    </label>

                    <select v-model="payment" class="kbs-select" aria-label="Filter by payment" @change="resetAndLoad">
                        <option value="">All payments</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="pending_verification">Checking payment</option>
                        <option value="paid">Paid</option>
                        <option value="rejected">Payment rejected</option>
                        <option value="refund_due">Refund due</option>
                    </select>
                </div>
            </div>

            <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
                {{ error }}
                <template #append><v-btn variant="text" @click="load">Try again</v-btn></template>
            </v-alert>

            <!-- List -->
            <div class="kbs-panel ao__panel">
                <div v-if="loading && !orders.length" class="ao__empty">Loading orders…</div>

                <div v-else-if="!orders.length" class="ao__empty">
                    <v-icon size="40" color="secondary">mdi-receipt-text-outline</v-icon>
                    <p class="ao__empty-title">No orders found</p>
                    <p>{{ hasFilters ? 'Try different filters.' : 'New orders from the shop will appear here.' }}</p>
                </div>

                <div v-else class="ao__scroll">
                    <table class="ao__table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th class="is-num">Items</th>
                                <th class="is-num">Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="o in orders"
                                :key="o.id"
                                tabindex="0"
                                :aria-label="`Open order ${o.order_number}`"
                                @click="open(o)"
                                @keydown.enter="open(o)"
                            >
                                <td>
                                    <strong>{{ o.order_number }}</strong>
                                    <small>{{ formatDate(o.created_at) }}</small>
                                </td>
                                <td>
                                    {{ o.customer.name }}
                                    <small>{{ o.customer.phone }}</small>
                                </td>
                                <td class="is-num">{{ itemCount(o) }}</td>
                                <td class="is-num"><strong>{{ peso(o.total) }}</strong></td>
                                <td>
                                    <StatusChip kind="payment" :value="o.payment_status" />
                                    <small>{{ methodLabel(o.payment_method) }}</small>
                                </td>
                                <td><StatusChip kind="order" :value="o.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <v-pagination
                v-if="meta.last_page > 1"
                :model-value="meta.current_page"
                :length="meta.last_page"
                :total-visible="5"
                rounded="circle"
                active-color="primary"
                class="mt-6"
                @update:model-value="goToPage"
            />
        </v-container>

        <!-- Detail -->
        <v-dialog v-model="dialog" max-width="720" scrollable>
            <v-card v-if="current" class="ao__dlg">
                <v-card-title class="ao__dlg-title">
                    <span>Order {{ current.order_number }}</span>
                    <v-btn icon variant="text" size="small" aria-label="Close" @click="dialog = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>

                <v-card-text>
                    <v-alert v-if="actionError" type="error" variant="tonal" density="compact" class="mb-3">
                        {{ actionError }}
                    </v-alert>

                    <!-- Admin actions first: this is what needs doing -->
                    <section v-if="hasActions" class="ao__actions">
                        <div v-if="reviewable" class="ao__review">
                            <h3>Check the GCash payment</h3>
                            <p>
                                Reference number
                                <strong>{{ reviewable.reference_number }}</strong>
                                for <strong>{{ peso(reviewable.amount) }}</strong>.
                                Match it with your GCash transactions before accepting.
                            </p>
                            <v-text-field
                                v-model="note"
                                label="Note to customer (optional)"
                                variant="outlined"
                                density="comfortable"
                                hide-details
                                class="mb-3"
                            />
                            <div class="ao__btns">
                                <v-btn color="success" variant="flat" :loading="busy === 'verify'" :disabled="!!busy" @click="review('verify')">
                                    Payment received
                                </v-btn>
                                <v-btn color="error" variant="outlined" :loading="busy === 'reject'" :disabled="!!busy" @click="review('reject')">
                                    Reject
                                </v-btn>
                            </div>
                        </div>

                        <p v-else-if="waitingForReference" class="ao__wait">
                            Waiting for the customer to send their GCash reference number.
                        </p>

                        <div v-if="nextStatus" class="ao__btns">
                            <v-btn color="primary" variant="flat" :loading="busy === nextStatus.value" :disabled="!!busy || blockedByPayment" @click="setOrderStatus(nextStatus.value)">
                                {{ nextStatus.label }}
                            </v-btn>
                            <span v-if="blockedByPayment" class="ao__hint">Verify the payment first.</span>
                        </div>

                        <div v-if="canCancel" class="ao__cancel">
                            <v-btn v-if="!confirmCancel" variant="text" color="error" :disabled="!!busy" @click="confirmCancel = true">
                                Cancel this order
                            </v-btn>
                            <template v-else>
                                <span>Cancel and return the items to stock?</span>
                                <v-btn color="error" variant="flat" :loading="busy === 'cancelled'" :disabled="!!busy" @click="setOrderStatus('cancelled')">
                                    Yes, cancel
                                </v-btn>
                                <v-btn variant="text" @click="confirmCancel = false">No</v-btn>
                            </template>
                        </div>
                    </section>

                    <OrderDetail :order="current" :settings="{}" admin />
                </v-card-text>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios'
import OrderDetail from '../../OrderDetail.vue'
import StatusChip from '../../StatusChip.vue'
import { API, PAYMENT_METHOD, apiError, formatDate, peso } from '../../../utils.js'

const NEXT = {
    pending: { value: 'confirmed', label: 'Confirm order' },
    confirmed: { value: 'shipped', label: 'Mark as shipped' },
    shipped: { value: 'completed', label: 'Mark as completed' },
}

export default {
    components: { OrderDetail, StatusChip },

    data() {
        return {
            orders: [],
            meta: { current_page: 1, last_page: 1, total: 0 },
            summary: { pending_orders: 0, to_verify: 0 },
            loading: true,
            error: '',
            status: '',
            payment: '',
            search: '',
            page: 1,
            timer: null,

            dialog: false,
            current: null,
            busy: '',
            actionError: '',
            note: '',
            confirmCancel: false,

            statusFilters: [
                { value: '', label: 'All' },
                { value: 'pending', label: 'Pending' },
                { value: 'confirmed', label: 'Confirmed' },
                { value: 'shipped', label: 'Shipped' },
                { value: 'completed', label: 'Completed' },
                { value: 'cancelled', label: 'Cancelled' },
            ],
        }
    },

    computed: {
        hasFilters() {
            return !!(this.status || this.payment || this.search)
        },

        lede() {
            const s = this.summary
            if (!s.pending_orders && !s.to_verify) return 'You are all caught up. No orders need attention.'
            const parts = []
            if (s.pending_orders) parts.push(`${s.pending_orders} new ${s.pending_orders === 1 ? 'order' : 'orders'}`)
            if (s.to_verify) parts.push(`${s.to_verify} GCash ${s.to_verify === 1 ? 'payment' : 'payments'} to check`)
            return `${parts.join(' and ')} waiting for you.`
        },

        nextStatus() {
            return this.current ? NEXT[this.current.status] || null : null
        },

        canCancel() {
            return !!this.current && ['pending', 'confirmed', 'shipped'].includes(this.current.status)
        },

        latestPayment() {
            const list = (this.current && this.current.payments) || []
            return list.length ? list[list.length - 1] : null
        },

        reviewable() {
            const p = this.latestPayment
            return this.current && p && p.status === 'pending' && p.reference_number
                && this.current.payment_status === 'pending_verification' ? p : null
        },

        waitingForReference() {
            return this.current && this.current.payment_method === 'gcash'
                && ['unpaid', 'rejected'].includes(this.current.payment_status)
                && this.current.status !== 'cancelled' && this.current.status !== 'completed'
        },

        blockedByPayment() {
            return !!this.current && this.current.payment_method === 'gcash' && this.current.payment_status !== 'paid'
        },

        hasActions() {
            return !!(this.reviewable || this.waitingForReference || this.nextStatus || this.canCancel)
        },
    },

    mounted() {
        this.load()
        this.loadSummary()
    },

    beforeUnmount() {
        clearTimeout(this.timer)
    },

    methods: {
        peso,
        formatDate,

        itemCount(o) {
            return o.items.reduce((sum, i) => sum + i.quantity, 0)
        },

        methodLabel(m) {
            return PAYMENT_METHOD[m] || m
        },

        async load() {
            this.loading = true
            this.error = ''
            try {
                const { data } = await axios.get(`${API}/admin/orders`, {
                    params: {
                        status: this.status || undefined,
                        payment_status: this.payment || undefined,
                        search: this.search.trim() || undefined,
                        page: this.page,
                    },
                })
                this.orders = data.data
                this.meta = data.meta
            } catch (e) {
                this.error = apiError(e)
            } finally {
                this.loading = false
            }
        },

        async loadSummary() {
            try {
                const { data } = await axios.get(`${API}/admin/summary`)
                this.summary = data
                window.dispatchEvent(new Event('kbs-summary'))
            } catch (e) { /* optional */ }
        },

        setStatus(value) {
            this.status = value
            this.resetAndLoad()
        },

        resetAndLoad() {
            this.page = 1
            this.load()
        },

        debounced() {
            clearTimeout(this.timer)
            this.timer = setTimeout(this.resetAndLoad, 350)
        },

        goToPage(page) {
            this.page = page
            this.load()
        },

        open(order) {
            this.current = order
            this.actionError = ''
            this.note = ''
            this.confirmCancel = false
            this.dialog = true
        },

        replace(order) {
            this.current = order
            const i = this.orders.findIndex(o => o.id === order.id)
            if (i !== -1) this.orders.splice(i, 1, order)
            this.loadSummary()
        },

        async setOrderStatus(status) {
            this.busy = status
            this.actionError = ''
            try {
                const { data } = await axios.put(`${API}/admin/orders/${this.current.id}/status`, { status })
                this.replace(data.order)
                this.confirmCancel = false
            } catch (e) {
                this.actionError = apiError(e)
            } finally {
                this.busy = ''
            }
        },

        async review(action) {
            this.busy = action
            this.actionError = ''
            try {
                const { data } = await axios.put(`${API}/admin/payments/${this.reviewable.id}/review`, {
                    action,
                    admin_note: this.note.trim() || null,
                })
                this.replace(data.order)
                this.note = ''
            } catch (e) {
                this.actionError = apiError(e)
            } finally {
                this.busy = ''
            }
        },
    },
}
</script>

<style scoped>
.ao__wrap {
    max-width: 1200px;
    padding: 28px 24px 48px;
}

.ao__title {
    font-family: var(--kbs-font-display);
    font-size: 15px;
    font-weight: 600;
    color: var(--kbs-muted);
    margin: 0 0 6px;
}

.ao__lede {
    font-family: var(--kbs-font-display);
    font-size: clamp(22px, 3vw, 30px);
    font-weight: 600;
    line-height: 1.2;
    letter-spacing: -0.015em;
    margin: 0 0 20px;
    max-width: 30em;
}

.ao__filters {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

.ao__status {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.ao__status button {
    padding: 8px 16px;
    border: 1px solid var(--kbs-line);
    border-radius: 999px;
    background: #fff;
    font: inherit;
    font-weight: 600;
    color: var(--kbs-ink);
    cursor: pointer;
}

.ao__status button.is-on {
    background: var(--kbs-brand);
    border-color: var(--kbs-brand);
    color: #fff;
}

.ao__status button:focus-visible {
    outline: 2px solid var(--kbs-ink);
    outline-offset: 2px;
}

.ao__row2 {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.ao__search {
    position: relative;
    flex: 1 1 260px;
    max-width: 420px;
}

.ao__search .v-icon {
    position: absolute;
    left: 12px;
    top: 12px;
    color: var(--kbs-muted);
}

.ao__search input {
    width: 100%;
    height: 44px;
    padding: 0 14px 0 40px;
    border: 1px solid rgba(43, 27, 38, 0.38);
    border-radius: 8px;
    background: #fff;
    font: inherit;
    color: var(--kbs-ink);
}

.ao__search input:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: -1px;
    border-color: var(--kbs-brand);
}

.ao__panel {
    overflow: hidden;
}

.ao__empty {
    padding: 56px 24px;
    text-align: center;
    color: var(--kbs-muted);
}

.ao__empty p {
    margin: 4px 0 0;
}

.ao__empty-title {
    font-weight: 700;
    color: var(--kbs-ink);
    margin-top: 8px !important;
}

.ao__scroll {
    overflow-x: auto;
}

.ao__table {
    width: 100%;
    border-collapse: collapse;
    min-width: 720px;
}

.ao__table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--kbs-muted);
    border-bottom: 1px solid var(--kbs-line);
    background: #fffafd;
}

.ao__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--kbs-line);
    vertical-align: top;
}

.ao__table tbody tr {
    cursor: pointer;
}

.ao__table tbody tr:last-child td {
    border-bottom: 0;
}

.ao__table tbody tr:hover {
    background: #fff7fb;
}

.ao__table tbody tr:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: -2px;
}

.ao__table small {
    display: block;
    color: var(--kbs-muted);
    margin-top: 2px;
}

.ao__table .is-num {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.ao__dlg {
    border-radius: 16px;
}

.ao__dlg-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: var(--kbs-font-display);
    font-weight: 600;
    padding: 16px 20px 4px;
}

.ao__actions {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 16px;
    margin-bottom: 16px;
    border: 2px solid var(--kbs-brand-soft);
    border-radius: 14px;
    background: #fff7fb;
}

.ao__review h3 {
    margin: 0 0 6px;
    font-family: var(--kbs-font-display);
    font-size: 16px;
}

.ao__review p {
    margin: 0 0 10px;
}

.ao__btns {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.ao__hint,
.ao__wait {
    margin: 0;
    font-size: 14px;
    color: var(--kbs-muted);
}

.ao__cancel {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
</style>
