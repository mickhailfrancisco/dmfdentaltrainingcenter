<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('enrollment_id')
                ->unique()
                ->constrained('enrollments')
                ->onDelete('cascade');

            // PayMongo fields
            $table->string('paymongo_checkout_session_id')->nullable();
            $table->string('paymongo_payment_intent_id')->nullable();
            $table->string('paymongo_payment_id')->nullable();

            $table->string('payment_method', 20)->nullable();   // card | bank_transfer | legacy rows may hold older Paymongo slugs
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('PHP');

            $table->string('status', 20)->default('pending');   // pending | paid | failed | refunded
            $table->timestamp('paid_at')->nullable();

            $table->json('paymongo_payload')->nullable();        // Raw webhook payload for audit

            $table->timestamps();

            $table->index('status');
            $table->index('paymongo_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
