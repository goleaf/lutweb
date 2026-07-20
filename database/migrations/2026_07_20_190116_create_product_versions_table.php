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
        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('status')->default('draft')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_versions');
    }
};
