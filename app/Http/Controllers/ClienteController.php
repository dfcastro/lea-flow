<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use App\Http\Requests\StoreClienteRequest; // Importamos o nosso validador

class ClienteController extends Controller
{
    // Listar clientes
    public function index(Request $request)
    {
        // Captura o que foi digitado na busca
        $search = $request->input('search');

        // Consulta com filtro e paginação
        $clientes = Cliente::when($search, function ($query, $search) {
            return $query->where('nome', 'like', "%{$search}%")
                ->orWhere('cpf_cnpj', 'like', "%{$search}%");
        })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    // Salvar novo cliente
    public function store(StoreClienteRequest $request) // Agora usamos StoreClienteRequest em vez de Request
    {
        // O Laravel valida tudo AUTOMATICAMENTE antes de chegar aqui.
        // Se falhar, ele devolve o utilizador para trás com os erros.

        Cliente::create($request->validated());

        return redirect()->route('clientes.index')->with('success', 'Cliente cadastrado!');
    }
}