<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones_practica', function (Blueprint $t) {
            $t->foreignId('lti_platform_id')->nullable()->constrained('lti_platforms')->nullOnDelete();
            $t->string('ags_lineitem_url')->nullable();
            $t->string('ags_endpoint')->nullable();
        });
        DB::statement('ALTER TABLE sesiones_practica ALTER COLUMN id_evento DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('sesiones_practica', function (Blueprint $t) {
            $t->dropConstrainedForeignId('lti_platform_id');
            $t->dropColumn(['ags_lineitem_url', 'ags_endpoint']);
        });
        DB::statement('ALTER TABLE sesiones_practica ALTER COLUMN id_evento SET NOT NULL');
    }
};
