<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // khqr = generate-qr shown on Mirai's pay page; card = PayWay's hosted card checkout (Purchase API).
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 10)->default('khqr')->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
