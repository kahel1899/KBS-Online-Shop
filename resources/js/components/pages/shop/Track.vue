<template>
    <div class="shop-wrap tr">
        <h1 class="kbs-h2">{{ placed && order ? 'Thank you for your order!' : 'Track your order' }}</h1>

        <v-alert v-if="placed && order" type="success" variant="tonal" class="mt-4">
            Your order <strong>{{ order.order_number }}</strong> has been placed. Please save this number.
            <span v-if="order.payment_method === 'gcash' && order.payment_status !== 'paid'">
                Send your GCash payment below to finish.
            </span>
        </v-alert>

        <!-- Lookup form -->
        <form v-if="!order" class="tr__form" novalidate @submit.prevent="lookup">
            <p class="kbs-muted">Enter your order number and the mobile number you used at checkout.</p>

            <v-text-field
                v-model="form.number"
                label="Order number"
                placeholder="KBS-261103-ABCDE"
                variant="outlined"
                density="comfortable"
                :error-messages="errors.number"
            />
            <v-text-field
                v-if="!auth.user"
                v-model="form.phone"
                label="Mobile number"
                type="tel"
                variant="outlined"
                density="comfortable"
                :error-messages="errors.phone"
            />

            <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>

            <v-btn type="submit" color="primary" variant="flat" size="large" :loading="loading">Find my order</v-btn>
        </form>

        <div v-else class="tr__order">
            <v-alert v-if="message" type="success" variant="tonal" density="compact" class="mb-3">{{ message }}</v-alert>
            <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>

            <OrderDetail
                :order="order"
                :settings="shop.settings"
                :busy="busy"
                @pay="pay"
                @cancel="cancelOrder"
            />

            <div class="tr__actions">
                <v-btn variant="text" color="primary" @click="reset">Track a different order</v-btn>
                <v-btn to="/shop" variant="tonal" color="primary">Continue shopping</v-btn>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios'
import OrderDetail from '../../OrderDetail.vue'
import { useAuthStore } from '../../../stores/auth.js'
import { useShopStore } from '../../../stores/shop.js'
import { API, apiError } from '../../../utils.js'

export default {
    components: { OrderDetail },

    data() {
        return {
            auth: useAuthStore(),
            shop: useShopStore(),
            form: { number: '', phone: '' },
            order: null,
            loading: false,
            busy: false,
            error: '',
            message: '',
            errors: {},
        }
    },

    computed: {
        placed() {
            return !!this.$route.query.placed
        },
    },

    mounted() {
        this.shop.load()
        this.form.number = String(this.$route.query.number || '')
        this.form.phone = String(this.$route.query.phone || '')
        if (this.form.number && (this.form.phone || this.auth.user)) this.lookup()
    },

    methods: {
        credentials() {
            return {
                order_number: this.form.number.trim(),
                phone: this.form.phone.trim(),
            }
        },

        async lookup() {
            this.errors = {}
            this.error = ''
            if (!this.form.number.trim()) this.errors.number = 'Enter your order number'
            if (!this.auth.user && !this.form.phone.trim()) this.errors.phone = 'Enter your mobile number'
            if (Object.keys(this.errors).length) return

            this.loading = true
            try {
                const { data } = await axios.post(`${API}/shop/orders/track`, this.credentials())
                this.order = data.order
                // keep the link shareable, but drop the "just placed" banner on later visits
                this.$router.replace({
                    name: 'Track',
                    query: { number: this.form.number.trim(), phone: this.form.phone.trim() || undefined, placed: this.$route.query.placed },
                })
            } catch (e) {
                this.error = apiError(e)
            } finally {
                this.loading = false
            }
        },

        async pay(reference) {
            await this.act('pay', { reference_number: reference })
        },

        async cancelOrder() {
            await this.act('cancel', {})
        },

        async act(path, extra) {
            this.busy = true
            this.error = ''
            this.message = ''
            try {
                const { data } = await axios.post(`${API}/shop/orders/${path}`, { ...this.credentials(), ...extra })
                this.order = data.order
                this.message = data.message
            } catch (e) {
                this.error = apiError(e)
            } finally {
                this.busy = false
            }
        },

        reset() {
            this.order = null
            this.message = ''
            this.error = ''
            this.form = { number: '', phone: '' }
            this.$router.replace({ name: 'Track' })
        },
    },
}
</script>

<style scoped>
.tr {
    padding-top: 28px;
    max-width: 760px;
}

.tr__form {
    margin-top: 16px;
    padding: 24px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.tr__order {
    margin-top: 16px;
}

.tr__actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 18px;
}
</style>
