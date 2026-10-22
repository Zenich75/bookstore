<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One uploaded CSV file and the progress of its queued processing.
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // uploader
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path'); // on the "local" disk: imports/...
            $table->string('status'); // ImportStatus enum
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('failed')->default(0);
            // {"<row number>": ["message", ...]} — keyed by row, so a retried chunk
            // overwrites its own entries instead of appending duplicates.
            $table->json('errors')->nullable();
            $table->string('batch_id')->nullable(); // job_batches.id
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
