<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processo_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained(); // Quem fez a alteração
            $table->string('acao'); // Ex: "Mudou status"
            $table->string('descricao')->nullable(); // Ex: "De Inicial para Sentença"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processo_historicos');
    }
};
