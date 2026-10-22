<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Lab\XssLab;
use Illuminate\View\View;

class LabXssController extends Controller
{
    public function __invoke(XssLab $lab): View
    {
        return view('lab.xss', [
            'approaches' => $lab->approaches(),
            'results' => $lab->run(),
        ]);
    }
}
