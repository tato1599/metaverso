<?php
namespace App\Console\Commands;

use App\Models\LtiPlatform;
use Illuminate\Console\Command;

class LtiRegistrarPlataforma extends Command {
    protected $signature = 'metaverso:lti-registrar-plataforma
        {--issuer=} {--client-id=} {--deployment-id=}
        {--auth-login-url=} {--auth-token-url=} {--jwks-url=}';
    protected $description = 'Registra o actualiza una plataforma LTI (Moodle)';

    public function handle(): int {
        foreach (['issuer','client-id','auth-login-url','auth-token-url','jwks-url'] as $req) {
            if (! $this->option($req)) { $this->error("Falta --{$req}"); return self::FAILURE; }
        }
        $p = LtiPlatform::updateOrCreate(
            ['issuer' => $this->option('issuer'), 'client_id' => $this->option('client-id')],
            [
                'deployment_id' => $this->option('deployment-id'),
                'auth_login_url' => $this->option('auth-login-url'),
                'auth_token_url' => $this->option('auth-token-url'),
                'jwks_url' => $this->option('jwks-url'),
                'activo' => true,
            ]
        );
        $this->info('Plataforma registrada: '.$p->issuer.' ('.$p->client_id.')');
        return self::SUCCESS;
    }
}
