<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('date');
            $table->foreignId('account_id')->constrained('accounts');
            $table->decimal('book_balance', 15, 2);
            $table->decimal('physical_balance', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->enum('status', ['open', 'adjusted'])->default('open');
            $table->foreignId('adjustment_transaction_id')->nullable()->constrained('transactions');
            $table->foreignId('prepared_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_opname_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_opname_id')->constrained('cash_opnames')->cascadeOnDelete();
            $table->integer('denomination');
            $table->enum('type', ['banknote', 'coin']);
            $table->integer('units')->default(0);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_opname_lines');
        Schema::dropIfExists('cash_opnames');
    }
};
