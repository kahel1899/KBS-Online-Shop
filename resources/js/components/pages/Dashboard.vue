<template>
    <div class="kd">
        <v-progress-linear
            v-if="loading"
            indeterminate
            color="primary"
            height="3"
            class="kd-progress"
        />

        <v-container fluid class="kd-wrap">

            <!-- HEADER: one plain-language sentence leads the page -->
            <header class="kd-head">
                <div class="kd-head__text">
                    <h1 class="kd-title">Sales</h1>
                    <p class="kd-lede" aria-live="polite">{{ headline }}</p>
                </div>

                <v-btn-toggle
                    v-model="rangeValue"
                    mandatory
                    divided
                    variant="outlined"
                    density="comfortable"
                    color="primary"
                    rounded="lg"
                    aria-label="Time range"
                    class="kd-range"
                >
                    <v-btn
                        v-for="r in ranges"
                        :key="r.value"
                        :value="r.value"
                    >
                        {{ r.short }}
                    </v-btn>
                </v-btn-toggle>
            </header>

            <v-alert
                v-if="error"
                type="error"
                variant="tonal"
                class="mb-4"
            >
                {{ error }}
                <template #append>
                    <v-btn variant="text" @click="getSales">Try again</v-btn>
                </template>
            </v-alert>

            <!-- KPI BAND -->
            <section class="kd-kpis" aria-label="Summary for the selected period">
                <div v-for="k in kpis" :key="k.key" class="kd-kpi">
                    <div class="kd-kpi__label">{{ k.label }}</div>
                    <div class="kd-kpi__value">{{ k.value }}</div>
                    <div class="kd-kpi__sub">
                        <span v-if="k.note" class="kd-kpi__note">{{ k.note }}</span>
                        <span
                            v-if="k.delta"
                            class="kd-delta"
                            :class="k.delta.tone"
                        >
                            <v-icon size="14">
                                {{ k.delta.up ? 'mdi-arrow-up-right' : 'mdi-arrow-down-right' }}
                            </v-icon>
                            {{ k.delta.text }}
                            <span class="kd-sr">compared with the previous period</span>
                        </span>
                    </div>
                </div>
            </section>

            <!-- CHART -->
            <section class="kbs-panel kd-panel mt-4">
                <div class="kd-panel__head">
                    <div>
                        <h2 class="kd-panel__title">Revenue and gross profit</h2>
                        <p class="kd-panel__sub">
                            {{ range.months ? 'Totals for each month' : 'Totals for each day' }}
                        </p>
                    </div>

                    <div class="kd-legend" role="group" aria-label="Show or hide a line">
                        <button
                            type="button"
                            class="kd-legend__btn"
                            :class="{ 'is-off': !show.revenue }"
                            :aria-pressed="show.revenue"
                            @click="toggle('revenue')"
                        >
                            <span class="kd-swatch kd-swatch--revenue"></span>
                            Revenue
                        </button>
                        <button
                            type="button"
                            class="kd-legend__btn"
                            :class="{ 'is-off': !show.profit }"
                            :aria-pressed="show.profit"
                            @click="toggle('profit')"
                        >
                            <span class="kd-swatch kd-swatch--profit"></span>
                            Gross profit
                        </button>
                    </div>
                </div>

                <div ref="chartWrap" class="kd-chart">

                    <div
                        v-if="!rangeSales.length"
                        class="kd-empty"
                    >
                        <v-icon size="36" color="secondary">mdi-chart-line-variant</v-icon>
                        <p class="kd-empty__title">
                            {{ loading ? 'Loading sales…' : 'No sales in this period' }}
                        </p>
                        <p v-if="!loading" class="kd-empty__text">
                            Record a sale on the Inventory page and it will show up here.
                        </p>
                        <v-btn
                            v-if="!loading"
                            to="/admin/inventory"
                            variant="tonal"
                            color="primary"
                            class="mt-3"
                        >
                            Go to Inventory
                        </v-btn>
                    </div>

                    <template v-else>
                        <svg
                            :width="chartW"
                            :height="chartH"
                            :viewBox="`0 0 ${chartW} ${chartH}`"
                            class="kd-svg"
                            role="img"
                            tabindex="0"
                            :aria-label="chartLabel"
                            @pointermove="onMove"
                            @pointerdown="onMove"
                            @pointerleave="hover = null"
                            @blur="hover = null"
                            @keydown.left.prevent="step(-1)"
                            @keydown.right.prevent="step(1)"
                            @keydown.esc="hover = null"
                        >
                            <defs>
                                <linearGradient id="kd-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#FF80C7" stop-opacity="0.4" />
                                    <stop offset="1" stop-color="#FF80C7" stop-opacity="0" />
                                </linearGradient>
                            </defs>

                            <!-- Y AXIS: grid lines and ₱ labels -->
                            <g class="kd-axis">
                                <g v-for="t in yTicks" :key="t.value">
                                    <line
                                        :x1="plot.x0"
                                        :x2="plot.x1"
                                        :y1="t.y"
                                        :y2="t.y"
                                        :class="t.value === 0 ? 'kd-zero' : 'kd-grid'"
                                    />
                                    <text
                                        :x="plot.x0 - 10"
                                        :y="t.y"
                                        text-anchor="end"
                                        dominant-baseline="middle"
                                    >
                                        {{ t.label }}
                                    </text>
                                </g>

                                <!-- X AXIS: only as many labels as fit -->
                                <text
                                    v-for="l in xLabels"
                                    :key="l.i"
                                    :x="l.x"
                                    :y="plot.y1 + 22"
                                    text-anchor="middle"
                                >
                                    {{ l.text }}
                                </text>
                            </g>

                            <!-- SERIES -->
                            <path
                                v-if="show.revenue"
                                :d="revenueArea"
                                fill="url(#kd-fill)"
                            />
                            <path
                                v-if="show.revenue"
                                :d="revenueLine"
                                class="kd-line kd-line--revenue"
                            />
                            <path
                                v-if="show.profit"
                                :d="profitLine"
                                class="kd-line kd-line--profit"
                            />

                            <!-- Dots on every point when there are few of them -->
                            <template v-if="series.length <= 14">
                                <template v-for="(p, i) in series" :key="'dot-' + i">
                                    <circle
                                        v-if="show.revenue"
                                        :cx="xAt(i)"
                                        :cy="yAt(p.revenue)"
                                        r="3.5"
                                        class="kd-dot kd-dot--revenue"
                                    />
                                    <circle
                                        v-if="show.profit"
                                        :cx="xAt(i)"
                                        :cy="yAt(p.profit)"
                                        r="3.5"
                                        class="kd-dot kd-dot--profit"
                                    />
                                </template>
                            </template>

                            <!-- HOVER -->
                            <g v-if="hoverPoint" pointer-events="none">
                                <line
                                    :x1="hoverX"
                                    :x2="hoverX"
                                    :y1="plot.y0"
                                    :y2="plot.y1"
                                    class="kd-cross"
                                />
                                <circle
                                    v-if="show.revenue"
                                    :cx="hoverX"
                                    :cy="yAt(hoverPoint.revenue)"
                                    r="5.5"
                                    class="kd-dot kd-dot--revenue kd-dot--active"
                                />
                                <circle
                                    v-if="show.profit"
                                    :cx="hoverX"
                                    :cy="yAt(hoverPoint.profit)"
                                    r="5.5"
                                    class="kd-dot kd-dot--profit kd-dot--active"
                                />
                            </g>
                        </svg>

                        <div
                            v-if="hoverPoint"
                            class="kd-tip"
                            :style="tipStyle"
                            role="status"
                        >
                            <div class="kd-tip__title">{{ hoverPoint.title }}</div>
                            <div class="kd-tip__row">
                                <span><span class="kd-swatch kd-swatch--revenue"></span>Revenue</span>
                                <strong>{{ peso(hoverPoint.revenue) }}</strong>
                            </div>
                            <div class="kd-tip__row">
                                <span><span class="kd-swatch kd-swatch--cost"></span>Product cost</span>
                                <strong>{{ peso(hoverPoint.cost) }}</strong>
                            </div>
                            <div class="kd-tip__row">
                                <span><span class="kd-swatch kd-swatch--profit"></span>Gross profit</span>
                                <strong :class="{ 'kd-neg': hoverPoint.profit < 0 }">
                                    {{ peso(hoverPoint.profit) }}
                                </strong>
                            </div>
                            <div class="kd-tip__row kd-tip__row--muted">
                                <span>Orders</span>
                                <strong>{{ hoverPoint.orders }}</strong>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- PLATFORMS + BEST SELLERS -->
            <div class="kd-split mt-4">

                <section class="kbs-panel kd-panel">
                    <div class="kd-panel__head">
                        <div>
                            <h2 class="kd-panel__title">Sales by platform</h2>
                            <p class="kd-panel__sub">Share of revenue in this period</p>
                        </div>
                    </div>

                    <div v-if="!platformRows.length" class="kd-empty kd-empty--small">
                        <p class="kd-empty__title">No sales in this period</p>
                    </div>

                    <div v-else class="kd-platforms">
                        <svg
                            viewBox="0 0 176 176"
                            width="176"
                            height="176"
                            class="kd-donut"
                            role="img"
                            :aria-label="donutLabel"
                        >
                            <circle
                                cx="88"
                                cy="88"
                                r="70"
                                fill="none"
                                stroke="#F7E6EF"
                                stroke-width="22"
                            />
                            <circle
                                v-for="seg in platformRows.filter(r => r.revenue > 0)"
                                :key="seg.name"
                                cx="88"
                                cy="88"
                                r="70"
                                fill="none"
                                :stroke="seg.color"
                                stroke-width="22"
                                :stroke-dasharray="seg.dashArray"
                                :stroke-dashoffset="seg.dashOffset"
                                transform="rotate(-90 88 88)"
                                :opacity="activePlatform && activePlatform !== seg.name ? 0.3 : 1"
                                class="kd-seg"
                            />
                            <text x="88" y="86" text-anchor="middle" class="kd-donut__value">
                                {{ pesoShort(totals.revenue) }}
                            </text>
                            <text x="88" y="106" text-anchor="middle" class="kd-donut__label">
                                revenue
                            </text>
                        </svg>

                        <table class="kd-table">
                            <caption class="kd-sr">Revenue, profit and orders by platform</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Platform</th>
                                    <th scope="col" class="is-num">Revenue</th>
                                    <th scope="col" class="is-num">Profit</th>
                                    <th scope="col" class="is-num">Orders</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in platformRows"
                                    :key="row.name"
                                    @mouseenter="activePlatform = row.name"
                                    @mouseleave="activePlatform = null"
                                >
                                    <th scope="row">
                                        <span class="kd-platform">
                                            <span
                                                class="kd-dotlg"
                                                :style="{ background: row.color }"
                                            ></span>
                                            {{ row.name }}
                                        </span>
                                        <span class="kd-share">{{ row.share.toFixed(1) }}% of revenue</span>
                                    </th>
                                    <td class="is-num">{{ peso(row.revenue) }}</td>
                                    <td class="is-num" :class="{ 'kd-neg': row.profit < 0 }">
                                        {{ peso(row.profit) }}
                                    </td>
                                    <td class="is-num">
                                        {{ row.orders }}
                                        <span class="kd-share">{{ row.items }} {{ row.items === 1 ? 'item' : 'items' }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="kbs-panel kd-panel">
                    <div class="kd-panel__head">
                        <div>
                            <h2 class="kd-panel__title">Best sellers</h2>
                            <p class="kd-panel__sub">Most units sold in this period</p>
                        </div>
                    </div>

                    <div v-if="!bestSellers.length" class="kd-empty kd-empty--small">
                        <p class="kd-empty__title">No sales in this period</p>
                    </div>

                    <ol v-else class="kd-best">
                        <li v-for="b in bestSellers" :key="b.id" class="kd-best__item">
                            <v-avatar size="44" rounded="lg" color="#FFEAF4">
                                <v-img v-if="b.image" :src="b.image" cover />
                                <v-icon v-else color="secondary">mdi-image-outline</v-icon>
                            </v-avatar>

                            <div class="kd-best__body">
                                <div class="kd-best__row">
                                    <span class="kd-best__name">{{ b.name }}</span>
                                    <span class="kd-best__units">
                                        {{ b.units }} {{ b.units === 1 ? 'unit' : 'units' }}
                                    </span>
                                </div>
                                <div class="kd-bar" aria-hidden="true">
                                    <span :style="{ width: b.width + '%' }"></span>
                                </div>
                                <div class="kd-best__meta">
                                    {{ peso(b.revenue) }} revenue
                                </div>
                            </div>
                        </li>
                    </ol>
                </section>

            </div>
        </v-container>
    </div>
</template>


<script>
import axios from 'axios'
import {
    API,
    PLATFORMS,
    UNASSIGNED,
    peso,
    pesoShort,
    saleRevenue,
    saleCost,
} from '../../utils.js'

const DAY = 24 * 60 * 60 * 1000

const RANGES = [
    {
        value: '7d',
        short: '7 days',
        days: 7,
        phrase: 'the last 7 days',
        prevPhrase: 'the 7 days before',
    },
    {
        value: '30d',
        short: '30 days',
        days: 30,
        phrase: 'the last 30 days',
        prevPhrase: 'the 30 days before',
    },
    {
        value: '12m',
        short: '12 months',
        months: 12,
        phrase: 'the last 12 months',
        prevPhrase: 'the 12 months before',
    },
]

// Round a chart step to 1, 2, 2.5, 5 or 10 times a power of ten
function niceStep(span, intervals) {
    const raw = span / intervals
    const mag = Math.pow(10, Math.floor(Math.log10(raw)))
    const n = raw / mag
    const factor = n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10
    return factor * mag
}

// Only compare against a positive starting point. "Up 788% from a loss"
// would read as good news without telling the real story.
function percentChange(current, previous) {
    if (!(previous > 0)) return null
    return ((current - previous) / previous) * 100
}

export default {

    data() {
        return {
            sales: [],
            loading: true,
            error: '',

            rangeValue: '30d',
            ranges: RANGES,

            show: { revenue: true, profit: true },

            hover: null,
            activePlatform: null,

            chartW: 800,
            resizeObserver: null,
        }
    },

    computed: {

        range() {
            return RANGES.find(r => r.value === this.rangeValue)
        },

        /* ---------- time window ---------- */

        // One bucket per day (or per month for the 12 month view)
        buckets() {
            const r = this.range
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            const list = []

            if (r.months) {
                for (let i = r.months - 1; i >= 0; i--) {
                    const start = new Date(today.getFullYear(), today.getMonth() - i, 1)
                    const end = new Date(start.getFullYear(), start.getMonth() + 1, 1)
                    list.push({
                        start: start.getTime(),
                        end: end.getTime(),
                        label: start.toLocaleDateString('en-PH', { month: 'short' }),
                        title: start.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }),
                    })
                }
            } else {
                for (let i = r.days - 1; i >= 0; i--) {
                    const start = new Date(today)
                    start.setDate(start.getDate() - i)
                    const end = new Date(start)
                    end.setDate(end.getDate() + 1)
                    list.push({
                        start: start.getTime(),
                        end: end.getTime(),
                        label: start.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }),
                        title: start.toLocaleDateString('en-PH', {
                            weekday: 'short',
                            month: 'long',
                            day: 'numeric',
                        }),
                    })
                }
            }

            return list
        },

        windowStart() {
            return this.buckets[0].start
        },

        windowEnd() {
            return this.buckets[this.buckets.length - 1].end
        },

        // The same length of time, immediately before this one
        previousStart() {
            const r = this.range
            const start = new Date(this.windowStart)
            if (r.months) {
                return new Date(start.getFullYear(), start.getMonth() - r.months, 1).getTime()
            }
            const prev = new Date(start)
            prev.setDate(prev.getDate() - r.days)
            return prev.getTime()
        },

        rangeSales() {
            return this.sales.filter(s => s.t >= this.windowStart && s.t < this.windowEnd)
        },

        previousSales() {
            return this.sales.filter(s => s.t >= this.previousStart && s.t < this.windowStart)
        },

        /* ---------- totals ---------- */

        totals() {
            return this.sumSales(this.rangeSales)
        },

        previousTotals() {
            return this.sumSales(this.previousSales)
        },

        margin() {
            return this.totals.revenue > 0
                ? (this.totals.profit / this.totals.revenue) * 100
                : null
        },

        deltas() {
            const t = this.totals
            const p = this.previousTotals
            return {
                revenue: percentChange(t.revenue, p.revenue),
                cost: percentChange(t.cost, p.cost),
                profit: percentChange(t.profit, p.profit),
                items: percentChange(t.items, p.items),
            }
        },

        kpis() {
            const t = this.totals
            const d = this.deltas
            return [
                {
                    key: 'revenue',
                    label: 'Revenue',
                    value: peso(t.revenue),
                    delta: this.formatDelta(d.revenue, 'good-up'),
                },
                {
                    key: 'cost',
                    label: 'Product cost',
                    value: peso(t.cost),
                    // Spending more on stock is not good or bad by itself
                    delta: this.formatDelta(d.cost, 'neutral'),
                },
                {
                    key: 'profit',
                    label: 'Gross profit',
                    value: peso(t.profit),
                    note: this.margin === null ? '' : `${this.margin.toFixed(1)}% margin`,
                    delta: this.formatDelta(d.profit, 'good-up'),
                },
                {
                    key: 'items',
                    label: 'Items sold',
                    value: String(t.items),
                    note: `${t.orders} ${t.orders === 1 ? 'order' : 'orders'}`,
                    delta: this.formatDelta(d.items, 'good-up'),
                },
            ]
        },

        headline() {
            if (this.loading && !this.sales.length) return 'Loading your sales…'
            if (this.error) return 'Sales could not be loaded.'

            const t = this.totals
            if (t.orders === 0) return `No sales were recorded in ${this.range.phrase}.`

            const orders = `${t.orders} ${t.orders === 1 ? 'order' : 'orders'}`
            let text =
                t.profit >= 0
                    ? `You made ${peso(t.profit)} in gross profit from ${orders} in ${this.range.phrase}`
                    : `Your ${orders} in ${this.range.phrase} sold ${peso(Math.abs(t.profit))} below product cost`

            const d = this.deltas.profit
            if (d !== null && Math.abs(d) >= 1) {
                text += `, ${d > 0 ? 'up' : 'down'} ${Math.round(Math.abs(d))}% from ${this.range.prevPhrase}`
            }

            return text + '.'
        },

        /* ---------- chart ---------- */

        // One point per bucket. Days with no sales stay in as zero,
        // so the line shows real gaps instead of skipping them.
        series() {
            const points = this.buckets.map(b => ({
                label: b.label,
                title: b.title,
                revenue: 0,
                cost: 0,
                profit: 0,
                orders: 0,
            }))

            this.rangeSales.forEach(s => {
                const i = this.buckets.findIndex(b => s.t >= b.start && s.t < b.end)
                if (i === -1) return
                points[i].revenue += s.revenue
                points[i].cost += s.cost
                points[i].profit += s.revenue - s.cost
                points[i].orders += 1
            })

            return points
        },

        chartH() {
            return this.chartW < 520 ? 240 : 300
        },

        plot() {
            return {
                x0: 56,
                x1: this.chartW - 24,
                y0: 12,
                y1: this.chartH - 34,
            }
        },

        yScale() {
            const values = []
            this.series.forEach(p => {
                if (this.show.revenue) values.push(p.revenue)
                if (this.show.profit) values.push(p.profit)
            })

            const max = Math.max(0, ...values)
            const min = Math.min(0, ...values)
            const step = niceStep(Math.max(max - min, 1), 4)

            let niceMin = Math.floor(min / step) * step
            let niceMax = Math.ceil(max / step) * step
            if (niceMax === niceMin) niceMax = niceMin + step

            return { min: niceMin, max: niceMax, step }
        },

        yTicks() {
            const { min, max, step } = this.yScale
            const ticks = []
            for (let v = min; v <= max + step / 2; v += step) {
                const value = Math.round(v * 100) / 100
                ticks.push({ value, y: this.yAt(value), label: pesoShort(value) })
            }
            return ticks
        },

        xLabels() {
            const n = this.series.length
            const perLabel = this.range.months ? 44 : 56
            const fit = Math.max(1, Math.floor((this.plot.x1 - this.plot.x0) / perLabel))
            const every = Math.ceil(n / fit)

            const labels = []
            for (let i = 0; i < n; i++) {
                // count back from the latest point so "today" is always labelled
                if ((n - 1 - i) % every === 0) {
                    labels.push({ i, x: this.xAt(i), text: this.series[i].label })
                }
            }
            return labels
        },

        revenueLine() {
            return this.linePath('revenue')
        },

        profitLine() {
            return this.linePath('profit')
        },

        revenueArea() {
            const n = this.series.length
            const base = this.yAt(0).toFixed(1)
            return `${this.linePath('revenue')} L${this.xAt(n - 1).toFixed(1)} ${base} L${this.xAt(0).toFixed(1)} ${base} Z`
        },

        hoverPoint() {
            return this.hover === null ? null : this.series[this.hover] || null
        },

        hoverX() {
            return this.hover === null ? 0 : this.xAt(this.hover)
        },

        tipStyle() {
            const width = 210
            const flip = this.hoverX > this.chartW / 2
            const left = flip ? this.hoverX - width - 14 : this.hoverX + 14
            return {
                left: Math.max(4, left) + 'px',
                width: width + 'px',
            }
        },

        chartLabel() {
            const unit = this.range.months ? 'month' : 'day'
            return (
                `Revenue and gross profit by ${unit} for ${this.range.phrase}. ` +
                `Total revenue ${peso(this.totals.revenue)}, gross profit ${peso(this.totals.profit)}. ` +
                `Use the left and right arrow keys to read each ${unit}.`
            )
        },

        /* ---------- platforms ---------- */

        platformRows() {
            const known = PLATFORMS.map(p => ({ ...p }))
            const rows = [...known, { ...UNASSIGNED }].map(p => ({
                ...p,
                revenue: 0,
                cost: 0,
                orders: 0,
                items: 0,
            }))

            this.rangeSales.forEach(s => {
                const row = rows.find(r => r.name === s.platform) || rows[rows.length - 1]
                row.revenue += s.revenue
                row.cost += s.cost
                row.orders += 1
                row.items += Number(s.quantity) || 0
            })

            const used = rows.filter(r => r.orders > 0)
            const total = used.reduce((sum, r) => sum + r.revenue, 0)
            const circumference = 2 * Math.PI * 70
            const gap = used.filter(r => r.revenue > 0).length > 1 ? 3 : 0

            let accumulated = 0
            return used.map(r => {
                const fraction = total > 0 ? r.revenue / total : 0
                const length = fraction * circumference
                const dash = Math.max(length - gap, 0.01)
                const row = {
                    ...r,
                    profit: r.revenue - r.cost,
                    share: fraction * 100,
                    dashArray: `${dash} ${circumference - dash}`,
                    dashOffset: -accumulated * circumference,
                }
                accumulated += fraction
                return row
            })
        },

        donutLabel() {
            return (
                'Share of revenue by platform: ' +
                this.platformRows
                    .map(r => `${r.name} ${r.share.toFixed(0)} percent`)
                    .join(', ')
            )
        },

        /* ---------- best sellers ---------- */

        bestSellers() {
            const byProduct = new Map()

            this.rangeSales.forEach(s => {
                const id = s.product_id
                if (!byProduct.has(id)) {
                    byProduct.set(id, {
                        id,
                        name: s.product?.name || 'Deleted product',
                        image: s.product?.image || '',
                        units: 0,
                        revenue: 0,
                    })
                }
                const item = byProduct.get(id)
                item.units += Number(s.quantity) || 0
                item.revenue += s.revenue
            })

            const list = [...byProduct.values()]
                .sort((a, b) => b.units - a.units || b.revenue - a.revenue)
                .slice(0, 5)

            const top = list.length ? list[0].units : 1
            return list.map(item => ({
                ...item,
                width: Math.max(4, (item.units / top) * 100),
            }))
        },
    },

    watch: {
        rangeValue() {
            this.hover = null
        },
    },

    mounted() {
        this.getSales()

        // Draw the chart at the exact width of its card so text stays readable
        if (typeof ResizeObserver !== 'undefined') {
            this.resizeObserver = new ResizeObserver(entries => {
                const width = Math.floor(entries[0].contentRect.width)
                if (width > 0) this.chartW = Math.max(280, width)
            })
            this.resizeObserver.observe(this.$refs.chartWrap)
        }
    },

    beforeUnmount() {
        if (this.resizeObserver) this.resizeObserver.disconnect()
    },

    methods: {

        peso,
        pesoShort,

        async getSales() {
            this.loading = true
            this.error = ''

            try {
                const response = await axios.get(`${API}/sales`)

                this.sales = response.data.map(sale => {
                    const revenue = saleRevenue(sale)
                    const cost = saleCost(sale)
                    return {
                        ...sale,
                        t: new Date(sale.created_at).getTime(),
                        revenue,
                        cost,
                    }
                })
            } catch (error) {
                console.error('DASHBOARD SALES ERROR:', error)
                this.error =
                    'Sales could not be loaded. Check that the Laravel server is running, then try again.'
            } finally {
                this.loading = false
            }
        },

        sumSales(list) {
            return list.reduce(
                (sum, s) => {
                    sum.revenue += s.revenue
                    sum.cost += s.cost
                    sum.profit += s.revenue - s.cost
                    sum.items += Number(s.quantity) || 0
                    sum.orders += 1
                    return sum
                },
                { revenue: 0, cost: 0, profit: 0, items: 0, orders: 0 }
            )
        },

        formatDelta(value, mode) {
            if (value === null || Math.abs(value) < 0.5) return null
            const up = value > 0
            let tone = 'is-neutral'
            if (mode === 'good-up') tone = up ? 'is-good' : 'is-bad'
            return { up, tone, text: `${Math.round(Math.abs(value))}%` }
        },

        toggle(key) {
            const other = key === 'revenue' ? 'profit' : 'revenue'
            // keep at least one line on screen
            if (this.show[key] && !this.show[other]) return
            this.show[key] = !this.show[key]
        },

        xAt(i) {
            const n = this.series.length
            const { x0, x1 } = this.plot
            return n === 1 ? (x0 + x1) / 2 : x0 + (i / (n - 1)) * (x1 - x0)
        },

        yAt(value) {
            const { min, max } = this.yScale
            const { y0, y1 } = this.plot
            return y1 - ((value - min) / (max - min)) * (y1 - y0)
        },

        linePath(key) {
            return this.series
                .map((p, i) => `${i ? 'L' : 'M'}${this.xAt(i).toFixed(1)} ${this.yAt(p[key]).toFixed(1)}`)
                .join(' ')
        },

        onMove(event) {
            const rect = event.currentTarget.getBoundingClientRect()
            const x = event.clientX - rect.left
            const n = this.series.length
            const { x0, x1 } = this.plot
            const ratio = (x - x0) / (x1 - x0)
            this.hover = Math.max(0, Math.min(n - 1, Math.round(ratio * (n - 1))))
        },

        step(direction) {
            const n = this.series.length
            if (this.hover === null) {
                this.hover = direction > 0 ? 0 : n - 1
                return
            }
            this.hover = Math.max(0, Math.min(n - 1, this.hover + direction))
        },
    },
}
</script>


