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
        Schema::table('custom_lut_build_files', function (Blueprint $table): void {
            $table->string('relative_package_path')->nullable()->after('path');
            $table->string('safe_download_name')->nullable()->after('relative_package_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_lut_build_files', function (Blueprint $table): void {
            $table->dropColumn([
                'relative_package_path',
                'safe_download_name',
            ]);
        });
    }
};
