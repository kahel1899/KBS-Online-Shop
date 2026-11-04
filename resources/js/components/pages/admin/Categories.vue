<template>
    <div class="ac">
        <v-container fluid class="ac__wrap">
            <header class="ac__head">
                <div>
                    <h1 class="ac__title">Categories</h1>
                    <p class="ac__lede">Group your products so customers can find them in the shop.</p>
                </div>
                <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="openNew">Add category</v-btn>
            </header>

            <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

            <div class="kbs-panel">
                <div v-if="loading" class="ac__empty">Loading…</div>

                <div v-else-if="!categories.length" class="ac__empty">
                    <v-icon size="40" color="secondary">mdi-shape-outline</v-icon>
                    <p class="ac__empty-title">No categories yet</p>
                    <p>Add your first category to organise the shop.</p>
                </div>

                <ul v-else class="ac__list">
                    <li v-for="c in categories" :key="c.id">
                        <div class="ac__thumb">
                            <img v-if="c.image" :src="c.image" alt="" />
                            <span v-else>{{ c.name.charAt(0) }}</span>
                        </div>
                        <div class="ac__info">
                            <strong>{{ c.name }}</strong>
                            <small>
                                {{ c.products_count }} {{ c.products_count === 1 ? 'product' : 'products' }},
                                order {{ c.sort_order }}
                            </small>
                        </div>
                        <span class="ac__state" :class="{ 'is-off': !c.is_active }">
                            {{ c.is_active ? 'Visible' : 'Hidden' }}
                        </span>
                        <div class="ac__btns">
                            <v-btn size="small" variant="text" color="primary" @click="openEdit(c)">Edit</v-btn>
                            <v-btn size="small" variant="text" color="error" @click="askDelete(c)">Delete</v-btn>
                        </div>
                    </li>
                </ul>
            </div>
        </v-container>

        <v-dialog v-model="dialog" max-width="520">
            <v-card class="ac__dlg">
                <v-card-title class="ac__dlg-title">{{ editingId ? 'Edit category' : 'Add category' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="form.name" label="Name" variant="outlined" density="comfortable" :error-messages="errors.name" />
                    <v-text-field v-model="form.image" label="Image link (optional)" variant="outlined" density="comfortable" />
                    <v-textarea v-model="form.description" label="Description (optional)" rows="2" auto-grow variant="outlined" density="comfortable" />
                    <v-text-field v-model.number="form.sort_order" label="Show order (smallest first)" type="number" min="0" variant="outlined" density="comfortable" />
                    <v-switch v-model="form.is_active" label="Visible in the shop" color="primary" hide-details density="comfortable" />
                    <v-alert v-if="formError" type="error" variant="tonal" density="compact" class="mt-3">{{ formError }}</v-alert>
                </v-card-text>
                <v-card-actions class="ac__dlg-actions">
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" :loading="saving" @click="save">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="440">
            <v-card class="ac__dlg">
                <v-card-title class="ac__dlg-title">Delete {{ toDelete ? toDelete.name : '' }}?</v-card-title>
                <v-card-text>
                    Its products are not deleted. They just become uncategorised.
                </v-card-text>
                <v-card-actions class="ac__dlg-actions">
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">Keep category</v-btn>
                    <v-btn color="error" variant="flat" :loading="deleting" @click="confirmDelete">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snack.show" :color="snack.color" timeout="3000">{{ snack.text }}</v-snackbar>
    </div>
</template>

<script>
import axios from 'axios'
import { API, apiError } from '../../../utils.js'

const blank = () => ({ name: '', image: '', description: '', sort_order: 0, is_active: true })

export default {
    data() {
        return {
            categories: [],
            loading: true,
            error: '',
            dialog: false,
            editingId: null,
            form: blank(),
            errors: {},
            formError: '',
            saving: false,
            deleteDialog: false,
            toDelete: null,
            deleting: false,
            snack: { show: false, text: '', color: 'success' },
        }
    },

    mounted() {
        this.load()
    },

    methods: {
        notify(text, color = 'success') {
            this.snack = { show: true, text, color }
        },

        async load() {
            this.loading = true
            this.error = ''
            try {
                const { data } = await axios.get(`${API}/admin/categories`)
                this.categories = data
            } catch (e) {
                this.error = apiError(e)
            } finally {
                this.loading = false
            }
        },

        openNew() {
            this.editingId = null
            this.form = { ...blank(), sort_order: this.categories.length + 1 }
            this.errors = {}
            this.formError = ''
            this.dialog = true
        },

        openEdit(c) {
            this.editingId = c.id
            this.form = {
                name: c.name,
                image: c.image || '',
                description: c.description || '',
                sort_order: c.sort_order,
                is_active: !!c.is_active,
            }
            this.errors = {}
            this.formError = ''
            this.dialog = true
        },

        async save() {
            this.errors = {}
            this.formError = ''
            if (!this.form.name.trim()) {
                this.errors.name = 'Enter a name'
                return
            }

            this.saving = true
            const payload = {
                name: this.form.name.trim(),
                image: this.form.image.trim() || null,
                description: this.form.description.trim() || null,
                sort_order: Number(this.form.sort_order) || 0,
                is_active: !!this.form.is_active,
            }
            try {
                if (this.editingId) await axios.put(`${API}/admin/categories/${this.editingId}`, payload)
                else await axios.post(`${API}/admin/categories`, payload)
                this.dialog = false
                this.notify(this.editingId ? 'Category updated' : 'Category added')
                await this.load()
            } catch (e) {
                this.formError = apiError(e)
            } finally {
                this.saving = false
            }
        },

        askDelete(c) {
            this.toDelete = c
            this.deleteDialog = true
        },

        async confirmDelete() {
            this.deleting = true
            try {
                await axios.delete(`${API}/admin/categories/${this.toDelete.id}`)
                this.deleteDialog = false
                this.notify('Category deleted')
                await this.load()
            } catch (e) {
                this.notify(apiError(e), 'error')
            } finally {
                this.deleting = false
            }
        },
    },
}
</script>

<style scoped>
.ac__wrap {
    max-width: 900px;
    padding: 28px 24px 48px;
}

.ac__head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.ac__title {
    font-family: var(--kbs-font-display);
    font-size: 15px;
    font-weight: 600;
    color: var(--kbs-muted);
    margin: 0 0 6px;
}

.ac__lede {
    font-family: var(--kbs-font-display);
    font-size: clamp(20px, 2.6vw, 26px);
    font-weight: 600;
    line-height: 1.2;
    margin: 0;
    max-width: 28em;
}

.ac__empty {
    padding: 48px 24px;
    text-align: center;
    color: var(--kbs-muted);
}

.ac__empty p {
    margin: 4px 0 0;
}

.ac__empty-title {
    font-weight: 700;
    color: var(--kbs-ink);
    margin-top: 8px !important;
}

.ac__list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.ac__list li {
    display: grid;
    grid-template-columns: 48px minmax(0, 1fr) auto auto;
    gap: 14px;
    align-items: center;
    padding: 12px 16px;
}

.ac__list li + li {
    border-top: 1px solid var(--kbs-line);
}

.ac__thumb {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #fdeaf4;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--kbs-font-display);
    font-weight: 700;
    font-size: 20px;
    color: var(--kbs-brand);
}

.ac__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ac__info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.ac__info small {
    color: var(--kbs-muted);
}

.ac__state {
    padding: 3px 10px;
    border-radius: 999px;
    background: #ddf4e8;
    color: #17694a;
    font-size: 12.5px;
    font-weight: 700;
}

.ac__state.is-off {
    background: #f1e7ec;
    color: var(--kbs-muted);
}

.ac__btns {
    display: flex;
}

.ac__dlg {
    border-radius: 16px;
}

.ac__dlg-title {
    font-family: var(--kbs-font-display);
    font-weight: 600;
    padding: 18px 24px 4px;
}

.ac__dlg-actions {
    padding: 8px 20px 16px;
}

@media (max-width: 600px) {
    .ac__list li {
        grid-template-columns: 48px minmax(0, 1fr);
    }

    .ac__state,
    .ac__btns {
        grid-column: 2;
    }
}
</style>
