<?php
namespace App\Lti;

interface DeepLinkRespondedor {
    // Retorna [ 'jwt' => string, 'returnUrl' => string ] para auto-postear a Moodle.
    public function construir(int $idPractica): array;
}
