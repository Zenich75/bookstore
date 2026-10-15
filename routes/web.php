<?php

use App\Http\Controllers\Lab\LabRelationsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/lab/relations', LabRelationsController::class)->name('lab.relations');
