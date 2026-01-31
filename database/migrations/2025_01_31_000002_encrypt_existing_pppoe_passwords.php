<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Encrypt all existing plaintext PPPoE passwords
     */
    public function up(): void
    {
        // Get all customers with PPPoE passwords
        $customers = DB::table('customers')
            ->whereNotNull('pppoe_password')
            ->where('pppoe_password', '!=', '')
            ->get(['id', 'pppoe_password']);

        foreach ($customers as $customer) {
            // Check if already encrypted
            try {
                Crypt::decryptString($customer->pppoe_password);
                // Already encrypted, skip
                continue;
            } catch (\Exception $e) {
                // Not encrypted, encrypt it
                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update([
                        'pppoe_password' => Crypt::encryptString($customer->pppoe_password)
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     * Decrypt all PPPoE passwords back to plaintext
     */
    public function down(): void
    {
        // Get all customers with PPPoE passwords
        $customers = DB::table('customers')
            ->whereNotNull('pppoe_password')
            ->where('pppoe_password', '!=', '')
            ->get(['id', 'pppoe_password']);

        foreach ($customers as $customer) {
            try {
                $decrypted = Crypt::decryptString($customer->pppoe_password);
                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update(['pppoe_password' => $decrypted]);
            } catch (\Exception $e) {
                // Already plaintext, skip
                continue;
            }
        }
    }
};
