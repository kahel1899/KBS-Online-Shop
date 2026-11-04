<template>
    <div class="od">
        <div class="od-head">
            <div>
                <div class="od-number">{{ order.order_number }}</div>
                <div class="od-date">Placed {{ formatDate(order.created_at) }}</div>
            </div>
            <div class="od-chips">
                <StatusChip kind="order" :value="order.status" />
                <StatusChip kind="payment" :value="order.payment_status" />
            </div>
        </div>

        <!-- Progress -->
        <ol v-if="order.status !== 'cancelled'" class="od-steps" aria-label="Order progress">
            <li
                v-for="(label, index) in steps"
                :key="label"
                :class="{ 'is-done': index <= stepIndex, 'is-current': index === stepIndex }"
            >
                <span class="od-steps__dot">
                    <v-icon v-if="index < stepIndex" size="14">mdi-check</v-icon>
                </span>
                {{ label }}
            </li>
        </ol>
        <p v-else class="od-cancelled">This order was cancelled.</p>

        <!-- Payment -->
        <section v-if="order.status !== 'cancelled'" class="od-box">
            <h3>Payment</h3>

            <template v-if="order.payment_method === 'cod'">
                <p v-if="order.payment_status === 'paid'">Paid in cash on delivery. Thank you!</p>
                <p v-else>
                    Please prepare <strong>{{ peso(order.total) }}</strong> in cash. You pay the
                    rider when your order arrives.
                </p>
            </template>

            <template v-else>
                <p v-if="order.payment_status === 'paid'">
                    Payment received through GCash. Thank you!
                </p>

                <template v-else>
                    <p v-if="order.payment_status === 'pending_verification'">
                        We got your GCash reference number
                        <strong>{{ latestPayment && latestPayment.reference_number }}</strong>.
                        We are checking it now.
                    </p>

                    <template v-else>
                        <p v-if="order.payment_status === 'rejected'" class="od-warn">
                            We could not confirm your last payment.
                            <span v-if="latestPayment && latestPayment.admin_note">
                                {{ latestPayment.admin_note }}
                            </span>
                            Please check the reference number and send it again.
                        </p>

                        <p>
                            Send <strong>{{ peso(order.total) }}</strong> by GCash to:
                        </p>
                        <div class="od-gcash">
                            <div>
                                <span>Account name</span>
                                <strong>{{ settings.gcash_name || 'Ask the shop' }}</strong>
                            </div>
                            <div>
                                <span>GCash number</span>
                                <strong>{{ settings.gcash_number || 'Ask the shop' }}</strong>
                            </div>
                        </div>

                        <form v-if="!admin" class="od-pay" @submit.prevent="submitPay">
                            <v-text-field
                                v-model="reference"
                                label="GCash reference number"
                                hint="The 13-digit number on your GCash receipt"
                                persistent-hint
                                variant="outlined"
                                density="comfortable"
                            />
                            <v-btn
                                type="submit"
                                color="primary"
                                variant="flat"
                                :loading="busy"
                                :disabled="reference.trim().length < 6"
                            >
                                I have paid
                            </v-btn>
                        </form>
                    </template>
                </template>
            </template>
        </section>

        <!-- Items -->
        <section class="od-box">
            <h3>Items</h3>
            <ul class="od-items">
                <li v-for="item in order.items" :key="item.id">
                    <div class="od-items__img">
                        <img v-if="item.image" :src="item.image" alt="" />
                        <v-icon v-else size="22" color="secondary">mdi-image-outline</v-icon>
                    </div>
                    <div class="od-items__name">
                        {{ item.name }}
                        <small>{{ item.quantity }} × {{ peso(item.price) }}</small>
                    </div>
                    <strong>{{ peso(item.line_total) }}</strong>
                </li>
            </ul>

            <dl class="od-totals">
                <div><dt>Subtotal</dt><dd>{{ peso(order.subtotal) }}</dd></div>
                <div>
                    <dt>Delivery</dt>
                    <dd>{{ order.shipping_fee > 0 ? peso(order.shipping_fee) : 'Free' }}</dd>
                </div>
                <div class="od-totals__total"><dt>Total</dt><dd>{{ peso(order.total) }}</dd></div>
            </dl>
        </section>

        <!-- Delivery -->
        <section class="od-box">
            <h3>Delivery</h3>
            <p class="od-addr">
                <strong>{{ order.shipping.name }}</strong><br />
                {{ order.shipping.phone }}<br />
                {{ address }}
            </p>
            <p class="od-meta">Paying by {{ methodLabel }}</p>
            <p v-if="order.notes" class="od-meta">Note: {{ order.notes }}</p>
            <p v-if="admin" class="od-meta">
                Customer: {{ order.customer.name }}, {{ order.customer.phone }}
                <span v-if="order.customer.email">, {{ order.customer.email }}</span>
            </p>
        </section>

        <!-- Cancel -->
        <div v-if="!admin && order.can_cancel" class="od-cancel">
            <template v-if="!confirming">
                <v-btn variant="outlined" color="error" @click="confirming = true">Cancel order</v-btn>
            </template>
            <template v-else>
                <span>Cancel this order?</span>
                <v-btn color="error" variant="flat" :loading="busy" @click="$emit('cancel')">
                    Yes, cancel it
                </v-btn>
                <v-btn variant="text" @click="confirming = false">Keep order</v-btn>
            </template>
        </div>
    </div>
