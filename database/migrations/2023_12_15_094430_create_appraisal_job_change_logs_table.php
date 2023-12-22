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
        Schema::create('appraisal_job_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_job_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('action');
            $table->unsignedInteger('duration')->nullable();
            $table->text('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_job_change_logs');
    }
};
