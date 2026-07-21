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
        Schema::table('custom_lut_builds', function (Blueprint $table): void {
            $table->dropUnique('custom_lut_builds_build_fingerprint_unique');
            $table->uuid('build_request_id')->nullable()->after('build_fingerprint');
            $table->json('parameters')->nullable()->after('project_revision');
            $table->string('disk')->default('private')->after('build_request_id');
            $table->unsignedBigInteger('parity_mean_error_millionths')->nullable()->after('ffmpeg_validation_passed');
            $table->unsignedBigInteger('parity_p95_error_millionths')->nullable()->after('parity_mean_error_millionths');
            $table->unsignedBigInteger('parity_p99_error_millionths')->nullable()->after('parity_p95_error_millionths');
            $table->unsignedBigInteger('parity_max_error_millionths')->nullable()->after('parity_p99_error_millionths');
            $table->unsignedBigInteger('zip_size_bytes')->nullable()->after('guide_template_hash');
            $table->char('zip_sha256', 64)->nullable()->after('zip_size_bytes');
            $table->unsignedBigInteger('uncompressed_size_bytes')->nullable()->after('zip_sha256');
            $table->string('failure_code')->nullable()->after('uncompressed_size_bytes');
            $table->string('failure_message', 300)->nullable()->after('failure_code');
            $table->json('license_document_snapshot')->nullable()->after('failure_message');
            $table->json('guide_document_snapshot')->nullable()->after('license_document_snapshot');
            $table->timestamp('started_at')->nullable()->after('guide_document_snapshot');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('superseded_at')->nullable()->after('completed_at');

            $table->unique(['wizard_project_id', 'build_request_id'], 'custom_lut_builds_project_request_unique');
            $table->index('build_fingerprint', 'custom_lut_builds_build_fingerprint_index');
            $table->index(['wizard_project_id', 'build_fingerprint'], 'custom_lut_builds_project_fingerprint_index');
            $table->index(['status', 'expires_at'], 'custom_lut_builds_status_expires_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_lut_builds', function (Blueprint $table): void {
            $table->dropUnique('custom_lut_builds_project_request_unique');
            $table->dropIndex('custom_lut_builds_build_fingerprint_index');
            $table->dropIndex('custom_lut_builds_project_fingerprint_index');
            $table->dropIndex('custom_lut_builds_status_expires_at_index');
            $table->dropColumn([
                'build_request_id',
                'parameters',
                'disk',
                'parity_mean_error_millionths',
                'parity_p95_error_millionths',
                'parity_p99_error_millionths',
                'parity_max_error_millionths',
                'zip_size_bytes',
                'zip_sha256',
                'uncompressed_size_bytes',
                'failure_code',
                'failure_message',
                'license_document_snapshot',
                'guide_document_snapshot',
                'started_at',
                'completed_at',
                'superseded_at',
            ]);
            $table->unique('build_fingerprint', 'custom_lut_builds_build_fingerprint_unique');
        });
    }
};
