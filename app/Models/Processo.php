<?php

namespace App\Models;

use App\Enums\ProcessoStatus;
use App\Models\Cliente; // Importação explicita é boa prática
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute; // Para os novos mutators
use Illuminate\Database\Eloquent\SoftDeletes; // <--- Importar

class Processo extends Model
{
    use SoftDeletes;
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
        'valor_causa' => 'decimal:2',
        'status' => ProcessoStatus::class, // O segredo está aqui! O Laravel converte sozinho.
    ];

    // --- RELACIONAMENTOS ---

    public function historico()
    {
        return $this->hasMany(ProcessoHistorico::class)->latest();
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function advogado()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // --- MUTATORS (Sintaxe Moderna Laravel 9+) ---

    // Converte automaticamente para Title Case ao salvar
    protected function titulo(): Attribute
    {
        return Attribute::make(
            set: fn($value) => mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
        );
    }

    protected function tribunal(): Attribute
    {
        return Attribute::make(
            set: fn($value) => mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
        );
    }

    protected function vara(): Attribute
    {
        return Attribute::make(
            set: fn($value) => mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
        );
    }

    // --- ACESSORS PERSONALIZADOS ---

    // Recupera a cor diretamente do Enum
    public function getCorAttribute()
    {
        return $this->status?->color() ?? 'bg-gray-50 text-gray-400 border-gray-100';
    }
}