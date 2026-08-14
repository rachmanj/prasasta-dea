<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('journal_no', 50)->unique();
            $table->enum('type', ['receipt', 'payment', 'transfer', 'journal']);
            $table->date('date');
            $table->string('description', 255)->nullable();
            $table->string('ref_no', 100)->nullable();
            $table->enum('status', ['draft', 'posted'])->default('posted');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
