<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_version_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('disk')->default('private');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['product_version_id', 'kind']);
            $table->index('kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_files');
    }
};
