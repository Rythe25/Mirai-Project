<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per KHQR. An order can have several (expired QR → new QR).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('tran_id', 20)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->text('qr_string')->nullable();
            $table->text('qr_image')->nullable();
            $table->text('deeplink')->nullable();
            $table->string('apv', 20)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
