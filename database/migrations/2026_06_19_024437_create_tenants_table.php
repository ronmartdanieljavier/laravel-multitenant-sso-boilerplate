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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('db_host');
            $table->unsignedSmallInteger('db_port')->default(3306);
            $table->string('db_name');
            $table->string('db_username');
            $table->string('db_password');
            $table->string('report_db_host')->nullable();
            $table->string('report_db_name')->nullable();
            $table->string('report_url')->nullable();
            $table->enum('report_server', ['shared', 'dedicated'])->default('shared');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
