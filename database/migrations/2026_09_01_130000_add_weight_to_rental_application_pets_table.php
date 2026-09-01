<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weight in pounds. Nullable because only dogs are asked for it, and
     * applications taken before this column existed have none either way.
     */
    public function up(): void
    {
        Schema::table('rental_application_pets', function (Blueprint $table) {
            $table->decimal('weight', 6, 2)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('rental_application_pets', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
