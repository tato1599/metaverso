<?php
namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class GenerarAccesoPanel extends Command {
    protected $signature = 'metaverso:panel-acceso {id_usuario}';
    protected $description = 'Genera un enlace de acceso firmado al panel para un usuario (Maestro/Coordinador/Admin)';

    public function handle(): int {
        $usuario = Usuario::find((int) $this->argument('id_usuario'));
        if (! $usuario) { $this->error('Usuario no encontrado'); return self::FAILURE; }
        if (! $usuario->esStaffPanel()) { $this->error('El usuario no tiene rol con acceso al panel'); return self::FAILURE; }

        $ttl = (int) config('metaverso.panel_login_ttl_minutes', 30);
        $url = URL::temporarySignedRoute('panel.acceso', now()->addMinutes($ttl), ['usuario' => $usuario->id_usuario]);
        $this->info('Enlace de acceso (válido '.$ttl.' min):');
        $this->line($url);
        return self::SUCCESS;
    }
}
