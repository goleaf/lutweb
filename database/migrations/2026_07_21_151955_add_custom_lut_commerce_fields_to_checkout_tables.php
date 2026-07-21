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
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('digital_asset_kind')->default('catalog_product')->after('id')->index();
            $table->foreignUlid('wizard_project_id')->nullable()->after('product_file_id')->constrained()->nullOnDelete();
            $table->foreignUlid('custom_lut_build_id')->nullable()->after('wizard_project_id')->constrained()->nullOnDelete();
            $table->foreignUlid('custom_lut_build_file_id')->nullable()->after('custom_lut_build_id')->constrained()->nullOnDelete();
            $table->char('custom_lut_build_fingerprint', 64)->nullable()->after('custom_lut_build_file_id');
            $table->char('custom_lut_parameters_hash', 64)->nullable()->after('custom_lut_build_fingerprint');
            $table->string('custom_lut_transform_version')->nullable()->after('custom_lut_parameters_hash');
            $table->string('custom_lut_generator_version')->nullable()->after('custom_lut_transform_version');
            $table->string('custom_lut_package_schema_version')->nullable()->after('custom_lut_generator_version');
            $table->char('custom_lut_package_sha256', 64)->nullable()->after('custom_lut_package_schema_version');
            $table->unsignedBigInteger('custom_lut_package_size_bytes')->nullable()->after('custom_lut_package_sha256');
            $table->string('custom_lut_style_name_snapshot')->nullable()->after('custom_lut_package_size_bytes');
            $table->unsignedBigInteger('custom_lut_pricing_version')->nullable()->after('custom_lut_style_name_snapshot');
            $table->index(['digital_asset_kind', 'created_at']);
            $table->index('custom_lut_build_id');
            $table->index('custom_lut_build_file_id');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('product_type')->nullable()->change();
        });

        Schema::table('entitlements', function (Blueprint $table): void {
            $table->string('digital_asset_kind')->default('catalog_product')->after('id')->index();
            $table->foreignUlid('wizard_project_id')->nullable()->after('product_file_id')->constrained()->nullOnDelete();
            $table->foreignUlid('custom_lut_build_id')->nullable()->after('wizard_project_id')->constrained()->nullOnDelete();
            $table->foreignUlid('custom_lut_build_file_id')->nullable()->after('custom_lut_build_id')->constrained()->nullOnDelete();
            $table->unique(['user_id', 'custom_lut_build_id']);
            $table->index(['digital_asset_kind', 'created_at']);
            $table->index('custom_lut_build_id');
            $table->index('custom_lut_build_file_id');
        });

        Schema::table('download_events', function (Blueprint $table): void {
            $table->string('digital_asset_kind')->default('catalog_product')->after('id')->index();
            $table->foreignUlid('wizard_project_id')->nullable()->after('product_file_id')->constrained()->nullOnDelete();
            $table->foreignUlid('custom_lut_build_id')->nullable()->after('wizard_project_id')->constrained()->nullOnDelete();
            $table->foreignUlid('custom_lut_build_file_id')->nullable()->after('custom_lut_build_id')->constrained()->nullOnDelete();
            $table->string('item_display_name_snapshot')->nullable()->after('custom_lut_build_file_id');
            $table->string('item_version_snapshot')->nullable()->after('item_display_name_snapshot');
            $table->index(['digital_asset_kind', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('download_events', function (Blueprint $table): void {
            $table->dropForeign(['wizard_project_id']);
            $table->dropForeign(['custom_lut_build_id']);
            $table->dropForeign(['custom_lut_build_file_id']);
            $table->dropColumn([
                'digital_asset_kind',
                'wizard_project_id',
                'custom_lut_build_id',
                'custom_lut_build_file_id',
                'item_display_name_snapshot',
                'item_version_snapshot',
            ]);
        });

        Schema::table('entitlements', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'custom_lut_build_id']);
            $table->dropForeign(['wizard_project_id']);
            $table->dropForeign(['custom_lut_build_id']);
            $table->dropForeign(['custom_lut_build_file_id']);
            $table->dropColumn([
                'digital_asset_kind',
                'wizard_project_id',
                'custom_lut_build_id',
                'custom_lut_build_file_id',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('product_type')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['wizard_project_id']);
            $table->dropForeign(['custom_lut_build_id']);
            $table->dropForeign(['custom_lut_build_file_id']);
            $table->dropColumn([
                'digital_asset_kind',
                'wizard_project_id',
                'custom_lut_build_id',
                'custom_lut_build_file_id',
                'custom_lut_build_fingerprint',
                'custom_lut_parameters_hash',
                'custom_lut_transform_version',
                'custom_lut_generator_version',
                'custom_lut_package_schema_version',
                'custom_lut_package_sha256',
                'custom_lut_package_size_bytes',
                'custom_lut_style_name_snapshot',
                'custom_lut_pricing_version',
            ]);
        });
    }
};
