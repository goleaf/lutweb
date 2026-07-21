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
        Schema::create('custom_lut_builds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('wizard_project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project_name_snapshot');
            $table->string('style_name_snapshot')->nullable();
            $table->string('package_stem');
            $table->unsignedBigInteger('project_revision')->default(1);
            $table->char('parameters_hash', 64);
            $table->char('build_fingerprint', 64)->unique();
            $table->string('transform_version');
            $table->string('generator_version');
            $table->string('package_schema_version');
            $table->string('status')->index();
            $table->boolean('sale_ready')->default(false)->index();
            $table->boolean('contains_draft_documents')->default(true)->index();
            $table->boolean('is_current')->default(false)->index();
            $table->boolean('zip_validation_completed')->default(false);
            $table->boolean('parity_validation_passed')->default(false);
            $table->boolean('ffmpeg_validation_passed')->default(false);
            $table->string('license_version');
            $table->char('license_template_hash', 64)->nullable();
            $table->string('guide_version')->nullable();
            $table->char('guide_template_hash', 64)->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable()->index();
            $table->timestamp('first_ordered_at')->nullable();
            $table->timestamp('purchased_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['wizard_project_id', 'is_current']);
            $table->index(['status', 'sale_ready', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_lut_builds');
    }
};
