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
        Schema::create('radius_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('auth_port')->default(1812);
            $table->unsignedSmallInteger('acct_port')->default(1813);
            $table->string('secret')->comment('Encrypted RADIUS secret');
            $table->enum('status', ['active', 'inactive', 'testing'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radius_servers');
    }
};
