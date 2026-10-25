<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set when staff approve a sandbox payment by hand; null when ABA confirmed it.
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('confirmed_by')->nullable()->after('apv')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
        });
    }
};
