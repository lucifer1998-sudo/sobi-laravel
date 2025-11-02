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
            $table->string('rating_platform_original', 10)->nullable()->after('rating');
            $table->timestamp('responded_at')->nullable()->after('reviewed_at');
            $table->boolean('can_respond')->default(false)->after('responded_at');
            $table->text('private_feedback')->nullable()->after('can_respond');
            $table->json('detailed_ratings')->nullable()->after('private_feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'rating_platform_original',
                'responded_at',
                'can_respond',
                'private_feedback',
                'detailed_ratings',
            ]);
        });
    }
};
