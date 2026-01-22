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
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address', 45)->unique();
            $table->enum('type', ['HIOSO', 'HSGQ', 'ZTE', 'VSOL', 'Lainnya'])->default('HIOSO');
            $table->enum('pon_ports', ['2', '4', '8', '16'])->default('8');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->unsignedSmallInteger('telnet_port')->default(23);
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('snmp_community')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->text('notes')->nullable();
            $table->string('firmware_version')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};
