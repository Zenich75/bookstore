<?php

// Texts of e-mail notifications (resources/views/mail/*). Terms follow docs/glossary.md.

return [
    'signature' => 'Regards,',
    'low_stock' => [
        'subject' => 'Low stock at ":warehouse" (:count)',
        'heading' => 'Warehouse ":warehouse": low stock',
        'intro' => ':count title has fewer than :threshold available copies.|:count titles have fewer than :threshold available copies.',
        'more' => '…and :count more — see the stock page for the full list.',
        'button' => 'Open warehouse stock',
        'columns' => [
            'book' => 'Book',
            'isbn' => 'ISBN',
            'shelf' => 'Shelf',
            'available' => 'Available',
        ],
    ],
    'pending_review' => [
        'subject' => 'New review awaiting moderation: ":title"',
        'heading' => 'A new review awaits moderation',
        'intro' => ':reviewer rates ":title" :rating out of 5:',
        'button' => 'Go to moderation',
    ],
    'import_finished' => [
        'subject' => 'Import ":file" finished',
        'heading' => 'Import finished',
        'intro' => 'File ":file" has been processed for warehouse ":warehouse".',
        'status' => 'Status',
        'total' => 'Rows in file',
        'imported' => 'Imported',
        'failed' => 'Rejected',
        'button' => 'View import',
        'errors' => 'Rejected rows and reasons:',
        'errors_link' => 'download the error CSV',
    ],
    'import_failed' => [
        'subject' => 'Import ":file" failed',
        'heading' => 'Import failed',
        'intro' => 'Processing of ":file" was stopped after all retries.',
        'button' => 'View import',
    ],
];
