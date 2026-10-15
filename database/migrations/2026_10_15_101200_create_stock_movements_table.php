<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger: rows are never updated, so there is no updated_at.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            // Keep the history when the user who made the movement is deleted.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('delta'); // positive = in, negative = out
            $table->string('reason'); // StockMovementReason enum
            $table->timestamp('created_at')->nullable();

            $table->index(['warehouse_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
