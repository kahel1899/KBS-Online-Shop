<template>
    <div class="sl">
        <div v-if="announcement" class="sl-announce">{{ announcement }}</div>

        <header class="sl-header">
            <div class="shop-wrap sl-bar">
                <router-link to="/" class="sl-logo" aria-label="Kooku Beauty Supplies, home">
                    <span class="sl-logo__name">Kooku</span>
                    <span class="sl-logo__sub">Beauty Supplies</span>
                </router-link>

                <form class="sl-search" role="search" @submit.prevent="search">
                    <v-icon size="20" class="sl-search__icon">mdi-magnify</v-icon>
                    <input
                        v-model="term"
                        type="search"
                        placeholder="Search nail polish, gel, tools…"
                        aria-label="Search products"
                    />
                </form>

                <nav class="sl-nav" aria-label="Main">
                    <router-link to="/shop" class="sl-nav__link">Shop</router-link>
                    <router-link to="/orders/track" class="sl-nav__link sl-hide-sm">Track order</router-link>

                    <div ref="menuRoot" class="sl-account">
                        <button
                            v-if="auth.user"
                            type="button"
                            class="sl-iconbtn sl-iconbtn--text"
                            :aria-expanded="menuOpen"
                            aria-haspopup="menu"
                            @click="menuOpen = !menuOpen"
                        >
                            <v-icon size="22">mdi-account-circle-outline</v-icon>
                            <span class="sl-hide-sm">{{ auth.firstName }}</span>
                        </button>
                        <router-link v-else to="/login" class="sl-iconbtn sl-iconbtn--text">
                            <v-icon size="22">mdi-account-outline</v-icon>
                            <span class="sl-hide-sm">Log in</span>
                        </router-link>

                        <div v-if="menuOpen && auth.user" class="sl-menu" role="menu">
                            <router-link to="/account" role="menuitem" @click="menuOpen = false">
                                <v-icon size="18">mdi-package-variant-closed</v-icon> My orders
                            </router-link>
                            <router-link
                                v-if="auth.isAdmin"
                                to="/admin/dashboard"
                                role="menuitem"
                                @click="menuOpen = false"
                            >
                                <v-icon size="18">mdi-view-dashboard-outline</v-icon> Admin panel
                            </router-link>
                            <button type="button" role="menuitem" @click="logout">
                                <v-icon size="18">mdi-logout</v-icon> Log out
                            </button>
                        </div>
                    </div>

                    <router-link to="/cart" class="sl-iconbtn" aria-label="Cart">
                        <v-badge
                            :content="cart.count"
                            :model-value="cart.count > 0"
                            color="primary"
                            floating
                        >
                            <v-icon size="24">mdi-cart-outline</v-icon>
                        </v-badge>
                    </router-link>
                </nav>
            </div>
        </header>

        <v-main class="sl-main">
            <router-view />

            <footer class="sl-footer">
                <div class="shop-wrap sl-footer__grid">
                    <div>
                        <div class="sl-footer__brand">Kooku Beauty Supplies</div>
                        <p>Nail care and beauty supplies you can order online.</p>
                    </div>
                    <div>
                        <div class="sl-footer__title">Shop</div>
                        <router-link to="/shop">All products</router-link>
                        <router-link to="/orders/track">Track an order</router-link>
                        <router-link to="/cart">Your cart</router-link>
                    </div>
                    <div>
                        <div class="sl-footer__title">Help</div>
                        <span>We accept Cash on Delivery and GCash.</span>
                        <span v-if="shop.settings.contact_phone">{{ shop.settings.contact_phone }}</span>
                        <a v-if="shop.settings.contact_email" :href="`mailto:${shop.settings.contact_email}`">
                            {{ shop.settings.contact_email }}
                        </a>
                        <a
                            v-if="shop.settings.contact_facebook"
                            :href="shop.settings.contact_facebook"
                            target="_blank"
                            rel="noopener"
                        >
                            Facebook page
                        </a>
                    </div>
                </div>
            </footer>
        </v-main>
    </div>
</template>

<script>
import { useAuthStore } from '../../stores/auth.js'
import { useCartStore } from '../../stores/cart.js'
import { useShopStore } from '../../stores/shop.js'
import { peso } from '../../utils.js'

