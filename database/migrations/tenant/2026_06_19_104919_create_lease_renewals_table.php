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
        Schema::create('lease_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->string('renewal_number')->unique();
            $table->enum('status', ['proposed', 'negotiating', 'approved', 'executed', 'declined'])->default('proposed');
            $table->date('proposed_commencement_date');
            $table->date('proposed_expiry_date');
            $table->decimal('proposed_base_rent', 15, 2)->nullable();
            $table->decimal('proposed_rent_per_sqm', 12, 2)->nullable();
            $table->string('currency', 3)->default('AUD');
            $table->unsignedTinyInteger('proposed_escalation_rate_percent')->nullable();
            $table->decimal('proposed_leasable_area', 12, 2)->nullable()->comment('in square meters, if unit changes');
            $table->date('offer_date')->nullable();
            $table->date('response_due_date')->nullable();
            $table->date('executed_date')->nullable();
            $table->foreignId('new_lease_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->text('terms_summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_renewals');
    }
};
