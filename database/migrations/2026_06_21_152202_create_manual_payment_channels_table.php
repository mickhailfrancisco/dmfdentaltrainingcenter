<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('manual_payment_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_code', 32)->unique();
            $table->string('type', 20);
            $table->string('display_name');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('qr_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $banks = (array) config('bank-transfer.banks', []);
        $remittance = (array) config('bank-transfer.remittance', []);

        $bankChannelCodes = [
            'BDO' => 'bdo',
            'BPI' => 'bpi',
            'ChinaBank' => 'chinabank',
        ];

        $now = now();

        foreach ($banks as $bank) {
            $displayName = (string) ($bank['bank_name'] ?? '');
            $channelCode = $bankChannelCodes[$displayName] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '', $displayName));

            DB::table('manual_payment_channels')->insert([
                'channel_code' => $channelCode,
                'type' => 'bank_transfer',
                'display_name' => $displayName,
                'account_name' => $bank['account_name'] ?? null,
                'account_number' => $bank['account_number'] ?? null,
                'receiver_name' => null,
                'contact_number' => null,
                'logo_path' => $bank['logo_path'] ?? null,
                'qr_path' => $bank['qr_path'] ?? null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('manual_payment_channels')->insert([
            'channel_code' => 'palawan_express',
            'type' => 'remittance',
            'display_name' => 'Palawan Express',
            'account_name' => null,
            'account_number' => null,
            'receiver_name' => $remittance['receiver_name'] ?? null,
            'contact_number' => $remittance['contact_number'] ?? null,
            'logo_path' => null,
            'qr_path' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_payment_channels');
    }
};
