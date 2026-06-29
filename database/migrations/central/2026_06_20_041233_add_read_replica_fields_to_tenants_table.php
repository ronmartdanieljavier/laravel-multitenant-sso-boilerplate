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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('read_replica_host')->nullable()->after('db_password');
            $table->unsignedSmallInteger('read_replica_port')->nullable()->after('read_replica_host');
            $table->string('read_replica_username')->nullable()->after('read_replica_port');
            $table->text('read_replica_password')->nullable()->after('read_replica_username');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['read_replica_host', 'read_replica_port', 'read_replica_username', 'read_replica_password']);
        });
    }
};
