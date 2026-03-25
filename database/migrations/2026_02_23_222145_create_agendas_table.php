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
        Schema::create('agendas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('tipo')->default('Audiência'); // Tipos: Audiência, Prazo, Reunião, Interno
            $table->dateTime('data_hora_inicio');
            $table->dateTime('data_hora_fim')->nullable();

            // Quem é o responsável?
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Está linkado a um processo? (Opcional)
            $table->foreignId('processo_id')->nullable()->constrained('processos')->onDelete('set null');

            $table->string('status')->default('Pendente'); // Pendente, Concluído, Cancelado
            $table->text('observacoes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agendas');
    }
};
