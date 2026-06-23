<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lti_keys', function (Blueprint $t) {
            $t->id();
            $t->string('kid')->unique();
            $t->text('public_key');
            $t->text('private_key');
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lti_keys');
    }
};
