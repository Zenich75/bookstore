<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Plain string column backed by the UserRole enum: adding a role later
            // needs no schema change (unlike a native DB ENUM).
            $table->string('role')->default('reader')->after('password');
            // A manager belongs to a warehouse; deleting the warehouse keeps the user.
            $table->foreignId('warehouse_id')->nullable()->after('role')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('role');
        });
    }
};
