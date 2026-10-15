<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Lab\CollectionExamples;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabCollectionsController extends Controller
{
    /**
     * Memory available to this route only: the PHP-side variants hydrate whole tables.
     */
    private const MEMORY_LIMIT = '1024M';

    public function __invoke(Request $request, CollectionExamples $examples): View
    {
        $this->raiseMemoryLimit();

        // Debugbar would also collect every query of every approach (time + memory).
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $section = $request->string('section', array_key_first(CollectionExamples::SECTIONS))->toString();
        abort_unless(array_key_exists($section, CollectionExamples::SECTIONS), 404);

        return view('lab.collections', [
            'sections' => CollectionExamples::SECTIONS,
            'section' => $section,
            'notes' => $examples->notes($section),
            'results' => array_map(fn ($comparison) => [
                'comparison' => $comparison,
                'runs' => $examples->run($comparison),
            ], $examples->comparisons($section)),
        ]);
    }

    /**
     * Raise (never lower) a finite memory_limit for this request.
     */
    private function raiseMemoryLimit(): void
    {
        $current = ini_get('memory_limit');

        if ($current !== '-1' && ini_parse_quantity($current) < ini_parse_quantity(self::MEMORY_LIMIT)) {
            ini_set('memory_limit', self::MEMORY_LIMIT);
        }
    }
}
