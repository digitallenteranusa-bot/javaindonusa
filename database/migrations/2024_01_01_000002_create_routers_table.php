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
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->string('username');
            $table->string('password');
            $table->string('identity')->nullable()->comment('Router identity dari Mikrotik');
            $table->string('version')->nullable()->comment('RouterOS version');
            $table->string('model')->nullable()->comment('Router model');
            $table->string('serial_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_connected_at')->nullable();
            $table->string('uptime')->nullable();
            $table->unsignedTinyInteger('cpu_load')->nullable()->comment('CPU usage percentage');
            $table->unsignedTinyInteger('memory_usage')->nullable()->comment('Memory usage percentage');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
