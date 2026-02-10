<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Importante: permite que a ação seja executada
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nome' => 'required|max:255',
            'cpf_cnpj' => 'required|unique:clientes,cpf_cnpj', // Boa prática: especificar a coluna
            'telefone' => 'required',
            'email' => 'nullable|email',
            'endereco' => 'nullable',
        ];
    }

    /**
     * (Opcional) Mensagens personalizadas
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do cliente é obrigatório.',
            'cpf_cnpj.unique' => 'Este CPF/CNPJ já está cadastrado.',
        ];
    }
}