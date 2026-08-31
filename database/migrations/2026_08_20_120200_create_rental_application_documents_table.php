<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_application_id')->constrained()->cascadeOnDelete();
            // What the applicant called the file, kept only so the admin list
            // reads sensibly. The stored filename is generated.
            $table->string('original_name');
            // Path on the private disk. These are photo IDs and pay stubs, so
            // there is deliberately no public URL for them.
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_application_documents');
    }
};
