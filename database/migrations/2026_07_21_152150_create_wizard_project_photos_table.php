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
        Schema::create('wizard_project_photos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('wizard_project_id')->constrained('wizard_projects')->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('disk')->default('private');
            $table->string('raw_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('original_name');
            $table->string('original_mime_type');
            $table->unsignedBigInteger('original_size_bytes');
            $table->unsignedInteger('original_width');
            $table->unsignedInteger('original_height');
            $table->string('preview_mime_type')->nullable();
            $table->unsignedInteger('preview_width')->nullable();
            $table->unsignedInteger('preview_height')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->string('failure_code')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['wizard_project_id', 'sort_order']);
            $table->index(['status', 'expires_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wizard_project_photos');
    }
};
