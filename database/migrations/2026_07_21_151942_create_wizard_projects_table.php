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
        Schema::create('wizard_projects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('wizard_style_id')->nullable()->constrained('wizard_styles')->nullOnDelete();
            $table->string('name', 80);
            $table->string('status')->default('draft');
            $table->string('transform_version');
            $table->string('style_name_snapshot')->nullable();
            $table->json('style_snapshot')->nullable();
            $table->json('parameters');
            $table->char('parameters_hash', 64);
            $table->char('project_seed', 64);
            $table->unsignedBigInteger('revision')->default(1);
            $table->unsignedInteger('variation_generation')->default(0);
            $table->uuid('last_mutation_id')->nullable();
            $table->timestamp('last_autosaved_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'expires_at']);
            $table->index(['status', 'expires_at']);
            $table->index('parameters_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wizard_projects');
    }
};
