<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tokens_juego', function (Blueprint $t) {
            $t->id('id_token');
            $t->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $t->foreignId('id_evento')->constrained('eventos_agenda', 'id_evento');
            $t->string('token_hash')->unique();
            $t->string('plataforma')->default('unreal'); // unreal, web, etc.
            $t->dateTime('fecha_expiracion');
            $t->boolean('usado')->default(false);
            $t->dateTime('fecha_uso')->nullable();
            $t->string('ip_origen')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tokens_juego');
    }
};
