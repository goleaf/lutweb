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
        Schema::create('custom_lut_commerce_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->default('custom_lut')->unique();
            $table->boolean('is_enabled')->default(false)->index();
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_lut_commerce_settings');
    }
};
