# 02 — Зв’язки Eloquent

## Мета

Описати в Eloquent кожен зв’язок предметної області складу: «один до одного», «один до багатьох»,
«багато до багатьох» (зі звичайною та власною проміжною таблицею (pivot)), «один із багатьох»
(has-one-of-many), зв’язки «через» (through) і поліморфні. Навчитися робити до них запити,
агрегувати й записувати — завжди завантажуючи зв’язки явно, бо суворий режим (strict mode)
забороняє ліниве завантаження (lazy loading).

Відкрийте **<http://localhost/lab/relations>**: кожен приклад показує свій код, результат і точний SQL.

## Де дивитися в коді

- `app/Models/*` — методи зв’язків із типами результату та generic-анотаціями `@return`.
- `app/Models/Stock.php` — власна pivot-модель (`extends Pivot`) зі своїм id та аксесором.
- `app/Providers/AppServiceProvider.php` — `Relation::enforceMorphMap()`.
- `database/migrations/2026_10_16_100000_use_morph_aliases_in_polymorphic_columns.php` — міграція
  даних з імен класів на псевдоніми.
- `database/factories/BookFactory.php` — `withReviews()`, `withAuthors()`, `inWarehouses()`;
  `AuthorFactory::withBooks()` (`has()` / `hasAttached()`).
- `app/Lab/RelationExamples.php`, `app/Lab/Samples.php` — реєстр прикладів лабораторії та зразкові записи.
- `tests/Feature/Relations/*`, `tests/Feature/Lab/LabRelationsTest.php`.

## Ключові поняття

### Типи зв’язків у проєкті

| Тип | Приклад | Згенерований SQL (скорочено) |
|---|---|---|
| `hasOne` | `User::profile()` | `select * from profiles where user_id in (…)` |
| `belongsTo` | `Review::book()`, `User::warehouse()`, `Genre::parent()` | `select * from books where id in (…)` |
| `hasMany` | `User::reviews()`, `Genre::children()`, `Book::stocks()` | `select * from reviews where user_id in (…)` |
| `hasMany` + `where` | `Book::approvedReviews()` (до етапу 6; тепер `Book::reviews()` + глобальний скоуп `ApprovedScope`, див. [06](06-attributes-casts.md)) | `… where book_id in (…) and is_approved = 1` |
| `hasOne` + `where` | `Warehouse::manager()` | `select * from users where role = 'manager' and warehouse_id in (…)` |
| `hasOne()->latestOfMany()` | `Book::latestReview()` | `inner join (select max(id) … group by book_id)` |
| `hasOne()->ofMany('rating','max')` | `Book::bestReview()` | вкладені підзапити `max(rating)`, потім `max(id)`, приєднані назад |
| `hasOneThrough` | `Warehouse::managerProfile()` | `select profiles.*, users.warehouse_id as laravel_through_key from profiles inner join users on users.id = profiles.user_id where users.warehouse_id in (…)` |
| `hasManyThrough` | `Genre::reviews()` | `select reviews.* … inner join books on books.id = reviews.book_id where books.genre_id = ? and books.deleted_at is null` |
| `belongsToMany` | `Book::genres()` / `Genre::secondaryBooks()` | `select genres.*, book_genre.book_id as pivot_book_id … inner join book_genre …` |
| `belongsToMany` + `withPivot`/`as` | `Book::authors()` / `Author::books()` | `… author_book.role as pivot_role, author_book.sort_order as pivot_sort_order … order by author_book.sort_order` |
| `belongsToMany` + `wherePivot` | `Book::translators()`, `Author::translatedBooks()` | `… and author_book.role = 'translator'` |
| `belongsToMany` + `using()` | `Warehouse::books()` / `Book::warehouses()` | `… stocks.id as pivot_id, stocks.quantity as pivot_quantity …` |
| `morphOne` (+ `where`) | `Book::cover()`, `Author::photo()` | `select * from images where imageable_type = 'book' and imageable_id in (…) and type = 'cover'` |
| `morphMany` | `Book::images()`, `Author::images()` | `… where imageable_type = 'book' and imageable_id in (…)` |
| `morphTo` | `Image::imageable()` | по одному запиту на тип: `select * from authors where id in (…)` |
| `morphToMany` | `Book::tags()`, `Author::tags()` | `… inner join taggables … where taggable_type = 'book'` |
| `morphedByMany` | `Tag::books()`, `Tag::authors()` | `… inner join taggables on books.id = taggables.taggable_id where taggable_type = 'book'` |

Запити (розділ лабораторії «Запити до зв’язків»): `has`, `whereHas`, `doesntHave`, `whereRelation`
додають підзапити `exists (select …)`; `withCount`, `withAvg('reviews', 'rating')`,
`withSum('stocks', 'quantity')`, `withExists('cover')` додають корельовані підзапити у вибірку як
`reviews_count`, `reviews_avg_rating`, `stocks_sum_quantity`, `cover_exists`.

Запис (розділ «Запис зв’язків», з відкатом): `attach`, `detach`, `sync`,
`syncWithoutDetaching`, `toggle` (останні три повертають id у `attached/detached/updated`),
`updateExistingPivot`, `associate`/`dissociate` (belongsTo — не забудьте `save()`),
`saveMany`/`createMany` (hasMany/morphMany).

