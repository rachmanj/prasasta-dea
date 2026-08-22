<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
        });

        Schema::table('cash_advance_realizations', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('cash_advance_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_advance_realizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });

        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
    }
};
