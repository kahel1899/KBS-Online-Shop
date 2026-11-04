<template>
    <div class="shop-wrap co">
        <h1 class="kbs-h2">Checkout</h1>

        <div v-if="!cart.items.length" class="co__empty">
            <p>Your cart is empty.</p>
            <v-btn to="/shop" color="primary" variant="flat">Go to the shop</v-btn>
        </div>

        <form v-else class="co__layout" novalidate @submit.prevent="submit">
            <div class="co__form">

                <!-- Contact + delivery -->
                <section class="co__card">
                    <h2>Delivery details</h2>

                    <div v-if="addresses.length" class="co__saved">
                        <label class="kbs-field-label" for="saved-address">Use a saved address</label>
                        <select id="saved-address" v-model="savedId" class="kbs-select" @change="applySaved">
                            <option value="">Enter a new address</option>
                            <option v-for="a in addresses" :key="a.id" :value="a.id">
                                {{ a.label || a.recipient_name }}, {{ a.line1 }}, {{ a.city }}
                            </option>
                        </select>
                    </div>

                    <v-text-field v-model="form.name" label="Full name" autocomplete="name" variant="outlined" density="comfortable" :error-messages="errors.name" />

                    <div class="co__two">
                        <v-text-field v-model="form.phone" label="Mobile number" type="tel" autocomplete="tel" variant="outlined" density="comfortable" :error-messages="errors.phone" />
                        <v-text-field v-model="form.email" label="Email (optional)" type="email" autocomplete="email" variant="outlined" density="comfortable" :error-messages="errors.email" />
                    </div>

                    <v-text-field v-model="form.line1" label="House no., street" autocomplete="address-line1" variant="outlined" density="comfortable" :error-messages="errors.line1" />
                    <v-text-field v-model="form.line2" label="Building, unit, landmark (optional)" autocomplete="address-line2" variant="outlined" density="comfortable" />

                    <div class="co__two">
                        <v-text-field v-model="form.barangay" label="Barangay" variant="outlined" density="comfortable" />
                        <v-text-field v-model="form.city" label="City / Municipality" autocomplete="address-level2" variant="outlined" density="comfortable" :error-messages="errors.city" />
                    </div>

                    <div class="co__two">
                        <v-text-field v-model="form.province" label="Province" autocomplete="address-level1" variant="outlined" density="comfortable" :error-messages="errors.province" />
                        <v-text-field v-model="form.postal" label="Postal code (optional)" autocomplete="postal-code" variant="outlined" density="comfortable" />
                    </div>

                    <v-checkbox
                        v-if="auth.user && !savedId"
                        v-model="saveAddress"
                        label="Save this address to my account"
                        density="comfortable"
                        hide-details
                    />
                </section>

                <!-- Payment -->
                <section class="co__card">
                    <h2>Payment</h2>
                    <div class="co__pay" role="radiogroup" aria-label="Payment method">
                        <label class="co__option" :class="{ 'is-on': form.method === 'cod' }">
                            <input v-model="form.method" type="radio" name="method" value="cod" />
                            <span class="co__option-title">Cash on delivery</span>
                            <span class="co__option-text">Pay the rider in cash when your order arrives.</span>
                        </label>
                        <label class="co__option" :class="{ 'is-on': form.method === 'gcash' }">
                            <input v-model="form.method" type="radio" name="method" value="gcash" />
                            <span class="co__option-title">GCash</span>
                            <span class="co__option-text">
                                After you place the order we show our GCash number. Send the payment, then enter your reference number.
                            </span>
                        </label>
                    </div>
                </section>

                <section class="co__card">
                    <h2>Note for the shop</h2>
                    <v-textarea v-model="form.notes" label="Anything we should know? (optional)" rows="2" auto-grow variant="outlined" density="comfortable" hide-details />
                </section>
            </div>

            <!-- Summary -->
            <aside class="co__sum" aria-label="Order summary">
                <h2>Order summary</h2>
                <ul class="co__items">
                    <li v-for="item in cart.items" :key="item.id">
                        <span class="co__items-name">{{ item.name }} <small>× {{ item.qty }}</small></span>
                        <span>{{ peso(item.price * item.qty) }}</span>
                    </li>
                </ul>

                <dl>
                    <div><dt>Subtotal</dt><dd>{{ peso(cart.subtotal) }}</dd></div>
                    <div><dt>Delivery</dt><dd>{{ shipping > 0 ? peso(shipping) : 'Free' }}</dd></div>
                    <div class="co__total"><dt>Total</dt><dd>{{ peso(total) }}</dd></div>
                </dl>

                <v-alert v-if="cart.hasProblems" type="warning" variant="tonal" density="compact" class="mt-3">
                    Some items in your cart are unavailable.
                    <router-link to="/cart">Review your cart</router-link>
                </v-alert>

                <v-alert v-if="serverError" type="error" variant="tonal" density="compact" class="mt-3">
                    {{ serverError }}
                </v-alert>

                <v-btn
                    type="submit"
                    block
                    size="large"
                    color="primary"
                    variant="flat"
                    class="mt-4"
                    :loading="submitting"
                    :disabled="cart.hasProblems"
                >
                    Place order, {{ peso(total) }}
                </v-btn>

                <p v-if="!auth.user" class="co__login">
                    Have an account? <router-link :to="{ name: 'Login', query: { redirect: '/checkout' } }">Log in</router-link>
                    to use your saved addresses and see your orders.
                </p>
            </aside>
        </form>
    </div>
