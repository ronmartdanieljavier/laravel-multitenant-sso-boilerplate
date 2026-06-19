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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('floor_id')->constrained('floors')->cascadeOnDelete();
            $table->string('unit_number');
            $table->string('unit_name')->nullable();
            $table->enum('type', ['office', 'retail', 'storage', 'car_park', 'common_area', 'other'])->default('office');
            $table->enum('status', ['available', 'occupied', 'under_fit_out', 'not_available'])->default('available');
            $table->decimal('gross_area', 12, 2)->nullable()->comment('in square meters');
            $table->decimal('net_lettable_area', 12, 2)->nullable()->comment('NLA in square meters');
            $table->decimal('asking_rent_per_sqm', 12, 2)->nullable();
            $table->string('currency', 3)->default('AUD');
            $table->unsignedTinyInteger('car_park_allocations')->default(0);
            $table->string('facing')->nullable()->comment('e.g. North, City-facing');
            $table->boolean('has_natural_light')->default(true);
            $table->boolean('is_corner_unit')->default(false);
            $table->text('fitout_description')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'unit_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
