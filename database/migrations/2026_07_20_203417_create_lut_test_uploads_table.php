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
        Schema::create('lut_test_uploads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued');
            $table->string('disk')->default('private');
            $table->string('raw_path')->nullable();
            $table->string('normalized_path')->nullable();
            $table->string('before_preview_path')->nullable();
            $table->string('after_preview_path')->nullable();
            $table->string('original_name');
            $table->string('original_mime_type');
            $table->unsignedBigInteger('original_size_bytes');
            $table->unsignedInteger('original_width');
            $table->unsignedInteger('original_height');
            $table->string('preview_mime_type')->nullable();
            $table->unsignedInteger('preview_width')->nullable();
            $table->unsignedInteger('preview_height')->nullable();
            $table->string('failure_code')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lut_test_uploads');
    }
};
