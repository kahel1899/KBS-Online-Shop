<template>
    <div class="au">
        <div class="au__card">
            <h1 class="kbs-h2">Welcome back</h1>
            <p class="kbs-muted au__sub">Log in to see your orders and use your saved addresses.</p>

            <form novalidate @submit.prevent="submit">
                <v-text-field
                    v-model="email"
                    label="Email"
                    type="email"
                    autocomplete="email"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="errors.email"
                />
                <v-text-field
                    v-model="password"
                    label="Password"
                    :type="show ? 'text' : 'password'"
                    autocomplete="current-password"
                    variant="outlined"
                    density="comfortable"
                    :append-inner-icon="show ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                    :error-messages="errors.password"
                    @click:append-inner="show = !show"
                />

                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>

                <v-btn type="submit" block size="large" color="primary" variant="flat" :loading="loading">Log in</v-btn>
            </form>

            <p class="au__alt">
                New here?
                <router-link :to="{ name: 'Register', query: $route.query }">Create an account</router-link>
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
            email: '',
            password: '',
            show: false,
            loading: false,
            error: '',
            errors: {},
        }
    },

    methods: {
        async submit() {
            this.error = ''
            this.errors = {}
            if (!this.email.trim()) this.errors.email = 'Enter your email'
            if (!this.password) this.errors.password = 'Enter your password'
            if (Object.keys(this.errors).length) return

            this.loading = true
            try {
                const user = await this.auth.login(this.email.trim(), this.password)

                // only follow redirects that stay on this site
                const redirect = String(this.$route.query.redirect || '')
                const safe = redirect.startsWith('/') && !redirect.startsWith('//')
                this.$router.push(safe ? redirect : user.role === 'admin' ? '/admin/dashboard' : '/')
            } catch (e) {
                this.error = apiError(e, 'We could not log you in.')
            } finally {
                this.loading = false
            }
        },
    },
}
</script>

<style scoped>
.au {
    padding: 56px 20px;
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
