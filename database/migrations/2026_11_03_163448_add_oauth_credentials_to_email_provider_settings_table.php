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
        Schema::table('email_provider_settings', function (Blueprint $table) {
            $table->string('google_client_id')->nullable();
            $table->text('google_client_secret')->nullable();
            $table->string('microsoft_client_id')->nullable();
            $table->text('microsoft_client_secret')->nullable();
            $table->string('microsoft_tenant')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_provider_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_client_id',
                'google_client_secret',
                'microsoft_client_id',
                'microsoft_client_secret',
                'microsoft_tenant',
            ]);
        });
    }
};
