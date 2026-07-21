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
        Schema::create('package_document_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('kind');
            $table->string('status')->default('draft')->index();
            $table->string('version');
            $table->string('title');
            $table->longText('body');
            $table->boolean('is_current')->default(false)->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kind', 'version']);
            $table->index(['kind', 'is_current']);
            $table->index(['kind', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_document_templates');
    }
};
