<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = [
        'nome',
        'cpf_cnpj',
        'email',
        'telefone',
        'cep',
        'logradouro',
        'numero',
        'bairro',
        'cidade',
        'estado'
    ];

    // Força MAIÚSCULAS em todos os campos de texto ao salvar
    public function setNomeAttribute($value)
    {
        // mb_convert_case com MB_CASE_TITLE coloca apenas as primeiras letras em maiúsculo
        $this->attributes['nome'] = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
    public function setLogradouroAttribute($value)
    {
        $this->attributes['logradouro'] = mb_strtoupper($value, 'UTF-8');
    }
    public function setBairroAttribute($value)
    {
        $this->attributes['bairro'] = mb_strtoupper($value, 'UTF-8');
    }
    public function setCidadeAttribute($value)
    {
        $this->attributes['cidade'] = mb_strtoupper($value, 'UTF-8');
    }
}