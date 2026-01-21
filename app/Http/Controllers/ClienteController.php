<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

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
            ->paginate(10) // Divide em páginas de 10
            ->withQueryString(); // Mantém o filtro na URL ao mudar de página

        return view('clientes.index', compact('clientes'));
    }
    // Salvar novo cliente
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|max:255',
            'cpf_cnpj' => 'required|unique:clientes',
            'telefone' => 'required',
            'email' => 'nullable|email',
            'endereco' => 'nullable',
        ]);

        Cliente::create($validated);

        return redirect()->route('clientes.index')->with('success', 'Cliente cadastrado!');
    }
}

