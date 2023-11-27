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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('email')->unique();
            $table->string('role');
            $table->string('token', 6)->unique();
            $table->string('name')->nullable();
            $table->unsignedInteger('capacity')->default(0);

            $table->string('pin')->nullable();
            $table->string('title')->nullable();
            $table->string('designation')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('commission')->nullable();
            $table->unsignedInteger('reviewer_commission')->nullable();
            $table->string('gst_number')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
