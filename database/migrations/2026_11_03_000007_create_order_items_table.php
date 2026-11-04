<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Kept as history even if the product is deleted later
            $table->unsignedInteger('product_id')->nullable();
            $table->foreign('product_id')
                ->references('id')
                ->on('kookuproducts')
                ->nullOnDelete();

            $table->string('product_name');
            $table->string('product_image')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_items');
    }
};
