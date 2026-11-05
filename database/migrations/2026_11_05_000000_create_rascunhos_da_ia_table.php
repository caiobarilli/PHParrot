<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rascunhos_da_ia', function (Blueprint $table) {
            $table->id();
            $table->string('tema', 30);
            $table->unsignedTinyInteger('nivel');
            $table->string('tipo', 10);
            $table->string('status', 12)->index();
            $table->text('en')->nullable();
            $table->text('pt_br')->nullable();
            $table->string('motivo', 1000)->nullable();
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->string('texto_id', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rascunhos_da_ia');
    }
};
