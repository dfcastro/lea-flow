<?php
use App\Models\Cliente;
use Illuminate\Support\Facades\Http;
use function Livewire\Volt\{state, with, usesPagination};

usesPagination();

state([
    'nome' => '',
    'cpf_cnpj' => '',
    'telefone' => '',
    'email' => '',
    'cep' => '',
    'logradouro' => '',
    'numero' => '',
    'bairro' => '',
    'cidade' => '',
    'estado' => '',
    'search' => '',
    'formId' => 1,
    'editingId' => null,
    'isEditing' => false
]);

// BUSCA DE CEP
$buscarCep = function () {
    $cepLimpo = preg_replace('/[^0-9]/', '', $this->cep);
    if (strlen($cepLimpo) === 8) {
        $response = Http::get("https://viacep.com.br/ws/{$cepLimpo}/json/");
        if ($response->successful() && !isset($response['erro'])) {
            $this->logradouro = $response['logradouro'];
            $this->bairro = $response['bairro'];
            $this->cidade = $response['localidade'];
            $this->estado = $response['uf'];
            $this->formId++; 
        }
    }
};

// FUNÇÃO CANCELAR
$cancelar = function () {
    $this->reset([
        'nome', 'cpf_cnpj', 'telefone', 'email', 'cep', 
        'logradouro', 'numero', 'bairro', 'cidade', 'estado', 
        'editingId', 'isEditing'
    ]);
    $this->formId++; 
};

$editar = function ($id) {
    $c = Cliente::find($id);
    if ($c) {
        $this->isEditing = true;
        $this->editingId = $c->id;
        $this->nome = $c->nome;
        $this->cpf_cnpj = $c->cpf_cnpj;
        $this->telefone = $c->telefone;
        $this->email = $c->email;
        $this->cep = $c->cep;
        $this->logradouro = $c->logradouro;
        $this->numero = $c->numero;
        $this->bairro = $c->bairro;
        $this->cidade = $c->cidade;
        $this->estado = $c->estado;
        $this->formId++;
    }
};

$salvar = function () {
    $this->validate([
        'nome' => 'required|min:3',
        'cpf_cnpj' => 'required|unique:clientes,cpf_cnpj,' . ($this->editingId ?? 'NULL'),
        'telefone' => 'required',
        // Adicionei validação de email opcional mas formato correto
        'email' => 'nullable|email', 
    ]);

    $dados = [
        'nome' => $this->nome,
        'cpf_cnpj' => $this->cpf_cnpj,
        'telefone' => $this->telefone,
        'email' => $this->email,
        'cep' => $this->cep,
        'logradouro' => $this->logradouro,
        'numero' => $this->numero,
        'bairro' => $this->bairro,
        'cidade' => $this->cidade,
        'estado' => $this->estado
    ];

    if ($this->isEditing) {
        Cliente::find($this->editingId)->update($dados);
        session()->flash('message', 'CLIENTE ATUALIZADO!');
    } else {
        Cliente::create($dados);
        session()->flash('message', 'CLIENTE CADASTRADO!');
    }

    $this->cancelar(); 
};

$excluir = function ($id) {
    Cliente::find($id)?->delete();
};

with(fn() => [
    'clientes' => Cliente::where('nome', 'like', "%{$this->search}%")
        ->orWhere('cpf_cnpj', 'like', "%{$this->search}%")
        ->latest()->paginate(10)
]);
?>

