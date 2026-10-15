<?php

namespace App\Http\Controllers;

use App\Reports\StockByWarehouseGenreReport;
use App\Reports\TopAuthorsReport;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invokable controller behind all reports.* routes; the route passes $report
 * via ->defaults(). Access is restricted to managers/admins in stage 7.
 */
class ReportController extends Controller
{
    public function __invoke(string $report): View|StreamedResponse
    {
        return match ($report) {
            'stock' => $this->stock(),
            'stock-csv' => $this->stockCsv(),
            'top-authors' => $this->topAuthors(),
        };
    }

    private function stock(): View
    {
        $report = new StockByWarehouseGenreReport;
        $rows = $report->rows();

        return view('reports.stock', [
            'warehouses' => $rows->groupBy('warehouse'),
            'totals' => $report->totals($rows),
        ]);
    }

    private function topAuthors(): View
    {
        $report = new TopAuthorsReport;

        return view('reports.top-authors', [
            'authors' => $report->rows(),
            'minReviews' => $report->minReviews(),
        ]);
    }

    /**
     * Streams the CSV while reading stock rows in chunks (lazyById):
     * memory stays flat no matter how many rows there are.
     */
    private function stockCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['warehouse', 'shelf', 'isbn', 'title', 'quantity', 'reserved', 'price_cents', 'value_cents'], escape: '');

            foreach ((new StockByWarehouseGenreReport)->lines() as $line) {
                fputcsv($out, [
                    $line->warehouse,
                    $line->shelf_code,
                    $line->isbn,
                    self::csvSafe($line->title),
                    $line->quantity,
                    $line->reserved,
                    $line->price,
                    $line->quantity * $line->price,
                ], escape: '');
            }

            fclose($out);
        }, 'stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Spreadsheet apps execute cells starting with = + - @ as formulas (CSV injection).
     */
    private static function csvSafe(string $value): string
    {
        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
