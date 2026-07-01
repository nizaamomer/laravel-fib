<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fib_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('account')->default('default');
            $table->uuid('payout_id')->unique();
            $table->string('target_account_iban');
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('IQD');
            $table->string('status')->default('CREATED')->index();
            $table->nullableMorphs('payable');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fib_payouts');
    }
};
