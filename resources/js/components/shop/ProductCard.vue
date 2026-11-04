<template>
    <article class="pc">
        <router-link :to="`/product/${product.slug}`" class="pc__media" :aria-label="product.name">
            <img
                v-if="product.image && !broken"
                :src="product.image"
                alt=""
                loading="lazy"
                @error="broken = true"
            />
            <div v-else class="pc__ph">
                <v-icon size="38">mdi-image-outline</v-icon>
            </div>

            <span v-if="product.stock > 0 && product.stock <= 5" class="pc__tag">
                Only {{ product.stock }} left
            </span>
            <span v-else-if="product.is_featured" class="pc__tag pc__tag--feature">Featured</span>
        </router-link>

        <div class="pc__body">
            <span v-if="product.category" class="pc__cat">{{ product.category.name }}</span>
            <h3 class="pc__name">
                <router-link :to="`/product/${product.slug}`">{{ product.name }}</router-link>
            </h3>

            <div class="pc__row">
                <strong class="pc__price">{{ peso(product.price) }}</strong>
                <button
                    type="button"
                    class="pc__add"
                    :class="{ 'is-added': added }"
                    :disabled="product.stock < 1"
                    :aria-label="`Add ${product.name} to cart`"
                    @click="add"
                >
                    <v-icon size="20">{{ added ? 'mdi-check' : 'mdi-cart-plus' }}</v-icon>
                </button>
            </div>
        </div>
    </article>
</template>

<script>
import { useCartStore } from '../../stores/cart.js'
import { peso } from '../../utils.js'

export default {
    name: 'ProductCard',

    props: {
        product: { type: Object, required: true },
    },

    data() {
        return { broken: false, added: false, timer: null }
    },

    beforeUnmount() {
        clearTimeout(this.timer)
    },

    methods: {
        peso,

        add() {
            useCartStore().add(this.product, 1)
            this.added = true
            clearTimeout(this.timer)
            this.timer = setTimeout(() => { this.added = false }, 1400)
        },
    },
}
</script>

<style scoped>
.pc {
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
    overflow: hidden;
    min-width: 0;
}

.pc__media {
    position: relative;
    display: block;
    aspect-ratio: 1 / 1;
    background: #fdeaf4;
    overflow: hidden;
}

.pc__media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s;
}

.pc:hover .pc__media img {
    transform: scale(1.04);
}

.pc__ph {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--kbs-brand-soft);
}

.pc__tag {
    position: absolute;
    left: 10px;
    top: 10px;
    padding: 3px 10px;
    border-radius: 999px;
    background: #fff;
    color: var(--kbs-warn);
    font-size: 12px;
    font-weight: 700;
}

.pc__tag--feature {
    background: var(--kbs-brand);
    color: #fff;
}

.pc__body {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px 14px 14px;
    flex: 1;
}

.pc__cat {
    font-size: 12px;
    color: var(--kbs-muted);
}

.pc__name {
    margin: 0;
    font-family: var(--kbs-font-display);
    font-size: 16px;
    font-weight: 600;
    line-height: 1.25;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.5em;
}

.pc__name a {
    color: var(--kbs-ink);
    text-decoration: none;
}

.pc__name a:hover {
    color: var(--kbs-brand);
}

.pc__row {
    margin-top: auto;
    padding-top: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.pc__price {
    font-size: 18px;
    font-variant-numeric: tabular-nums;
}

.pc__add {
    width: 40px;
    height: 40px;
    border: 0;
    border-radius: 50%;
    background: var(--kbs-brand);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.15s;
}

.pc__add:hover:not(:disabled) {
    background: #b32573;
}

.pc__add.is-added {
    background: var(--kbs-profit);
}

.pc__add:disabled {
    background: #e7d6df;
    color: #8b7783;
    cursor: not-allowed;
}

.pc__add:focus-visible {
    outline: 2px solid var(--kbs-ink);
    outline-offset: 2px;
}
</style>
