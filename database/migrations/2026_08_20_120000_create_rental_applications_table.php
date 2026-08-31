<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_applications', function (Blueprint $table) {
            $table->id();
            // Unguessable public key. The receipt and the document upload link are
            // both addressed by this, never by the row id.
            $table->string('application_id', 32)->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('apartment');

            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('own_rent', 10)->nullable();
            $table->decimal('monthly_cost', 10, 2)->nullable();
            $table->date('move_in_date')->nullable();
            $table->date('move_out_date')->nullable();
            $table->string('landlord_name')->nullable();
            $table->string('landlord_phone', 30)->nullable();

            $table->boolean('is_student')->default(false);
            $table->string('school_name')->nullable();
            $table->string('major')->nullable();
            $table->date('enrollment_date')->nullable();
            $table->date('graduation_date')->nullable();
            $table->decimal('monthly_stipend', 10, 2)->nullable();

            $table->boolean('is_employed')->default(false);
            $table->string('employer_name')->nullable();
            $table->string('position')->nullable();
            $table->string('supervisor')->nullable();
            $table->string('work_phone', 30)->nullable();
            $table->decimal('monthly_income', 10, 2)->nullable();
            $table->date('employment_start_date')->nullable();

            $table->boolean('has_past_employer')->default(false);
            $table->string('past_employer_name')->nullable();
            $table->string('past_position')->nullable();
            $table->string('past_supervisor')->nullable();
            $table->string('past_work_phone', 30)->nullable();
            $table->decimal('past_monthly_income', 10, 2)->nullable();
            $table->date('past_start_date')->nullable();
            $table->date('past_end_date')->nullable();

            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->string('emergency_relation')->nullable();
            $table->date('desired_move_in')->nullable();
            $table->date('desired_move_out')->nullable();
            $table->text('reason_for_moving')->nullable();

            $table->boolean('has_legal_issue')->default(false);
            $table->text('legal_explanation')->nullable();

            $table->timestamp('agreed_at');
            // Path on the private disk. Never a public URL.
            $table->string('signature_path');

            $table->timestamps();

            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_applications');
    }
};
