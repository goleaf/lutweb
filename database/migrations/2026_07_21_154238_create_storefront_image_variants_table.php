<?php

use App\Enums\StorefrontImageFormat;
use App\Enums\StorefrontImageVariantRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_image_variants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->morphs('imageable');
            $table->string('role')->default(StorefrontImageVariantRole::Media->value);
            $table->string('format')->default(StorefrontImageFormat::Jpeg->value);
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedSmallInteger('quality')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['imageable_type', 'imageable_id', 'role', 'format', 'width'], 'storefront_variant_unique');
            $table->index(['role', 'format', 'width']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_image_variants');
    }
};
