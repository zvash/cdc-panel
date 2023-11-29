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
        Schema::create('appraisal_job_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_job_id')->constrained('appraisal_jobs');
            $table->foreignId('appraiser_id')->constrained('users');
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamp('seen_at')->nullable();
            $table->string('status')->default(\App\Enums\AppraisalJobAssignmentStatus::Pending);
            $table->timestamps();

            $table->unique(['appraisal_job_id', 'appraiser_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_job_assignments');
    }
};
