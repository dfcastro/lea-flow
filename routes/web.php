<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;
use Livewire\Volt\Volt;

// 1. Página Inicial (Pública)
Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-role', function () {
    if (auth()->check()) {
        return 'O teu cargo atual no banco de dados é: ' . auth()->user()->role;
    }
    return 'Tu não estás logado! Por favor, faz login primeiro.';
})->middleware('auth');

// 2. Dashboard (Logado)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// 3. Rotas Gerais do Sistema (Acessíveis a TODOS os utilizadores logados)
Route::middleware('auth')->group(function () {

    // Rotas de Clientes
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');

    // Rota de Processos
    Route::get('/processos', function () {
        return view('processos.index');
    })->name('processos.index');

    // --- ROTA DO MÓDULO FINANCEIRO (Restrita para Admin e Advogado) ---
    // Criamos um grupo que exige a permissão 'acesso-financeiro'
    Route::middleware('can:acesso-financeiro')->group(function () {
        Volt::route('/financeiro', 'gestao-financeira')->name('financeiro.index');
    });

    // Rotas de Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 4. ÁREA ADMINISTRATIVA L&A FLOW (Apenas Administradores)
Route::middleware(['auth', 'can:gerir-equipe'])->group(function () {
    Route::get('/equipe', function () {
        return view('equipe.index');
    })->name('users.index');
});

require __DIR__ . '/auth.php';