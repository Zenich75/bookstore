<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Which import row produced the movement. The unique index makes a re-run
            // of an import chunk idempotent: the same row cannot be booked twice.
            // NULLs are not equal to each other, so manual movements are unaffected.
            $table->foreignId('import_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('source_row')->nullable()->after('import_id');
            $table->unique(['import_id', 'source_row']);
            // model:prune deletes by age.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropUnique(['import_id', 'source_row']);
            $table->dropConstrainedForeignId('import_id');
            $table->dropColumn('source_row');
        });
    }
};
