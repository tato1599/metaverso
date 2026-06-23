<?php
namespace App\Lti;

use Illuminate\Http\Request;

interface LaunchValidador {
    public function validar(Request $request): DatosLaunch;
}
