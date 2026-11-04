<template>
    <div class="shop-wrap pd">
        <div v-if="loading" class="pd__loading">Loading…</div>

        <div v-else-if="notFound" class="pd__missing">
            <h1 class="kbs-h2">We could not find that product</h1>
            <p class="kbs-muted">It may have been removed or is no longer for sale.</p>
            <v-btn to="/shop" color="primary" variant="flat" class="mt-3">Back to the shop</v-btn>
        </div>

        <template v-else-if="product">
            <nav class="pd__crumbs" aria-label="Breadcrumb">
                <router-link to="/shop">Shop</router-link>
                <template v-if="product.category">
                    <span aria-hidden="true">/</span>
                    <router-link :to="{ name: 'Shop', query: { category: product.category.slug } }">
                        {{ product.category.name }}
                    </router-link>
                </template>
            </nav>

            <div class="pd__layout">
                <!-- Gallery -->
                <div class="pd__gallery">
                    <div class="pd__main">
                        <img v-if="activeImage" :src="activeImage" :alt="product.name" @error="onImageError" />
                        <div v-else class="pd__ph"><v-icon size="56">mdi-image-outline</v-icon></div>
                    </div>
                    <div v-if="product.images.length > 1" class="pd__thumbs">
                        <button
                            v-for="(img, i) in product.images"
                            :key="img"
                            type="button"
                            :class="{ 'is-on': i === active }"
                            :aria-label="`Show picture ${i + 1}`"
                            @click="active = i"
                        >
                            <img :src="img" alt="" />
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="pd__info">
                    <h1 class="kbs-h2 pd__title">{{ product.name }}</h1>
                    <div class="pd__price">{{ peso(product.price) }}</div>

                    <p v-if="product.stock < 1" class="pd__stock pd__stock--out">Sold out</p>
                    <p v-else-if="product.stock <= 5" class="pd__stock pd__stock--low">
                        Only {{ product.stock }} left
                    </p>
                    <p v-else class="pd__stock">In stock</p>

                    <div v-if="product.stock > 0" class="pd__buy">
                        <div class="pd__qty" role="group" aria-label="Quantity">
                            <button type="button" :disabled="qty <= 1" aria-label="Decrease quantity" @click="qty--">
                                <v-icon size="18">mdi-minus</v-icon>
                            </button>
                            <span aria-live="polite">{{ qty }}</span>
                            <button
                                type="button"
                                :disabled="qty >= product.stock"
                                aria-label="Increase quantity"
                                @click="qty++"
                            >
                                <v-icon size="18">mdi-plus</v-icon>
                            </button>
                        </div>

                        <v-btn color="primary" variant="flat" size="large" class="pd__cta" @click="addToCart">
                            {{ justAdded ? 'Added to cart' : 'Add to cart' }}
                        </v-btn>
                        <v-btn variant="outlined" size="large" class="pd__ghost" @click="buyNow">Buy now</v-btn>
                    </div>

                    <p v-if="inCart" class="pd__incart">
                        You have {{ inCart }} in your cart.
                        <router-link to="/cart">View cart</router-link>
                    </p>

                    <section v-if="product.description" class="pd__desc">
                        <h2>About this product</h2>
                        <p>{{ product.description }}</p>
                    </section>

                    <ul class="pd__notes">
                        <li><v-icon size="18" color="primary">mdi-cash-multiple</v-icon> Cash on delivery or GCash</li>
                        <li v-if="freeFrom > 0">
                            <v-icon size="18" color="primary">mdi-truck-fast-outline</v-icon>
                            Free delivery on orders of {{ peso(freeFrom) }} or more
                        </li>
                    </ul>
                </div>
            </div>

            <section v-if="related.length" class="pd__related">
                <h2 class="kbs-h2">You may also like</h2>
                <div class="grid">
                    <ProductCard v-for="p in related" :key="p.id" :product="p" />
                </div>
            </section>
        </template>
    </div>
</template>

<script>
import axios from 'axios'
import ProductCard from '../../shop/ProductCard.vue'
import { useCartStore } from '../../../stores/cart.js'
import { useShopStore } from '../../../stores/shop.js'
import { API, peso } from '../../../utils.js'

