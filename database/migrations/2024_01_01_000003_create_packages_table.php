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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('speed_download')->comment('Speed in Kbps');
            $table->unsignedInteger('speed_upload')->comment('Speed in Kbps');
            $table->decimal('price', 12, 2);
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('mikrotik_profile')->nullable()->comment('PPPoE profile name di Mikrotik');
            $table->string('burst_limit')->nullable()->comment('Burst limit setting');
            $table->string('burst_threshold')->nullable();
            $table->string('burst_time')->nullable();
            $table->unsignedTinyInteger('priority')->default(8)->comment('Queue priority 1-8');
            $table->string('address_list')->nullable()->comment('Address list untuk firewall');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
