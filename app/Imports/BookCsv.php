<?php

namespace App\Imports;

use App\Models\Import;
use Generator;
use SplFileObject;

/**
 * Streaming reader for the books CSV (header row + one book per line).
 *
 * Rows are addressed by their line number in the file ("row 2" = first data row),
 * the number a person sees in a spreadsheet — used in error reports and as
 * stock_movements.source_row. Line-based seek() means fields must not contain
 * line breaks, which this format does not need.
 */
final class BookCsv
{
    private SplFileObject $file;

    /**
     * @var list<string>
     */
    private array $header;

    public function __construct(string $path)
    {
        $this->file = new SplFileObject($path);
        $this->file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $this->file->rewind();
        $header = $this->file->current() ?: [];
        // Excel adds a UTF-8 BOM to "CSV UTF-8" files.
        $this->header = array_map(fn ($column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), $header);
    }

    /**
     * @return list<string>
     */
    public function header(): array
    {
        return $this->header;
    }

    /**
     * @return list<string>
     */
    public function missingColumns(): array
    {
        return array_values(array_diff(Import::REQUIRED_COLUMNS, $this->header));
    }

    /**
     * Data rows only (header excluded), counted without loading the file.
     */
    public static function countRows(string $path): int
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $lines = 0;

        foreach ($file as $line) {
            if (trim((string) $line) !== '') {
                $lines++;
            }
        }

        return max(0, $lines - 1);
    }

    /**
     * $limit data rows starting at data row $offset (0-based), keyed by line number.
     *
     * @return Generator<int, array<string, string>>
     */
    public function rows(int $offset, int $limit): Generator
    {
        $this->file->seek($offset + 1); // +1: skip the header

        for ($i = 0; $i < $limit && $this->file->valid(); $i++, $this->file->next()) {
            $values = $this->file->current();

            if (! is_array($values) || $values === [null]) {
                continue;
            }

            $values = array_pad(array_slice($values, 0, count($this->header)), count($this->header), '');

            yield $offset + $i + 2 => array_map(fn ($value) => trim((string) $value), array_combine($this->header, $values));
        }
    }
}
