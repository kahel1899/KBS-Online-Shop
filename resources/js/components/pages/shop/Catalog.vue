<template>
    <div class="shop-wrap cat">
        <header class="cat__head">
            <h1 class="kbs-h2">{{ title }}</h1>
            <p class="kbs-muted" aria-live="polite">{{ summary }}</p>
        </header>

        <div class="cat__layout">
            <aside class="cat__side" aria-label="Filters">
                <div class="cat__label">Categories</div>
                <ul class="cat__list">
                    <li>
                        <button type="button" :class="{ 'is-on': !category }" @click="setQuery({ category: '' })">
                            All products
                        </button>
                    </li>
                    <li v-for="c in categories" :key="c.id">
                        <button
                            type="button"
                            :class="{ 'is-on': category === c.slug }"
                            @click="setQuery({ category: c.slug })"
                        >
                            {{ c.name }} <span>{{ c.product_count }}</span>
                        </button>
                    </li>
                </ul>
            </aside>

            <div class="cat__main">
                <div class="cat__tools">
                    <label class="cat__sort">
                        <span class="kbs-field-label">Sort by</span>
                        <select class="kbs-select" :value="sort" @change="setQuery({ sort: $event.target.value })">
                            <option value="newest">Newest first</option>
                            <option value="price_asc">Price, low to high</option>
                            <option value="price_desc">Price, high to low</option>
                            <option value="name">Name, A to Z</option>
                        </select>
                    </label>

                    <v-btn v-if="hasFilters" variant="text" color="primary" @click="clearAll">
                        Clear filters
                    </v-btn>
                </div>

                <div v-if="loading" class="grid">
                    <div v-for="n in 6" :key="n" class="skeleton"></div>
                </div>

                <div v-else-if="error" class="state">
                    <p class="state__title">We could not load the products</p>
                    <p class="kbs-muted">{{ error }}</p>
                    <v-btn color="primary" variant="tonal" @click="load">Try again</v-btn>
                </div>

                <div v-else-if="!products.length" class="state">
                    <v-icon size="40" color="secondary">mdi-magnify</v-icon>
                    <p class="state__title">No products found</p>
                    <p class="kbs-muted">Try a different search or category.</p>
                    <v-btn v-if="hasFilters" color="primary" variant="tonal" @click="clearAll">
                        Show all products
                    </v-btn>
                </div>

                <template v-else>
                    <div class="grid">
                        <ProductCard v-for="p in products" :key="p.id" :product="p" />
                    </div>

                    <v-pagination
                        v-if="meta.last_page > 1"
                        :model-value="meta.current_page"
                        :length="meta.last_page"
                        :total-visible="5"
                        rounded="circle"
                        active-color="primary"
                        class="mt-8"
                        @update:model-value="goToPage"
                    />
                </template>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios'
import ProductCard from '../../shop/ProductCard.vue'
import { API, apiError } from '../../../utils.js'

export default {
    components: { ProductCard },

    data() {
        return {
            categories: [],
            products: [],
            meta: { current_page: 1, last_page: 1, total: 0 },
            loading: true,
            error: '',
        }
    },

    computed: {
        search() { return String(this.$route.query.search || '') },
        category() { return String(this.$route.query.category || '') },
        sort() { return String(this.$route.query.sort || 'newest') },
        featured() { return !!this.$route.query.featured },
        page() { return Math.max(1, parseInt(this.$route.query.page, 10) || 1) },

        hasFilters() {
            return !!(this.search || this.category || this.featured)
        },

        title() {
            if (this.search) return `Results for “${this.search}”`
            const active = this.categories.find(c => c.slug === this.category)
            if (active) return active.name
            return this.featured ? 'Featured products' : 'All products'
        },

        summary() {
            if (this.loading) return 'Loading…'
            const n = this.meta.total
            return `${n} ${n === 1 ? 'product' : 'products'}`
        },
    },

    watch: {
        '$route.query': {
            handler() {
                if (this.$route.name === 'Shop') this.load()
            },
            deep: true,
        },
    },

    async mounted() {
        this.load()
        try {
            const { data } = await axios.get(`${API}/shop/categories`)
            this.categories = data
        } catch (e) { /* filters just stay hidden */ }
    },

    methods: {
        setQuery(changes) {
            const query = { ...this.$route.query, ...changes, page: undefined }
            Object.keys(query).forEach(k => {
                if (query[k] === '' || query[k] === undefined || query[k] === 'newest') delete query[k]
            })
            this.$router.push({ name: 'Shop', query })
        },

        goToPage(page) {
            this.$router.push({ name: 'Shop', query: { ...this.$route.query, page } })
        },

        clearAll() {
            this.$router.push({ name: 'Shop' })
        },

        async load() {
            this.loading = true
            this.error = ''
            try {
                const { data } = await axios.get(`${API}/shop/products`, {
                    params: {
                        search: this.search || undefined,
                        category: this.category || undefined,
                        featured: this.featured ? 1 : undefined,
                        sort: this.sort,
                        page: this.page,
                        per_page: 12,
                    },
                })
                this.products = data.data
                this.meta = data.meta
            } catch (e) {
                this.error = apiError(e)
            } finally {
                this.loading = false
            }
        },
    },
}
</script>

<style scoped>
.cat {
    padding-top: 28px;
}

.cat__head {
    margin-bottom: 20px;
}

.cat__head p {
    margin: 4px 0 0;
}

.cat__layout {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    gap: 32px;
    align-items: start;
}

.cat__label {
    font-weight: 700;
    margin-bottom: 8px;
}

.cat__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.cat__list button {
    width: 100%;
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 12px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    font: inherit;
    color: var(--kbs-ink);
    text-align: left;
    cursor: pointer;
}

.cat__list button span {
    color: var(--kbs-muted);
    font-size: 13px;
}

.cat__list button:hover {
    background: #fff;
}

.cat__list button.is-on {
    background: #fff;
    color: var(--kbs-brand);
    font-weight: 700;
    box-shadow: inset 3px 0 0 var(--kbs-brand);
}

.cat__list button:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: -2px;
}

.cat__tools {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.cat__sort {
    display: flex;
    flex-direction: column;
}

.grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.skeleton {
    aspect-ratio: 3 / 4;
    border-radius: 16px;
    background: linear-gradient(90deg, #fbeaf3, #fff3f9, #fbeaf3);
}

.state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 56px 24px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.state p {
    margin: 0;
}

.state__title {
    font-weight: 700;
}

@media (max-width: 960px) {
    .cat__layout {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .cat__list {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 8px;
    }

    .cat__list button {
        width: auto;
        padding: 8px 14px;
        border: 1px solid var(--kbs-line);
        background: #fff;
        border-radius: 999px;
    }

    .cat__list button.is-on {
        box-shadow: none;
        border-color: var(--kbs-brand);
    }

    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 420px) {
    .grid {
        gap: 10px;
    }
}
</style>
