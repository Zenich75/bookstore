<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Tag;
use Database\Seeders\Support\BulkInserts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    use BulkInserts;

    private const TAGS = [
        'Бестселер', 'Новинка', 'Лауреат премії', 'Класика', 'Вибір продавця', 'Дебют',
        'Для книжкового клубу', 'Серія', 'Окрема книга', 'Ілюстроване видання', 'З автографом', 'Обмежений наклад',
        'Переклад', 'На реальних подіях', 'Екранізовано', 'Коротке читання', 'Епопея', 'Затишне',
        'Похмуре', 'Смішне', 'Надихає', 'Пізнавальне', 'Для всієї родини', 'Пригоди', 'Філософія',
        'Нобелівський лауреат', 'Пулітцерівська премія', 'Букерівська премія', 'Незалежний автор', 'Український автор',
    ];

    public function run(): void
    {
        $now = now();

        DB::transaction(function () {
            foreach (self::TAGS as $name) {
                Tag::create(['name' => $name, 'slug' => Str::slug($name, '-', 'uk')]);
            }

            $tagIds = DB::table('tags')->pluck('id')->all();

            // ~30% of books and authors get 1-3 tags.
            foreach (['books' => new Book, 'authors' => new Author] as $table => $model) {
                $morphClass = $model->getMorphClass();

                $this->insertChunked('taggables', (function () use ($table, $morphClass, $tagIds) {
                    foreach (DB::table($table)->select('id')->lazyById(1000) as $row) {
                        if (mt_rand(1, 100) > 30) {
                            continue;
                        }

                        foreach ($this->pickRandom($tagIds, mt_rand(1, 3)) as $tagId) {
                            yield [
                                'tag_id' => $tagId,
                                'taggable_type' => $morphClass,
                                'taggable_id' => $row->id,
                            ];
                        }
                    }
                })());
            }
        });
    }
}
