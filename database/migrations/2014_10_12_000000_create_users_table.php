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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedInteger('capacity')->default(0);
            $table->string('pin')->nullable();
            $table->string('title')->nullable();
            $table->string('designation')->nullable();
            $table->unsignedInteger('commission')->nullable();
            $table->unsignedInteger('reviewer_commission')->nullable();
            $table->string('gst_number')->nullable();
            $table->rememberToken();
            $table->json('preferred_appraisal_types')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
