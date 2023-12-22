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
            $table->string('province')->after('due_date')->nullable();
            $table->double('tax')->after('province')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_jobs', function (Blueprint $table) {
            $table->dropColumn('province');
            $table->dropColumn('tax');
        });
    }
};
