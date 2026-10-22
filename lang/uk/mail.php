<?php

// Texts of e-mail notifications (resources/views/mail/*). Terms follow docs/glossary.md.

return [
    'signature' => 'З повагою,',
    'low_stock' => [
        'subject' => 'Закінчуються книги на складі «:warehouse» (:count)',
        'heading' => 'Склад «:warehouse»: закінчуються книги',
        'intro' => ':count позиція має менше :threshold доступних примірників.|:count позиції мають менше :threshold доступних примірників.|:count позицій мають менше :threshold доступних примірників.',
        'more' => '…і ще :count — повний список на сторінці залишків.',
        'button' => 'Відкрити залишки складу',
        'columns' => [
            'book' => 'Книга',
            'isbn' => 'ISBN',
            'shelf' => 'Полиця',
            'available' => 'Доступно',
        ],
    ],
    'pending_review' => [
        'subject' => 'Новий відгук на модерації: «:title»',
        'heading' => 'Новий відгук чекає на модерацію',
        'intro' => ':reviewer оцінює книгу «:title» на :rating з 5:',
        'button' => 'До модерації',
    ],
    'import_finished' => [
        'subject' => 'Імпорт «:file» завершено',
        'heading' => 'Імпорт завершено',
        'intro' => 'Файл «:file» оброблено для складу «:warehouse».',
        'status' => 'Статус',
        'total' => 'Рядків у файлі',
        'imported' => 'Імпортовано',
        'failed' => 'Відхилено',
        'button' => 'Переглянути імпорт',
        'errors' => 'Відхилені рядки та причини:',
        'errors_link' => 'завантажити CSV з помилками',
    ],
    'import_failed' => [
        'subject' => 'Імпорт «:file» не вдався',
        'heading' => 'Імпорт не вдався',
        'intro' => 'Обробку файлу «:file» зупинено після всіх повторних спроб.',
        'button' => 'Переглянути імпорт',
    ],
];
