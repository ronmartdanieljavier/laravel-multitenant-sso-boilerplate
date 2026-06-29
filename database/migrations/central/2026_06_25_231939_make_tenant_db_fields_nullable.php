<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('db_host')->nullable()->change();
            $table->string('db_name')->nullable()->change();
            $table->string('db_username')->nullable()->change();
            $table->text('db_password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('db_host')->nullable(false)->change();
            $table->string('db_name')->nullable(false)->change();
            $table->string('db_username')->nullable(false)->change();
            $table->text('db_password')->nullable(false)->change();
        });
    }
};
