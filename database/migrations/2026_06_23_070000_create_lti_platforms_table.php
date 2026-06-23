<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lti_platforms', function (Blueprint $t) {
            $t->id();
            $t->string('issuer');
            $t->string('client_id');
            $t->string('auth_login_url');
            $t->string('auth_token_url');
            $t->string('jwks_url');
            $t->string('deployment_id')->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
            $t->unique(['issuer', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lti_platforms');
    }
};
