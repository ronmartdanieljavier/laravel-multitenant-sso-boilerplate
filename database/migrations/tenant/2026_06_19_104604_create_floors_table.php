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
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('name')->comment('Display name, e.g. "Level 3", "Ground Floor", "Basement 1"');
            $table->smallInteger('level_number')->comment('Numeric level for ordering; negative for below-ground');
            $table->decimal('gross_area', 12, 2)->nullable()->comment('in square meters');
            $table->decimal('leasable_area', 12, 2)->nullable()->comment('in square meters');
            $table->decimal('common_area', 12, 2)->nullable()->comment('in square meters');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'level_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('floors');
    }
};