<style scoped>
.kd {
    position: relative;
}

.kd-progress {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 2;
}

.kd-wrap {
    max-width: 1200px;
    padding: 28px 24px 48px;
}

/* ---------- header ---------- */

.kd-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px 24px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.kd-head__text {
    flex: 1 1 420px;
    min-width: 0;
}

.kd-title {
    font-family: var(--kbs-font-display);
    font-size: 15px;
    font-weight: 600;
    color: var(--kbs-muted);
    margin: 0 0 6px;
}

.kd-lede {
    font-family: var(--kbs-font-display);
    font-size: clamp(22px, 3.2vw, 32px);
    font-weight: 600;
    line-height: 1.2;
    letter-spacing: -0.015em;
    margin: 0;
    max-width: 30em;
}

/* ---------- KPI band ---------- */

.kd-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1px;
    background: var(--kbs-line);
    border: 1px solid var(--kbs-line);
    border-radius: 16px;
    overflow: hidden;
}

.kd-kpi {
    background: #fff;
    padding: 18px 20px 16px;
    min-width: 0;
}

.kd-kpi__label {
    font-size: 14px;
    color: var(--kbs-muted);
}

.kd-kpi__value {
    font-family: var(--kbs-font-display);
    font-size: clamp(20px, 2.2vw, 27px);
    font-weight: 700;
    letter-spacing: -0.01em;
    font-variant-numeric: tabular-nums;
    margin: 4px 0 6px;
    overflow-wrap: anywhere;
}

