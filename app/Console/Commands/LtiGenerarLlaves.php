<?php
namespace App\Console\Commands;

use App\Models\LtiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LtiGenerarLlaves extends Command {
    protected $signature = 'metaverso:lti-generar-llaves';
    protected $description = 'Genera un par de llaves RSA para firmar mensajes LTI de la herramienta';

    public function handle(): int {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $privatePem);
        $publicPem = openssl_pkey_get_details($res)['key'];

        $key = LtiKey::create([
            'kid' => (string) Str::uuid(),
            'private_key' => $privatePem,
            'public_key' => $publicPem,
            'activo' => true,
        ]);
        $this->info('Llave LTI generada. kid: '.$key->kid);
        return self::SUCCESS;
    }
}
