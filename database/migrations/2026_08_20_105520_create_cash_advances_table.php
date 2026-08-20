<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->string('advance_no', 50)->unique();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('description', 255)->nullable();
            $table->enum('status', ['open', 'partial', 'settled'])->default('open');
            $table->decimal('realized_amount', 15, 2)->default(0);
            $table->decimal('returned_amount', 15, 2)->default(0);
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advances');
    }
};
