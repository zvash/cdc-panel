<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appraisal_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients');
            $table->foreignId('appraisal_type_id')->constrained('appraisal_types');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('office_id')->constrained('offices');
            $table->foreignId('appraiser_id')->nullable()->constrained('users');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->string('lender')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('applicant')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->decimal('fee_quoted', 8, 2)->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('invoice_name')->nullable();
            $table->string('invoice_email')->nullable();
            $table->string('property_province')->nullable();
            $table->string('property_city')->nullable();
            $table->string('property_postal_code')->nullable();
            $table->string('property_address')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('additional_information')->nullable();
            $table->string('status')->default(\App\Enums\AppraisalJobStatus::Pending);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_on_hold')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_jobs');
    }
};
