<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_document_spatie_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->unique()->constrained('legal_documents')->cascadeOnDelete();
            $table->json('title')->nullable();
            $table->json('content')->nullable();
            $table->json('summary_of_changes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_spatie_translations');
    }
};