</template>

<script>
import StatusChip from './StatusChip.vue'
import { PAYMENT_METHOD, formatDate, fullAddress, peso } from '../utils.js'

export default {
    name: 'OrderDetail',

    components: { StatusChip },

    props: {
        order: { type: Object, required: true },
        settings: { type: Object, default: () => ({}) },
        admin: { type: Boolean, default: false },
        busy: { type: Boolean, default: false },
    },

    emits: ['pay', 'cancel'],

    data() {
        return {
            steps: ['Placed', 'Confirmed', 'Shipped', 'Completed'],
            reference: '',
            confirming: false,
        }
    },

    computed: {
        stepIndex() {
            return ['pending', 'confirmed', 'shipped', 'completed'].indexOf(this.order.status)
        },

        latestPayment() {
            const list = this.order.payments || []
            return list.length ? list[list.length - 1] : null
        },

        methodLabel() {
            return PAYMENT_METHOD[this.order.payment_method] || this.order.payment_method
        },

        address() {
            return fullAddress(this.order.shipping)
        },
    },

    watch: {
        'order.status'() {
            this.confirming = false
        },
        'order.payment_status'() {
            this.reference = ''
        },
    },

    methods: {
        peso,
        formatDate,

        submitPay() {
            this.$emit('pay', this.reference.trim())
        },
    },
}
</script>

<style scoped>
.od {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.od-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
}

.od-number {
    font-family: var(--kbs-font-display);
    font-size: 22px;
    font-weight: 700;
}

.od-date {
    font-size: 13px;
    color: var(--kbs-muted);
}

.od-chips {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.od-steps {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4px;
}

.od-steps li {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--kbs-muted);
    text-align: center;
    position: relative;
}

.od-steps li:not(:first-child)::before {
    content: '';
    position: absolute;
    top: 10px;
    right: 50%;
    width: 100%;
    height: 2px;
    background: var(--kbs-line);
    z-index: 0;
}

.od-steps li.is-done:not(:first-child)::before {
    background: var(--kbs-brand);
}

.od-steps__dot {
    position: relative;
    z-index: 1;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid var(--kbs-line);
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.od-steps li.is-done .od-steps__dot {
    background: var(--kbs-brand);
    border-color: var(--kbs-brand);
}

.od-steps li.is-current {
    color: var(--kbs-ink);
    font-weight: 700;
}

.od-cancelled {
    margin: 0;
    padding: 10px 14px;
    border-radius: 12px;
    background: #fbe3e1;
    color: #a8231b;
    font-weight: 600;
}

.od-box {
    padding: 16px 18px;
    border: 1px solid var(--kbs-line);
    border-radius: 14px;
    background: #fff;
}

.od-box h3 {
    margin: 0 0 8px;
    font-family: var(--kbs-font-display);
    font-size: 16px;
    font-weight: 600;
}

.od-box p {
    margin: 0 0 8px;
}

.od-warn {
    padding: 8px 12px;
    border-radius: 10px;
    background: #fbe3e1;
    color: #a8231b;
}

.od-gcash {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.od-gcash > div {
    flex: 1 1 180px;
    display: flex;
    flex-direction: column;
    padding: 10px 14px;
    border-radius: 12px;
    background: var(--kbs-paper);
    border: 1px solid var(--kbs-line);
}

.od-gcash span {
    font-size: 12px;
    color: var(--kbs-muted);
}

.od-gcash strong {
    font-size: 17px;
    font-variant-numeric: tabular-nums;
}

.od-pay {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.od-pay .v-input {
    flex: 1 1 240px;
}

.od-pay .v-btn {
    height: 48px;
}

.od-items {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.od-items li {
    display: flex;
    align-items: center;
    gap: 12px;
}

.od-items__img {
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: #fdeaf4;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.od-items__img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.od-items__name {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.od-items__name small {
    color: var(--kbs-muted);
}

.od-items strong {
    font-variant-numeric: tabular-nums;
}

.od-totals {
    margin: 14px 0 0;
    padding-top: 10px;
    border-top: 1px solid var(--kbs-line);
}

.od-totals > div {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
    font-variant-numeric: tabular-nums;
}

.od-totals dt {
    color: var(--kbs-muted);
}

.od-totals dd {
    margin: 0;
}

.od-totals__total {
    font-size: 18px;
    font-weight: 700;
    padding-top: 6px !important;
}

.od-totals__total dt {
    color: var(--kbs-ink);
}

.od-addr {
    line-height: 1.5;
}

.od-meta {
    color: var(--kbs-muted);
    font-size: 14px;
}

.od-cancel {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
</style>
