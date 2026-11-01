<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traducoes_de_conteudo', function (Blueprint $table) {
            $table->id();
            $table->char('hash', 64);
            $table->string('origem', 10);
            $table->string('destino', 10);
            $table->string('motor', 20);
            $table->text('texto');
            $table->timestamp('criado_em');
            $table->unique(['hash', 'origem', 'destino', 'motor']);
        });

        Schema::create('uso_de_traducao', function (Blueprint $table) {
            $table->string('mes', 7)->primary();
            $table->unsignedBigInteger('caracteres')->default(0);
        });

        Schema::create('alvos_de_traducao', function (Blueprint $table) {
            $table->id();
            $table->string('idioma', 10);
            $table->string('tema', 30);
            $table->boolean('pausado')->default(false);
            $table->unique(['idioma', 'tema']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alvos_de_traducao');
        Schema::dropIfExists('uso_de_traducao');
        Schema::dropIfExists('traducoes_de_conteudo');
    }
};
