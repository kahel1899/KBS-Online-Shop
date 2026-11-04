<template>
    <div class="ki">
        <v-progress-linear
            v-if="loadingProducts"
            indeterminate
            color="primary"
            height="3"
            class="ki-progress"
        />

        <v-container fluid class="ki-wrap">

            <!-- HEADER -->
            <header class="ki-head">
                <div class="ki-head__text">
                    <h1 class="ki-title">Stock</h1>
                    <p class="ki-lede" aria-live="polite">{{ headline }}</p>
                </div>

                <div class="ki-head__actions">
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-cart-plus"
                        @click="openSaleDialog()"
                    >
                        Record sale
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="tonal"
                        prepend-icon="mdi-plus"
                        @click="openAddProduct"
                    >
                        Add product
                    </v-btn>
                    <v-btn
                        variant="outlined"
                        prepend-icon="mdi-history"
                        class="ki-outline-btn"
                        :loading="loadingSales"
                        @click="getSales"
                    >
                        Sales history
                    </v-btn>
                </div>
            </header>

            <!-- FIND + SORT -->
            <div class="ki-tools">
                <v-text-field
                    v-model="search"
                    label="Search products"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    clearable
                    class="ki-search"
                />

                <v-select
                    v-model="sortBy"
                    :items="sortOptions"
                    item-title="title"
                    item-value="value"
                    label="Sort by"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    class="ki-sort"
                />

                <v-btn
                    :variant="onlyLow ? 'tonal' : 'outlined'"
                    :color="onlyLow ? 'warning' : undefined"
                    :aria-pressed="onlyLow"
                    class="ki-outline-btn"
                    prepend-icon="mdi-alert-outline"
                    @click="onlyLow = !onlyLow"
                >
                    Low stock ({{ stats.low }})
                </v-btn>
            </div>

            <!-- PRODUCTS -->
            <div v-if="visibleProducts.length" class="ki-grid">
                <article
                    v-for="product in visibleProducts"
                    :key="product.id"
                    class="kbs-panel ki-card"
                >
                    <div class="ki-media">
                        <v-img
                            v-if="product.image"
                            :src="product.image"
                            :alt="product.name"
                            aspect-ratio="4/3"
                            cover
                        >
                            <template #error>
                                <div class="ki-media__fallback">
                                    <v-icon size="32">mdi-image-off-outline</v-icon>
                                </div>
                            </template>
                        </v-img>
                        <div v-else class="ki-media__fallback">
                            <v-icon size="32">mdi-image-outline</v-icon>
                        </div>

                        <div class="ki-media__actions">
                            <v-btn
                                icon
                                size="small"
                                variant="flat"
                                color="white"
                                :aria-label="`Edit ${product.name}`"
                                @click="editProduct(product)"
                            >
                                <v-icon size="18" color="primary">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                size="small"
                                variant="flat"
                                color="white"
                                :aria-label="`Delete ${product.name}`"
                                @click="deleteProduct(product)"
                            >
                                <v-icon size="18" color="error">mdi-delete-outline</v-icon>
                            </v-btn>
                        </div>
                    </div>

                    <div class="ki-body">
                        <h2 class="ki-name">{{ product.name }}</h2>

                        <v-chip
                            size="small"
                            :color="stockTone(product)"
                            variant="tonal"
                            class="ki-chip"
                        >
                            {{ stockText(product) }}
                        </v-chip>

                        <span
                            class="ki-shop"
                            :class="{ 'is-live': product.is_active && Number(product.online_price) > 0 }"
                        >
                            {{ product.is_active && Number(product.online_price) > 0 ? 'Live in shop' : 'Not in shop' }}
                        </span>

                        <dl class="ki-prices">
                            <div v-for="row in priceRows(product)" :key="row.name">
                                <dt>
                                    <span class="ki-dot" :style="{ background: row.color }"></span>
                                    {{ row.name }}
                                </dt>
                                <dd>{{ peso(row.value) }}</dd>
                            </div>
                        </dl>

                        <div class="ki-cost">
                            <span>Cost {{ peso(product.cost) }}</span>
                            <span v-if="margin(product) !== null">
                                Margin {{ margin(product) }}%
                            </span>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else-if="!loadingProducts" class="kbs-panel ki-empty">
                <v-icon size="40" color="secondary">mdi-archive-outline</v-icon>

                <template v-if="products.length === 0">
                    <p class="ki-empty__title">No products yet</p>
                    <p class="ki-empty__text">
                        Add your first product to start tracking stock and sales.
                    </p>
                    <v-btn color="primary" class="mt-3" @click="openAddProduct">
                        Add product
                    </v-btn>
                </template>

                <template v-else>
                    <p class="ki-empty__title">No products match</p>
                    <p class="ki-empty__text">
                        Try a different search, or clear the filters.
                    </p>
                    <v-btn variant="tonal" color="primary" class="mt-3" @click="clearFilters">
                        Clear filters
                    </v-btn>
                </template>
            </div>

            <!-- ADD / EDIT PRODUCT -->
            <v-dialog v-model="dialog" max-width="560">
                <v-card class="ki-dialog">
                    <v-card-title class="ki-dialog__title">
                        {{ title === 'Add' ? 'Add product' : 'Edit product' }}
                    </v-card-title>

                    <v-card-text>
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="newProduct.name"
                                    label="Product name"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[v => !!String(v || '').trim() || 'Enter a product name']"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-text-field
                                    v-model="newProduct.image"
                                    label="Image URL (optional)"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                />
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model.number="newProduct.quantity"
                                    label="Stock quantity"
                                    type="number"
                                    min="0"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[nonNegative]"
                                />
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model.number="newProduct.cost"
                                    label="Cost per unit"
                                    type="number"
                                    min="0"
                                    prefix="₱"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[nonNegative]"
                                />
                            </v-col>

                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model.number="newProduct.price"
                                    label="Walk-in price"
                                    type="number"
                                    min="0"
                                    prefix="₱"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[nonNegative]"
                                />
                            </v-col>

                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model.number="newProduct.tiktok_price"
                                    label="TikTok price"
                                    type="number"
                                    min="0"
                                    prefix="₱"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[nonNegative]"
                                />
                            </v-col>

                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model.number="newProduct.shopee_price"
                                    label="Shopee price"
                                    type="number"
                                    min="0"
                                    prefix="₱"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[nonNegative]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <div class="ki-shopsec">Online shop</div>
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model.number="newProduct.online_price"
                                    label="Online price"
                                    type="number"
                                    min="0"
                                    prefix="₱"
                                    hint="0 keeps it out of the shop"
                                    persistent-hint
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[nonNegative]"
                                />
                            </v-col>

                            <v-col cols="12" sm="6">
                                <label class="kbs-field-label" for="product-category">Category</label>
                                <select id="product-category" v-model="newProduct.category_id" class="kbs-select ki-fullselect">
                                    <option :value="null">No category</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </v-col>

                            <v-col cols="12">
                                <label class="kbs-field-label" for="product-description">Description</label>
                                <textarea
                                    id="product-description"
                                    v-model="newProduct.description"
                                    class="kbs-textarea"
                                    rows="3"
                                    placeholder="What it is, shade or size, how to use it…"
                                ></textarea>
                            </v-col>

                            <v-col cols="12">
                                <label class="kbs-field-label" for="product-images">More picture links (one per line)</label>
                                <textarea
                                    id="product-images"
                                    v-model="newProduct.imagesText"
                                    class="kbs-textarea"
                                    rows="2"
                                    placeholder="https://…"
                                ></textarea>
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-switch
                                    v-model="newProduct.is_active"
                                    label="Show in the online shop"
                                    color="primary"
                                    hide-details
                                    density="comfortable"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-switch
                                    v-model="newProduct.is_featured"
                                    label="Feature on the home page"
                                    color="primary"
                                    hide-details
                                    density="comfortable"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-actions class="ki-dialog__actions">
                        <v-spacer></v-spacer>
                        <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
                        <v-btn
                            color="primary"
                            variant="flat"
                            :loading="saving"
                            :disabled="!productValid"
                            @click="addProducts"
                        >
                            {{ title === 'Add' ? 'Add product' : 'Save changes' }}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- DELETE CONFIRMATION -->
            <v-dialog v-model="deleteDialog" max-width="440">
                <v-card class="ki-dialog">
                    <v-card-title class="ki-dialog__title">
                        Delete {{ productToDelete ? productToDelete.name : 'product' }}?
                    </v-card-title>
                    <v-card-text>
                        This also deletes every sale recorded for this product, so your
                        dashboard totals will change. This can't be undone.
                    </v-card-text>
                    <v-card-actions class="ki-dialog__actions">
                        <v-spacer></v-spacer>
                        <v-btn variant="text" @click="deleteDialog = false">Keep product</v-btn>
                        <v-btn
                            color="error"
                            variant="flat"
                            :loading="deleting"
                            @click="confirmDelete"
                        >
                            Delete product
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- RECORD SALE -->
            <v-dialog v-model="saleDialog" max-width="520">
                <v-card class="ki-dialog">
                    <v-card-title class="ki-dialog__title">Record sale</v-card-title>

                    <v-card-text>
                        <DropSelect
                            v-model="newSale.product_id"
                            :items="productItems"
                            label="Product"
                            placeholder="Choose a product"
                            :hint="selectedSaleProduct ? `${selectedSaleProduct.quantity} in stock` : ''"
                        />

                        <DropSelect
                            v-model="newSale.platform"
                            :items="platformItems"
                            label="Platform"
                            placeholder="Choose a platform"
                            class="mt-4"
                        />

                        <v-row dense class="mt-1">
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model.number="newSale.quantity"
                                    label="Quantity sold"
                                    type="number"
                                    min="1"
                                    :max="selectedSaleProduct ? selectedSaleProduct.quantity : undefined"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[quantityRule]"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model.number="newSale.selling_price"
                                    label="Selling price each"
                                    type="number"
                                    min="0"
                                    prefix="₱"
                                    variant="outlined"
                                    density="comfortable"
                                    :hint="suggestedPrice > 0 ? `Listed at ${peso(suggestedPrice)}` : ''"
                                    persistent-hint
                                    :rules="[nonNegative]"
                                />
                            </v-col>
                        </v-row>

                        <div v-if="saleValid" class="ki-saletotal">
                            <div>
                                <span>Total</span>
                                <strong>{{ peso(saleTotals.revenue) }}</strong>
                            </div>
                            <div>
                                <span>Gross profit</span>
                                <strong :class="{ 'ki-neg': saleTotals.profit < 0 }">
                                    {{ peso(saleTotals.profit) }}
                                </strong>
                            </div>
                        </div>
                    </v-card-text>

                    <v-card-actions class="ki-dialog__actions">
                        <v-spacer></v-spacer>
                        <v-btn variant="text" @click="saleDialog = false">Cancel</v-btn>
                        <v-btn
                            color="primary"
                            variant="flat"
                            :loading="savingSale"
                            :disabled="!saleValid"
                            @click="recordSale"
                        >
                            Record sale
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- SALES HISTORY -->
            <v-dialog v-model="salesDialog" max-width="1040">
                <v-card class="ki-dialog">
                    <v-card-title class="ki-dialog__title ki-history__title">
                        <span>Sales history</span>
                        <v-btn
                            icon
                            variant="text"
                            size="small"
                            aria-label="Close sales history"
                            @click="salesDialog = false"
                        >
                            <v-icon>mdi-close</v-icon>
                        </v-btn>
                    </v-card-title>

                    <v-card-text>
                        <div v-if="!sales.length" class="ki-empty ki-empty--plain">
                            <p class="ki-empty__title">No sales yet</p>
                            <p class="ki-empty__text">
                                Sales you record will be listed here, newest first.
                            </p>
                        </div>

                        <template v-else>
                            <p class="ki-history__summary">
                                {{ sales.length }} {{ sales.length === 1 ? 'sale' : 'sales' }},
                                {{ peso(historyTotals.revenue) }} revenue,
                                {{ peso(historyTotals.profit) }} gross profit.
                            </p>

                            <v-table fixed-header height="440" class="ki-history">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Platform</th>
                                        <th class="text-right">Qty</th>
                                        <th class="text-right">Price</th>
                                        <th class="text-right">Revenue</th>
                                        <th class="text-right">Cost</th>
                                        <th class="text-right">Gross profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="sale in sales" :key="sale.id">
                                        <td class="text-no-wrap">{{ formatDate(sale.created_at) }}</td>
                                        <td>{{ sale.product?.name || 'Deleted product' }}</td>
                                        <td class="text-no-wrap">
                                            <span class="ki-dot" :style="{ background: platformColor(sale.platform) }"></span>
                                            {{ sale.platform || 'Unassigned' }}
                                        </td>
                                        <td class="text-right">{{ sale.quantity }}</td>
                                        <td class="text-right">{{ peso(sale.selling_price) }}</td>
                                        <td class="text-right">{{ peso(calculateRevenue(sale)) }}</td>
                                        <td class="text-right">{{ peso(calculateCost(sale)) }}</td>
                                        <td
                                            class="text-right font-weight-bold"
                                            :class="{ 'ki-neg': calculateGrossProfit(sale) < 0 }"
                                        >
                                            {{ peso(calculateGrossProfit(sale)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </template>
                    </v-card-text>
                </v-card>
            </v-dialog>

            <v-snackbar
                v-model="snack.show"
                :color="snack.color"
                timeout="3500"
                location="bottom"
            >
                {{ snack.text }}
                <template #actions>
                    <v-btn variant="text" @click="snack.show = false">Close</v-btn>
                </template>
            </v-snackbar>

        </v-container>
    </div>
</template>

<script>
import axios from "axios";
import DropSelect from '../DropSelect.vue'
import {
    API,
    PLATFORMS,
    peso,
    platformColor,
    saleRevenue,
    saleCost,
} from '../../utils.js'

// Products at or below this many units are flagged as low stock
const LOW_STOCK = 5

const emptyProduct = () => ({
    image: '',
    name: '',
    quantity: 0,
    price: 0,
    cost: 0,
    tiktok_price: 0,
    shopee_price: 0,
    category_id: null,
    description: '',
    online_price: 0,
    is_active: false,
    is_featured: false,
    imagesText: '',
})

const emptySale = () => ({
    product_id: null,
    platform: '',
    quantity: 1,
    selling_price: 0,
})

export default {
    components: { DropSelect },

    data() {
        return {
            products: [],
            loadingProducts: true,

            search: '',
            sortBy: 'name',
            onlyLow: false,
            sortOptions: [
                { title: 'Name, A to Z', value: 'name' },
                { title: 'Lowest stock first', value: 'stock-asc' },
                { title: 'Highest stock first', value: 'stock-desc' },
                { title: 'Newest first', value: 'newest' },
            ],

            dialog: false,
            title: 'Add',
            newProduct: emptyProduct(),
            categories: [],
            saving: false,

            deleteDialog: false,
            productToDelete: null,
            deleting: false,

            saleDialog: false,
            newSale: emptySale(),
            savingSale: false,
            platformNames: PLATFORMS.map(p => p.name),

            sales: [],
            salesDialog: false,
            loadingSales: false,

            snack: { show: false, text: '', color: 'success' },
        };
    },

    mounted() {
        this.getProducts();
        this.loadCategories();
    },

    computed: {
        stats() {
            let units = 0
            let value = 0
            let low = 0
            this.products.forEach(p => {
                const qty = Number(p.quantity) || 0
                units += qty
                value += qty * (Number(p.cost) || 0)
                if (qty <= LOW_STOCK) low += 1
            })
            return { count: this.products.length, units, value, low }
        },

        headline() {
            if (this.loadingProducts && !this.products.length) return 'Loading your products…'
            const s = this.stats
            if (!s.count) return 'No products yet. Add one to start tracking stock.'

            let text =
                `${s.units} ${s.units === 1 ? 'unit' : 'units'} across ` +
                `${s.count} ${s.count === 1 ? 'product' : 'products'}, ` +
                `worth ${peso(s.value)} at cost.`

            if (s.low) {
                text += ` ${s.low} ${s.low === 1 ? 'product is' : 'products are'} low or out of stock.`
            }
            return text
        },

        visibleProducts() {
            const term = (this.search || '').trim().toLowerCase()

            let list = this.products.filter(p => {
                if (term && !String(p.name || '').toLowerCase().includes(term)) return false
                if (this.onlyLow && Number(p.quantity) > LOW_STOCK) return false
                return true
            })

            const qty = p => Number(p.quantity) || 0
            switch (this.sortBy) {
                case 'stock-asc':
                    list = [...list].sort((a, b) => qty(a) - qty(b))
                    break
                case 'stock-desc':
                    list = [...list].sort((a, b) => qty(b) - qty(a))
                    break
                case 'newest':
                    list = [...list].sort((a, b) => b.id - a.id)
                    break
                default:
                    list = [...list].sort((a, b) =>
                        String(a.name).localeCompare(String(b.name))
                    )
            }
            return list
        },

        productItems() {
            return this.products.map(p => {
                const qty = Number(p.quantity) || 0
                return {
                    value: p.id,
                    title: p.name,
                    subtitle: qty < 1 ? 'Out of stock' : `${qty} in stock`,
                    disabled: qty < 1,
                }
            })
        },

        platformItems() {
            return this.platformNames.map(name => ({ value: name, title: name }))
        },

        selectedSaleProduct() {
            return this.products.find(
                product => product.id === this.newSale.product_id
            );
        },

        // The price you listed for the chosen product on the chosen platform
        suggestedPrice() {
            const p = this.selectedSaleProduct
            if (!p) return 0
            switch (this.newSale.platform) {
                case 'TikTok': return Number(p.tiktok_price) || 0
                case 'Shopee': return Number(p.shopee_price) || 0
                case 'Online': return Number(p.online_price) || 0
                case 'Walk-in': return Number(p.price) || 0
                default: return 0
            }
        },

        saleValid() {
            const p = this.selectedSaleProduct
            const qty = Number(this.newSale.quantity)
            const price = this.newSale.selling_price
            return (
                !!p &&
                !!this.newSale.platform &&
                Number.isInteger(qty) &&
                qty >= 1 &&
                qty <= Number(p.quantity) &&
                price !== '' &&
                price !== null &&
                Number(price) >= 0
            )
        },

        saleTotals() {
            const p = this.selectedSaleProduct
            const qty = Number(this.newSale.quantity) || 0
            const revenue = qty * (Number(this.newSale.selling_price) || 0)
            const cost = p ? qty * (Number(p.cost) || 0) : 0
            return { revenue, profit: revenue - cost }
        },

        productValid() {
            const p = this.newProduct
            const fields = ['quantity', 'cost', 'price', 'tiktok_price', 'shopee_price', 'online_price']
            return (
                !!String(p.name || '').trim() &&
                fields.every(f => p[f] === 0 || (p[f] !== '' && p[f] !== null && Number(p[f]) >= 0))
            )
        },

        historyTotals() {
            return this.sales.reduce(
                (sum, sale) => {
                    sum.revenue += saleRevenue(sale)
                    sum.profit += saleRevenue(sale) - saleCost(sale)
                    return sum
                },
                { revenue: 0, profit: 0 }
            )
        },
    },

    watch: {
        // Fill in the listed price when the product or platform changes
        'newSale.product_id'() {
            this.applySuggestedPrice()
        },
        'newSale.platform'() {
            this.applySuggestedPrice()
        },
    },

    methods: {
        peso,
        platformColor,

        notify(text, color = 'success') {
            this.snack = { show: true, text, color }
        },

        errorMessage(error, fallback) {
            const data = error.response?.data
            if (data?.errors) {
                const first = Object.values(data.errors)[0]
                if (first && first[0]) return first[0]
            }
            return data?.message || fallback
        },

        nonNegative(value) {
            return (value !== '' && value !== null && Number(value) >= 0) || 'Enter 0 or more'
        },

        quantityRule(value) {
            const p = this.selectedSaleProduct
            const n = Number(value)
            if (!Number.isInteger(n) || n < 1) return 'Enter a whole number, 1 or more'
            if (p && n > Number(p.quantity)) return `Only ${p.quantity} in stock`
            return true
        },

        stockTone(product) {
            const qty = Number(product.quantity) || 0
            if (qty <= 0) return 'error'
            if (qty <= LOW_STOCK) return 'warning'
            return undefined
        },

        stockText(product) {
            const qty = Number(product.quantity) || 0
            if (qty <= 0) return 'Out of stock'
            if (qty <= LOW_STOCK) return `Only ${qty} left`
            return `${qty} in stock`
        },

        priceRows(product) {
            return [
                { name: 'Walk-in', color: platformColor('Walk-in'), value: product.price },
                { name: 'TikTok', color: platformColor('TikTok'), value: product.tiktok_price },
                { name: 'Shopee', color: platformColor('Shopee'), value: product.shopee_price },
                { name: 'Online', color: platformColor('Online'), value: product.online_price },
            ]
        },

        margin(product) {
            const price = Number(product.price)
            if (!price) return null
            return Math.round(((price - Number(product.cost || 0)) / price) * 100)
        },

        clearFilters() {
            this.search = ''
            this.onlyLow = false
        },

        applySuggestedPrice() {
            if (this.suggestedPrice > 0) {
                this.newSale.selling_price = this.suggestedPrice
            }
        },

        async loadCategories() {
            try {
                const response = await axios.get(`${API}/admin/categories`);
                this.categories = response.data;
            } catch (error) {
                console.error(error);
            }
        },

        async getProducts() {
            this.loadingProducts = true
            try {
                const response = await axios.get(`${API}/kookuproducts`);
                this.products = response.data;
            } catch (error) {
                console.error(error);
                this.notify('Products could not be loaded. Check that the Laravel server is running.', 'error')
            } finally {
                this.loadingProducts = false
            }
        },

        openAddProduct() {
            this.title = 'Add'
            this.newProduct = emptyProduct()
            this.dialog = true
        },

        editProduct(product) {
            this.title = 'Edit'
            this.newProduct = {
                ...emptyProduct(),
                ...product,
                description: product.description || '',
                category_id: product.category_id || null,
                online_price: Number(product.online_price) || 0,
                is_active: !!product.is_active,
                is_featured: !!product.is_featured,
                imagesText: (product.images || []).map(i => i.url).join('\n'),
            };
            this.dialog = true
        },

        async addProducts() {
            if (!this.productValid) return
            this.saving = true

            // Send only the fields the API expects, as real numbers
            const p = this.newProduct
            const payload = {
                name: String(p.name).trim(),
                image: p.image || '',
                quantity: Number(p.quantity) || 0,
                price: Number(p.price) || 0,
                cost: Number(p.cost) || 0,
                tiktok_price: Number(p.tiktok_price) || 0,
                shopee_price: Number(p.shopee_price) || 0,
                category_id: p.category_id || null,
                description: (p.description || '').trim() || null,
                online_price: Number(p.online_price) || 0,
                is_active: !!p.is_active,
                is_featured: !!p.is_featured,
                images: String(p.imagesText || '')
                    .split('\n')
                    .map(line => line.trim())
                    .filter(Boolean),
            }

            try {
                if (this.title === 'Add') {
                    await axios.post(`${API}/kookuproducts`, payload);
                    this.notify(`${payload.name} added`)
                } else {
                    await axios.put(`${API}/kookuproducts/${p.id}`, payload);
                    this.notify(`${payload.name} updated`)
                }

                await this.getProducts();
                this.dialog = false;
            } catch (error) {
                console.error('ERROR:', error);
                this.notify(this.errorMessage(error, 'The product could not be saved.'), 'error')
            } finally {
                this.saving = false
            }
        },

        // Ask first, because deleting a product also deletes its sales
        deleteProduct(product) {
            this.productToDelete = product
            this.deleteDialog = true
        },

        async confirmDelete() {
            if (!this.productToDelete) return
            this.deleting = true
            const name = this.productToDelete.name

            try {
                await axios.delete(`${API}/kookuproducts/${this.productToDelete.id}`);
                await this.getProducts();
                this.deleteDialog = false
                this.notify(`${name} deleted`)
            } catch (error) {
                console.error(error);
                this.notify(this.errorMessage(error, 'The product could not be deleted.'), 'error')
            } finally {
                this.deleting = false
            }
        },

        // SALES
        openSaleDialog(product = null) {
            this.newSale = emptySale()
            if (product) this.newSale.product_id = product.id
            this.saleDialog = true;
        },

        async recordSale() {
            if (!this.saleValid) return
            this.savingSale = true

            const payload = {
                product_id: this.newSale.product_id,
                platform: this.newSale.platform,
                quantity: Number(this.newSale.quantity),
                selling_price: Number(this.newSale.selling_price),
            }

            try {
                const response = await axios.post(`${API}/sales`, payload);

                this.saleDialog = false;
                this.notify(
                    `Sale recorded. ${response.data.remaining_stock} left in stock.`
                )

                // Refresh products so the new stock shows
                await this.getProducts();
            } catch (error) {
                console.error('SALE ERROR:', error);
                this.notify(this.errorMessage(error, 'The sale could not be recorded.'), 'error')
            } finally {
                this.savingSale = false
            }
        },

        async getSales() {
            this.loadingSales = true
            try {
                const response = await axios.get(`${API}/sales`);
                this.sales = response.data;
                this.salesDialog = true;
            } catch (error) {
                console.error('SALES ERROR:', error);
                this.notify('Sales history could not be loaded.', 'error')
            } finally {
                this.loadingSales = false
            }
        },

        formatDate(date) {
            return new Date(date).toLocaleString('en-PH', {
                dateStyle: 'medium',
                timeStyle: 'short',
            });
        },

        calculateRevenue(sale) {
            return saleRevenue(sale)
        },

        calculateCost(sale) {
            return saleCost(sale)
        },

        calculateGrossProfit(sale) {
            return saleRevenue(sale) - saleCost(sale)
        },
    }
};
</script>

<style scoped>
.ki {
    position: relative;
}

.ki-progress {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 2;
}

.ki-wrap {
    max-width: 1280px;
    padding: 28px 24px 48px;
}

/* ---------- header ---------- */

.ki-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px 24px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.ki-head__text {
    flex: 1 1 420px;
    min-width: 0;
}

.ki-title {
    font-family: var(--kbs-font-display);
    font-size: 15px;
    font-weight: 600;
    color: var(--kbs-muted);
    margin: 0 0 6px;
}

.ki-lede {
    font-family: var(--kbs-font-display);
    font-size: clamp(22px, 3.2vw, 32px);
    font-weight: 600;
    line-height: 1.2;
    letter-spacing: -0.015em;
    margin: 0;
    max-width: 30em;
}

.ki-head__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.ki-outline-btn {
    border-color: var(--kbs-line);
}

/* ---------- tools ---------- */

.ki-tools {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
}

.ki-search {
    flex: 1 1 240px;
    max-width: 360px;
}

.ki-sort {
    flex: 0 1 220px;
}

/* ---------- product grid ---------- */

.ki-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}

.ki-card {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.ki-media {
    position: relative;
    background: #fdeaf4;
}

.ki-media__fallback {
    aspect-ratio: 4 / 3;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--kbs-brand-soft);
    background: #fdeaf4;
}

.ki-media__actions {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    gap: 6px;
}

.ki-body {
    padding: 14px 14px 14px;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.ki-name {
    font-family: var(--kbs-font-display);
    font-size: 16px;
    font-weight: 600;
    line-height: 1.25;
    margin: 0 0 8px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.5em;
}

.ki-shop {
    align-self: flex-start;
    margin: -6px 0 10px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--kbs-muted);
}

.ki-shop.is-live {
    color: var(--kbs-profit);
}

.ki-shopsec {
    margin-top: 6px;
    padding-top: 12px;
    border-top: 1px solid var(--kbs-line);
    font-family: var(--kbs-font-display);
    font-weight: 600;
    font-size: 16px;
}

.ki-fullselect {
    width: 100%;
}

.ki-chip {
    align-self: flex-start;
    margin-bottom: 12px;
}

.ki-prices {
    margin: 0;
    padding: 10px 0;
    border-top: 1px solid var(--kbs-line);
    border-bottom: 1px solid var(--kbs-line);
    font-size: 14px;
}

.ki-prices > div {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
}

.ki-prices dt {
    color: var(--kbs-muted);
    display: flex;
    align-items: center;
    gap: 8px;
}

.ki-prices dd {
    margin: 0;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.ki-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 2px;
}

.ki-cost {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-top: 10px;
    font-size: 13px;
    color: var(--kbs-muted);
}

/* ---------- empty states ---------- */

.ki-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 48px 24px;
}

.ki-empty--plain {
    border: 0;
    padding: 32px 12px;
}

.ki-empty__title {
    font-weight: 700;
    margin: 8px 0 0;
}

.ki-empty__text {
    color: var(--kbs-muted);
    margin: 4px 0 0;
    max-width: 30em;
}

/* ---------- dialogs ---------- */

.ki-dialog {
    border-radius: 16px;
}

.ki-dialog__title {
    font-family: var(--kbs-font-display);
    font-size: 20px;
    font-weight: 600;
    padding: 20px 24px 8px;
}

.ki-dialog__actions {
    padding: 8px 20px 18px;
    gap: 4px;
}

.ki-saletotal {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    margin-top: 16px;
    padding: 12px 14px;
    border-radius: 12px;
    background: var(--kbs-paper);
    border: 1px solid var(--kbs-line);
}

.ki-saletotal div {
    display: flex;
    flex-direction: column;
    font-size: 13px;
    color: var(--kbs-muted);
}

.ki-saletotal strong {
    font-size: 18px;
    color: var(--kbs-ink);
    font-variant-numeric: tabular-nums;
}

.ki-neg,
.ki-saletotal strong.ki-neg {
    color: var(--kbs-loss);
}

.ki-history__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ki-history__summary {
    margin: 0 0 12px;
    color: var(--kbs-muted);
}

.ki-history {
    font-variant-numeric: tabular-nums;
}

/* ---------- small screens ---------- */

@media (max-width: 600px) {
    .ki-wrap {
        padding: 20px 14px 40px;
    }

    .ki-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .ki-search,
    .ki-sort {
        max-width: none;
        flex: 1 1 100%;
    }
}

@media (max-width: 380px) {
    .ki-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
