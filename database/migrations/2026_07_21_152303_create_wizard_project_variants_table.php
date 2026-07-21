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
        Schema::create('wizard_project_variants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('wizard_project_id')->constrained('wizard_projects')->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->unsignedSmallInteger('position');
            $table->string('mode');
            $table->char('seed', 64);
            $table->json('parameters');
            $table->char('parameters_hash', 64);
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->unique(['wizard_project_id', 'position']);
            $table->unique(['wizard_project_id', 'generation', 'parameters_hash']);
            $table->index(['wizard_project_id', 'generation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wizard_project_variants');
    }
};
