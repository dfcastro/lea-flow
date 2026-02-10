<?php

use App\Models\Processo;
use App\Models\ProcessoHistorico;
use App\Models\Cliente;
use App\Models\User;
use App\Enums\ProcessoStatus;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with, usesPagination};

usesPagination();

state([
    // FORMULÁRIO
    'numero_processo' => '',
    'cliente_id' => '',
    'cliente_nome_search' => '',
    'user_id' => '',
    'titulo' => '',
    'tribunal' => '',
    'tribunal_outro' => '',
    'vara' => '',
    'vara_outro' => '',
    'status' => 'Distribuído',
    'data_prazo' => '',
    'valor_causa' => '',
    'observacoes' => '',
    'search' => '',
    'formId' => 1,
    'isEditing' => false,
    'editingId' => null,

    // MODAL & FILTROS
    'drawerOpen' => false,
    'activeProcess' => null,
    'filtroAtivo' => 'todos'
]);

$tribunaisLista = ['TRF1', 'TRF6', 'JEF', 'TJMG', 'TRT3', 'STJ', 'STF'];
$varasLista = ['VARA PREVIDENCIÁRIA', 'JUIZADO ESPECIAL FEDERAL', 'VARA CÍVEL', 'VARA FEDERAL CÍVEL', 'VARA ÚNICA', 'VARA DO TRABALHO'];

// --- FUNÇÕES DO MODAL & HISTÓRICO ---
$openDrawer = function ($id) {
    $this->activeProcess = Processo::with(['cliente', 'advogado', 'historico.user'])->find($id);
    $this->drawerOpen = true;
};

$closeDrawer = function () {
    $this->drawerOpen = false;
    $this->activeProcess = null;
};

$updateStatus = function ($novoStatus) {
    if ($this->activeProcess) {
        $statusAtualEnum = $this->activeProcess->status;
        $statusAntigoString = $statusAtualEnum instanceof \App\Enums\ProcessoStatus ? $statusAtualEnum->value : $statusAtualEnum;

        if ($statusAntigoString !== $novoStatus) {
            $this->activeProcess->update(['status' => $novoStatus]);

            ProcessoHistorico::create([
                'processo_id' => $this->activeProcess->id,
                'user_id' => Auth::id(),
                'acao' => 'Alteração de Fase',
                'descricao' => "Alterou de '{$statusAntigoString}' para '{$novoStatus}'"
            ]);

            $this->activeProcess->refresh();
            session()->flash('message', 'STATUS ATUALIZADO!');
        }
    }
};

$mudarFiltro = function ($filtro) {
    $this->filtroAtivo = $filtro;
    $this->resetPage();
};

$selecionarCliente = function ($id, $nome) {
    $this->cliente_id = $id;
    $this->cliente_nome_search = $nome;
};

$cancelar = function () {
    $this->reset(['numero_processo', 'cliente_id', 'cliente_nome_search', 'user_id', 'titulo', 'tribunal', 'tribunal_outro', 'vara', 'vara_outro', 'status', 'data_prazo', 'valor_causa', 'observacoes', 'isEditing', 'editingId']);
    $this->status = 'Distribuído';
    $this->formId++;
    $this->drawerOpen = false;
};

$editar = function ($id) use ($tribunaisLista, $varasLista) {
    $p = Processo::find($id);
    if ($p) {
        $this->isEditing = true;
        $this->editingId = $p->id;
        $this->numero_processo = $p->numero_processo;
        $this->cliente_id = $p->cliente_id;
        $this->cliente_nome_search = $p->cliente?->nome;
        $this->user_id = $p->user_id;
        $this->titulo = $p->titulo;

        $valTribunal = strtoupper(trim($p->tribunal));
        if (in_array($valTribunal, $tribunaisLista))
            $this->tribunal = $valTribunal;
        else {
            $this->tribunal = 'OUTROS';
            $this->tribunal_outro = $p->tribunal;
        }

        $valVara = strtoupper(trim($p->vara));
        if (in_array($valVara, $varasLista))
            $this->vara = $valVara;
        else {
            $this->vara = 'OUTROS';
            $this->vara_outro = $p->vara;
        }

        $this->status = $p->status instanceof \App\Enums\ProcessoStatus ? $p->status->value : $p->status;
        
        $this->data_prazo = $p->data_prazo ? $p->data_prazo->format('Y-m-d') : '';
        $this->valor_causa = number_format($p->valor_causa, 2, ',', '.');
        $this->observacoes = $p->observacoes;
        $this->formId++;
        $this->drawerOpen = false;
    }
};

