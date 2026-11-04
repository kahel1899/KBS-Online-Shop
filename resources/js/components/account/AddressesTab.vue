<template>
    <div>
        <div class="bar">
            <p class="muted">Saved addresses make checkout faster.</p>
            <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="openNew">Add address</v-btn>
        </div>

        <div v-if="loading" class="muted">Loading…</div>

        <v-alert v-else-if="error" type="error" variant="tonal">{{ error }}</v-alert>

        <div v-else-if="!addresses.length" class="empty">
            <v-icon size="40" color="secondary">mdi-map-marker-outline</v-icon>
            <p class="empty__title">No saved addresses</p>
            <p class="muted">Add the address where you want your orders delivered.</p>
        </div>

        <div v-else class="grid">
            <article v-for="a in addresses" :key="a.id" class="card">
                <div class="card__top">
                    <strong>{{ a.label || a.recipient_name }}</strong>
                    <span v-if="a.is_default" class="badge">Default</span>
                </div>
                <p>
                    {{ a.recipient_name }}<br />
                    {{ a.phone }}<br />
                    {{ line(a) }}
                </p>
                <div class="card__actions">
                    <v-btn size="small" variant="text" color="primary" @click="openEdit(a)">Edit</v-btn>
                    <v-btn v-if="!a.is_default" size="small" variant="text" @click="makeDefault(a)">Make default</v-btn>
                    <v-btn size="small" variant="text" color="error" @click="remove(a)">Remove</v-btn>
                </div>
            </article>
        </div>

        <v-dialog v-model="dialog" max-width="520" scrollable>
            <v-card class="dlg">
                <v-card-title class="dlg__title">{{ editingId ? 'Edit address' : 'Add address' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="form.label" label="Label (Home, Work…)" variant="outlined" density="comfortable" />
                    <v-text-field v-model="form.recipient_name" label="Recipient name" variant="outlined" density="comfortable" :error-messages="errors.recipient_name" />
                    <v-text-field v-model="form.phone" label="Mobile number" type="tel" variant="outlined" density="comfortable" :error-messages="errors.phone" />
                    <v-text-field v-model="form.line1" label="House no., street" variant="outlined" density="comfortable" :error-messages="errors.line1" />
                    <v-text-field v-model="form.line2" label="Building, unit, landmark (optional)" variant="outlined" density="comfortable" />
                    <v-text-field v-model="form.barangay" label="Barangay" variant="outlined" density="comfortable" />
                    <v-text-field v-model="form.city" label="City / Municipality" variant="outlined" density="comfortable" :error-messages="errors.city" />
                    <v-text-field v-model="form.province" label="Province" variant="outlined" density="comfortable" :error-messages="errors.province" />
                    <v-text-field v-model="form.postal_code" label="Postal code (optional)" variant="outlined" density="comfortable" />
                    <v-checkbox v-model="form.is_default" label="Use as my default address" density="comfortable" hide-details />
                    <v-alert v-if="formError" type="error" variant="tonal" density="compact" class="mt-3">{{ formError }}</v-alert>
                </v-card-text>
                <v-card-actions class="dlg__actions">
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" :loading="saving" @click="save">Save address</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios'
import { API, apiError } from '../../utils.js'

const blank = () => ({
    label: '', recipient_name: '', phone: '', line1: '', line2: '',
    barangay: '', city: '', province: '', postal_code: '', is_default: false,
})

export default {
    data() {
        return {
            addresses: [],
            loading: true,
            error: '',
            dialog: false,
            editingId: null,
            form: blank(),
            errors: {},
            formError: '',
            saving: false,
        }
    },

    mounted() {
        this.load()
    },

    methods: {
        line(a) {
            return [a.line1, a.line2, a.barangay, a.city, a.province, a.postal_code].filter(Boolean).join(', ')
        },

        async load() {
            this.loading = true
            this.error = ''
            try {
                const { data } = await axios.get(`${API}/account/addresses`)
                this.addresses = data
            } catch (e) {
                this.error = apiError(e)
            } finally {
                this.loading = false
            }
        },

        openNew() {
            this.editingId = null
            this.form = blank()
            this.errors = {}
            this.formError = ''
            this.dialog = true
        },

        openEdit(a) {
            this.editingId = a.id
            this.form = { ...blank(), ...a, line2: a.line2 || '', barangay: a.barangay || '', postal_code: a.postal_code || '', label: a.label || '' }
            this.errors = {}
            this.formError = ''
            this.dialog = true
        },

        async save() {
            const f = this.form
            const e = {}
            if (!f.recipient_name.trim()) e.recipient_name = 'Enter the recipient name'
            if (!/^[0-9+\-\s()]{7,20}$/.test(f.phone.trim())) e.phone = 'Enter a valid mobile number'
            if (!f.line1.trim()) e.line1 = 'Enter the house number and street'
            if (!f.city.trim()) e.city = 'Enter the city'
            if (!f.province.trim()) e.province = 'Enter the province'
            this.errors = e
            if (Object.keys(e).length) return

            this.saving = true
            this.formError = ''
            const payload = {
                label: f.label.trim() || null,
                recipient_name: f.recipient_name.trim(),
                phone: f.phone.trim(),
                line1: f.line1.trim(),
                line2: f.line2.trim() || null,
                barangay: f.barangay.trim() || null,
                city: f.city.trim(),
                province: f.province.trim(),
                postal_code: f.postal_code.trim() || null,
                is_default: !!f.is_default,
            }
            try {
                if (this.editingId) await axios.put(`${API}/account/addresses/${this.editingId}`, payload)
                else await axios.post(`${API}/account/addresses`, payload)
                this.dialog = false
                await this.load()
            } catch (err) {
                this.formError = apiError(err)
            } finally {
                this.saving = false
            }
        },

        async makeDefault(a) {
            try {
                await axios.put(`${API}/account/addresses/${a.id}`, { ...a, is_default: true })
                await this.load()
            } catch (e) {
                this.error = apiError(e)
            }
        },

        async remove(a) {
            if (!window.confirm('Remove this address?')) return
            try {
                await axios.delete(`${API}/account/addresses/${a.id}`)
                await this.load()
            } catch (e) {
                this.error = apiError(e)
            }
        },
    },
}
</script>

<style scoped>
.bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.bar p,
.muted {
    margin: 0;
    color: var(--kbs-muted);
}

.empty {
    padding: 48px 24px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
}

.empty__title {
    margin: 8px 0 2px;
    font-weight: 700;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
}

.card {
    padding: 16px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 14px;
}

.card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.card p {
    margin: 8px 0;
    line-height: 1.5;
    color: var(--kbs-muted);
}

.card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
    margin-left: -8px;
}

.badge {
    padding: 2px 10px;
    border-radius: 999px;
    background: #ffe3f1;
    color: var(--kbs-brand);
    font-size: 12px;
    font-weight: 700;
}

.dlg {
    border-radius: 16px;
}

.dlg__title {
    font-family: var(--kbs-font-display);
    font-weight: 600;
    padding: 18px 24px 4px;
}

.dlg__actions {
    padding: 8px 20px 16px;
}
</style>
