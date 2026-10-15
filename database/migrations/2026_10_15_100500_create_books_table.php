<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->char('isbn', 13)->unique(); // ISBN-13, digits only
            $table->unsignedInteger('price'); // in cents
            $table->smallInteger('published_year')->nullable();
            $table->string('format'); // BookFormat enum
            // restrict: a genre that still has books cannot be deleted by accident.
            $table->foreignId('genre_id')->constrained()->restrictOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->json('meta')->nullable();
            // Denormalized average of approved reviews, recalculated by a job later.
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