.kd-kpi__sub {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px 10px;
    min-height: 22px;
    font-size: 13px;
}

.kd-kpi__note {
    color: var(--kbs-muted);
}

.kd-delta {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-weight: 600;
}

.kd-delta.is-good { color: var(--kbs-profit); }
.kd-delta.is-bad { color: var(--kbs-loss); }
.kd-delta.is-neutral { color: var(--kbs-muted); }

/* ---------- panels ---------- */

.kd-panel {
    padding: 20px 22px 22px;
    min-width: 0;
}

.kd-panel__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.kd-panel__title {
    font-family: var(--kbs-font-display);
    font-size: 19px;
    font-weight: 600;
    margin: 0;
}

.kd-panel__sub {
    font-size: 13px;
    color: var(--kbs-muted);
    margin: 2px 0 0;
}

.kd-split {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
    gap: 16px;
}

/* ---------- legend toggles ---------- */

.kd-legend {
    display: flex;
    gap: 8px;
}

.kd-legend__btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border: 1px solid var(--kbs-line);
    border-radius: 999px;
    background: #fff;
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    color: var(--kbs-ink);
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s;
}

.kd-legend__btn:hover {
    background: var(--kbs-paper);
}

.kd-legend__btn:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: 2px;
}

.kd-legend__btn.is-off {
    color: var(--kbs-muted);
    text-decoration: line-through;
}