$salvar = function () {
    $valorLimpo = str_replace(['.', ','], ['', '.'], $this->valor_causa);
    $this->validate(['numero_processo' => 'required', 'cliente_id' => 'required', 'titulo' => 'required', 'tribunal' => 'required', 'vara' => 'required']);

    $dados = [
        'numero_processo' => $this->numero_processo,
        'cliente_id' => $this->cliente_id,
        'user_id' => $this->user_id,
        'titulo' => $this->titulo,
        'tribunal' => ($this->tribunal === 'OUTROS') ? strtoupper(trim($this->tribunal_outro)) : $this->tribunal,
        'vara' => ($this->vara === 'OUTROS') ? strtoupper(trim($this->vara_outro)) : $this->vara,
        'status' => $this->status,
        'data_prazo' => $this->data_prazo ?: null,
        'valor_causa' => (float) $valorLimpo ?: 0,
        'observacoes' => $this->observacoes,
    ];

    if ($this->isEditing) {
        $proc = Processo::find($this->editingId);
        $proc->update($dados);
    } else {
        $proc = Processo::create($dados);
        ProcessoHistorico::create([
            'processo_id' => $proc->id,
            'user_id' => Auth::id(),
            'acao' => 'Criação',
            'descricao' => 'Processo cadastrado no sistema.'
        ]);
    }
    $this->cancelar();
};

$excluir = function ($id) {
    Processo::find($id)?->delete();
    $this->drawerOpen = false;
};

