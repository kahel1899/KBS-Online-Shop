import './bootstrap';
import '../css/app.css'
import { createApp } from 'vue'
import axios from 'axios'
import App from './components/templates/App.vue'

// ROUTER
import router  from './router.js'

// VUETIFY
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

// MDI/FONT
import '@mdi/font/css/materialdesignicons.css'

// PINIA
import { createPinia } from 'pinia'
import { useAuthStore } from './stores/auth.js'

const kooku = {
    dark: false,
    colors: {
        background: '#FFF7FB',
        surface: '#FFFFFF',
        primary: '#CF2E84',
        secondary: '#FF80C7',
        error: '#C62839',
        success: '#1F8F5F',
        warning: '#A35F00',
    },
}

const pinia = createPinia()
const vuetify = createVuetify({
    components,
    directives,
    icons: {
        defaultSet: 'mdi',
    },
    theme: {
        defaultTheme: 'kooku',
        themes: { kooku },
    },
})

const app = createApp(App)
app.use(pinia)
app.use(vuetify)
app.use(router)

// Send the saved login token with every request
const auth = useAuthStore(pinia)
auth.init()

// A rejected token on a members-only page sends the person back to log in
axios.interceptors.response.use(
    response => response,
    error => {
        const status = error.response && error.response.status
        const url = (error.config && error.config.url) || ''
        const route = router.currentRoute.value
        const protectedPage = route.matched.some(r => r.meta.requiresAuth || r.meta.requiresAdmin)

        if (status === 401 && auth.token && !url.endsWith('/login') && !url.endsWith('/me')) {
            auth.clear()
            if (protectedPage) {
                router.push({ name: 'Login', query: { redirect: route.fullPath } })
            }
        }
        return Promise.reject(error)
    }
)

app.mount('#app')
