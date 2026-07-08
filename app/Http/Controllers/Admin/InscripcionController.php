<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use Illuminate\Http\Request;

class InscripcionController extends Controller
{
    public function store(Request $request, Grupo $grupo)
    {
        abort(501);
    }

    public function destroy(Request $request, Grupo $grupo, int $inscripcion)
    {
        abort(501);
    }
}
