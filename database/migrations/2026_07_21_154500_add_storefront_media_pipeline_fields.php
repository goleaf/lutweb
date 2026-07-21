<?php

use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontMediaPipelineVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table): void {
            $table->string('source_disk')->nullable()->after('sort_order');
            $table->string('source_path')->nullable()->after('source_disk');
            $table->string('source_original_name')->nullable()->after('source_path');
            $table->string('source_mime_type')->nullable()->after('source_original_name');
            $table->unsignedBigInteger('source_size_bytes')->nullable()->after('source_mime_type');
            $table->unsignedInteger('source_width')->nullable()->after('source_size_bytes');
            $table->unsignedInteger('source_height')->nullable()->after('source_width');
            $table->char('source_sha256', 64)->nullable()->after('source_height');
            $table->string('processing_status')->default(StorefrontImageStatus::Ready->value)->after('source_sha256');
            $table->string('pipeline_version')->nullable()->default(StorefrontMediaPipelineVersion::V1->value)->after('processing_status');
            $table->char('processing_fingerprint', 64)->nullable()->after('pipeline_version');
            $table->string('failure_code')->nullable()->after('processing_fingerprint');
            $table->string('failure_message')->nullable()->after('failure_code');
            $table->timestamp('processed_at')->nullable()->after('failure_message');
            $table->timestamp('stale_at')->nullable()->after('processed_at');
            $table->timestamp('rights_confirmed_at')->nullable()->after('stale_at');
            $table->foreignId('rights_confirmed_by')->nullable()->after('rights_confirmed_at')->constrained('users')->nullOnDelete();
            $table->text('rights_note')->nullable()->after('rights_confirmed_by');
            $table->string('source_credit')->nullable()->after('rights_note');
            $table->string('source_license_reference')->nullable()->after('source_credit');
            $table->boolean('source_credit_is_public')->default(false)->after('source_license_reference');

            $table->index('processing_status');
            $table->index('processing_fingerprint');
            $table->index(['product_id', 'processing_status']);
        });

        Schema::table('product_examples', function (Blueprint $table): void {
            $table->string('source_disk')->nullable()->after('sort_order');
            $table->string('source_path')->nullable()->after('source_disk');
            $table->string('source_original_name')->nullable()->after('source_path');
            $table->string('source_mime_type')->nullable()->after('source_original_name');
            $table->unsignedBigInteger('source_size_bytes')->nullable()->after('source_mime_type');
            $table->unsignedInteger('source_width')->nullable()->after('source_size_bytes');
            $table->unsignedInteger('source_height')->nullable()->after('source_width');
            $table->char('source_sha256', 64)->nullable()->after('source_height');
            $table->foreignId('preview_product_id')->nullable()->after('source_sha256')->constrained('products')->nullOnDelete();
            $table->foreignId('processed_product_version_id')->nullable()->after('preview_product_id')->constrained('product_versions')->nullOnDelete();
            $table->foreignId('processed_product_file_id')->nullable()->after('processed_product_version_id')->constrained('product_files')->nullOnDelete();
            $table->string('processing_status')->default(StorefrontImageStatus::Ready->value)->after('processed_product_file_id');
            $table->string('pipeline_version')->nullable()->default(StorefrontMediaPipelineVersion::V1->value)->after('processing_status');
            $table->char('processing_fingerprint', 64)->nullable()->after('pipeline_version');
            $table->string('failure_code')->nullable()->after('processing_fingerprint');
            $table->string('failure_message')->nullable()->after('failure_code');
            $table->timestamp('processed_at')->nullable()->after('failure_message');
            $table->timestamp('stale_at')->nullable()->after('processed_at');
            $table->timestamp('rights_confirmed_at')->nullable()->after('stale_at');
            $table->foreignId('rights_confirmed_by')->nullable()->after('rights_confirmed_at')->constrained('users')->nullOnDelete();
            $table->text('rights_note')->nullable()->after('rights_confirmed_by');
            $table->string('source_credit')->nullable()->after('rights_note');
            $table->string('source_license_reference')->nullable()->after('source_credit');
            $table->boolean('source_credit_is_public')->default(false)->after('source_license_reference');

            $table->index('processing_status');
            $table->index('processing_fingerprint');
            $table->index(['product_id', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::table('product_examples', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rights_confirmed_by');
            $table->dropConstrainedForeignId('processed_product_file_id');
            $table->dropConstrainedForeignId('processed_product_version_id');
            $table->dropConstrainedForeignId('preview_product_id');
            $table->dropIndex(['processing_status']);
            $table->dropIndex(['processing_fingerprint']);
            $table->dropIndex(['product_id', 'processing_status']);
            $table->dropColumn([
                'source_disk',
                'source_path',
                'source_original_name',
                'source_mime_type',
                'source_size_bytes',
                'source_width',
                'source_height',
                'source_sha256',
                'processing_status',
                'pipeline_version',
                'processing_fingerprint',
                'failure_code',
                'failure_message',
                'processed_at',
                'stale_at',
                'rights_confirmed_at',
                'rights_note',
                'source_credit',
                'source_license_reference',
                'source_credit_is_public',
            ]);
        });

        Schema::table('product_media', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rights_confirmed_by');
            $table->dropIndex(['processing_status']);
            $table->dropIndex(['processing_fingerprint']);
            $table->dropIndex(['product_id', 'processing_status']);
            $table->dropColumn([
                'source_disk',
                'source_path',
                'source_original_name',
                'source_mime_type',
                'source_size_bytes',
                'source_width',
                'source_height',
                'source_sha256',
                'processing_status',
                'pipeline_version',
                'processing_fingerprint',
                'failure_code',
                'failure_message',
                'processed_at',
                'stale_at',
                'rights_confirmed_at',
                'rights_note',
                'source_credit',
                'source_license_reference',
                'source_credit_is_public',
            ]);
        });
    }
};