### Суворий режим і завантаження зв’язків

З `Model::shouldBeStrict()` звернення `$book->authors` до книги, завантаженої без `authors`, кидає
`LazyLoadingViolationException`. Завантажуйте явно:

```php
Book::with('authors')->find($id);              // eager, 2 queries for any number of books
$book->load('authors');                        // eager, after the fact
$book->authors()->where(...)->get();           // relation *method* = a query builder, always fine
```

### Підводні камені

**`hasOne`, коли рядків багато.** `hasOne(Review::class)` для книги з 10 відгуками повертає
довільний відгук (той, що база поверне першим). Використовуйте «один із багатьох»:
`->latestOfMany()`, `->oldestOfMany()` або `->ofMany('rating', 'max')`. Нічию за агрегованим
стовпцем розв’язує `MAX(id)`, тож результат детермінований.

**Порядок ключів у `hasOneThrough` / `hasManyThrough`.**

```php
$this->hasManyThrough(
    Review::class,  // final
    Book::class,    // intermediate
    'genre_id',     // FK on the intermediate table (books) pointing to this model
    'book_id',      // FK on the final table (reviews) pointing to the intermediate
    'id',           // local key on this model (genres)
    'id',           // local key on the intermediate model (books)
);
```

Переплутати 3-й і 4-й аргументи — класична помилка: SQL коректний, але з’єднує не ті стовпці й
повертає порожній результат (або сміття). М’яке видалення проміжної моделі застосовується
автоматично (`books.deleted_at is null`).

**Стовпці проміжної таблиці за замовчуванням не завантажуються.** Без `->withPivot([...])`
вибираються лише два ключі, тож `$author->credit->role` дорівнює `null`, а із суворим режимом —
кидає `MissingAttributeException`; без `->withTimestamps()` `attach()` залишає `created_at/updated_at`
порожніми. Обидва виклики треба оголосити з *кожного боку* зв’язку (`Book::authors()` і `Author::books()`).

**Власна pivot-модель і `$incrementing`.** `Pivot` встановлює `$incrementing = false`, бо класичні
проміжні таблиці мають складений ключ. У `stocks` є власний автоінкрементний `id`, тому `Stock`
оголошує `#[Table(name: 'stocks', incrementing: true)]` (старий варіант: `public $incrementing = true;`).
Додайте також `'id'` у `withPivot()`, щоб отримати його в `$book->stock`. Вказуйте
`->using(Stock::class)` з обох боків і `->as('stock')` — для зрозумілої назви замість `->pivot`.

**Мапа морфів (morph map).** Без мапи Eloquent зберігає в `imageable_type` ім’я класу
(`App\Models\Book`). Це прив’язує дані до коду: перейменування чи переміщення моделі непомітно
залишає рядки «сиротами». `Relation::morphMap([...])` зберігає короткі псевдоніми (`book`), але
класи, *яких немає* в мапі, як і раніше записуються іменами класів — дані стають неоднорідними.
`Relation::enforceMorphMap([...])` зберігає псевдоніми **і кидає `ClassMorphViolationException`**
для будь-якої моделі без псевдоніма, тож забута реєстрація нової поліморфної моделі гучно падає в
тестах, а не записує імена класів у робочі дані. Наявні рядки перетворює міграція даних
(`up()`: повне ім’я класу → псевдонім, `down()` — назад); вона використовує звичайні рядки, а не
`Book::class`, тому лишається коректною, навіть якщо моделі зміняться.

**Зв’язки з умовою і запис.** `Book::cover()` додає `where type = 'cover'`, але
`$book->cover()->create([...])` *не* встановлює `type` за вас — передавайте його явно.

### Угоди щодо назв

- `hasOne`/`belongsTo`/`morphOne`/`morphTo` → однина (`profile`, `genre`, `cover`, `imageable`);
  `hasMany`/`belongsToMany`/`morph*Many` → множина (`reviews`, `authors`, `tags`).
- Зовнішній ключ = `{singular_model}_id` (`genre_id`); проміжна таблиця = обидві назви в однині
  в алфавітному порядку (`author_book`, `book_genre`) — тоді додаткові аргументи не потрібні.
- Зв’язки сам на себе й нестандартні назви потребують явних ключів: `belongsTo(Genre::class, 'parent_id')`.
- Варіанти з умовою називаємо за змістом: `translators`, `latestReview`, `bestReview`.
- Морф-стовпці: `{name}able_type` / `{name}able_id` (`imageable`, `taggable`).

## Вправа

1. Додайте `Author::coAuthors()` — інших авторів, з якими автор писав книги. Який тип зв’язку
   підходить? (Підказка: готового вбудованого немає; подумайте про запит через `books`.)
2. Додайте `Book::lowestReview()` з `ofMany` і тест з однаковими оцінками.
3. Зробіть `Warehouse` поліморфним власником зображень (фото будівлі): що треба змінити в
   `enforceMorphMap` і що станеться, якщо забути? Спершу напишіть тест, що падає.
4. У лабораторії порівняйте SQL `Book::has('reviews', '>=', 8)` і
   `Book::withCount('reviews')->having('reviews_count', '>=', 8)`.
