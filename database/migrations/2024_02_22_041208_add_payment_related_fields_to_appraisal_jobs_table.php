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
            $table->decimal('admin_fee', 8, 2)->nullable()->after('on_hold_until');
            $table->timestamp('admin_paid_at')->nullable()->after('admin_fee');
            $table->timestamp('appraiser_paid_at')->nullable()->after('admin_paid_at');
            $table->timestamp('reviewer_paid_at')->nullable()->after('appraiser_paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_jobs', function (Blueprint $table) {
            $table->dropColumn('admin_fee');
            $table->dropColumn('admin_paid_at');
            $table->dropColumn('appraiser_paid_at');
            $table->dropColumn('reviewer_paid_at');
        });
    }
};
