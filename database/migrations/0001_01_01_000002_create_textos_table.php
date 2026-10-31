<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textos', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedTinyInteger('nivel');
            $table->string('tipo', 10);
            $table->json('traducoes');
            $table->timestamps();

            $table->index(['nivel', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textos');
    }
};