</template>

<script>
import axios from 'axios'
import { useAuthStore } from '../../../stores/auth.js'
import { useCartStore } from '../../../stores/cart.js'
import { useShopStore } from '../../../stores/shop.js'
import { API, apiError, peso } from '../../../utils.js'

const PHONE = /^[0-9+\-\s()]{7,20}$/

// Server field names to the form's field names
const FIELD_MAP = {
    customer_name: 'name',
    shipping_name: 'name',
    customer_phone: 'phone',
    shipping_phone: 'phone',
    customer_email: 'email',
    shipping_line1: 'line1',
    shipping_city: 'city',
    shipping_province: 'province',
}

export default {
    data() {
        return {
            auth: useAuthStore(),
            cart: useCartStore(),
            shop: useShopStore(),
            addresses: [],
            savedId: '',
            saveAddress: false,
            submitting: false,
            serverError: '',
            errors: {},
            form: {
                name: '', phone: '', email: '',
                line1: '', line2: '', barangay: '', city: '', province: '', postal: '',
                method: 'cod', notes: '',
            },
        }
    },

    computed: {
        shipping() {
            return this.shop.shippingFor(this.cart.subtotal)
        },

        total() {
            return this.cart.subtotal + this.shipping
        },
    },

    async mounted() {
        this.shop.load()
        this.cart.refresh()

        if (this.auth.user) {
            this.form.name = this.auth.user.name || ''
            this.form.email = this.auth.user.email || ''
            this.form.phone = this.auth.user.phone || ''

            try {
                const { data } = await axios.get(`${API}/account/addresses`)
                this.addresses = data
                const def = data.find(a => a.is_default)
                if (def) {
                    this.savedId = def.id
                    this.applySaved()
                }
            } catch (e) { /* the form still works without saved addresses */ }
        }
    },

    methods: {
        peso,

        applySaved() {
            const a = this.addresses.find(x => x.id === this.savedId)
            if (!a) return
            this.form.name = a.recipient_name
            this.form.phone = a.phone
            this.form.line1 = a.line1
            this.form.line2 = a.line2 || ''
            this.form.barangay = a.barangay || ''
            this.form.city = a.city
            this.form.province = a.province
            this.form.postal = a.postal_code || ''
        },

        validate() {
            const f = this.form
            const e = {}
            if (!f.name.trim()) e.name = 'Enter your full name'
            if (!PHONE.test(f.phone.trim())) e.phone = 'Enter a valid mobile number'
            if (f.email.trim() && !/^\S+@\S+\.\S+$/.test(f.email.trim())) e.email = 'Enter a valid email address'
            if (!f.line1.trim()) e.line1 = 'Enter your house number and street'
            if (!f.city.trim()) e.city = 'Enter your city or municipality'
            if (!f.province.trim()) e.province = 'Enter your province'
            this.errors = e
            return Object.keys(e).length === 0
        },

        async submit() {
            this.serverError = ''
            if (!this.validate()) {
                this.serverError = 'Please fix the highlighted fields.'
                return
            }

            const f = this.form
            const payload = {
                customer_name: f.name.trim(),
                customer_email: f.email.trim() || null,
                customer_phone: f.phone.trim(),
                shipping_name: f.name.trim(),
                shipping_phone: f.phone.trim(),
                shipping_line1: f.line1.trim(),
                shipping_line2: f.line2.trim() || null,
                shipping_barangay: f.barangay.trim() || null,
                shipping_city: f.city.trim(),
                shipping_province: f.province.trim(),
                shipping_postal_code: f.postal.trim() || null,
                payment_method: f.method,
                notes: f.notes.trim() || null,
                items: this.cart.items.map(i => ({ product_id: i.id, quantity: i.qty })),
            }

            this.submitting = true
            try {
                const { data } = await axios.post(`${API}/shop/orders`, payload)

                if (this.auth.user && this.saveAddress && !this.savedId) {
                    try {
                        await axios.post(`${API}/account/addresses`, {
                            recipient_name: payload.shipping_name,
                            phone: payload.shipping_phone,
                            line1: payload.shipping_line1,
                            line2: payload.shipping_line2,
                            barangay: payload.shipping_barangay,
                            city: payload.shipping_city,
                            province: payload.shipping_province,
                            postal_code: payload.shipping_postal_code,
                        })
                    } catch (e) { /* the order is placed; the address is a bonus */ }
                }

                this.cart.clear()
                this.$router.push({
                    name: 'Track',
                    query: { number: data.order.order_number, phone: payload.customer_phone, placed: 1 },
                })
            } catch (error) {
                const fieldErrors = error.response && error.response.data && error.response.data.errors
                if (fieldErrors) {
                    const mapped = {}
                    Object.keys(fieldErrors).forEach(key => {
                        if (FIELD_MAP[key]) mapped[FIELD_MAP[key]] = fieldErrors[key][0]
                    })
                    this.errors = mapped
                }
                this.serverError = apiError(error)
                if (fieldErrors && fieldErrors.items) this.cart.refresh()
            } finally {
                this.submitting = false
            }
        },
    },
}
</script>

