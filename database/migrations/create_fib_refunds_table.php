<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fib_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('fib_payments')->cascadeOnDelete();
            $table->string('fib_trace_id')->nullable();
            $table->string('status')->default('PENDING')->index();
            $table->json('error_codes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fib_refunds');
    }
};
