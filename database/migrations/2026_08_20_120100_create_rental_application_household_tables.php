<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The three repeaters on the application form. They are always read and
     * written together with the application, so they are created together.
     */
    public function up(): void
    {
        Schema::create('rental_application_kids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_application_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('age')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_application_pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_application_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('rental_application_income_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_application_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->decimal('monthly_amount', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_application_income_sources');
        Schema::dropIfExists('rental_application_pets');
        Schema::dropIfExists('rental_application_kids');
    }
};
