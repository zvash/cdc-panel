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
        Schema::table('appraisal_jobs', function (Blueprint $table) {
            $table->timestamp('left_in_progress_at')->nullable()->after('accepted_at');
            $table->timestamp('reviewed_at')->nullable()->after('left_in_progress_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_jobs', function (Blueprint $table) {
            $table->dropColumn('left_in_progress_at');
            $table->dropColumn('reviewed_at');
        });
    }
};
