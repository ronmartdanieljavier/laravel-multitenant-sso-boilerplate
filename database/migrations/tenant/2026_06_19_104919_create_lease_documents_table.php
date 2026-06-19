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
        Schema::create('lease_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->enum('type', [
                'lease_agreement',
                'amendment',
                'renewal',
                'termination',
                'offer_letter',
                'disclosure_statement',
                'bank_guarantee',
                'insurance_certificate',
                'fit_out_approval',
                'make_good',
                'other',
            ])->default('other');
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('in bytes');
            $table->string('disk')->default('local');
            $table->date('document_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
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
        Schema::dropIfExists('lease_documents');
    }
};
