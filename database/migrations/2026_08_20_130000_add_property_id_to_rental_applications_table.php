<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The unit is picked from the real listings now, so the application points
     * at the property it was made for. The apartment column stays as the label
     * the applicant saw, which keeps the receipt readable if a listing is later
     * renamed or taken down.
     */
    public function up(): void
    {
        Schema::table('rental_applications', function (Blueprint $table) {
            $table->char('property_id', 36)->nullable()->after('apartment');

            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rental_applications', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
    }
};
