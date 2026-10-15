<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot between warehouses and books with its own data (becomes a pivot model in stage 2).
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->string('shelf_code')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
