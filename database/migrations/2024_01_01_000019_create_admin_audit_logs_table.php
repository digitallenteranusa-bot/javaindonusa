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
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();

            // Admin yang melakukan aksi
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();

            // Module/kategori aksi
            $table->string('module', 50)->comment('Module: user, customer, invoice, payment, router, area, package, setting, etc');

            // Aksi yang dilakukan
            $table->string('action', 50)->comment('Action: create, update, delete, login, logout, export, import, etc');

            // Deskripsi aksi
            $table->text('description');

            // Polymorphic relationship ke model yang terpengaruh
            $table->string('auditable_type')->nullable()->comment('Model class name');
            $table->unsignedBigInteger('auditable_id')->nullable()->comment('Model ID');

            // Data changes
            $table->json('old_values')->nullable()->comment('Data sebelum perubahan');
            $table->json('new_values')->nullable()->comment('Data setelah perubahan');

            // Request info
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable()->comment('Data tambahan seperti filters, export params, dll');

            $table->timestamps();

            // Indexes for efficient querying
            $table->index('module');
            $table->index('action');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
            $table->index(['admin_id', 'created_at']);
            $table->index(['module', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
