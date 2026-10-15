<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Lab\NPlusOne\NPlusOneLab;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabNPlusOneController extends Controller
{
    public function __invoke(Request $request, NPlusOneLab $lab): View
    {
        // Debugbar records every query of every variant — that would skew time and memory.
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        if ($request->filled('variant')) {
            $variant = $lab->variant($request->string('variant')->toString()) ?? abort(404);

            return view('lab.n-plus-one.show', [
                'variant' => $variant,
                'variants' => $lab->variants(),
                'profile' => $lab->profile($variant),
            ]);
        }

        $rows = collect($lab->variants())->map(fn ($variant) => [
            'variant' => $variant,
            'profile' => $lab->profile($variant),
        ])->values();

        // Ranking only among variants that rendered correctly (not the failure demos b, k3).
        $ranked = $rows
            ->filter(fn ($row) => $row['variant']->ranked && $row['profile']->error === null)
            ->sortBy([fn ($a, $b) => $a['profile']->queries <=> $b['profile']->queries, fn ($a, $b) => $a['profile']->wallMs <=> $b['profile']->wallMs]);

        return view('lab.n-plus-one.index', [
            'rows' => $rows,
            'best' => $ranked->first()['variant']->key ?? null,
            'worst' => $ranked->last()['variant']->key ?? null,
            'sampleSize' => count($lab->bookIds()),
        ]);
    }
}
