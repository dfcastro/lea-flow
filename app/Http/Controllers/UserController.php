<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Mostra a lista de utilizadores e o formulário
    public function index()
    {
        $users = User::all(); // Vai buscar todos os membros da equipa
        return view('users.index', compact('users'));
    }

    // Salva um novo membro na base de dados
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,advogado,secretaria',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // Encripta a senha
            'role' => $request->role,
        ]);

        return redirect()->route('users.index')->with('success', 'Membro da equipa adicionado com sucesso!');
    }

}
