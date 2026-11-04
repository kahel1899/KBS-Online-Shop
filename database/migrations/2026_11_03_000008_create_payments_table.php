<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // cod, gcash
            $table->string('method', 20);
            $table->decimal('amount', 10, 2);

            // Manual GCash: the customer sends the reference number / screenshot
            $table->string('reference_number', 100)->nullable();
            $table->string('proof_url')->nullable();

            // pending, verified, rejected
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
