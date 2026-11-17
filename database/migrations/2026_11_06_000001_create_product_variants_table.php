<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('kookuproducts')
                ->onDelete('cascade');

            $table->string('name', 80);
            // null = same price as the product
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('kookuproducts', function (Blueprint $table) {
            // What the customer chooses, e.g. "Shade" or "Size"
            $table->string('variant_label', 40)->nullable();
        });
    }

    public function down()
    {
        Schema::table('kookuproducts', function (Blueprint $table) {
            $table->dropColumn('variant_label');
        });

        Schema::dropIfExists('product_variants');
    }
};
