<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_server_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            $table->enum('protocol', ['openvpn', 'wireguard'])->default('openvpn');
            $table->string('common_name', 100)->unique()->nullable(); // For OpenVPN
            $table->string('public_key', 64)->unique()->nullable();   // For WireGuard
            $table->string('private_key', 64)->nullable();            // For WireGuard
            $table->string('preshared_key', 64)->nullable();          // For WireGuard
            $table->string('client_vpn_ip', 45);
            $table->string('mikrotik_lan_subnet')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->string('remote_ip')->nullable();
            $table->bigInteger('bytes_received')->default(0);
            $table->bigInteger('bytes_sent')->default(0);
            $table->text('generated_config')->nullable();
            $table->text('generated_script')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_enabled');
            $table->index('protocol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_server_clients');
    }
};
