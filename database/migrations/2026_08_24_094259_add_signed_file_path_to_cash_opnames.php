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
        Schema::table('cash_opnames', function (Blueprint $table) {
            $table->string('signed_file_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cash_opnames', function (Blueprint $table) {
            $table->dropColumn('signed_file_path');
        });
    }
};