.kd-legend__btn.is-off .kd-swatch {
    opacity: 0.35;
}

.kd-swatch {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
    margin-right: 6px;
    vertical-align: baseline;
}

.kd-legend__btn .kd-swatch {
    margin-right: 0;
}

.kd-swatch--revenue { background: var(--kbs-brand); }
.kd-swatch--profit { background: var(--kbs-profit); }
.kd-swatch--cost { background: #c9b4c0; }

/* ---------- chart ---------- */

.kd-chart {
    position: relative;
    width: 100%;
    overflow: hidden;
    min-height: 240px;
}

.kd-svg {
    display: block;
    touch-action: pan-y;
    border-radius: 8px;
    cursor: crosshair;
}

.kd-svg:focus-visible {
    outline: 2px solid var(--kbs-brand);
    outline-offset: 2px;
}

.kd-axis text {
    fill: var(--kbs-muted);
    font-size: 12px;
    font-family: var(--kbs-font-body);
}

.kd-grid {
    stroke: #f6e4ee;
    stroke-width: 1;
}

.kd-zero {
    stroke: #d9bccb;
    stroke-width: 1;
}

.kd-line {
    fill: none;
    stroke-width: 2.5;
    stroke-linejoin: round;
    stroke-linecap: round;
}

.kd-line--revenue { stroke: var(--kbs-brand); }
.kd-line--profit { stroke: var(--kbs-profit); }

.kd-dot {
    stroke: #fff;
    stroke-width: 2;
}

.kd-dot--revenue { fill: var(--kbs-brand); }
.kd-dot--profit { fill: var(--kbs-profit); }
.kd-dot--active { stroke-width: 2.5; }

.kd-cross {
    stroke: #c9a8bb;
    stroke-width: 1;
    stroke-dasharray: 3 3;
}

.kd-tip {
    position: absolute;
    top: 8px;
    padding: 10px 12px;
    background: #fff;
    border: 1px solid var(--kbs-line);
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(43, 27, 38, 0.12);
    font-size: 13px;
    pointer-events: none;
}

.kd-tip__title {
    font-weight: 700;
    margin-bottom: 6px;
}

.kd-tip__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 2px 0;
    font-variant-numeric: tabular-nums;
}

