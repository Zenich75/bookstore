<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Lab\ModelExamples;
use Illuminate\View\View;

class LabModelsController extends Controller
{
    public function __invoke(ModelExamples $examples): View
    {
        return view('lab.models', [
            'sections' => collect($examples->run())->groupBy('section'),
        ]);
    }
}
