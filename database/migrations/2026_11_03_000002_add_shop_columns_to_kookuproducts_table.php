<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kookuproducts', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();

            // Price customers pay in the online shop (0 = not for sale online yet)
            $table->decimal('online_price', 10, 2)->default(0);

            // Existing products stay hidden from the shop until you publish them
            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);
        });

        // Give every existing product a slug (used in shop URLs)
        DB::table('kookuproducts')->orderBy('id')->each(function ($product) {
            $base = Str::slug($product->name) ?: 'product';

            DB::table('kookuproducts')
                ->where('id', $product->id)
                ->update(['slug' => $base . '-' . $product->id]);
        });
    }

    public function down()
    {
        Schema::table('kookuproducts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug',
                'description',
                'online_price',
                'is_active',
                'is_featured',
            ]);
        });
    }
};
