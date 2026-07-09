<?php

namespace App\Lti;

use App\Models\Grupo;

interface RosterCliente
{
    /**
     * Miembros NRPS del contexto vinculado al grupo.
     *
     * @return list<array{user_id?: string, status?: string, roles?: list<string>, name?: string, given_name?: string, family_name?: string, email?: string}>
     */
    public function getMembers(Grupo $grupo): array;
}
