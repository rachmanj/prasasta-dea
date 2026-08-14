<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['receivable', 'payable']);
            $table->string('bill_no', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->date('due_date');
            $table->enum('status', ['open', 'partial', 'paid'])->default('open');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->foreignId('account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