<style scoped>
.co {
    padding-top: 28px;
}

.co__empty {
    margin-top: 20px;
    padding: 56px 24px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.co__layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 28px;
    align-items: start;
    margin-top: 20px;
}

.co__form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.co__card,
.co__sum {
    padding: 20px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.co__card h2,
.co__sum h2 {
    margin: 0 0 14px;
    font-family: var(--kbs-font-display);
    font-size: 19px;
    font-weight: 600;
}

.co__saved {
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
}

.co__two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 14px;
}

.co__pay {
    display: grid;
    gap: 12px;
}

.co__option {
    display: grid;
    grid-template-columns: 22px 1fr;
    column-gap: 12px;
    padding: 14px 16px;
    border: 2px solid var(--kbs-line);
    border-radius: 14px;
    cursor: pointer;
}

.co__option.is-on {
    border-color: var(--kbs-brand);
    background: #fff7fb;
}

.co__option:focus-within {
    outline: 2px solid var(--kbs-brand);
    outline-offset: 2px;
}

.co__option input {
    grid-row: 1 / span 2;
    margin-top: 3px;
    accent-color: var(--kbs-brand);
    width: 18px;
    height: 18px;
}

.co__option-title {
    font-weight: 700;
}

.co__option-text {
    grid-column: 2;
    font-size: 14px;
    color: var(--kbs-muted);
}

.co__sum {
    position: sticky;
    top: 92px;
}

.co__items {
    list-style: none;
    margin: 0 0 12px;
    padding: 0 0 12px;
    border-bottom: 1px solid var(--kbs-line);
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 14px;
    font-variant-numeric: tabular-nums;
}

.co__items li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.co__items-name small {
    color: var(--kbs-muted);
}

.co__sum dl {
    margin: 0;
}

.co__sum dl > div {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    font-variant-numeric: tabular-nums;
}

.co__sum dt {
    color: var(--kbs-muted);
}

.co__sum dd {
    margin: 0;
}

.co__total {
    margin-top: 6px;
    padding-top: 10px !important;
    border-top: 1px solid var(--kbs-line);
    font-size: 20px;
    font-weight: 700;
}

.co__total dt {
    color: var(--kbs-ink);
}

.co__login {
    margin: 14px 0 0;
    font-size: 13px;
    color: var(--kbs-muted);
}

.co__login a {
    color: var(--kbs-brand);
    font-weight: 600;
}

@media (max-width: 900px) {
    .co__layout {
        grid-template-columns: 1fr;
    }

    .co__sum {
        position: static;
    }
}

@media (max-width: 520px) {
    .co__two {
        grid-template-columns: 1fr;
    }
}
</style>
