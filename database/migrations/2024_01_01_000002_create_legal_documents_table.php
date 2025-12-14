<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_type_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('title');
            $table->longText('content');
            $table->text('summary_of_changes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->boolean('requires_re_acceptance')->default(true);
            $table->boolean('notify_users')->default(true);
            $table->timestamps();

            $table->unique(['legal_document_type_id', 'version']);
            $table->index(['legal_document_type_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
