<template>
    <div class="pf">
        <form class="pf__card" novalidate @submit.prevent="saveProfile">
            <h2>Your details</h2>
            <v-text-field v-model="profile.name" label="Full name" variant="outlined" density="comfortable" :error-messages="pErrors.name" />
            <v-text-field v-model="profile.email" label="Email" type="email" variant="outlined" density="comfortable" :error-messages="pErrors.email" />
            <v-text-field v-model="profile.phone" label="Mobile number" type="tel" variant="outlined" density="comfortable" :error-messages="pErrors.phone" />
            <v-alert v-if="pMessage" type="success" variant="tonal" density="compact" class="mb-3">{{ pMessage }}</v-alert>
            <v-alert v-if="pError" type="error" variant="tonal" density="compact" class="mb-3">{{ pError }}</v-alert>
            <v-btn type="submit" color="primary" variant="flat" :loading="pSaving">Save details</v-btn>
        </form>

        <form class="pf__card" novalidate @submit.prevent="savePassword">
            <h2>Change password</h2>
            <v-text-field v-model="pw.current_password" label="Current password" type="password" autocomplete="current-password" variant="outlined" density="comfortable" :error-messages="wErrors.current_password" />
            <v-text-field v-model="pw.password" label="New password" type="password" autocomplete="new-password" hint="At least 8 characters" variant="outlined" density="comfortable" :error-messages="wErrors.password" />
            <v-text-field v-model="pw.password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" variant="outlined" density="comfortable" :error-messages="wErrors.password_confirmation" />
            <v-alert v-if="wMessage" type="success" variant="tonal" density="compact" class="mb-3">{{ wMessage }}</v-alert>
            <v-alert v-if="wError" type="error" variant="tonal" density="compact" class="mb-3">{{ wError }}</v-alert>
            <v-btn type="submit" color="primary" variant="flat" :loading="wSaving">Update password</v-btn>
        </form>
    </div>
</template>

<script>
import axios from 'axios'
import { useAuthStore } from '../../stores/auth.js'
import { API, apiError } from '../../utils.js'

export default {
    data() {
        const auth = useAuthStore()
        return {
            auth,
            profile: { name: auth.user.name || '', email: auth.user.email || '', phone: auth.user.phone || '' },
            pErrors: {}, pError: '', pMessage: '', pSaving: false,
            pw: { current_password: '', password: '', password_confirmation: '' },
            wErrors: {}, wError: '', wMessage: '', wSaving: false,
        }
    },

    methods: {
        fieldErrors(err) {
            const e = err.response && err.response.data && err.response.data.errors
            const mapped = {}
            if (e) Object.keys(e).forEach(k => { mapped[k] = e[k][0] })
            return mapped
        },

        async saveProfile() {
            this.pError = ''
            this.pMessage = ''
            const e = {}
            if (!this.profile.name.trim()) e.name = 'Enter your name'
            if (!/^\S+@\S+\.\S+$/.test(this.profile.email.trim())) e.email = 'Enter a valid email address'
            this.pErrors = e
            if (Object.keys(e).length) return

            this.pSaving = true
            try {
                const { data } = await axios.put(`${API}/me`, {
                    name: this.profile.name.trim(),
                    email: this.profile.email.trim(),
                    phone: this.profile.phone.trim() || null,
                })
                this.auth.user = data
                this.pMessage = 'Your details were saved.'
            } catch (err) {
                this.pErrors = this.fieldErrors(err)
                this.pError = apiError(err)
            } finally {
                this.pSaving = false
            }
        },

        async savePassword() {
            this.wError = ''
            this.wMessage = ''
            const e = {}
            if (!this.pw.current_password) e.current_password = 'Enter your current password'
            if (this.pw.password.length < 8) e.password = 'Use at least 8 characters'
            if (this.pw.password !== this.pw.password_confirmation) e.password_confirmation = 'The passwords do not match'
            this.wErrors = e
            if (Object.keys(e).length) return

            this.wSaving = true
            try {
                await axios.put(`${API}/me/password`, this.pw)
                this.wMessage = 'Your password was updated.'
                this.pw = { current_password: '', password: '', password_confirmation: '' }
            } catch (err) {
                this.wErrors = this.fieldErrors(err)
                this.wError = apiError(err)
            } finally {
                this.wSaving = false
            }
        },
    },
}
</script>

<style scoped>
.pf {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
    align-items: start;
}

.pf__card {
    padding: 20px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.pf__card h2 {
    margin: 0 0 14px;
    font-family: var(--kbs-font-display);
    font-size: 19px;
    font-weight: 600;
}
</style>
