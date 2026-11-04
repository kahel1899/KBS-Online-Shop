import { defineStore } from 'pinia'
import axios from 'axios'
import { API } from '../utils.js'

const TOKEN_KEY = 'kbs_token'

function readToken() {
    try {
        return localStorage.getItem(TOKEN_KEY) || ''
    } catch (e) {
        return ''
    }
}

function applyToken(token) {
    if (token) {
        axios.defaults.headers.common.Authorization = `Bearer ${token}`
    } else {
        delete axios.defaults.headers.common.Authorization
    }
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: readToken(),
        user: null,
        loaded: false,
    }),

    getters: {
        isLoggedIn: state => !!state.token && !!state.user,
        isAdmin: state => !!state.user && state.user.role === 'admin',
        firstName: state => (state.user ? String(state.user.name).split(' ')[0] : ''),
    },

    actions: {
        // Call once when the app starts
        init() {
            applyToken(this.token)
        },

        setSession(token, user) {
            this.token = token
            this.user = user
            this.loaded = true
            try {
                localStorage.setItem(TOKEN_KEY, token)
            } catch (e) { /* private mode: stay logged in for this tab only */ }
            applyToken(token)
        },

        clear() {
            this.token = ''
            this.user = null
            this.loaded = true
            try {
                localStorage.removeItem(TOKEN_KEY)
            } catch (e) { /* ignore */ }
            applyToken('')
        },

        async fetchMe() {
            if (!this.token) {
                this.loaded = true
                return
            }
            try {
                const { data } = await axios.get(`${API}/me`)
                this.user = data
            } catch (e) {
                if (e.response && e.response.status === 401) this.clear()
            } finally {
                this.loaded = true
            }
        },

        async login(email, password) {
            const { data } = await axios.post(`${API}/login`, { email, password })
            this.setSession(data.token, data.user)
            return data.user
        },

        async register(payload) {
            const { data } = await axios.post(`${API}/register`, payload)
            this.setSession(data.token, data.user)
            return data.user
        },

        async logout() {
            try {
                await axios.post(`${API}/logout`)
            } catch (e) { /* token may already be gone */ }
            this.clear()
        },
    },
})
