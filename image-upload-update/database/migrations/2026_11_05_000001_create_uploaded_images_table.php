<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Photos are kept in the database, so they survive Render restarts and
    // redeploys (a server's own disk is wiped each time on the free plan).
    public function up()
    {
        Schema::create('uploaded_images', function (Blueprint $table) {
            $table->id();
            $table->string('mime', 30);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->longText('data'); // base64
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('uploaded_images');
    }
};
