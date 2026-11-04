<?php

return [

    'name' => env('SHOP_NAME', 'Kooku Beauty Supplies'),

    // Flat delivery fee. It becomes free once the order reaches the minimum
    // (set SHOP_FREE_SHIPPING_MIN=0 to never offer free delivery).
    'shipping_fee' => (float) env('SHOP_SHIPPING_FEE', 100),
    'free_shipping_min' => (float) env('SHOP_FREE_SHIPPING_MIN', 1500),

    // Shown to customers who pay by GCash
    'gcash_name' => env('SHOP_GCASH_NAME', ''),
    'gcash_number' => env('SHOP_GCASH_NUMBER', ''),

    'contact_phone' => env('SHOP_CONTACT_PHONE', ''),
    'contact_email' => env('SHOP_CONTACT_EMAIL', ''),
    'contact_facebook' => env('SHOP_CONTACT_FACEBOOK', ''),

];
