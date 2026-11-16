<template>
    <div class="iu">
        <ul v-if="modelValue.length" class="iu__grid" aria-label="Photos">
            <li v-for="(url, index) in modelValue" :key="url + index" class="iu__item">
                <img :src="url" alt="" />
                <span v-if="index === 0 && max > 1" class="iu__main">Main photo</span>

                <div class="iu__tools">
                    <button
                        v-if="index > 0"
                        type="button"
                        class="iu__btn"
                        aria-label="Make this the main photo"
                        title="Make main photo"
                        @click="makeMain(index)"
                    >
                        <v-icon size="16">mdi-star-outline</v-icon>
                    </button>
                    <button
                        type="button"
                        class="iu__btn iu__btn--danger"
                        aria-label="Remove this photo"
                        title="Remove"
                        @click="remove(index)"
                    >
                        <v-icon size="16">mdi-close</v-icon>
                    </button>
                </div>
            </li>

            <li v-if="busyCount" class="iu__item iu__item--busy" aria-live="polite">
                <v-progress-circular indeterminate size="24" width="3" color="primary" />
                <span>Uploading…</span>
            </li>
        </ul>

        <div class="iu__actions">
            <label class="iu__pick" :class="{ 'is-disabled': !canAdd }">
                <input
                    ref="file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    :multiple="max > 1"
                    :disabled="!canAdd"
                    @change="onPick"
                />
                <v-icon size="20">mdi-image-plus</v-icon>
                {{ modelValue.length ? 'Add another photo' : 'Upload a photo' }}
            </label>
            <span class="iu__hint">JPG, PNG or WebP. Photos are resized automatically.</span>
        </div>

        <div class="iu__link">
            <input
                v-model="link"
                type="url"
                class="iu__linkinput"
                placeholder="Or paste a picture link (https://…)"
                aria-label="Picture link"
                :disabled="!canAdd"
                @keydown.enter.prevent="addLink"
            />
            <v-btn size="small" variant="tonal" color="primary" :disabled="!canAdd || !link.trim()" @click="addLink">
                Add link
            </v-btn>
        </div>

        <p v-if="error" class="iu__error" role="alert">{{ error }}</p>
    </div>
</template>

<script>
import axios from 'axios'
import { API, apiError } from '../utils.js'

const MAX_SIDE = 1000          // longest side in pixels
const TARGET_BYTES = 450 * 1024 // keep each photo small enough for any database

