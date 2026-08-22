<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('reconciliations');

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->decimal('opening_balance_bank', 15, 2)->nullable();
            $table->decimal('closing_balance_bank', 15, 2)->nullable();
            $table->decimal('opening_balance_book', 15, 2)->nullable();
            $table->decimal('closing_balance_book', 15, 2)->nullable();
            $table->enum('status', ['draft', 'in_review', 'completed'])->default('draft');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'period']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->text('description');
            $table->string('reference', 191)->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->nullable();
            $table->enum('matched_status', ['unmatched', 'matched', 'manual', 'excluded'])->default('unmatched');
            $table->string('exclude_reason', 255)->nullable();
            $table->unsignedInteger('line_order')->nullable();
            $table->timestamps();

            $table->index(['bank_reconciliation_id', 'matched_status']);
        });

        Schema::create('reconciliation_match_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->enum('match_type', ['auto', 'manual']);
            $table->decimal('bank_total', 15, 2);
            $table->decimal('book_total', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('match_group_bank_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_group_id')->constrained('reconciliation_match_groups')->cascadeOnDelete();
            $table->foreignId('bank_statement_line_id')->constrained()->cascadeOnDelete();

            $table->unique('bank_statement_line_id');
        });

        Schema::create('match_group_book_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_group_id')->constrained('reconciliation_match_groups')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();

            $table->unique('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_group_book_lines');
        Schema::dropIfExists('match_group_bank_lines');
        Schema::dropIfExists('reconciliation_match_groups');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_reconciliations');

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('source_ref', 191)->nullable();
            $table->boolean('is_matched')->default(false);
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'source_ref']);
        });

        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('as_of_date');
            $table->decimal('closing_balance', 15, 2);
            $table->enum('status', ['open', 'completed'])->default('open');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
