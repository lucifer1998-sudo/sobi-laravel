<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the house number is shown with the address on the public listing
     * cards. Off by default, so a synced property never publishes its exact
     * street number until somebody turns it on.
     *
     * The Hospitable sync writes a fixed list of columns and this is not one of
     * them, so the choice survives every sync.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('show_street_number')->default(false)->after('address_display');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('show_street_number');
        });
    }
};
