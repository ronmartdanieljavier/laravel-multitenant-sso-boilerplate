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
        Schema::connection('tenant')->create('report_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('format');
            $table->string('frequency');
            $table->string('delivery');
            $table->json('recipients')->nullable();
            $table->string('s3_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'frequency', 'last_dispatched_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('report_subscriptions');
    }
};
