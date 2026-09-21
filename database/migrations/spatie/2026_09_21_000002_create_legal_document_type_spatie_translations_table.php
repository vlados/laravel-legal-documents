<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_document_type_spatie_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_type_id')->unique('ld_type_translation_parent_unique');
            $table->foreign('legal_document_type_id', 'ld_type_translation_parent_foreign')
                ->references('id')->on('legal_document_types')->cascadeOnDelete();
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_type_spatie_translations');
    }
};
