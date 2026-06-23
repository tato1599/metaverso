<?php
namespace App\Lti;

use App\Models\SesionPractica;

interface AgsCliente {
    public function enviar(SesionPractica $sesion): void;
}
