<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones_practica', function (Blueprint $t) {
            // Nullable: los caminos LTI-directo y magic-link del maestro no llevan reserva.
            $t->foreignId('id_reserva')->nullable()->constrained('reservas', 'id_reserva');
        });
    }

    public function down(): void
    {
        Schema::table('sesiones_practica', function (Blueprint $t) {
            $t->dropConstrainedForeignId('id_reserva');
        });
    }
};
