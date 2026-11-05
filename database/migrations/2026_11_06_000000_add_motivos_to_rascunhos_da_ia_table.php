<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rascunhos_da_ia', function (Blueprint $table) {
            $table->json('motivos')->nullable()->after('motivo');
        });
    }

    public function down(): void
    {
        Schema::table('rascunhos_da_ia', function (Blueprint $table) {
            $table->dropColumn('motivos');
        });
    }
};
