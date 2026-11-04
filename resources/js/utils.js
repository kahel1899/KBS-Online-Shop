// Shared helpers for the KBS pages.

// Change this one line if your Laravel server runs somewhere else.
export const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'

// Platform colours are used on both the dashboard and the inventory cards,
// so a platform looks the same everywhere.
export const PLATFORMS = [
    { name: 'TikTok', color: '#0FA3B1' },
    { name: 'Shopee', color: '#EE4D2D' },
    { name: 'Walk-in', color: '#7C5CFC' },
    { name: 'Online', color: '#2563EB' },
]
export const UNASSIGNED = { name: 'Unassigned', color: '#A8949F' }

export function platformColor(name) {
    return (PLATFORMS.find(p => p.name === name) || UNASSIGNED).color
}

const pesoFormat = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
})

export function peso(value) {
    return pesoFormat.format(Number(value) || 0)
}

// ₱1.2k style, for chart axes and tight spaces
export function pesoShort(value) {
    const n = Number(value) || 0
    const sign = n < 0 ? '-' : ''
    const abs = Math.abs(n)
    if (abs >= 1000000) return `${sign}₱${trim(abs / 1000000)}M`
    if (abs >= 1000) return `${sign}₱${trim(abs / 1000)}k`
    return `${sign}₱${trim(abs)}`
}

function trim(n) {
    return String(Math.round(n * 10) / 10)
}

export function saleRevenue(sale) {
    return (Number(sale.quantity) * Number(sale.selling_price)) || 0
}

export function saleCost(sale) {
    return (Number(sale.quantity) * Number(sale.unit_cost)) || 0
}


// ---------- online shop helpers ----------

export const ORDER_STATUS = {
    pending: { label: 'Pending', color: '#8A5200', bg: '#FFF1D6' },
    confirmed: { label: 'Confirmed', color: '#1B55B3', bg: '#E3EEFF' },
    shipped: { label: 'Shipped', color: '#5B33C4', bg: '#EEE7FF' },
    completed: { label: 'Completed', color: '#17694A', bg: '#DDF4E8' },
    cancelled: { label: 'Cancelled', color: '#A8231B', bg: '#FBE3E1' },
}

export const PAYMENT_STATUS = {
    unpaid: { label: 'Unpaid', color: '#8A5200', bg: '#FFF1D6' },
    pending_verification: { label: 'Checking payment', color: '#1B55B3', bg: '#E3EEFF' },
    paid: { label: 'Paid', color: '#17694A', bg: '#DDF4E8' },
    rejected: { label: 'Payment rejected', color: '#A8231B', bg: '#FBE3E1' },
    refund_due: { label: 'Refund due', color: '#5B33C4', bg: '#EEE7FF' },
}

export const PAYMENT_METHOD = {
    cod: 'Cash on delivery',
    gcash: 'GCash',
}

export function formatDate(value) {
    if (!value) return ''
    return new Date(value).toLocaleString('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    })
}

// Turns an API error into one readable sentence
export function apiError(error, fallback = 'Something went wrong. Please try again.') {
    const data = error && error.response && error.response.data
    if (data && data.errors) {
        const first = Object.values(data.errors)[0]
        if (first && first[0]) return first[0]
    }
    if (data && data.message) return data.message
    if (error && !error.response) return 'Cannot reach the server. Check your internet connection.'
    return fallback
}

export function fullAddress(shipping) {
    return [
        shipping.line1,
        shipping.line2,
        shipping.barangay,
        shipping.city,
        shipping.province,
        shipping.postal_code,
    ].filter(Boolean).join(', ')
}
