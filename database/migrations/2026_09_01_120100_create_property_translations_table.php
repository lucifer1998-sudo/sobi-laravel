<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_translations', function (Blueprint $table) {
            $table->id();
            // The Hospitable sync rewrites the whole properties row every time it
            // runs, so the translations have to sit beside it rather than on it.
            $table->uuid('property_id');
            $table->string('locale', 5);
            // Only the fields somebody has actually translated. Anything missing
            // falls back to the English column on the property itself.
            $table->json('content');
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->unique(['property_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_translations');
    }
};