export default {
    data() {
        return {
            auth: useAuthStore(),
            cart: useCartStore(),
            shop: useShopStore(),
            term: '',
            menuOpen: false,
        }
    },

    computed: {
        announcement() {
            const min = Number(this.shop.settings.free_shipping_min) || 0
            if (min > 0) return `Free delivery on orders of ${peso(min)} or more. Pay by GCash or cash on delivery.`
            return ''
        },
    },

    watch: {
        '$route.fullPath'() {
            this.menuOpen = false
        },
        '$route.query.search': {
            immediate: true,
            handler(value) {
                if (this.$route.name === 'Shop') this.term = value ? String(value) : ''
            },
        },
    },

    mounted() {
        this.shop.load()
        document.addEventListener('click', this.onOutside)
    },

    beforeUnmount() {
        document.removeEventListener('click', this.onOutside)
    },

    methods: {
        search() {
            const q = this.term.trim()
            this.$router.push({ name: 'Shop', query: q ? { search: q } : {} })
        },

        onOutside(event) {
            const root = this.$refs.menuRoot
            if (this.menuOpen && root && !root.contains(event.target)) this.menuOpen = false
        },

        async logout() {
            this.menuOpen = false
            await this.auth.logout()
            this.$router.push('/')
        },
    },
}
</script>

<style scoped>
.sl-announce {
    background: var(--kbs-ink);
    color: #fff;
    text-align: center;
    font-size: 13px;
    padding: 8px 16px;
}

.sl-header {
    position: sticky;
    top: 0;
    z-index: 20;
    background: #fff;
    border-bottom: 1px solid var(--kbs-line);
}

.sl-bar {
    display: flex;
    align-items: center;
    gap: 16px 24px;
    min-height: 68px;
    flex-wrap: wrap;
    padding-top: 8px;
    padding-bottom: 8px;
}

.sl-logo {
    display: flex;
    flex-direction: column;
    line-height: 1.05;
    text-decoration: none;
    margin-right: 4px;
}

.sl-logo__name {
    font-family: var(--kbs-font-display);
    font-weight: 700;
    font-size: 28px;
    color: var(--kbs-brand);
    letter-spacing: -0.02em;
}

.sl-logo__sub {
    font-size: 12px;
    color: var(--kbs-muted);
}

.sl-search {
    position: relative;
    flex: 1 1 280px;
    max-width: 520px;
}

.sl-search input {
    width: 100%;
    height: 44px;
    padding: 0 16px 0 44px;
    border: 1px solid var(--kbs-line);
    border-radius: 999px;
    background: var(--kbs-paper);
    font: inherit;
    color: var(--kbs-ink);
}

.sl-search input:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: 1px;
    background: #fff;
}

.sl-search__icon {
    position: absolute;
    left: 14px;
    top: 12px;
    color: var(--kbs-muted);
}

.sl-nav {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
}

.sl-nav__link {
    padding: 10px 12px;
    border-radius: 999px;
    color: var(--kbs-ink);
    font-weight: 600;
    text-decoration: none;
}

.sl-nav__link:hover,
.sl-nav__link.router-link-active {
    background: var(--kbs-paper);
    color: var(--kbs-brand);
}

.sl-iconbtn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 44px;
    padding: 0 12px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--kbs-ink);
    font: inherit;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

.sl-iconbtn:hover {
    background: var(--kbs-paper);
}

.sl-iconbtn:focus-visible,
.sl-nav__link:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: 1px;
}

.sl-account {
    position: relative;
}

.sl-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    min-width: 190px;
    padding: 6px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 14px;
    box-shadow: 0 10px 28px rgba(43, 27, 38, 0.14);
    display: flex;
    flex-direction: column;
    z-index: 30;
}

.sl-menu a,
.sl-menu button {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--kbs-ink);
    font: inherit;
    text-align: left;
    text-decoration: none;
    cursor: pointer;
}

.sl-menu a:hover,
.sl-menu button:hover {
    background: var(--kbs-paper);
}

.sl-main {
    background: var(--kbs-paper);
}

.sl-footer {
    margin-top: 64px;
    background: #fff;
    border-top: 1px solid var(--kbs-line);
    padding: 36px 0 44px;
    color: var(--kbs-muted);
    font-size: 14px;
}

.sl-footer__grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1.2fr;
    gap: 28px;
}

.sl-footer__grid > div {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sl-footer__brand {
    font-family: var(--kbs-font-display);
    font-weight: 700;
    font-size: 20px;
    color: var(--kbs-brand);
}

.sl-footer__title {
    font-weight: 700;
    color: var(--kbs-ink);
}

.sl-footer p {
    margin: 0;
    max-width: 28em;
}

.sl-footer a {
    color: var(--kbs-muted);
    text-decoration: none;
}

.sl-footer a:hover {
    color: var(--kbs-brand);
    text-decoration: underline;
}

@media (max-width: 720px) {
    .sl-hide-sm {
        display: none;
    }

    .sl-search {
        order: 3;
        flex: 1 1 100%;
        max-width: none;
    }

    .sl-footer__grid {
        grid-template-columns: 1fr;
    }
}
</style>
