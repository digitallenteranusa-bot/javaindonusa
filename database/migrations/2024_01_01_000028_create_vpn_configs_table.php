<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vpn_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->enum('protocol', ['l2tp', 'pptp', 'sstp', 'wireguard'])->default('l2tp');
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable()->comment('Protocol-specific settings');
            $table->text('generated_script')->nullable()->comment('Generated RouterOS script');
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['router_id', 'protocol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vpn_configs');
    }
};
