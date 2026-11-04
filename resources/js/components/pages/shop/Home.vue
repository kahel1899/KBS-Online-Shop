<template>
    <div>
        <!-- Hero -->
        <section class="hero">
            <div class="shop-wrap hero__inner">
                <div class="hero__text">
                    <p class="hero__eyebrow">Kooku Beauty Supplies</p>
                    <h1 class="kbs-h1">Nail care you will love to do.</h1>
                    <p class="hero__lead">
                        Polish, gel, press-ons, tools and nail art. Order online and pay by GCash or
                        cash on delivery.
                    </p>
                    <div class="hero__cta">
                        <v-btn to="/shop" color="primary" variant="flat" size="x-large">Shop now</v-btn>
                        <v-btn to="/orders/track" variant="outlined" size="x-large" class="hero__ghost">
                            Track an order
                        </v-btn>
                    </div>
                </div>

                <ul class="hero__perks" aria-label="Why shop with us">
                    <li>
                        <v-icon color="primary">mdi-cash-multiple</v-icon>
                        <div><strong>Cash on delivery</strong><span>Pay when it arrives</span></div>
                    </li>
                    <li>
                        <v-icon color="primary">mdi-cellphone-check</v-icon>
                        <div><strong>GCash accepted</strong><span>Send and enter your reference</span></div>
                    </li>
                    <li v-if="freeFrom > 0">
                        <v-icon color="primary">mdi-truck-fast-outline</v-icon>
                        <div><strong>Free delivery</strong><span>On orders of {{ peso(freeFrom) }}+</span></div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Categories -->
        <section v-if="categories.length" class="shop-wrap block">
            <h2 class="kbs-h2">Shop by category</h2>
            <div class="cats">
                <router-link
                    v-for="category in categories"
                    :key="category.id"
                    :to="{ name: 'Shop', query: { category: category.slug } }"
                    class="cat"
                >
                    <span class="cat__img">
                        <img v-if="category.image" :src="category.image" alt="" loading="lazy" />
                        <span v-else class="cat__letter">{{ category.name.charAt(0) }}</span>
                    </span>
                    <span class="cat__name">{{ category.name }}</span>
                    <span class="cat__count">{{ category.product_count }}
                        {{ category.product_count === 1 ? 'item' : 'items' }}</span>
                </router-link>
            </div>
        </section>

        <!-- Featured -->
        <section v-if="featured.length" class="shop-wrap block">
            <div class="block__head">
                <h2 class="kbs-h2">Featured</h2>
                <router-link :to="{ name: 'Shop', query: { featured: 1 } }" class="more">See all</router-link>
            </div>
            <div class="grid">
                <ProductCard v-for="p in featured" :key="p.id" :product="p" />
            </div>
        </section>

        <!-- New -->
        <section class="shop-wrap block">
            <div class="block__head">
                <h2 class="kbs-h2">New arrivals</h2>
                <router-link to="/shop" class="more">Browse everything</router-link>
            </div>

            <div v-if="loading" class="grid">
                <div v-for="n in 4" :key="n" class="skeleton"></div>
            </div>

            <div v-else-if="latest.length" class="grid">
                <ProductCard v-for="p in latest" :key="p.id" :product="p" />
            </div>

            <div v-else class="empty">
                <v-icon size="40" color="secondary">mdi-storefront-outline</v-icon>
                <p class="empty__title">Our shelves are being stocked</p>
                <p class="kbs-muted">New products will appear here very soon. Please check back.</p>
            </div>
        </section>
    </div>
</template>

<script>
import axios from 'axios'
import ProductCard from '../../shop/ProductCard.vue'
import { useShopStore } from '../../../stores/shop.js'
import { API, peso } from '../../../utils.js'

export default {
    components: { ProductCard },

    data() {
        return {
            categories: [],
            featured: [],
            latest: [],
            loading: true,
            shop: useShopStore(),
        }
    },

    computed: {
        freeFrom() {
            return Number(this.shop.settings.free_shipping_min) || 0
        },
    },

    async mounted() {
        this.shop.load()
        try {
            const [cats, featured, latest] = await Promise.all([
                axios.get(`${API}/shop/categories`),
                axios.get(`${API}/shop/products`, { params: { featured: 1, per_page: 4 } }),
                axios.get(`${API}/shop/products`, { params: { per_page: 8 } }),
            ])
            this.categories = cats.data.filter(c => c.product_count > 0)
            this.featured = featured.data.data
            this.latest = latest.data.data
        } catch (e) {
            /* the empty state explains it */
        } finally {
            this.loading = false
        }
    },

    methods: { peso },
}
</script>

<style scoped>
.hero {
    background: linear-gradient(180deg, #ffe3f1 0%, var(--kbs-paper) 100%);
    padding: 56px 0 40px;
}

.hero__inner {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 40px;
    align-items: center;
}

.hero__eyebrow {
    margin: 0 0 10px;
    font-weight: 700;
    color: var(--kbs-brand);
}

.hero__lead {
    margin: 16px 0 24px;
    font-size: 18px;
    line-height: 1.5;
    max-width: 30em;
    color: var(--kbs-muted);
}

.hero__cta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.hero__ghost {
    border-color: var(--kbs-ink);
}

.hero__perks {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.hero__perks li {
    display: flex;
    gap: 14px;
    align-items: center;
    padding: 16px 18px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.hero__perks div {
    display: flex;
    flex-direction: column;
}

.hero__perks span {
    font-size: 14px;
    color: var(--kbs-muted);
}

.block {
    margin-top: 48px;
}

.block__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.more {
    color: var(--kbs-brand);
    font-weight: 600;
    text-decoration: none;
}

.more:hover {
    text-decoration: underline;
}

.cats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
    margin-top: 16px;
}

.cat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 18px 12px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
    text-decoration: none;
    color: var(--kbs-ink);
    text-align: center;
    transition: border-color 0.15s;
}

.cat:hover {
    border-color: var(--kbs-brand);
}

.cat__img {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #fdeaf4;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 6px;
}

.cat__img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cat__letter {
    font-family: var(--kbs-font-display);
    font-size: 30px;
    font-weight: 700;
    color: var(--kbs-brand);
}

.cat__name {
    font-weight: 700;
}

.cat__count {
    font-size: 13px;
    color: var(--kbs-muted);
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.skeleton {
    aspect-ratio: 3 / 4;
    border-radius: 16px;
    background: linear-gradient(90deg, #fbeaf3, #fff3f9, #fbeaf3);
}

.empty {
    margin-top: 16px;
    padding: 48px 24px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.empty__title {
    margin: 8px 0 4px;
    font-weight: 700;
}

@media (max-width: 960px) {
    .hero__inner {
        grid-template-columns: 1fr;
    }

    .grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .hero {
        padding: 36px 0 24px;
    }

    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
}
</style>