.kd-tip__row--muted {
    color: var(--kbs-muted);
    border-top: 1px solid var(--kbs-line);
    margin-top: 4px;
    padding-top: 6px;
}

.kd-neg {
    color: var(--kbs-loss);
}

/* ---------- empty states ---------- */

.kd-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-height: 240px;
    padding: 24px;
}

.kd-empty--small {
    min-height: 140px;
}

.kd-empty__title {
    font-weight: 700;
    margin: 8px 0 0;
}

.kd-empty__text {
    color: var(--kbs-muted);
    margin: 4px 0 0;
    max-width: 30em;
}

/* ---------- platforms ---------- */

.kd-platforms {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.kd-donut {
    flex: 0 0 auto;
    margin: 0 auto;
}

.kd-seg {
    transition: opacity 0.15s;
}

.kd-donut__value {
    font-family: var(--kbs-font-display);
    font-size: 24px;
    font-weight: 700;
    fill: var(--kbs-ink);
}

.kd-donut__label {
    font-size: 12px;
    fill: var(--kbs-muted);
}

.kd-table {
    flex: 1 1 280px;
    min-width: 0;
    border-collapse: collapse;
    font-size: 14px;
}

.kd-table th,
.kd-table td {
    padding: 10px 6px;
    text-align: left;
    vertical-align: top;
    border-bottom: 1px solid var(--kbs-line);
    font-weight: 400;
}

.kd-table thead th {
    font-size: 12px;
    color: var(--kbs-muted);
    font-weight: 600;
    padding-top: 0;
}

.kd-table tbody tr:last-child th,
.kd-table tbody tr:last-child td {
    border-bottom: 0;
}

.kd-table .is-num {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.kd-table tbody th {
    font-weight: 600;
}

.kd-platform {
    display: flex;
    align-items: center;
    gap: 8px;
}

.kd-dotlg {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex: 0 0 auto;
}

.kd-share {
    display: block;
    font-size: 12px;
    font-weight: 400;
    color: var(--kbs-muted);
}

/* ---------- best sellers ---------- */

.kd-best {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.kd-best__item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.kd-best__body {
    flex: 1;
    min-width: 0;
}

.kd-best__row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 14px;
}

.kd-best__name {
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kd-best__units {
    flex: 0 0 auto;
    font-variant-numeric: tabular-nums;
}

.kd-best__meta {
    font-size: 12px;
    color: var(--kbs-muted);
    margin-top: 4px;
}

.kd-bar {
    height: 6px;
    border-radius: 999px;
    background: #f7e6ef;
    margin-top: 6px;
    overflow: hidden;
}

.kd-bar span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: var(--kbs-brand-soft);
}

/* visually hidden, still read by screen readers */
.kd-sr {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}

/* ---------- small screens ---------- */

@media (max-width: 960px) {
    .kd-split {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 800px) {
    .kd-kpis {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .kd-wrap {
        padding: 20px 14px 40px;
    }

    .kd-panel {
        padding: 16px 14px 18px;
    }
}
</style>
