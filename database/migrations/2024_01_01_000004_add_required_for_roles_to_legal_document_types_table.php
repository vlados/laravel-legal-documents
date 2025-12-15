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
        Schema::table('legal_document_types', function (Blueprint $table) {
            $table->json('required_for_roles')->nullable()->after('is_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_document_types', function (Blueprint $table) {
            $table->dropColumn('required_for_roles');
        });
    }
};
