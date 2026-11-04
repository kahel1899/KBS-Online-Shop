import { defineStore } from 'pinia'
import axios from 'axios'
import { API } from '../utils.js'

export const useShopStore = defineStore('shop', {
    state: () => ({
        settings: {
            name: 'Kooku Beauty Supplies',
            shipping_fee: 0,
            free_shipping_min: 0,
            gcash_name: '',
            gcash_number: '',
            contact_phone: '',
            contact_email: '',
            contact_facebook: '',
        },
        loaded: false,
    }),

    actions: {
        async load() {
            if (this.loaded) return
            try {
                const { data } = await axios.get(`${API}/shop/settings`)
                this.settings = data
                this.loaded = true
            } catch (e) { /* defaults are fine */ }
        },

        // Same rule as the server, for showing the total before checkout
        shippingFor(subtotal) {
            const free = Number(this.settings.free_shipping_min) || 0
            if (free > 0 && subtotal >= free) return 0
            return Number(this.settings.shipping_fee) || 0
        },
    },
})
