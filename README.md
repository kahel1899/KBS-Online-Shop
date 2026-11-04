# Kooku Beauty Supplies - online shop

Laravel 9 API + Vue 3 / Vuetify single-page app.

* **Shop** (`/`): browse, search, cart, checkout (Cash on Delivery or manual GCash), order tracking.
* **Customer account** (`/account`): orders, addresses, profile, password.
* **Admin panel** (`/admin`): dashboard, inventory (with shop fields), orders, categories.

## Run locally (PowerShell)

```powershell
composer install
yarn install            # or: npm install
copy .env.example .env
php artisan key:generate
# edit .env: database settings, ADMIN_EMAIL, ADMIN_PASSWORD, SHOP_GCASH_NAME, SHOP_GCASH_NUMBER
php artisan migrate
php artisan db:seed     # categories, platforms, first admin
php artisan serve       # terminal 1
yarn dev                # terminal 2
```

Open http://127.0.0.1:8000 and log in at `/login` with ADMIN_EMAIL / ADMIN_PASSWORD
to reach the admin panel.

## Selling something

1. Admin > Inventory > edit a product. Set an **Online price**, a category, a description,
   and turn on **Show in the online shop**. Products with online price 0, hidden products
   and sold-out products do not appear in the shop listings.
2. A customer orders. The stock goes down right away.
3. Admin > Orders: for GCash, check the reference number against your GCash history, then
   press **Payment received**. Then Confirm > Shipped > Completed.
   Completed orders are counted as "Online" sales on the Dashboard.
4. Cancelling an order puts its items back in stock.

## Deploy on Render

Push to GitHub, then Render > New > Blueprint > pick the repo. Fill in `APP_KEY`
(`php artisan key:generate --show`), `ADMIN_EMAIL`, `ADMIN_PASSWORD` and the GCash details.
Tables, categories and the admin account are created automatically on every start.
