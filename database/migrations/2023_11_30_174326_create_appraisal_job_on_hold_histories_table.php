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
        Schema::create('appraisal_job_on_hold_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_job_id')->constrained('appraisal_jobs');
            $table->foreignId('done_by')->constrained('users');
            $table->string('action')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_job_on_hold_histories');
    }
};
