import { defineStore } from 'pinia'
import axios from 'axios'
import { API } from '../utils.js'

const CART_KEY = 'kbs_cart'

function lineKey(productId, variantId) {
    return `${productId}-${variantId || 0}`
}

function readCart() {
    try {
        const saved = JSON.parse(localStorage.getItem(CART_KEY) || '[]')
        if (!Array.isArray(saved)) return []
        // carts saved before variants existed have no key
        return saved.map(i => ({
            variant_id: null,
            variant_name: '',
            ...i,
            key: i.key || lineKey(i.id, i.variant_id),
        }))
    } catch (e) {
        return []
    }
}

function save(items) {
    try {
        localStorage.setItem(CART_KEY, JSON.stringify(items))
    } catch (e) { /* ignore */ }
}

export const useCartStore = defineStore('cart', {
    state: () => ({
        // { key, id, variant_id, variant_name, slug, name, image, price, stock, qty, unavailable }
        items: readCart(),
    }),

    getters: {
        count: state => state.items.reduce((sum, i) => sum + i.qty, 0),
        subtotal: state => state.items.reduce((sum, i) => sum + i.price * i.qty, 0),
        hasProblems: state => state.items.some(i => i.unavailable || i.stock < 1 || i.qty > i.stock),
    },

    actions: {
        persist() {
            save(this.items)
        },

        // variant is optional: pass the chosen { id, name, price, stock } for products that have them
        add(product, qty = 1, variant = null) {
            const key = lineKey(product.id, variant ? variant.id : 0)
            const stock = Number(variant ? variant.stock : product.stock) || 0
            const price = Number(variant ? variant.price : product.price) || 0
            const existing = this.items.find(i => i.key === key)

            if (existing) {
                existing.qty = Math.min(existing.qty + qty, Math.max(stock, 1))
                existing.stock = stock
                existing.price = price
            } else {
                this.items.push({
                    key,
                    id: product.id,
                    variant_id: variant ? variant.id : null,
                    variant_name: variant ? variant.name : '',
                    slug: product.slug,
                    name: product.name,
                    image: product.image || '',
                    price,
                    stock,
                    qty: Math.min(qty, Math.max(stock, 1)),
                    unavailable: false,
                })
            }
            this.persist()
        },

        setQty(key, qty) {
            const item = this.items.find(i => i.key === key)
            if (!item) return
            item.qty = Math.max(1, Math.min(Number(qty) || 1, Math.max(item.stock, 1)))
            this.persist()
        },

        remove(key) {
            this.items = this.items.filter(i => i.key !== key)
            this.persist()
        },

        clear() {
            this.items = []
            this.persist()
        },

        // Prices and stock change: ask the server what is true right now
        async refresh() {
            if (!this.items.length) return
            try {
                const ids = [...new Set(this.items.map(i => i.id))].join(',')
                const { data } = await axios.get(`${API}/shop/cart`, { params: { ids } })
                const fresh = new Map(data.map(p => [p.id, p]))

                this.items.forEach(item => {
                    const latest = fresh.get(item.id)
                    if (!latest) {
                        item.unavailable = true
                        return
                    }

                    let variant = null
                    if (latest.has_variants) {
                        variant = latest.variants.find(v => v.id === item.variant_id)
                        if (!variant) {
                            item.unavailable = true
                            return
                        }
                    } else if (item.variant_id) {
                        item.unavailable = true
                        return
                    }

                    item.unavailable = false
                    item.price = variant ? variant.price : latest.price
                    item.stock = variant ? variant.stock : latest.stock
                    item.variant_name = variant ? variant.name : ''
                    item.name = latest.name
                    item.slug = latest.slug
                    item.image = latest.image || ''
                    if (item.stock > 0 && item.qty > item.stock) item.qty = item.stock
                })
                this.persist()
            } catch (e) { /* keep what we have; checkout re-checks on the server */ }
        },
    },
})
