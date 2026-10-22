<?php

namespace App\Console\Commands;

use App\Enums\BookFormat;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Import;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sample CSV for the admin import (/admin/imports). Dev tool: uses Faker (uk_UA).
 * About 10 % of the rows reuse ISBNs of existing books (upsert + extra stock),
 * --errors percent of the rows are broken in one of several ways.
 */
#[Signature('import:make-sample {--rows=1000} {--errors=5 : Percent of invalid rows} {--name= : File name (default sample-<rows>.csv)}')]
#[Description('Generate a sample books CSV for the admin import')]
class MakeImportSample extends Command
{
    public function handle(): int
    {
        $rows = max(1, (int) $this->option('rows'));
        $errorPercent = min(100, max(0, (int) $this->option('errors')));
        $genres = Genre::pluck('slug')->all();

        if ($genres === []) {
            $this->error('No genres yet — run the seeders first.');

            return self::FAILURE;
        }

        $existing = Book::inRandomOrder()->limit(max(1, intdiv($rows, 10)))->pluck('isbn')->all();
        $faker = fake('uk_UA');
        $name = $this->option('name') ?: "samples/sample-{$rows}.csv";
        $disk = Storage::disk('imports');
        $disk->makeDirectory(dirname($name));

        $out = fopen($disk->path($name), 'w');
        fputcsv($out, Import::COLUMNS);
        $broken = 0;

        for ($i = 0; $i < $rows; $i++) {
            $row = [
                'isbn' => $existing !== [] && $faker->boolean(10) ? $faker->randomElement($existing) : $this->isbn(),
                'title' => rtrim($faker->realTextBetween(15, 50), '.,!?;: '),
                'authors' => implode('; ', array_map(fn () => $faker->firstName().' '.$faker->lastName(), range(1, $faker->numberBetween(1, 2)))),
                'genre' => $faker->randomElement($genres),
                'format' => $faker->randomElement(BookFormat::cases())->value,
                'price' => $faker->numberBetween(99, 1499).','.$faker->randomElement(['00', '50', '90']),
                // Inside the books.published_at TIMESTAMP range (after 1970-01-01): Faker's
                // date() starts at the Unix epoch and would produce invalid "valid" rows.
                'published_at' => $faker->optional(0.8)->dateTimeBetween('-40 years', '-1 day')?->format('Y-m-d') ?? '',
                'quantity' => $faker->numberBetween(1, 40),
                'shelf_code' => sprintf('%s-%02d-%d', chr(random_int(65, 72)), random_int(1, 40), random_int(1, 6)),
            ];

            if (random_int(1, 100) <= $errorPercent) {
                $row = $this->break($row);
                $broken++;
            }

            fputcsv($out, array_values($row));
        }

        fclose($out);

        $this->info("{$rows} rows ({$broken} invalid) written to storage/app/imports/{$name}");

        return self::SUCCESS;
    }

    /**
     * Valid ISBN-13 with the 979 prefix (does not collide with the seeded 978… books).
     */
    private function isbn(): string
    {
        $body = '979'.str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($body) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }

    /**
     * @param  array<string, string|int>  $row
     * @return array<string, string|int>
     */
    private function break(array $row): array
    {
        return match (random_int(1, 6)) {
            1 => [...$row, 'isbn' => substr((string) $row['isbn'], 0, 12).(((int) substr((string) $row['isbn'], -1) + 1) % 10)], // wrong check digit
            2 => [...$row, 'genre' => 'no-such-genre'],
            3 => [...$row, 'quantity' => -3],
            4 => [...$row, 'price' => '12,345'],
            5 => [...$row, 'title' => ''],
            default => [...$row, 'format' => 'vinyl'],
        };
    }
}
