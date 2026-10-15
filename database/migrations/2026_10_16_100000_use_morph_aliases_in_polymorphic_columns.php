<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: class names -> morph map aliases (Relation::enforceMorphMap).
 *
 * Plain strings on purpose: a data migration must not depend on today's models
 * or morph map, which may change after it was written.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> FQCN => alias
     */
    private const ALIASES = [
        'App\Models\Book' => 'book',
        'App\Models\Author' => 'author',
    ];

    /**
     * @var array<string, string> table => morph type column
     */
    private const COLUMNS = [
        'images' => 'imageable_type',
        'taggables' => 'taggable_type',
    ];

    public function up(): void
    {
        $this->replace(self::ALIASES);
    }

    public function down(): void
    {
        $this->replace(array_flip(self::ALIASES));
    }

    /**
     * @param  array<string, string>  $map  from => to
     */
    private function replace(array $map): void
    {
        DB::transaction(function () use ($map) {
            foreach (self::COLUMNS as $table => $column) {
                foreach ($map as $from => $to) {
                    DB::table($table)->where($column, $from)->update([$column => $to]);
                }
            }
        });
    }
};
