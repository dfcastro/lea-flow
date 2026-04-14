<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Financeiro extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo', 'tipo', 'valor', 'data_vencimento', 'data_pagamento', 
        'status', 'observacoes', 'cliente_id', 'processo_id', 'user_id'
    ];

    // Garante que o Laravel entenda esses campos como datas reais
    protected $casts = [
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
    ];

    // Relacionamentos
    public function cliente() {
        return $this->belongsTo(Cliente::class);
    }

    public function processo() {
        return $this->belongsTo(Processo::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}