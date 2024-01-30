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
            $table->boolean('ensure_unique_address')->after('property_address')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_jobs', function (Blueprint $table) {
            $table->dropColumn('ensure_unique_address');
        });
    }
};
