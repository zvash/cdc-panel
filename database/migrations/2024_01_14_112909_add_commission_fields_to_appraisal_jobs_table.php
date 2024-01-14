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
        Schema::table('appraisal_jobs', function (Blueprint $table) {
            $table->unsignedInteger('commission')->nullable()->after('fee_quoted');
            $table->unsignedInteger('reviewer_commission')->nullable()->after('commission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_jobs', function (Blueprint $table) {
            $table->dropColumn('commission');
            $table->dropColumn('reviewer_commission');
        });
    }
};
