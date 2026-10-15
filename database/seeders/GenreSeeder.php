<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenreSeeder extends Seeder
{
    /**
     * Fixed two-level genre tree: root => children.
     *
     * @var array<string, list<string>>
     */
    private const TREE = [
        'Художня література' => ['Сучасна проза', 'Історичний роман', 'Класична література'],
        'Детективи та трилери' => ['Кримінальний детектив', 'Класичний детектив', 'Психологічний трилер'],
        'Наукова фантастика' => ['Космічна опера', 'Кіберпанк', 'Антиутопія'],
        'Фентезі' => ['Епічне фентезі', 'Міське фентезі', 'Темне фентезі'],
        'Любовний роман' => ['Романтична комедія', 'Історичний любовний роман', 'Паранормальний любовний роман'],
        'Жахи' => ['Готичні жахи', 'Містичні жахи'],
        'Біографії та мемуари' => ['Автобіографія', 'Політична біографія', 'Мемуари мандрівників'],
        'Історія' => ['Стародавня історія', 'Воєнна історія', 'Світова історія'],
        'Наука і техніка' => ['Науково-популярна література', 'Інформатика', 'Математика'],
        'Дитяча література' => ['Книжки з картинками', 'Підліткова література', 'Для молодшого шкільного віку'],
        'Бізнес і економіка' => ['Менеджмент', 'Особисті фінанси'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::TREE as $rootName => $children) {
                $root = Genre::create(['name' => $rootName, 'slug' => Str::slug($rootName, '-', 'uk')]);

                foreach ($children as $childName) {
                    Genre::create([
                        'name' => $childName,
                        'slug' => Str::slug($childName, '-', 'uk'),
                        'parent_id' => $root->id,
                    ]);
                }
            }
        });
    }
}
