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
        Schema::create('tenant_job_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracking_id')->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('job_class');
            $table->string('display_name');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_job_records');
    }
};
