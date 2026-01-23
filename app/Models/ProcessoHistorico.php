<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessoHistorico extends Model
{
    use HasFactory;

    // LIBERA OS CAMPOS PARA GRAVAÇÃO
    protected $fillable = [
        'processo_id',
        'user_id',
        'acao',
        'descricao'
    ];

    // RELACIONAMENTO COM USUÁRIO (Para mostrar o nome na timeline)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // RELACIONAMENTO COM PROCESSO
    public function processo()
    {
        return $this->belongsTo(Processo::class);
    }
}