with(fn() => [
    'processos' => Processo::with(['cliente', 'advogado'])
        ->where(function ($query) {
            $query->where('titulo', 'like', "%{$this->search}%")
                ->orWhere('numero_processo', 'like', "%{$this->search}%")
                ->orWhereHas('cliente', fn($q) => $q->where('nome', 'like', "%{$this->search}%"))
                ->orWhereHas('advogado', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
        })
        ->when($this->filtroAtivo === 'meus', fn($q) => $q->where('user_id', Auth::id()))
        ->when($this->filtroAtivo === 'urgentes', fn($q) => $q->whereIn('status', [
            ProcessoStatus::URGENCIA_LIMINAR, 
            ProcessoStatus::PRAZO_ABERTO, 
            ProcessoStatus::AUDIENCIA_DESIGNADA
        ]))
        ->when($this->filtroAtivo === 'vencidos', fn($q) => $q->where('data_prazo', '<', now()))
        ->latest()->paginate(10),
    'resultadosClientes' => Cliente::where('nome', 'like', "%{$this->cliente_nome_search}%")->limit(5)->get(),
    'listaAdvogados' => User::where('cargo', 'Advogado')->orderBy('name')->get(),
    'tribunais' => $tribunaisLista,
    'varas' => $varasLista
]);
?>

<div> 
    <div class="space-y-8 text-left animate-fadeIn font-sans relative">

        @if (session()->has('message'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                class="fixed top-5 right-5 z-[200] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-r-xl shadow-2xl animate-bounce-in">
                <div class="text-emerald-500 mr-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg></div>
                <div class="font-black text-emerald-800 uppercase tracking-widest text-[10px]">{{ session('message') }}</div>
            </div>
        @endif

        {{-- FORMULÁRIO --}}
        <div class="bg-white rounded-2xl shadow-sm border {{ $isEditing ? 'border-indigo-400 ring-2 ring-indigo-50' : 'border-gray-100' }} transition-all duration-500"
            wire:key="form-proc-{{ $formId }}">
            <div class="p-8">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-gray-900 text-white shadow-lg"><svg class="w-6 h-6" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg></div>
                        <div>
                            <h2 class="text-2xl font-black text-gray-900 tracking-tighter italic uppercase">
                                {{ $isEditing ? 'Editar Processo' : 'Novo Processo' }}</h2>
                            <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest text-left">L&A Flow •
                                Dashboard Jurídica</p>
                        </div>
                    </div>
                    @if($isEditing)<button wire:click="cancelar"
                        class="text-xs font-black text-red-500 uppercase tracking-widest hover:underline">Cancelar
                    Edição</button>@endif
                </div>
                <form wire:submit.prevent="salvar" class="grid grid-cols-1 md:grid-cols-12 gap-x-6 gap-y-6">
                    <div class="md:col-span-6 relative"><x-input-label value="BUSCAR CLIENTE"
                            class="text-[10px] font-bold text-indigo-500" /><input type="text"
                            wire:model.live="cliente_nome_search"
                            class="w-full mt-1 border-none bg-indigo-50 rounded-xl shadow-inner font-bold text-xs uppercase p-3"
                            placeholder="Nome..."><input type="hidden"
                            wire:model="cliente_id">@if(strlen($cliente_nome_search) > 2 && $cliente_id == null)
                            <div
                                class="absolute z-40 w-full bg-white border border-gray-100 shadow-2xl rounded-xl mt-1 overflow-hidden">
                                @foreach($resultadosClientes as $cli)<button type="button"
                                    wire:click="selecionarCliente({{ $cli->id }}, '{{ $cli->nome }}')"
                                class="w-full text-left px-4 py-3 text-[10px] font-black hover:bg-indigo-600 hover:text-white border-b border-gray-50 uppercase">{{ $cli->nome }}</button>@endforeach
                        </div>@endif<x-input-error :messages="$errors->get('cliente_id')" class="mt-1" />
                    </div>
                    <div class="md:col-span-6"><x-input-label value="ADVOGADO"
                            class="text-[10px] font-bold text-gray-400" /><select wire:model="user_id"
                            class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner font-bold text-xs uppercase">
                            <option value="">SELECIONE...</option>@foreach($listaAdvogados as $adv) <option
                            value="{{ $adv->id }}">{{ $adv->name }}</option> @endforeach
                        </select><x-input-error :messages="$errors->get('user_id')" class="mt-1" /></div>
                    <div class="md:col-span-4"><x-input-label value="CNJ"
                            class="text-[10px] font-bold text-gray-400" /><x-text-input wire:model="numero_processo"
                            class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold"
                            x-mask="9999999-99.9999.9.99.9999" /><x-input-error :messages="$errors->get('numero_processo')"
                            class="mt-1" /></div>
                    <div class="md:col-span-8"><x-input-label value="TÍTULO"
                            class="text-[10px] font-bold text-gray-400" /><x-text-input wire:model="titulo"
                            class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold uppercase" /><x-input-error
                            :messages="$errors->get('titulo')" class="mt-1" /></div>
                    <div class="md:col-span-4"><x-input-label value="TRIBUNAL"
                            class="text-[10px] font-bold text-gray-400" /><select wire:model.live="tribunal"
                            class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner font-bold text-xs uppercase">
                            <option value="">SELECIONE...</option>@foreach($tribunais as $tri) <option value="{{ $tri }}">
                            {{ $tri }}</option> @endforeach<option value="OUTROS">OUTROS...</option>
                        </select>@if($tribunal === 'OUTROS')<input type="text" wire:model="tribunal_outro"
                        class="w-full mt-2 border-none bg-yellow-50 rounded-xl shadow-inner font-bold text-xs uppercase p-3">@endif<x-input-error
                            :messages="$errors->get('tribunal')" class="mt-1" /></div>
                    <div class="md:col-span-4"><x-input-label value="VARA"
                            class="text-[10px] font-bold text-gray-400" /><select wire:model.live="vara"
                            class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner font-bold text-xs uppercase">
                            <option value="">SELECIONE...</option>@foreach($varas as $v) <option value="{{ $v }}">{{ $v }}
                            </option> @endforeach<option value="OUTROS">OUTROS...</option>
                        </select>@if($vara === 'OUTROS')<input type="text" wire:model="vara_outro"
                        class="w-full mt-2 border-none bg-yellow-50 rounded-xl shadow-inner font-bold text-xs uppercase p-3">@endif<x-input-error
                            :messages="$errors->get('vara')" class="mt-1" /></div>
                    <div class="md:col-span-4"><x-input-label value="STATUS"
                            class="text-[10px] font-bold text-gray-400" /><select wire:model="status"
                            class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner font-bold text-xs uppercase">
                             <optgroup label="🔵 INICIAL">
                                            <option>Distribuído</option>
                                            <option>Petição Inicial</option>
                                            <option>Aguardando Citação</option>
                                        </optgroup>
                                        <optgroup label="🟢 TRAMITAÇÃO">
                                            <option>Em Andamento</option>
                                            <option>Concluso para Decisão</option>
                                            <option>Instrução</option>
                                            <option>Contestação/Réplica</option>
                                        </optgroup>
                                        <optgroup label="🟡 AGENDAMENTOS">
                                            <option>Audiência Designada</option>
                                            <option>Aguardando Audiência</option>
                                            <option>Perícia Designada</option>
                                            <option>Apresentação de Laudo</option>
                                        </optgroup>
                                        <optgroup label="🔴 URGÊNCIA">
                                            <option>Prazo em Aberto</option>
                                            <option>Urgência / Liminar</option>
                                            <option>Aguardando Protocolo</option>
                                        </optgroup>
                                        <optgroup label="🟣 DECISÃO">
                                            <option>Sentenciado</option>
                                            <option>Em Grau de Recurso</option>
                                            <option>Cumprimento de Sentença</option>
                                            <option>Acordo/Pagamento</option>
                                        </optgroup>
                                        <optgroup label="⚪ FINALIZADO">
                                            <option>Trânsito em Julgado</option>
                                            <option>Suspenso / Sobrestado</option>
                                            <option>Arquivado</option>
                                        </optgroup>
                        </select><x-input-error :messages="$errors->get('status')" class="mt-1" /></div>
                    <div class="md:col-span-3"><x-input-label value="PRAZO"
                            class="text-[10px] font-bold text-rose-500" /><x-text-input wire:model="data_prazo" type="date"
                            class="w-full mt-1 bg-rose-50 border-none shadow-inner font-bold text-rose-700" /><x-input-error
                            :messages="$errors->get('data_prazo')" class="mt-1" /></div>
                    <div class="md:col-span-3"><x-input-label value="VALOR (R$)"
                            class="text-[10px] font-bold text-gray-400" /><x-text-input wire:model="valor_causa" type="text"
                            class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold"
                            x-mask:dynamic="$money($input, ',', '.', 2)" /><x-input-error
                            :messages="$errors->get('valor_causa')" class="mt-1" /></div>
                    <div class="md:col-span-6 flex items-end"><button type="submit"
                            class="w-full py-4 bg-gray-900 text-white rounded-xl font-black shadow-xl hover:bg-indigo-600 transition-all uppercase text-xs tracking-widest">{{ $isEditing ? 'Salvar' : 'Cadastrar' }}</button>
                    </div>
                    <div class="md:col-span-12"><x-input-label value="OBSERVAÇÕES"
                            class="text-[10px] font-bold text-gray-400" /><textarea wire:model="observacoes" rows="2"
                            class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 text-sm font-medium"></textarea>
                    </div>
                </form>
            </div>
        </div>

        {{-- LISTAGEM --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden text-left mt-8">

            <div class="px-6 py-5 border-b border-gray-100 bg-white flex flex-col md:flex-row md:items-center justify-between gap-4">
                
                <div class="flex p-1 bg-gray-100 rounded-lg self-start md:self-auto">
                    <button wire:click="mudarFiltro('todos')"
                        class="px-4 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-widest transition-all {{ $filtroAtivo === 'todos' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900' }}">
                        Todos
                    </button>
                    <button wire:click="mudarFiltro('meus')"
                        class="px-4 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-widest transition-all {{ $filtroAtivo === 'meus' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-indigo-600' }}">
                        Meus
                    </button>
                    <button wire:click="mudarFiltro('urgentes')"
                        class="px-4 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-widest transition-all {{ $filtroAtivo === 'urgentes' ? 'bg-white text-rose-600 shadow-sm' : 'text-gray-500 hover:text-rose-600' }}">
                        Urgentes
                    </button>
                </div>

                <div class="relative w-full md:w-72 group">
                    <input wire:model.live.debounce.300ms="search" type="text" 
                        class="block w-full rounded-xl border-gray-200 bg-gray-50 py-2.5 pl-4 pr-10 text-xs font-bold uppercase tracking-wide text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-sm" 
                        placeholder="Buscar por CNJ, advogado..." />
                    
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Processo</th>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Cliente / Local</th>
                            
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Responsável</th>
                            
                            <th scope="col" class="px-6 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Próximo Prazo</th>
                            <th scope="col" class="relative px-6 py-4">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-50">
                        @forelse($processos as $proc)
                            <tr class="group hover:bg-gray-50 transition duration-150 border-l-4 border-transparent hover:border-indigo-500">
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-900 truncate max-w-[200px]" title="{{ $proc->titulo }}">
                                            {{ $proc->titulo }}
                                        </span>
                                        <div class="flex items-center gap-1.5 mt-1">
                                            <svg class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                            </svg>
                                            <span class="font-mono text-[11px] text-gray-500 font-medium tracking-tight">
                                                {{ $proc->numero_processo }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-gray-700 truncate max-w-[150px]">{{ $proc->cliente?->nome }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 mt-1 text-gray-400">
                                            <span class="text-[10px] font-medium uppercase">{{ $proc->tribunal }}</span>
                                            <span class="text-[10px]">&bull;</span>
                                            <span class="text-[10px] font-medium truncate max-w-[100px]">{{ $proc->vara }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if($proc->advogado)
                                            <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 border border-slate-200">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                            <span class="text-xs font-bold text-gray-600 truncate max-w-[140px]">
                                                {{ $proc->advogado->name }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 italic pl-1">Não atribuído</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-3 py-1 inline-flex text-[9px] font-black uppercase tracking-widest rounded-full border {{ $proc->cor }}">
                                        {{ $proc->status instanceof \App\Enums\ProcessoStatus ? $proc->status->value : $proc->status }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($proc->data_prazo)
                                        @php
                                            $hoje = now()->startOfDay();
                                            $prazo = $proc->data_prazo->startOfDay();
                                            $diff = $hoje->diffInDays($prazo, false);
                                            $isVencido = $diff < 0;
                                            $isHoje = $diff == 0;
                                        @endphp
                                        <div class="flex items-center gap-3">
                                            <div class="flex flex-col items-center justify-center w-9 h-9 border rounded-lg {{ $isVencido ? 'bg-rose-50 border-rose-100 text-rose-600' : ($isHoje ? 'bg-amber-50 border-amber-100 text-amber-600' : 'bg-gray-50 border-gray-200 text-gray-600') }}">
                                                <span class="text-[9px] font-bold uppercase leading-none">{{ $proc->data_prazo->format('M') }}</span>
                                                <span class="text-xs font-black leading-none mt-0.5">{{ $proc->data_prazo->format('d') }}</span>
                                            </div>
                                            <div class="flex flex-col">
                                                @if($isVencido)
                                                    <span class="text-[10px] font-bold text-rose-600 uppercase">Vencido</span>
                                                @elseif($isHoje)
                                                    <span class="text-[10px] font-bold text-amber-600 uppercase">Hoje</span>
                                                @else
                                                    <span class="text-[10px] font-bold text-gray-600 uppercase">Em dia</span>
                                                    <span class="text-[9px] text-gray-400">{{ $diff }} dias</span>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-300 italic pl-2">-</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button wire:click="openDrawer({{ $proc->id }})" 
                                        class="text-[10px] font-bold text-indigo-600 border border-indigo-100 bg-indigo-50 px-3 py-1.5 rounded-lg hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all uppercase tracking-widest shadow-sm">
                                        Abrir
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-16 h-16 bg-gray-50 border border-gray-100 rounded-full flex items-center justify-center text-gray-300 mb-4">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        </div>
                                        <p class="text-sm font-bold text-gray-900 uppercase tracking-widest">Nenhum processo encontrado</p>
                                        <button wire:click="$set('search', '')" class="mt-4 text-xs font-bold text-indigo-600 hover:underline uppercase">Limpar Busca</button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                {{ $processos->links() }}
            </div>
        </div>

    </div>

    {{-- MODAL COM EFEITO BLACKOUT TOTAL USANDO TELEPORT --}}
    @if($drawerOpen && $activeProcess)
        @teleport('body')
            <div class="fixed inset-0 z-[99999]" role="dialog" aria-modal="true" style="z-index: 99999;">
                
                {{-- BLACKOUT: Fundo escuro cobrindo toda a tela (com estilo inline para garantir) --}}
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm" 
                     style="background-color: rgba(17, 24, 39, 0.85); position: fixed; top: 0; left: 0; width: 100%; height: 100%;"
                     wire:click="closeDrawer"></div>

                <div class="fixed inset-0 z-[99999] overflow-y-auto" style="z-index: 99999;">
                    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                        
                        {{-- Conteúdo do Modal --}}
                        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-white/10 z-[100000]">

                            <div class="bg-slate-900 relative overflow-hidden shrink-0">
                                <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-600 rounded-full blur-[100px] opacity-30 -mr-20 -mt-20 pointer-events-none"></div>
                                <div class="absolute bottom-0 left-0 w-40 h-40 bg-cyan-500 rounded-full blur-[80px] opacity-20 -ml-10 -mb-10 pointer-events-none"></div>

                                <div class="relative z-10 px-8 py-6">
                                    <div class="flex justify-between items-start mb-6">
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border shadow-lg {{ $activeProcess->cor }}">
                                            {{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}
                                        </span>
                                        
                                        <button wire:click="closeDrawer" class="text-slate-400 hover:text-white transition bg-white/5 hover:bg-white/20 p-2 rounded-full backdrop-blur-md">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                        </button>
                                    </div>

                                    <h2 class="text-2xl md:text-3xl font-black text-white uppercase tracking-tight leading-tight mb-8 drop-shadow-lg">
                                        {{ $activeProcess->titulo }}
                                    </h2>

                                    <div x-data="{ copied: false }">
                                        <button 
                                            @click="navigator.clipboard.writeText('{{ $activeProcess->numero_processo }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="group relative w-full sm:w-auto flex items-center justify-between gap-6 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-cyan-500/50 rounded-2xl px-6 py-5 transition-all duration-300 shadow-xl overflow-hidden ring-1 ring-white/5">
                                            
                                            <div class="absolute inset-0 bg-gradient-to-r from-cyan-500/0 via-cyan-500/5 to-cyan-500/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 transform translate-x-[-100%] group-hover:translate-x-[100%]"></div>

                                            <div class="flex flex-col items-start text-left relative z-10">
                                                <div class="flex items-center gap-2 mb-1.5">
                                                    <svg class="w-3 h-3 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" /></svg>
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.25em]">CNJ / Identificador</span>
                                                </div>
                                                
                                                <span class="font-mono text-xl sm:text-3xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-white via-cyan-100 to-slate-300 group-hover:from-cyan-300 group-hover:to-indigo-300 transition-all duration-300 drop-shadow-sm">
                                                    {{ $activeProcess->numero_processo }}
                                                </span>
                                            </div>

                                            <div class="relative z-10 flex flex-col items-center justify-center pl-6 border-l border-white/10 h-full">
                                                <div class="p-2 rounded-lg bg-white/5 group-hover:bg-cyan-500 group-hover:text-white text-slate-400 transition-all duration-300">
                                                    <svg x-show="!copied" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                    <svg x-show="copied" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </div>
                                                <span x-show="!copied" class="text-[8px] font-bold uppercase tracking-wider text-slate-500 mt-1 group-hover:text-cyan-300 transition-colors">Copiar</span>
                                                <span x-show="copied" x-cloak class="text-[8px] font-bold uppercase tracking-wider text-emerald-400 mt-1 animate-pulse">Copiado</span>
                                            </div>
                                        </button>
                                    </div>

                                </div>
                            </div>

                            <div class="p-8 bg-slate-50 space-y-8">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="bg-white p-4 rounded-2xl shadow-[0_2px_8px_rgba(0,0,0,0.04)] border border-slate-100 flex items-center gap-4 hover:border-indigo-200 transition">
                                        <div class="w-12 h-12 shrink-0 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-black border-2 border-indigo-100">
                                            {{ substr($activeProcess->cliente->nome, 0, 1) }}
                                        </div>
                                        <div class="overflow-hidden">
                                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Cliente</span>
                                            <h3 class="text-sm font-bold text-slate-900 truncate" title="{{ $activeProcess->cliente->nome }}">
                                                {{ $activeProcess->cliente->nome }}
                                            </h3>
                                        </div>
                                    </div>

                                    <div class="bg-white p-4 rounded-2xl shadow-[0_2px_8px_rgba(0,0,0,0.04)] border border-slate-100 flex items-center gap-4 hover:border-slate-300 transition">
                                        <div class="w-12 h-12 shrink-0 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-sm font-black border-2 border-slate-200">
                                            {{ substr($activeProcess->advogado->name ?? '?', 0, 1) }}
                                        </div>
                                        <div class="overflow-hidden">
                                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Responsável</span>
                                            <h3 class="text-sm font-bold text-slate-900 truncate" title="{{ $activeProcess->advogado->name ?? 'Sem Advogado' }}">
                                                {{ $activeProcess->advogado->name ?? 'Não atribuído' }}
                                            </h3>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                    <div class="col-span-2 sm:col-span-1 bg-emerald-50/50 p-4 rounded-2xl border border-emerald-100 flex flex-col justify-center">
                                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mb-1">Valor da Causa</span>
                                        <div class="text-lg font-black text-emerald-900">
                                            R$ {{ number_format($activeProcess->valor_causa, 2, ',', '.') }}
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-col justify-center">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Tribunal</span>
                                        <div class="text-sm font-bold text-slate-700 truncate" title="{{ $activeProcess->tribunal }}">
                                            {{ $activeProcess->tribunal }}
                                        </div>
                                    </div>

                                    <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-col justify-center">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Vara</span>
                                        <div class="text-sm font-bold text-slate-700 truncate" title="{{ $activeProcess->vara }}">
                                            {{ $activeProcess->vara }}
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                                        <h4 class="text-xs font-black text-slate-500 uppercase tracking-widest">Controle de Fase</h4>
                                        @if($activeProcess->data_prazo)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-rose-50 border border-rose-100 text-[10px] font-bold text-rose-600 uppercase">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                Vence {{ $activeProcess->data_prazo->format('d/m') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="p-5">
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Atualizar Status</label>
                                        <div class="relative">
                                            <select wire:change="updateStatus($event.target.value)"
                                                class="w-full appearance-none bg-none rounded-xl border-slate-200 text-sm font-bold text-slate-700 uppercase focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 py-3 pl-4 pr-10 bg-slate-50 hover:bg-white transition cursor-pointer shadow-sm">
                                                
                                                <option value="{{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}" selected>
                                                    {{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}
                                                </option>
                                                
                                                <optgroup label="🔵 INICIAL">
                                                    <option>Distribuído</option>
                                                    <option>Petição Inicial</option>
                                                    <option>Aguardando Citação</option>
                                                </optgroup>
                                                <optgroup label="🟢 TRAMITAÇÃO">
                                                    <option>Em Andamento</option>
                                                    <option>Concluso para Decisão</option>
                                                    <option>Instrução</option>
                                                    <option>Contestação/Réplica</option>
                                                </optgroup>
                                                <optgroup label="🟡 AGENDAMENTOS">
                                                    <option>Audiência Designada</option>
                                                    <option>Aguardando Audiência</option>
                                                    <option>Perícia Designada</option>
                                                    <option>Apresentação de Laudo</option>
                                                </optgroup>
                                                <optgroup label="🔴 URGÊNCIA">
                                                    <option>Prazo em Aberto</option>
                                                    <option>Urgência / Liminar</option>
                                                    <option>Aguardando Protocolo</option>
                                                </optgroup>
                                                <optgroup label="🟣 DECISÃO">
                                                    <option>Sentenciado</option>
                                                    <option>Em Grau de Recurso</option>
                                                    <option>Cumprimento de Sentença</option>
                                                    <option>Acordo/Pagamento</option>
                                                </optgroup>
                                                <optgroup label="⚪ FINALIZADO">
                                                    <option>Trânsito em Julgado</option>
                                                    <option>Suspenso / Sobrestado</option>
                                                    <option>Arquivado</option>
                                                </optgroup>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-8">
                                    <div>
                                        <h4 class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            Notas Internas
                                        </h4>
                                        <div class="bg-amber-50/50 border border-amber-100 rounded-xl p-4 text-sm text-amber-900/80 font-medium leading-relaxed italic">
                                            @if($activeProcess->observacoes)
                                                "{{ $activeProcess->observacoes }}"
                                            @else
                                                <span class="text-amber-900/40 not-italic">Sem observações registradas.</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Linha do Tempo
                                        </h4>
                                        
                                        <div class="relative pl-2 space-y-6 before:content-[''] before:absolute before:left-[19px] before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-200">
                                            @foreach($activeProcess->historico as $hist)
                                                <div class="relative flex gap-4 group">
                                                    
                                                    @if($hist->acao === 'Criação')
                                                        <div class="relative z-10 flex-none w-6 h-6 rounded-full bg-white border-2 border-emerald-100 text-emerald-500 flex items-center justify-center group-hover:border-emerald-500 group-hover:scale-110 transition-all shadow-sm">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                        </div>
                                                    @else
                                                        <div class="relative z-10 flex-none w-6 h-6 rounded-full bg-white border-2 border-indigo-100 text-indigo-500 flex items-center justify-center group-hover:border-indigo-500 group-hover:scale-110 transition-all shadow-sm">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                            </svg>
                                                        </div>
                                                    @endif
                                                    
                                                    <div class="flex-1 pb-2">
                                                        <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1">
                                                            <p class="text-xs font-bold text-slate-800 uppercase tracking-tight">{{ $hist->acao }}</p>
                                                            <span class="text-[10px] font-bold text-slate-400 tabular-nums">
                                                                {{ $hist->created_at->format('d/m/Y H:i') }}
                                                            </span>
                                                        </div>
                                                        <p class="text-xs text-slate-500 mt-1 leading-snug">{{ $hist->descricao }}</p>
                                                        <p class="text-[9px] font-bold text-slate-300 mt-1 uppercase tracking-wider">
                                                            Por: {{ $hist->user->name ?? 'Sistema' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="bg-white px-8 py-5 border-t border-slate-100 flex gap-4 shrink-0">
                                <button wire:click="editar({{ $activeProcess->id }})"
                                    class="flex-1 py-3 bg-white border-2 border-slate-200 rounded-xl text-xs font-black text-slate-700 uppercase tracking-widest hover:border-indigo-600 hover:text-indigo-600 transition shadow-sm hover:shadow-md">
                                    Editar Dados
                                </button>
                                <button onclick="confirm('Tem certeza que deseja excluir este processo? Isso não pode ser desfeito.') || event.stopImmediatePropagation()"
                                    wire:click="excluir({{ $activeProcess->id }})"
                                    class="flex-1 py-3 bg-rose-50 border-2 border-rose-100 rounded-xl text-xs font-black text-rose-600 uppercase tracking-widest hover:bg-rose-100 hover:border-rose-200 transition shadow-sm hover:shadow-md">
                                    Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>