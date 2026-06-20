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
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->string('lease_number')->unique();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('tenant_company_id')->constrained('companies')->restrictOnDelete();
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'renewed'])->default('draft');
            $table->enum('lease_type', ['gross', 'net', 'modified_gross', 'percentage'])->default('gross');
            $table->date('commencement_date');
            $table->date('expiry_date');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('leasable_area', 12, 2)->comment('in square meters');
            $table->decimal('base_rent', 15, 2)->comment('monthly base rent');
            $table->string('currency', 3)->default('AUD');
            $table->decimal('rent_per_sqm', 12, 2)->nullable();
            $table->decimal('cusa_per_sqm', 12, 2)->nullable()->comment('Common Use Service Area charge');
            $table->unsignedTinyInteger('escalation_rate_percent')->nullable()->comment('annual escalation %');
            $table->unsignedTinyInteger('escalation_frequency_months')->default(12);
            $table->decimal('security_deposit', 15, 2)->nullable();
            $table->unsignedTinyInteger('advance_rent_months')->default(2);
            $table->date('rent_free_start')->nullable();
            $table->date('rent_free_end')->nullable();
            $table->unsignedTinyInteger('billing_day')->default(1)->comment('Day of month invoices are generated');
            $table->string('vat_registration_number')->nullable();
            $table->boolean('vat_inclusive')->default(false);
            $table->text('special_conditions')->nullable();
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
        Schema::dropIfExists('leases');
    }
};
