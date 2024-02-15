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
        Schema::table('province_taxes', function (Blueprint $table) {
            $table->decimal('qst', 6, 3)->after('province_id')->default(0);
        });
        $quebecTax = \App\Models\Province::query()->where('name', 'Quebec (QC)')->first()?->tax;
        if ($quebecTax) {
            $quebecTax->update([
                'qst' => 9.975,
                'pst' => 0,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('province_taxes', function (Blueprint $table) {
            $table->dropColumn('qst');
        });
    }
};
