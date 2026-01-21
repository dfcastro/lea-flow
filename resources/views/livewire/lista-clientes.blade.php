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

// BUSCA DE CEP (Aciona ao sair do campo ou mudar)
$buscarCep = function () {
    $cepLimpo = preg_replace('/[^0-9]/', '', $this->cep);
    if (strlen($cepLimpo) === 8) {
        $response = Http::get("https://viacep.com.br/ws/{$cepLimpo}/json/");
        if ($response->successful() && !isset($response['erro'])) {
            $this->logradouro = $response['logradouro'];
            $this->bairro = $response['bairro'];
            $this->cidade = $response['localidade'];
            $this->estado = $response['uf'];
            $this->formId++; // Força o refresh visual dos campos
        }
    }
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

    $this->reset();
    $this->formId++;
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

    <div
        class="bg-white rounded-2xl shadow-sm border {{ $isEditing ? 'border-indigo-400 ring-2 ring-indigo-50' : 'border-gray-100' }} transition-all duration-500">
        <div class="p-8">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-xl bg-gray-900 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 005.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight">
                            {{ $isEditing ? 'Editar Ficha' : 'Ficha de Cliente' }}</h2>
                        <p class="text-sm text-gray-500 font-medium">Controle de clientes L&A Flow</p>
                    </div>
                </div>
                @if($isEditing)
                    <button wire:click="reset()"
                        class="text-xs font-bold text-red-500 uppercase tracking-widest hover:underline">Cancelar</button>
                @endif
            </div>

            <form wire:submit.prevent="salvar" class="grid grid-cols-1 md:grid-cols-12 gap-x-6 gap-y-5"
                wire:key="form-cli-{{ $formId }}">

                <div class="md:col-span-8">
                    <x-input-label value="Nome Completo / Razão Social" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="nome" wire:key="n-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold" />
                    <x-input-error :messages="$errors->get('nome')" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="CPF / CNPJ" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="cpf_cnpj" wire:key="c-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold"
                        x-mask:dynamic="$input.length > 14 ? '99.999.999/9999-99' : '999.999.999-99'" />
                    <x-input-error :messages="$errors->get('cpf_cnpj')" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="E-mail" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="email" wire:key="e-{{ $formId }}" type="email"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="Telefone" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="telefone" wire:key="t-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner" x-mask="(99) 99999-9999" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="CEP" class="font-semibold text-indigo-600" />
                    <x-text-input wire:model.blur="cep" wire:change="buscarCep" wire:key="z-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-indigo-50 border-none shadow-inner font-bold" x-mask="99999-999" />
                </div>

                <div class="md:col-span-6">
                    <x-input-label value="Logradouro" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="logradouro" wire:key="l-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner uppercase" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label value="Nº" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="numero" wire:key="num-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="Bairro" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="bairro" wire:key="b-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner uppercase" />
                </div>

                <div class="md:col-span-6">
                    <x-input-label value="Cidade" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="cidade" wire:key="cid-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner uppercase" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label value="UF" class="font-semibold text-gray-700" />
                    <x-text-input wire:model="estado" wire:key="uf-{{ $formId }}" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner uppercase" x-mask="aa" />
                </div>

                <div class="md:col-span-4 flex items-end">
                    <button type="submit" wire:loading.attr="disabled"
                        class="w-full py-3 rounded-xl text-white font-bold shadow-lg transition-all transform hover:-translate-y-1 active:scale-95 bg-gray-900 shadow-gray-200 uppercase tracking-widest text-xs">
                        <span wire:loading.remove>{{ $isEditing ? 'Atualizar' : 'Cadastrar' }}</span>
                        <span wire:loading>...</span>
                    </button>
                </div>
            </form>

           @if (session()->has('message'))
    <div x-data="{ show: true }" 
         x-show="show" 
         x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-90"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-90"
         class="mt-6 flex items-center p-4 border-l-4 border-emerald-500 bg-emerald-50 rounded-xl shadow-sm">
        
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
        </div>
        
        <div class="ml-3">
            <p class="text-xs font-black text-emerald-800 uppercase tracking-widest">
                {{ session('message') }}
            </p>
        </div>

        <div class="ml-auto pl-3">
            <div class="-mx-1.5 -my-1.5">
                <button @click="show = false" type="button" class="inline-flex rounded-md p-1.5 text-emerald-500 hover:bg-emerald-100 focus:outline-none transition">
                    <span class="sr-only">Fechar</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-8 py-5 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="BUSCAR CLIENTE..."
                class="border-none bg-transparent focus:ring-0 text-xs font-bold w-full md:w-1/3 uppercase" />
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total:
                {{ $clientes->total() }}</span>
        </div>
        <table class="min-w-full divide-y divide-gray-100">
            <tbody class="divide-y divide-gray-50">
                @foreach($clientes as $cliente)
                    <tr class="group hover:bg-gray-50/50 transition-all" wire:key="cli-{{ $cliente->id }}">
                        <td class="px-8 py-4">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 rounded-full bg-gray-900 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-gray-200">
                                    {{ substr($cliente->nome, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-black text-gray-900  tracking-tighter">{{ $cliente->nome }}
                                    </div>
                                    <div class="text-[10px] text-gray-400 font-bold tracking-widest uppercase">
                                        {{ $cliente->cpf_cnpj }} • {{ $cliente->cidade }}/{{ $cliente->estado }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-4 text-right">
                            <div class="flex justify-end items-center gap-4">
                                <button wire:click="editar({{ $cliente->id }})"
                                    class="text-indigo-600 hover:text-indigo-900 font-black text-[10px] uppercase">Editar</button>
                                <button onclick="confirm('Excluir cliente?') || event.stopImmediatePropagation()"
                                    wire:click="excluir({{ $cliente->id }})"
                                    class="text-gray-300 hover:text-red-500 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 bg-gray-50/50">
            {{ $clientes->links() }}
        </div>
    </div>
</div>