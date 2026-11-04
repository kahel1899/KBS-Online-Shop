import { defineStore } from 'pinia'
import axios from 'axios'
import { API } from '../utils.js'

const CART_KEY = 'kbs_cart'

function readCart() {
    try {
        const saved = JSON.parse(localStorage.getItem(CART_KEY) || '[]')
        return Array.isArray(saved) ? saved : []
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
        // { id, slug, name, image, price, stock, qty, unavailable }
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

        add(product, qty = 1) {
            const stock = Number(product.stock) || 0
            const existing = this.items.find(i => i.id === product.id)

            if (existing) {
                existing.qty = Math.min(existing.qty + qty, Math.max(stock, 1))
                existing.stock = stock
                existing.price = product.price
            } else {
                this.items.push({
                    id: product.id,
                    slug: product.slug,
                    name: product.name,
                    image: product.image || '',
                    price: product.price,
                    stock,
                    qty: Math.min(qty, Math.max(stock, 1)),
                    unavailable: false,
                })
            }
            this.persist()
        },

        setQty(id, qty) {
            const item = this.items.find(i => i.id === id)
            if (!item) return
            item.qty = Math.max(1, Math.min(Number(qty) || 1, Math.max(item.stock, 1)))
            this.persist()
        },

        remove(id) {
            this.items = this.items.filter(i => i.id !== id)
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
                const ids = this.items.map(i => i.id).join(',')
                const { data } = await axios.get(`${API}/shop/cart`, { params: { ids } })
                const fresh = new Map(data.map(p => [p.id, p]))

                this.items.forEach(item => {
                    const latest = fresh.get(item.id)
                    if (!latest) {
                        item.unavailable = true
                        return
                    }
                    item.unavailable = false
                    item.price = latest.price
                    item.stock = latest.stock
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
