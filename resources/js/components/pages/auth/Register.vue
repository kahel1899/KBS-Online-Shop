<template>
    <div class="au">
        <div class="au__card">
            <h1 class="kbs-h2">Create your account</h1>
            <p class="kbs-muted au__sub">Track orders and check out faster next time.</p>

            <form novalidate @submit.prevent="submit">
                <v-text-field v-model="form.name" label="Full name" autocomplete="name" variant="outlined" density="comfortable" :error-messages="errors.name" />
                <v-text-field v-model="form.email" label="Email" type="email" autocomplete="email" variant="outlined" density="comfortable" :error-messages="errors.email" />
                <v-text-field v-model="form.phone" label="Mobile number (optional)" type="tel" autocomplete="tel" variant="outlined" density="comfortable" :error-messages="errors.phone" />
                <v-text-field
                    v-model="form.password"
                    label="Password"
                    :type="show ? 'text' : 'password'"
                    autocomplete="new-password"
                    hint="At least 8 characters"
                    variant="outlined"
                    density="comfortable"
                    :append-inner-icon="show ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                    :error-messages="errors.password"
                    @click:append-inner="show = !show"
                />
                <v-text-field
                    v-model="form.password_confirmation"
                    label="Confirm password"
                    :type="show ? 'text' : 'password'"
                    autocomplete="new-password"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="errors.password_confirmation"
                />

                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>

                <v-btn type="submit" block size="large" color="primary" variant="flat" :loading="loading">Create account</v-btn>
            </form>

            <p class="au__alt">
                Already have an account?
                <router-link :to="{ name: 'Login', query: $route.query }">Log in</router-link>
            </p>
        </div>
    </div>
</template>

<script>
import { useAuthStore } from '../../../stores/auth.js'
import { apiError } from '../../../utils.js'

export default {
    data() {
        return {
            auth: useAuthStore(),
            form: { name: '', email: '', phone: '', password: '', password_confirmation: '' },
            show: false,
            loading: false,
            error: '',
            errors: {},
        }
    },

    methods: {
        async submit() {
            this.error = ''
            const f = this.form
            const e = {}
            if (!f.name.trim()) e.name = 'Enter your name'
            if (!/^\S+@\S+\.\S+$/.test(f.email.trim())) e.email = 'Enter a valid email address'
            if (f.password.length < 8) e.password = 'Use at least 8 characters'
            if (f.password !== f.password_confirmation) e.password_confirmation = 'The passwords do not match'
            this.errors = e
            if (Object.keys(e).length) return

            this.loading = true
            try {
                await this.auth.register({
                    name: f.name.trim(),
                    email: f.email.trim(),
                    phone: f.phone.trim() || null,
                    password: f.password,
                    password_confirmation: f.password_confirmation,
                })

                const redirect = String(this.$route.query.redirect || '')
                const safe = redirect.startsWith('/') && !redirect.startsWith('//')
                this.$router.push(safe ? redirect : '/')
            } catch (err) {
                const fieldErrors = err.response && err.response.data && err.response.data.errors
                if (fieldErrors) {
                    const mapped = {}
                    Object.keys(fieldErrors).forEach(k => { mapped[k] = fieldErrors[k][0] })
                    this.errors = mapped
                }
                this.error = apiError(err, 'We could not create your account.')
            } finally {
                this.loading = false
            }
        },
    },
}
</script>

<style scoped>
.au {
    padding: 48px 20px;
    display: flex;
    justify-content: center;
}

.au__card {
    width: 100%;
    max-width: 440px;
    padding: 28px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 20px;
}

.au__sub {
    margin: 6px 0 20px;
}

.au__alt {
    margin: 18px 0 0;
    text-align: center;
    color: var(--kbs-muted);
}

.au__alt a {
    color: var(--kbs-brand);
    font-weight: 600;
}
</style>
