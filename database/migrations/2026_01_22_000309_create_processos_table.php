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
        Schema::create('processos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_processo')->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');

            // MUDANÇA 1: Proteção do Advogado
            // Tornamos nullable e mudamos para 'set null'. 
            // Se o advogado for apagado, o processo continua a existir, mas sem dono.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->string('titulo');
            $table->string('tribunal')->nullable();
            $table->string('vara')->nullable();
            $table->string('status')->default('Petição Inicial');
            $table->decimal('valor_causa', 15, 2)->nullable();
            $table->text('observacoes')->nullable();

            $table->timestamps();

            // MUDANÇA 2: Adicionar Soft Deletes
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processos');
    }
};
