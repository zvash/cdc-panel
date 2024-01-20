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
        Schema::table('appraisal_job_assignments', function (Blueprint $table) {
            $table->text('reject_reason')->nullable()->after('assigned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_job_assignments', function (Blueprint $table) {
            $table->dropColumn('reject_reason');
        });
    }
};
