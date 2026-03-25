<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'tipo',
        'subtipo',         
        'link_reuniao',     
        'data_hora_inicio',
        'data_hora_fim',
        'user_id',
        'processo_id',
        'observacoes',
        'status',
    ];

    protected $casts = [
        'data_hora_inicio' => 'datetime',
        'data_hora_fim' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processo()
    {
        return $this->belongsTo(Processo::class);
    }
}