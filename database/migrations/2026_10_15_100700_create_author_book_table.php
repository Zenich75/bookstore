<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_book', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('author'); // AuthorRole enum
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            // The same person may be both author and translator of a book.
            $table->unique(['author_id', 'book_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_book');
    }
};
