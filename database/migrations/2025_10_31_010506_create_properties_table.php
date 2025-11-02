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
        Schema::create('properties', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();

            $table->string('name');
            $table->string('public_name')->nullable();
            $table->string('picture_url')->nullable();
            $table->string('timezone_offset', 10)->nullable();
            $table->boolean('listed')->default(false);
            $table->string('currency', 8)->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->time('checkin_time')->nullable();
            $table->time('checkout_time')->nullable();
            $table->string('property_type')->nullable();
            $table->string('room_type')->nullable();
            $table->boolean('calendar_restricted')->default(false);
            

            $table->string('address_number')->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_state', 64)->nullable();
            $table->string('address_postcode', 32)->nullable();
            $table->string('address_country_code', 2)->nullable();
            $table->string('address_country_name', 128)->nullable();
            $table->string('address_display')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();

            
            $table->unsignedSmallInteger('capacity_max')->nullable();
            $table->unsignedSmallInteger('capacity_bedrooms')->nullable();
            $table->unsignedSmallInteger('capacity_beds')->nullable();
            $table->decimal('capacity_bathrooms', 3, 1)->nullable();

            $table->timestamps();

            // $table->foreign('parent_id')->references('id')->on('properties')->nullOnDelete();

            $table->index('listed');
            $table->index(['address_city', 'address_state']);
            $table->index('address_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
