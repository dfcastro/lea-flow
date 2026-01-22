<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Processo extends Model
{
    protected $fillable = [
        'numero_processo',
        'cliente_id',
        'user_id',
        'titulo',
        'tribunal',
        'vara',
        'status',
        'data_prazo',
        'valor_causa',
        'observacoes'
    ];

    protected $casts = [
        'data_prazo' => 'date',
        'valor_causa' => 'decimal:2'
    ];

    // PADRÃO DE NOME: Apenas a primeira letra maiúscula (Title Case)
    public function setTituloAttribute($value)
    {
        $this->attributes['titulo'] = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
    public function setTribunalAttribute($value)
    {
        $this->attributes['tribunal'] = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
    public function setVaraAttribute($value)
    {
        $this->attributes['vara'] = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    // RELACIONAMENTOS
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
    public function advogado()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // LÓGICA DE CORES COMPLETA (Solicitada pela advogada)
    public function getCorAttribute()
    {
        return match ($this->status) {
            'Distribuído', 'Petição Inicial', 'Aguardando Citação' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'Em Andamento', 'Contestação/Réplica', 'Concluso para Decisão', 'Instrução' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'Audiência Designada', 'Aguardando Audiência' => 'bg-amber-50 text-amber-700 border-amber-200',
            'Perícia Designada', 'Apresentação de Laudo' => 'bg-orange-50 text-orange-700 border-orange-200',
            'Prazo em Aberto', 'Urgência / Liminar', 'Aguardando Protocolo' => 'bg-rose-50 text-rose-700 border-rose-200',
            'Sentenciado', 'Em Grau de Recurso', 'Cumprimento de Sentença', 'Acordo/Pagamento' => 'bg-purple-50 text-purple-700 border-purple-200',
            'Trânsito em Julgado', 'Suspenso / Sobrestado', 'Arquivado' => 'bg-gray-100 text-gray-500 border-gray-200',
            default => 'bg-gray-50 text-gray-400 border-gray-100',
        };
    }
}