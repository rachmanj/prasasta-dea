<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_no', 50)->unique();
            $table->string('name', 191);
            $table->decimal('cost', 15, 2);
            $table->date('acquisition_date');
            $table->unsignedInteger('useful_life_months');
            $table->decimal('monthly_depreciation', 15, 2);
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->enum('status', ['active', 'fully_depreciated'])->default('active');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
