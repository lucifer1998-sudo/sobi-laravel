<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_contents', function (Blueprint $table) {
            // Everything saved so far is the English copy, which is what the
            // default backfills the existing rows with.
            $table->string('locale', 5)->default('en')->after('section');
        });

        Schema::table('site_contents', function (Blueprint $table) {
            // A section now has one row per language, so section alone is not
            // unique any more. Dropped by name because MariaDB is fussy about
            // working out the index from a column list.
            $table->dropUnique('site_contents_section_unique');
            $table->unique(['section', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('site_contents', function (Blueprint $table) {
            $table->dropUnique(['section', 'locale']);
        });

        // The translations would collide on the old unique index, so they go first.
        DB::table('site_contents')->where('locale', '!=', 'en')->delete();

        Schema::table('site_contents', function (Blueprint $table) {
            $table->unique('section');
            $table->dropColumn('locale');
        });
    }
};
