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
            $table->timestamp('appointment_date')
                ->after('file')
                ->nullable()
                ->default(null)
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_jobs', function (Blueprint $table) {
            $table->dropColumn('appointment_date');
        });
    }
};
