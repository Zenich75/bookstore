<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Lab\RelationExamples;
use Illuminate\View\View;

class LabRelationsController extends Controller
{
    public function __invoke(RelationExamples $examples): View
    {
        return view('lab.relations', [
            'sections' => collect($examples->run())->groupBy('section'),
        ]);
    }
}
