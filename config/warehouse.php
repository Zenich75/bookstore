<?php

return [

    /*
    | A stock row is "low" when quantity - reserved drops below this number
    | (catalog badge, CheckLowStock digest).
    */
    'low_stock_threshold' => (int) env('WAREHOUSE_LOW_STOCK_THRESHOLD', 5),

    /*
    | stock_movements older than this are deleted by the daily model:prune run.
    */
    'movements_retention_days' => (int) env('WAREHOUSE_MOVEMENTS_RETENTION_DAYS', 730),

    /*
    | CSV import: rows per ImportBooksChunk job, max upload size (KB), max rows per file.
    */
    'import' => [
        'chunk_size' => (int) env('WAREHOUSE_IMPORT_CHUNK_SIZE', 500),
        'max_kb' => (int) env('WAREHOUSE_IMPORT_MAX_KB', 10240),
        'max_rows' => (int) env('WAREHOUSE_IMPORT_MAX_ROWS', 50000),
    ],

];
