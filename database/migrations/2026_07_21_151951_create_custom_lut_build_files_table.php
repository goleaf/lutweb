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
        Schema::create('custom_lut_build_files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('custom_lut_build_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->index();
            $table->string('disk');
            $table->text('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['custom_lut_build_id', 'kind']);
            $table->index(['disk', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_lut_build_files');
    }
};
