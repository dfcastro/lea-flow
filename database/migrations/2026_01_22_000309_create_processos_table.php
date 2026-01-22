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
            $table->string('numero_processo')->unique(); // Padrão CNJ: 0000000-00.0000.0.00.0000
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Advogado responsável
            $table->string('titulo'); // Ex: Ação De Indenização
            $table->string('tribunal')->nullable(); // Ex: TJMG, TRT3
            $table->string('vara')->nullable(); // Ex: 2ª Vara Cível
            $table->string('status')->default('Petição Inicial');
            $table->decimal('valor_causa', 15, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
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
