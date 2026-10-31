<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacotes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('nome', 80);
            $table->string('versao', 20);
            $table->json('idiomas');
            $table->longText('conteudo');
            $table->string('hash', 64);
            $table->boolean('ativo')->default(false);
            $table->timestamp('importado_em');
        });

        Schema::table('textos', function (Blueprint $table) {
            $table->string('tema', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('textos', function (Blueprint $table) {
            $table->dropColumn('tema');
        });

        Schema::dropIfExists('pacotes');
    }
};
