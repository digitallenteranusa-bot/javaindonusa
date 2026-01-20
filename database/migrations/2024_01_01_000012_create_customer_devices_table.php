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
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // GenieACS Device Info
            $table->string('device_id')->unique()->comment('GenieACS Device ID');
            $table->string('serial_number')->nullable()->index();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('hardware_version')->nullable();

            // Connection Info
            $table->string('wan_ip')->nullable();
            $table->string('wan_mac')->nullable();

            // PON/Optical Info
            $table->string('pon_serial')->nullable();
            $table->decimal('rx_power', 8, 2)->nullable()->comment('dBm');
            $table->decimal('tx_power', 8, 2)->nullable()->comment('dBm');

            // WiFi Info
            $table->string('wifi_ssid')->nullable();
            $table->boolean('wifi_enabled')->nullable();

            // Status
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_inform')->nullable();
            $table->timestamp('last_boot')->nullable();
            $table->unsignedBigInteger('uptime')->nullable()->comment('seconds');

            // Metadata
            $table->json('tags')->nullable()->comment('GenieACS tags');
            $table->json('raw_data')->nullable()->comment('Raw device data from GenieACS');
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('is_online');
            $table->index('last_inform');
            $table->index(['manufacturer', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_devices');
    }
};
