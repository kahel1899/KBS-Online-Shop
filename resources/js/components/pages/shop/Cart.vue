<template>
    <div class="shop-wrap cart">
        <h1 class="kbs-h2">Your cart</h1>

        <div v-if="!cart.items.length" class="cart__empty">
            <v-icon size="44" color="secondary">mdi-cart-outline</v-icon>
            <p class="cart__empty-title">Your cart is empty</p>
            <p class="kbs-muted">Find something you like and add it to your cart.</p>
            <v-btn to="/shop" color="primary" variant="flat" size="large" class="mt-3">Start shopping</v-btn>
        </div>

        <div v-else class="cart__layout">
            <section aria-label="Items in your cart">
                <ul class="lines">
                    <li v-for="item in cart.items" :key="item.id" class="line" :class="{ 'is-bad': item.unavailable || item.stock < 1 }">
                        <router-link :to="`/product/${item.slug}`" class="line__img" :aria-label="item.name">
                            <img v-if="item.image" :src="item.image" alt="" />
                            <v-icon v-else size="26" color="secondary">mdi-image-outline</v-icon>
                        </router-link>

                        <div class="line__info">
                            <router-link :to="`/product/${item.slug}`" class="line__name">{{ item.name }}</router-link>
                            <span class="line__price">{{ peso(item.price) }} each</span>
                            <span v-if="item.unavailable" class="line__warn">No longer available. Please remove it.</span>
                            <span v-else-if="item.stock < 1" class="line__warn">Sold out. Please remove it.</span>
                            <span v-else-if="item.qty >= item.stock" class="line__note">That is all we have in stock.</span>
                        </div>

                        <div class="line__qty" role="group" :aria-label="`Quantity of ${item.name}`">
                            <button type="button" :disabled="item.qty <= 1" aria-label="Decrease quantity" @click="cart.setQty(item.id, item.qty - 1)">
                                <v-icon size="16">mdi-minus</v-icon>
                            </button>
                            <span>{{ item.qty }}</span>
                            <button type="button" :disabled="item.qty >= item.stock" aria-label="Increase quantity" @click="cart.setQty(item.id, item.qty + 1)">
                                <v-icon size="16">mdi-plus</v-icon>
                            </button>
                        </div>

                        <strong class="line__total">{{ peso(item.price * item.qty) }}</strong>

                        <button type="button" class="line__remove" :aria-label="`Remove ${item.name}`" @click="cart.remove(item.id)">
                            <v-icon size="20">mdi-close</v-icon>
                        </button>
                    </li>
                </ul>
            </section>

            <aside class="sum" aria-label="Order summary">
                <h2>Order summary</h2>

                <dl>
                    <div><dt>Subtotal</dt><dd>{{ peso(cart.subtotal) }}</dd></div>
                    <div><dt>Delivery</dt><dd>{{ shipping > 0 ? peso(shipping) : 'Free' }}</dd></div>
                    <div class="sum__total"><dt>Total</dt><dd>{{ peso(total) }}</dd></div>
                </dl>

                <p v-if="remainingForFree > 0" class="sum__free">
                    Add {{ peso(remainingForFree) }} more for free delivery.
                </p>

                <v-btn
                    block
                    size="large"
                    color="primary"
                    variant="flat"
                    :disabled="cart.hasProblems"
                    class="mt-3"
                    @click="$router.push('/checkout')"
                >
                    Checkout
                </v-btn>
                <p v-if="cart.hasProblems" class="sum__problem">Remove the unavailable items to continue.</p>

                <router-link to="/shop" class="sum__more">Keep shopping</router-link>
            </aside>
        </div>
    </div>
</template>

<script>
import { useCartStore } from '../../../stores/cart.js'
import { useShopStore } from '../../../stores/shop.js'
import { peso } from '../../../utils.js'

export default {
    data() {
        return { cart: useCartStore(), shop: useShopStore() }
    },

    computed: {
        shipping() {
            return this.shop.shippingFor(this.cart.subtotal)
        },

        total() {
            return this.cart.subtotal + this.shipping
        },

        remainingForFree() {
            const min = Number(this.shop.settings.free_shipping_min) || 0
            if (min <= 0 || this.cart.subtotal >= min) return 0
            return min - this.cart.subtotal
        },
    },

    mounted() {
        this.shop.load()
        this.cart.refresh()
    },

    methods: { peso },
}
</script>

