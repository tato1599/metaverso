<?php
namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Lti\DeepLinkRespondedor;
use App\Models\Practica;
use Illuminate\Http\Request;

class LtiDeepLinkController extends Controller {
    public function seleccionar() {
        $practicas = Practica::orderBy('titulo')->get();
        return view('lti.seleccionar-practica', ['practicas' => $practicas]);
    }

    public function responder(Request $request, DeepLinkRespondedor $respondedor) {
        $data = $request->validate(['id_practica' => 'required|integer']);
        $res = $respondedor->construir((int) $data['id_practica']);
        return view('lti.auto-post', ['jwt' => $res['jwt'], 'returnUrl' => $res['returnUrl']]);
    }
}
