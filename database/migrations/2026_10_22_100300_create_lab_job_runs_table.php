<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // /lab/queues: what demo jobs measured on the worker side.
        Schema::create('lab_job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('variant');
            $table->string('job');
            $table->string('queue')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedInteger('payload_bytes')->nullable();
            $table->unsignedInteger('restore_queries')->nullable(); // while unserializing models
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_job_runs');
    }
};
