<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();

            // null = guest checkout
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Contact + delivery details are copied onto the order, so later
            // edits to an account or address never change an old order.
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30);

            $table->string('shipping_name');
            $table->string('shipping_phone', 30);
            $table->string('shipping_line1');
            $table->string('shipping_line2')->nullable();
            $table->string('shipping_barangay')->nullable();
            $table->string('shipping_city');
            $table->string('shipping_province');
            $table->string('shipping_postal_code', 10)->nullable();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            // pending, confirmed, shipped, completed, cancelled
            $table->string('status', 20)->default('pending')->index();
            // cod, gcash
            $table->string('payment_method', 20);
            // unpaid, pending_verification, paid, rejected
            $table->string('payment_status', 30)->default('unpaid')->index();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
