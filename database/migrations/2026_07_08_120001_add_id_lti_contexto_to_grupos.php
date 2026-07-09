<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $t) {
            $t->foreignId('id_lti_contexto')->nullable()->constrained('lti_contextos');
        });
    }

    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('id_lti_contexto');
        });
    }
};