export default {
    components: { ProductCard },

    data() {
        return {
            product: null,
            related: [],
            loading: true,
            notFound: false,
            active: 0,
            qty: 1,
            justAdded: false,
            timer: null,
            cart: useCartStore(),
            shop: useShopStore(),
        }
    },

    computed: {
        activeImage() {
            return this.product ? this.product.images[this.active] || this.product.image : null
        },

        inCart() {
            const line = this.product && this.cart.items.find(i => i.id === this.product.id)
            return line ? line.qty : 0
        },

        freeFrom() {
            return Number(this.shop.settings.free_shipping_min) || 0
        },
    },

    watch: {
        '$route.params.slug'() {
            if (this.$route.name === 'Product') this.load()
        },
    },

    mounted() {
        this.shop.load()
        this.load()
    },

    beforeUnmount() {
        clearTimeout(this.timer)
    },

    methods: {
        peso,

        async load() {
            this.loading = true
            this.notFound = false
            this.active = 0
            this.qty = 1
            try {
                const { data } = await axios.get(`${API}/shop/products/${encodeURIComponent(this.$route.params.slug)}`)
                this.product = data.product
                this.related = data.related
                document.title = `${data.product.name} | Kooku Beauty Supplies`
            } catch (e) {
                this.product = null
                this.notFound = true
            } finally {
                this.loading = false
            }
        },

        onImageError() {
            // drop a broken picture so the next one (or the placeholder) shows
            if (this.product && this.product.images.length) {
                this.product.images.splice(this.active, 1)
                this.active = 0
            }
        },

        addToCart() {
            this.cart.add(this.product, this.qty)
            this.justAdded = true
            clearTimeout(this.timer)
            this.timer = setTimeout(() => { this.justAdded = false }, 1600)
        },

        buyNow() {
            this.cart.add(this.product, this.qty)
            this.$router.push('/checkout')
        },
    },
}
</script>

<style scoped>
.pd {
    padding-top: 24px;
}

.pd__loading,
.pd__missing {
    padding: 80px 0;
    text-align: center;
}

.pd__crumbs {
    display: flex;
    gap: 8px;
    font-size: 14px;
    color: var(--kbs-muted);
    margin-bottom: 16px;
}

.pd__crumbs a {
    color: var(--kbs-muted);
}

.pd__crumbs a:hover {
    color: var(--kbs-brand);
}

.pd__layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 40px;
    align-items: start;
}

.pd__main {
    aspect-ratio: 1 / 1;
    background: #fdeaf4;
    border: 1px solid var(--kbs-line);
    border-radius: 20px;
    overflow: hidden;
}

.pd__main img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.pd__ph {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--kbs-brand-soft);
}

.pd__thumbs {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.pd__thumbs button {
    width: 68px;
    height: 68px;
    padding: 0;
    border: 2px solid transparent;
    border-radius: 12px;
    overflow: hidden;
    background: #fdeaf4;
    cursor: pointer;
}

.pd__thumbs button.is-on {
    border-color: var(--kbs-brand);
}

.pd__thumbs button:focus-visible {
    outline: 2px solid var(--kbs-ink);
    outline-offset: 2px;
}

.pd__thumbs img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.pd__title {
    font-size: clamp(26px, 3.4vw, 36px);
}

.pd__price {
    font-family: var(--kbs-font-display);
    font-size: 30px;
    font-weight: 700;
    margin: 10px 0 4px;
    font-variant-numeric: tabular-nums;
}

.pd__stock {
    margin: 0 0 18px;
    font-weight: 600;
    color: var(--kbs-profit);
}

.pd__stock--low {
    color: var(--kbs-warn);
}

.pd__stock--out {
    color: var(--kbs-loss);
}

.pd__buy {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.pd__qty {
    display: inline-flex;
    align-items: center;
    height: 48px;
    border: 1px solid rgba(43, 27, 38, 0.38);
    border-radius: 999px;
    background: #fff;
}

.pd__qty button {
    width: 44px;
    height: 100%;
    border: 0;
    background: transparent;
    border-radius: 999px;
    cursor: pointer;
    color: var(--kbs-ink);
}

.pd__qty button:disabled {
    color: #b9a8b2;
    cursor: not-allowed;
}

.pd__qty button:focus-visible {
    outline: 2px solid var(--kbs-brand);
}

.pd__qty span {
    min-width: 32px;
    text-align: center;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pd__cta {
    flex: 1 1 160px;
}

.pd__ghost {
    border-color: var(--kbs-ink);
}

.pd__incart {
    margin: 12px 0 0;
    font-size: 14px;
    color: var(--kbs-muted);
}

.pd__incart a {
    color: var(--kbs-brand);
    font-weight: 600;
}

.pd__desc {
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid var(--kbs-line);
}

.pd__desc h2 {
    font-family: var(--kbs-font-display);
    font-size: 18px;
    margin: 0 0 8px;
}

.pd__desc p {
    margin: 0;
    white-space: pre-line;
    line-height: 1.6;
}

.pd__notes {
    list-style: none;
    margin: 24px 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
    color: var(--kbs-muted);
    font-size: 14px;
}

.pd__notes li {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pd__related {
    margin-top: 56px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-top: 16px;
}

@media (max-width: 860px) {
    .pd__layout {
        grid-template-columns: 1fr;
        gap: 24px;
    }

    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
