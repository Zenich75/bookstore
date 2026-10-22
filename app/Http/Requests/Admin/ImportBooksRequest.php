<?php

namespace App\Http\Requests\Admin;

use App\Imports\BookCsv;
use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportBooksRequest extends FormRequest
{
    /**
     * Importing creates/updates books: the same ability as the book form.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', Book::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // mimes checks the extension and the detected MIME type (text/csv, text/plain).
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('warehouse.import.max_kb')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
        ];
    }

    /**
     * The row count decides how many chunk jobs are planned, so check it up front.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('file')) {
                    return;
                }

                $rows = BookCsv::countRows($this->file('file')->getRealPath());

                if ($rows === 0) {
                    $validator->errors()->add('file', __('ui.imports.errors.empty'));
                } elseif ($rows > config('warehouse.import.max_rows')) {
                    $validator->errors()->add('file', __('ui.imports.errors.too_many_rows', ['max' => config('warehouse.import.max_rows')]));
                }
            },
        ];
    }
}