<div class="space-y-8 animate-fadeIn text-left">
    <div class="bg-white rounded-2xl shadow-sm border {{ $isEditing ? 'border-indigo-400 ring-2 ring-indigo-50' : 'border-gray-100' }} transition-all duration-500">
        <div class="p-8">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-xl bg-gray-900 text-white shadow-lg shadow-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 005.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 tracking-tighter uppercase">{{ $isEditing ? 'Editar Ficha' : 'Ficha de Cliente' }}</h2>
                        <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest text-left">Controle de Clientes • L&A Flow</p>
                    </div>
                </div>
                @if($isEditing)
                    <button wire:click="cancelar" class="text-xs font-black text-red-500 uppercase tracking-widest hover:underline">CANCELAR</button>
                @endif
            </div>

            <form wire:submit.prevent="salvar" class="grid grid-cols-1 md:grid-cols-12 gap-x-6 gap-y-5" wire:key="form-cli-{{ $formId }}">
                <div class="md:col-span-8">
                    <x-input-label value="NOME COMPLETO / RAZÃO SOCIAL" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="nome" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold uppercase" />
                    <x-input-error :messages="$errors->get('nome')" class="mt-1" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="CPF / CNPJ" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="cpf_cnpj" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold" x-mask:dynamic="$input.length > 14 ? '99.999.999/9999-99' : '999.999.999-99'" />
                    <x-input-error :messages="$errors->get('cpf_cnpj')" class="mt-1" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="E-MAIL DE CONTATO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="email" type="email" class="w-full mt-1 bg-gray-50 border-none shadow-inner font-medium" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="TELEFONE" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="telefone" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner" x-mask="(99) 99999-9999" />
                    <x-input-error :messages="$errors->get('telefone')" class="mt-1" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="CEP" class="text-[10px] font-bold text-indigo-500 uppercase" />
                    <x-text-input wire:model.blur="cep" wire:change="buscarCep" type="text" class="w-full mt-1 bg-indigo-50 border-none shadow-inner font-bold text-indigo-700" x-mask="99999-999" />
                    <x-input-error :messages="$errors->get('cep')" class="mt-1" />
                </div>

                <div class="md:col-span-6">
                    <x-input-label value="LOGRADOURO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="logradouro" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner uppercase" />
                    <x-input-error :messages="$errors->get('logradouro')" class="mt-1" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label value="Nº" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="numero" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner" />
                    <x-input-error :messages="$errors->get('numero')" class="mt-1" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="BAIRRO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="bairro" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner uppercase" />
                    <x-input-error :messages="$errors->get('bairro')" class="mt-1" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="CIDADE" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="cidade" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner uppercase" />
                    <x-input-error :messages="$errors->get('cidade')" class="mt-1" />
                </div>

                <div class="md:col-span-2 text-left">
                    <x-input-label value="UF" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="estado" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner uppercase" x-mask="aa" />
                    <x-input-error :messages="$errors->get('estado')" class="mt-1" />
                </div>

                <div class="md:col-span-2 flex items-end">
                    <button type="submit" class="w-full py-3 bg-gray-900 text-white rounded-xl font-black shadow-xl hover:bg-indigo-600 transition-all uppercase text-[10px] tracking-widest">
                        {{ $isEditing ? 'Atualizar' : 'Salvar' }}
                    </button>
                </div>
            </form>

            @if (session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mt-6 flex items-center p-4 border-l-4 border-emerald-500 bg-emerald-50 rounded-xl shadow-sm">
                    <div class="ml-3 font-black text-emerald-800 uppercase tracking-widest text-[10px]">{{ session('message') }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-left">
        <div class="px-8 py-5 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="BUSCAR CLIENTE..." class="border-none bg-transparent focus:ring-0 text-xs font-black w-full md:w-1/3 uppercase tracking-widest" />
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest uppercase tracking-widest">Total: {{ $clientes->total() }}</span>
        </div>
        <table class="min-w-full divide-y divide-gray-100">
            <tbody class="divide-y divide-gray-50">
                @foreach($clientes as $cliente)
                    <tr class="group hover:bg-gray-50/50 transition-all" wire:key="cli-{{ $cliente->id }}">
                        <td class="px-8 py-4 text-left">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-gray-900 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-gray-200 uppercase">
                                    {{ substr($cliente->nome, 0, 1) }}
                                </div>
                                <div class="text-left">
                                    <div class="font-black text-gray-900 tracking-tighter ">{{ $cliente->nome }}</div>
                                    <div class="text-[10px] text-gray-400 font-bold tracking-widest uppercase">{{ $cliente->cpf_cnpj }} • {{ $cliente->cidade }}/{{ $cliente->estado }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-4 text-right">
                            <div class="flex justify-end items-center gap-6">
                                <button wire:click="editar({{ $cliente->id }})" class="text-indigo-600 font-black text-[10px] uppercase tracking-widest hover:underline">Editar</button>
                                <button onclick="confirm('Excluir cliente?') || event.stopImmediatePropagation()" wire:click="excluir({{ $cliente->id }})" class="text-gray-300 hover:text-red-500 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 bg-gray-50/50">{{ $clientes->links() }}</div>
    </div>
</div>