<style scoped>
.cart {
    padding-top: 28px;
}

.cart__empty {
    margin-top: 20px;
    padding: 64px 24px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.cart__empty-title {
    margin: 8px 0 4px;
    font-weight: 700;
    font-size: 18px;
}

.cart__layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 28px;
    align-items: start;
    margin-top: 20px;
}

.lines {
    list-style: none;
    margin: 0;
    padding: 0;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.line {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr) auto 96px 32px;
    gap: 14px;
    align-items: center;
    padding: 14px 16px;
}

.line + .line {
    border-top: 1px solid var(--kbs-line);
}

.line.is-bad {
    background: #fff6f6;
}

.line__img {
    width: 72px;
    height: 72px;
    border-radius: 12px;
    background: #fdeaf4;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.line__img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.line__info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.line__name {
    font-weight: 700;
    color: var(--kbs-ink);
    text-decoration: none;
}

.line__name:hover {
    color: var(--kbs-brand);
}

.line__price {
    font-size: 14px;
    color: var(--kbs-muted);
}

.line__warn {
    font-size: 13px;
    font-weight: 600;
    color: var(--kbs-loss);
}

.line__note {
    font-size: 13px;
    color: var(--kbs-warn);
}

.line__qty {
    display: inline-flex;
    align-items: center;
    height: 38px;
    border: 1px solid rgba(43, 27, 38, 0.38);
    border-radius: 999px;
}

.line__qty button {
    width: 34px;
    height: 100%;
    border: 0;
    background: transparent;
    border-radius: 999px;
    cursor: pointer;
    color: var(--kbs-ink);
}

.line__qty button:disabled {
    color: #b9a8b2;
    cursor: not-allowed;
}

.line__qty button:focus-visible,
.line__remove:focus-visible {
    outline: 2px solid var(--kbs-brand);
}

.line__qty span {
    min-width: 26px;
    text-align: center;
    font-weight: 700;
}

.line__total {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.line__remove {
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--kbs-muted);
    cursor: pointer;
}

.line__remove:hover {
    background: var(--kbs-paper);
    color: var(--kbs-loss);
}

.sum {
    position: sticky;
    top: 92px;
    padding: 20px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.sum h2 {
    margin: 0 0 12px;
    font-family: var(--kbs-font-display);
    font-size: 20px;
}

.sum dl {
    margin: 0;
}

.sum dl > div {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    font-variant-numeric: tabular-nums;
}

.sum dt {
    color: var(--kbs-muted);
}

.sum dd {
    margin: 0;
}

.sum__total {
    margin-top: 6px;
    padding-top: 10px !important;
    border-top: 1px solid var(--kbs-line);
    font-size: 20px;
    font-weight: 700;
}

.sum__total dt {
    color: var(--kbs-ink);
}

.sum__free {
    margin: 10px 0 0;
    padding: 8px 12px;
    border-radius: 10px;
    background: #fff1f8;
    color: var(--kbs-brand);
    font-size: 14px;
    font-weight: 600;
}

.sum__problem {
    margin: 8px 0 0;
    font-size: 13px;
    color: var(--kbs-loss);
}

.sum__more {
    display: block;
    margin-top: 14px;
    text-align: center;
    color: var(--kbs-brand);
    font-weight: 600;
}

@media (max-width: 900px) {
    .cart__layout {
        grid-template-columns: 1fr;
    }

    .sum {
        position: static;
    }
}

@media (max-width: 600px) {
    .line {
        grid-template-columns: 64px minmax(0, 1fr) 32px;
        grid-template-areas:
            'img info remove'
            'img qty total';
        row-gap: 8px;
    }

    .line__img { grid-area: img; width: 64px; height: 64px; }
    .line__info { grid-area: info; }
    .line__qty { grid-area: qty; justify-self: start; }
    .line__total { grid-area: total; }
    .line__remove { grid-area: remove; }
}
</style>