export default {
    name: 'ImageUpload',

    props: {
        modelValue: { type: Array, default: () => [] },
        max: { type: Number, default: 8 },
    },

    emits: ['update:modelValue'],

    data() {
        return { busyCount: 0, error: '', link: '' }
    },

    computed: {
        canAdd() {
            return this.modelValue.length + this.busyCount < this.max
        },
    },

    methods: {
        async onPick(event) {
            const files = Array.from(event.target.files || [])
            event.target.value = '' // lets the same photo be picked again
            this.error = ''

            const room = this.max - this.modelValue.length - this.busyCount
            if (files.length > room) {
                this.error = `You can add up to ${this.max} ${this.max === 1 ? 'photo' : 'photos'}.`
            }

            for (const file of files.slice(0, Math.max(room, 0))) {
                await this.upload(file)
            }
        },

        async upload(file) {
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
                this.error = 'Please choose a JPG, PNG or WebP photo.'
                return
            }

            this.busyCount++
            try {
                const blob = await this.shrink(file)
                const form = new FormData()
                form.append('image', blob, 'photo.jpg')

                const { data } = await axios.post(`${API}/admin/images`, form)
                this.$emit('update:modelValue', [...this.modelValue, data.url])
            } catch (e) {
                this.error = e && e.shrinkFailed
                    ? 'This photo could not be read. Try a JPG or PNG.'
                    : apiError(e, 'The photo could not be uploaded.')
            } finally {
                this.busyCount--
            }
        },

        // Resize in the browser so phone photos (several MB) upload quickly
        async shrink(file) {
            let bitmap
            try {
                bitmap = await createImageBitmap(file)
            } catch (e) {
                const failure = new Error('unreadable')
                failure.shrinkFailed = true
                throw failure
            }

            const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height))
            const width = Math.max(1, Math.round(bitmap.width * scale))
            const height = Math.max(1, Math.round(bitmap.height * scale))

            const canvas = document.createElement('canvas')
            canvas.width = width
            canvas.height = height
            const ctx = canvas.getContext('2d')
            ctx.fillStyle = '#ffffff' // transparent PNGs get a white background
            ctx.fillRect(0, 0, width, height)
            ctx.drawImage(bitmap, 0, 0, width, height)
            if (bitmap.close) bitmap.close()

            let quality = 0.86
            let blob = await this.toBlob(canvas, quality)
            while (blob && blob.size > TARGET_BYTES && quality > 0.5) {
                quality -= 0.1
                blob = await this.toBlob(canvas, quality)
            }

            if (!blob) {
                const failure = new Error('unreadable')
                failure.shrinkFailed = true
                throw failure
            }
            return blob
        },

        toBlob(canvas, quality) {
            return new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality))
        },

        addLink() {
            const url = this.link.trim()
            if (!/^https?:\/\//i.test(url)) {
                this.error = 'A picture link must start with http:// or https://'
                return
            }
            this.error = ''
            this.$emit('update:modelValue', [...this.modelValue, url])
            this.link = ''
        },

        remove(index) {
            this.$emit('update:modelValue', this.modelValue.filter((_, i) => i !== index))
        },

        makeMain(index) {
            const list = [...this.modelValue]
            const [picked] = list.splice(index, 1)
            this.$emit('update:modelValue', [picked, ...list])
        },
    },
}
</script>

<style scoped>
.iu__grid {
    list-style: none;
    margin: 0 0 12px;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    gap: 10px;
}

.iu__item {
    position: relative;
    aspect-ratio: 1 / 1;
    border: 1px solid var(--kbs-line);
    border-radius: 12px;
    overflow: hidden;
    background: #fdeaf4;
}

.iu__item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.iu__item--busy {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 12px;
    color: var(--kbs-muted);
    background: #fff;
}

.iu__main {
    position: absolute;
    left: 6px;
    bottom: 6px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--kbs-brand);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}

.iu__tools {
    position: absolute;
    top: 6px;
    right: 6px;
    display: flex;
    gap: 4px;
}

.iu__btn {
    width: 26px;
    height: 26px;
    border: 0;
    border-radius: 50%;
    background: #fff;
    color: var(--kbs-ink);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(43, 27, 38, 0.25);
}

.iu__btn--danger {
    color: var(--kbs-loss);
}

.iu__btn:focus-visible {
    outline: 2px solid var(--kbs-ink);
    outline-offset: 1px;
}

.iu__actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.iu__pick {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 44px;
    padding: 0 18px;
    border-radius: 999px;
    background: var(--kbs-brand);
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}

.iu__pick:hover {
    background: #b32573;
}

.iu__pick.is-disabled {
    background: #e7d6df;
    color: #8b7783;
    cursor: not-allowed;
}

.iu__pick input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.iu__pick:focus-within {
    outline: 2px solid var(--kbs-ink);
    outline-offset: 2px;
}

.iu__hint {
    font-size: 12.5px;
    color: var(--kbs-muted);
}

.iu__link {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 10px;
}

.iu__linkinput {
    flex: 1;
    min-width: 0;
    height: 38px;
    padding: 0 12px;
    border: 1px solid rgba(43, 27, 38, 0.3);
    border-radius: 8px;
    background: #fff;
    font: inherit;
    font-size: 14px;
    color: var(--kbs-ink);
}

.iu__linkinput:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: -1px;
}

.iu__error {
    margin: 8px 0 0;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--kbs-loss);
}
</style>
