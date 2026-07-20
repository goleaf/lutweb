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
        Schema::table('users', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('email')->index();
            $table->timestamp('terms_accepted_at')->nullable()->after('remember_token');
            $table->timestamp('privacy_accepted_at')->nullable()->after('terms_accepted_at');
            $table->string('terms_version')->nullable()->after('privacy_accepted_at');
            $table->string('privacy_version')->nullable()->after('terms_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['country_code']);
            $table->dropColumn([
                'country_code',
                'terms_accepted_at',
                'privacy_accepted_at',
                'terms_version',
                'privacy_version',
            ]);
        });
    }
};
