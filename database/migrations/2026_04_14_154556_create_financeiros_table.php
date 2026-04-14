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
        Schema::create('financeiros', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('tipo'); // Honorários Iniciais, Honorários de Êxito, Custas, Despesas
            $table->decimal('valor', 10, 2); // Prepara a base para aceitar valores com centavos
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable(); // Fica nulo se ainda não foi pago
            $table->string('status')->default('Pendente'); // Pendente, Pago, Atrasado
            $table->text('observacoes')->nullable();

            // Chaves Estrangeiras (Vínculos)
            // Se o cliente ou processo for apagado, o lançamento financeiro não é apagado, apenas perde o vínculo (nullOnDelete)
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('processo_id')->nullable()->constrained('processos')->nullOnDelete();

            // Quem registou o lançamento
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financeiros');
    }
};
