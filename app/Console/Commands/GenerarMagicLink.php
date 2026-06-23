<?php
namespace App\Console\Commands;

use App\Services\MagicLinkService;
use Illuminate\Console\Command;

class GenerarMagicLink extends Command {
    protected $signature = 'metaverso:magic-link {id_usuario} {id_evento} {--plataforma=unreal}';
    protected $description = 'Genera un magic link de prueba para un alumno y evento';

    public function handle(MagicLinkService $magicLink): int {
        $res = $magicLink->generar(
            (int) $this->argument('id_usuario'),
            (int) $this->argument('id_evento'),
            $this->option('plataforma'),
        );
        $this->info('URL:      '.$res['url']);
        $this->info('Deeplink: '.$res['deeplink']);
        $this->info('Expira:   '.$res['modelo']->fecha_expiracion->toDateTimeString());
        return self::SUCCESS;
    }
}
