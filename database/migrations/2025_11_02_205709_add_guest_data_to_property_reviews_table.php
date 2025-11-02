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
        Schema::table('property_reviews', function (Blueprint $table) {
            $table->json('guest_data')->nullable()->after('reviewer_avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_reviews', function (Blueprint $table) {
            $table->dropColumn('guest_data');
        });
    }
};
