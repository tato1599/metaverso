<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lti_contextos', function (Blueprint $t) {
            $t->id();
            // NOT NULL: un NULL en el unique de Postgres no deduplica y el
            // upsert atómico por (lti_platform_id, context_id) dejaría filas repetidas.
            $t->foreignId('lti_platform_id')->constrained('lti_platforms');
            $t->string('context_id');
            $t->string('titulo')->nullable();
            $t->string('nrps_url')->nullable();
            $t->timestamps();
            $t->unique(['lti_platform_id', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lti_contextos');
    }
};
