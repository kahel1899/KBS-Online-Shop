import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth.js'

// Layouts
const ShopLayout = () => import('./components/layouts/ShopLayout.vue')
const AdminLayout = () => import('./components/layouts/AdminLayout.vue')

// Shop
const Home = () => import('./components/pages/shop/Home.vue')
const Catalog = () => import('./components/pages/shop/Catalog.vue')
const ProductDetail = () => import('./components/pages/shop/ProductDetail.vue')
const Cart = () => import('./components/pages/shop/Cart.vue')
const Checkout = () => import('./components/pages/shop/Checkout.vue')
const Track = () => import('./components/pages/shop/Track.vue')
const NotFound = () => import('./components/pages/shop/NotFound.vue')

// Accounts
const Login = () => import('./components/pages/auth/Login.vue')
const Register = () => import('./components/pages/auth/Register.vue')
const Account = () => import('./components/pages/account/Account.vue')

// Admin
const Dashboard = () => import('./components/pages/Dashboard.vue')
const Inventory = () => import('./components/pages/Inventory.vue')
const AdminOrders = () => import('./components/pages/admin/Orders.vue')
const AdminCategories = () => import('./components/pages/admin/Categories.vue')

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior: () => ({ top: 0 }),
    routes: [
        {
            path: '/',
            component: ShopLayout,
            children: [
                { path: '', name: 'Home', component: Home, meta: { title: 'Nail care & beauty supplies' } },
                { path: 'shop', name: 'Shop', component: Catalog, meta: { title: 'Shop' } },
                { path: 'product/:slug', name: 'Product', component: ProductDetail },
                { path: 'cart', name: 'Cart', component: Cart, meta: { title: 'Your cart' } },
                { path: 'checkout', name: 'Checkout', component: Checkout, meta: { title: 'Checkout' } },
                { path: 'orders/track', name: 'Track', component: Track, meta: { title: 'Track your order' } },
                { path: 'login', name: 'Login', component: Login, meta: { title: 'Log in', guestOnly: true } },
                { path: 'register', name: 'Register', component: Register, meta: { title: 'Create account', guestOnly: true } },
                { path: 'account', name: 'Account', component: Account, meta: { title: 'My account', requiresAuth: true } },
            ],
        },
        {
            path: '/admin',
            component: AdminLayout,
            meta: { requiresAdmin: true },
            children: [
                { path: '', redirect: '/admin/dashboard' },
                { path: 'dashboard', name: 'Dashboard', component: Dashboard, meta: { title: 'Dashboard' } },
                { path: 'inventory', name: 'Inventory', component: Inventory, meta: { title: 'Inventory' } },
                { path: 'orders', name: 'Orders', component: AdminOrders, meta: { title: 'Orders' } },
                { path: 'categories', name: 'Categories', component: AdminCategories, meta: { title: 'Categories' } },
            ],
        },

        // Links from the old inventory-only version still work
        { path: '/Dashboard', redirect: '/admin/dashboard' },
        { path: '/Inventory', redirect: '/admin/inventory' },

        {
            path: '/:pathMatch(.*)*',
            component: ShopLayout,
            children: [{ path: '', name: 'NotFound', component: NotFound, meta: { title: 'Page not found' } }],
        },
    ],
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()

    // First page load: find out who the saved login belongs to
    if (!auth.loaded) await auth.fetchMe()

    const needsAdmin = to.matched.some(r => r.meta.requiresAdmin)
    const needsLogin = needsAdmin || to.matched.some(r => r.meta.requiresAuth)

    if (needsLogin && !auth.user) {
        return { name: 'Login', query: { redirect: to.fullPath } }
    }
    if (needsAdmin && !auth.isAdmin) {
        return { name: 'Home' }
    }
    if (to.meta.guestOnly && auth.user) {
        return auth.isAdmin ? '/admin/dashboard' : '/account'
    }
})

router.afterEach((to) => {
    const shop = 'Kooku Beauty Supplies'
    document.title = to.meta && to.meta.title ? `${to.meta.title} | ${shop}` : shop
})

export default router
