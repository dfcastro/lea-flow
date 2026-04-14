<?php

use App\Models\Cliente;
use App\Models\Processo;
use App\Models\Financeiro;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use function Livewire\Volt\{state, with, usesPagination};

usesPagination();

state([
    // Filtros Principais
    'search' => '',
    'filtroStatus' => 'todos', // todos, pago, pendente, atrasado
    'data_inicio' => '',
    'data_fim' => '',

    // Formulário de Lançamento
    'titulo' => '',
    'tipo' => 'Honorários Iniciais', // Honorários Iniciais, Honorários de Êxito, Custas Judiciais, Despesa
    'valor' => '',
    'data_vencimento' => '',
    'data_pagamento' => '',
    'status' => 'Pendente',
    'cliente_id' => null,
    'processo_id' => null,
    'observacoes' => '',

    // Variáveis do Autocomplete
    'buscaCliente' => '',
    'buscaProcesso' => '',
    'nomeClienteSelecionado' => '',
    'nomeProcessoSelecionado' => '',

    // Controles UI
    'showModal' => false,
    'isEditing' => false,
    'editingId' => null,
    'formId' => 1,
]);

$abrirModal = function () {
    $this->reset([
        'titulo',
        'tipo',
        'valor',
        'data_vencimento',
        'data_pagamento',
        'status',
        'cliente_id',
        'processo_id',
        'observacoes',
        'isEditing',
        'editingId',
        'buscaCliente',
        'buscaProcesso',
        'nomeClienteSelecionado',
        'nomeProcessoSelecionado'
    ]);

    $this->tipo = 'Honorários Iniciais';
    $this->status = 'Pendente';
    $this->data_vencimento = date('Y-m-d');
    $this->data_pagamento = date('Y-m-d');
    $this->showModal = true;
};

$fecharModal = function () {
    $this->showModal = false;
    $this->formId++;
};

$mudarFiltroStatus = function ($novoStatus) {
    $this->filtroStatus = $novoStatus;
    $this->resetPage();
};

$limparFiltroDatas = function () {
    $this->data_inicio = '';
    $this->data_fim = '';
    $this->resetPage();
};

$editar = function ($id) {
    $lancamento = Financeiro::find($id);
    if ($lancamento) {
        $this->isEditing = true;
        $this->editingId = $lancamento->id;
        $this->titulo = $lancamento->titulo;
        $this->tipo = $lancamento->tipo;
        $this->valor = number_format($lancamento->valor, 2, ',', '.');
        $this->data_vencimento = $lancamento->data_vencimento->format('Y-m-d');
        $this->data_pagamento = $lancamento->data_pagamento ? $lancamento->data_pagamento->format('Y-m-d') : date('Y-m-d');
        $this->status = $lancamento->status;
        $this->cliente_id = $lancamento->cliente_id;
        $this->processo_id = $lancamento->processo_id;
        $this->observacoes = $lancamento->observacoes;

        // Preenche o visual do autocomplete se houver vínculo
        $this->nomeClienteSelecionado = $lancamento->cliente ? $lancamento->cliente->nome : '';
        $this->nomeProcessoSelecionado = $lancamento->processo ? $lancamento->processo->numero_processo . ' - ' . mb_substr($lancamento->processo->titulo, 0, 30) . '...' : '';

        $this->showModal = true;
    }
};

$salvar = function () {
    $valorLimpo = str_replace(['.', ','], ['', '.'], $this->valor);

    $this->validate([
        'titulo' => 'required|min:3',
        'valor' => 'required',
        'data_vencimento' => 'required|date',
        'tipo' => 'required',
        'status' => 'required',
    ]);

    $dados = [
        'titulo' => $this->titulo,
        'tipo' => $this->tipo,
        'valor' => (float) $valorLimpo,
        'data_vencimento' => $this->data_vencimento,
        'data_pagamento' => $this->status === 'Pago' ? ($this->data_pagamento ?: now()) : null,
        'status' => $this->status,
        'cliente_id' => $this->cliente_id ?: null,
        'processo_id' => $this->processo_id ?: null,
        'observacoes' => $this->observacoes,
        'user_id' => Auth::id(),
    ];

    if ($this->isEditing) {
        Financeiro::find($this->editingId)->update($dados);
        session()->flash('message', 'Lançamento atualizado!');
    } else {
        Financeiro::create($dados);
        session()->flash('message', 'Novo lançamento registado!');
    }

    $this->fecharModal();
};

$marcarComoPago = function ($id) {
    $lancamento = Financeiro::find($id);
    if ($lancamento && $lancamento->status !== 'Pago') {
        $lancamento->update([
            'status' => 'Pago',
            'data_pagamento' => now()
        ]);
        session()->flash('message', 'Lançamento marcado como Pago!');
    }
};

$excluir = function ($id) {
    Financeiro::find($id)?->delete();
    session()->flash('message', 'Lançamento excluído com sucesso.');
};

with(function () {
    // 1. Atualização automática de pendentes para atrasados
    Financeiro::where('status', 'Pendente')
        ->where('data_vencimento', '<', now()->toDateString())
        ->update(['status' => 'Atrasado']);

    // 2. Query principal de transações (Com Filtros de Data Integrados)
    $query = Financeiro::with(['cliente', 'processo'])
        ->when($this->search, function ($q) {
            $q->where(function ($subQ) {
                $subQ->where('titulo', 'like', "%{$this->search}%")
                    ->orWhereHas('cliente', fn($c) => $c->where('nome', 'like', "%{$this->search}%"))
                    ->orWhereHas('processo', fn($p) => $p->where('numero_processo', 'like', "%{$this->search}%"));
            });
        })
        ->when($this->filtroStatus !== 'todos', function ($q) {
            if ($this->filtroStatus === 'pago')
                $q->where('status', 'Pago');
            if ($this->filtroStatus === 'pendente')
                $q->where('status', 'Pendente');
            if ($this->filtroStatus === 'atrasado')
                $q->where('status', 'Atrasado');
        })
        ->when($this->data_inicio, function ($q) {
            $q->whereDate('data_vencimento', '>=', $this->data_inicio);
        })
        ->when($this->data_fim, function ($q) {
            $q->whereDate('data_vencimento', '<=', $this->data_fim);
        })
        ->orderByRaw("FIELD(status, 'Atrasado', 'Pendente', 'Pago')")
        ->orderBy('data_vencimento', 'asc');

    // 3. Cálculos para os KPIs (Fixos no Mês Atual para dar overview constante)
    $recebidoMes = Financeiro::where('status', 'Pago')
        ->whereMonth('data_pagamento', now()->month)
        ->whereYear('data_pagamento', now()->year)
        ->sum('valor');

    $aReceberMes = Financeiro::where('status', 'Pendente')
        ->whereMonth('data_vencimento', now()->month)
        ->whereYear('data_vencimento', now()->year)
        ->sum('valor');

    $atrasadoTotal = Financeiro::where('status', 'Atrasado')->sum('valor');

    return [
        'transacoes' => $query->paginate(15),
        'recebidoMes' => $recebidoMes,
        'aReceberMes' => $aReceberMes,
        'atrasadoTotal' => $atrasadoTotal,
        'tiposLancamento' => ['Honorários Iniciais', 'Honorários de Êxito', 'Custas Judiciais', 'Despesa Administrativa'],

        // Autocomplete Inteligente (Busca apenas 10 por vez)
        'clientesLista' => strlen($this->buscaCliente) > 1
            ? Cliente::where('nome', 'like', "%{$this->buscaCliente}%")->orderBy('nome')->take(10)->get()
            : Cliente::orderBy('nome')->take(5)->get(),

        'processosLista' => strlen($this->buscaProcesso) > 1
            ? Processo::where('numero_processo', 'like', "%{$this->buscaProcesso}%")->orWhere('titulo', 'like', "%{$this->buscaProcesso}%")->orderBy('titulo')->take(10)->get()
            : Processo::orderBy('titulo')->take(5)->get(),
    ];
});
?>

<div class="min-h-screen bg-slate-50 p-4 sm:p-6 lg:p-8 font-sans antialiased text-slate-900 w-full relative">

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/mask@3.x.x/dist/cdn.min.js"></script>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    </style>

    {{-- Notificações --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="fixed top-5 right-5 z-[999999] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-xl shadow-lg transition-all">
            <svg class="w-5 h-5 text-emerald-500 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <div class="font-bold text-emerald-800 uppercase tracking-wider text-[11px]">{{ session('message') }}</div>
        </div>
    @endif

    {{-- Container Principal --}}
    <div class="bg-white rounded-2xl p-4 sm:p-8 shadow-sm border border-slate-200 w-full overflow-hidden mb-10">

        {{-- Cabeçalho --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end border-b border-slate-100 pb-5 mb-6 gap-5">
            <div class="w-full md:w-auto">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-7 bg-emerald-500 rounded-sm"></div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Financeiro</h1>
                </div>
                <p class="mt-1 text-sm text-slate-500 pl-3">Controlo de honorários, custas e despesas.</p>
            </div>

            <div class="flex flex-col sm:flex-row w-full md:w-auto gap-3 items-center">
                <div class="relative w-full sm:w-64">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar lançamento..."
                        class="w-full pl-10 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition" />
                </div>
                <button wire:click="abrirModal" class="w-full sm:w-auto px-6 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 transition flex items-center justify-center gap-2 shrink-0 shadow-sm shadow-emerald-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Novo Lançamento
                </button>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white border border-emerald-100 border-l-4 border-l-emerald-500 rounded-xl p-5 flex flex-col gap-2 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 mb-0.5">Recebido no Mês Atual</p>
                    <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg></div>
                </div>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight">R$ {{ number_format($recebidoMes, 2, ',', '.') }}</h3>
            </div>
            <div class="bg-white border border-amber-100 border-l-4 border-l-amber-500 rounded-xl p-5 flex flex-col gap-2 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600 mb-0.5">A Receber (Mês Atual)</p>
                    <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                </div>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight">R$ {{ number_format($aReceberMes, 2, ',', '.') }}</h3>
            </div>
            <div class="bg-white border border-rose-100 border-l-4 border-l-rose-500 rounded-xl p-5 flex flex-col gap-2 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-rose-600 mb-0.5">Inadimplência Total</p>
                    <div class="w-8 h-8 rounded-full bg-rose-50 flex items-center justify-center text-rose-500 shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div>
                </div>
                <h3 class="text-3xl font-black text-rose-600 tracking-tight">R$ {{ number_format($atrasadoTotal, 2, ',', '.') }}</h3>
            </div>
        </div>
        
        {{-- ÁREA DE FILTROS (STATUS E DATA) --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 bg-slate-50 p-3 rounded-xl border border-slate-100">
            
            {{-- Filtros de Status --}}
            <div class="flex flex-wrap items-center gap-2">
                <button wire:click="mudarFiltroStatus('todos')" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition {{ $filtroStatus === 'todos' ? 'bg-slate-900 border-slate-900 text-white' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-100' }}">Todas</button>
                <button wire:click="mudarFiltroStatus('pendente')" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition {{ $filtroStatus === 'pendente' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-100' }}">Pendentes</button>
                <button wire:click="mudarFiltroStatus('pago')" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition {{ $filtroStatus === 'pago' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-100' }}">Pagas</button>
                <button wire:click="mudarFiltroStatus('atrasado')" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition {{ $filtroStatus === 'atrasado' ? 'bg-rose-50 border-rose-200 text-rose-600' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-100' }}">Atrasadas</button>
            </div>

            {{-- Filtros de Data (Período) --}}
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <div class="flex items-center gap-2 w-full sm:w-auto bg-white p-1 rounded-lg border border-slate-200 shadow-sm">
                    <input type="date" wire:model.live="data_inicio" class="text-xs text-slate-600 border-none bg-transparent focus:ring-0 cursor-pointer" title="Data Inicial">
                    <span class="text-slate-300 text-xs font-bold uppercase">Até</span>
                    <input type="date" wire:model.live="data_fim" class="text-xs text-slate-600 border-none bg-transparent focus:ring-0 cursor-pointer" title="Data Final">
                </div>
                
                @if($data_inicio || $data_fim)
                    <button wire:click="limparFiltroDatas" class="px-3 py-1.5 text-[10px] font-bold uppercase text-slate-500 hover:text-rose-500 transition flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Limpar
                    </button>
                @endif
            </div>

        </div>

        {{-- VISUALIZAÇÃO MOBILE (CARDS) --}}
        <div class="block md:hidden space-y-4">
            @forelse($transacoes as $transacao)
                @php
                    $isDespesa = $transacao->tipo === 'Despesa Administrativa';
                    $corStatus = match ($transacao->status) {
                        'Pago' => 'text-emerald-600 bg-emerald-50 border-emerald-200',
                        'Atrasado' => 'text-rose-600 bg-rose-50 border-rose-200',
                        default => 'text-amber-600 bg-amber-50 border-amber-200'
                    };
                    $corBordaEsquerda = $transacao->status === 'Pago' ? 'border-l-emerald-500' : ($transacao->status === 'Atrasado' ? 'border-l-rose-500' : 'border-l-amber-400');
                @endphp
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col relative border-l-4 {{ $corBordaEsquerda }}">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-[9px] font-bold uppercase tracking-wider {{ $corStatus }} border px-2 py-0.5 rounded">{{ $transacao->status }}</span>
                        <div class="text-sm font-black {{ $isDespesa ? 'text-slate-600' : 'text-emerald-600' }}">
                            {{ $isDespesa ? '-' : '+' }} R$ {{ number_format($transacao->valor, 2, ',', '.') }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <h4 class="text-sm font-bold text-slate-900 leading-snug">{{ $transacao->titulo }}</h4>
                        <div class="text-[10px] font-bold text-slate-400 uppercase mt-1">{{ $transacao->tipo }}</div>
                    </div>

                    @if($transacao->cliente || $transacao->processo)
                        <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 mb-3">
                            @if($transacao->cliente) <div class="text-[11px] font-bold text-slate-700 truncate">👤 {{ $transacao->cliente->nome }}</div> @endif
                            @if($transacao->processo) <div class="text-[10px] font-mono text-slate-500 mt-1 truncate">⚖️ {{ $transacao->processo->numero_processo }}</div> @endif
                        </div>
                    @endif

                    <div class="flex justify-between items-center border-t border-slate-100 pt-3 mt-1">
                        <div class="text-[10px] font-bold text-slate-500">
                            Venc: {{ \Carbon\Carbon::parse($transacao->data_vencimento)->format('d/m/Y') }}
                        </div>
                        <div class="flex gap-2">
                            @if($transacao->status !== 'Pago')
                                <button wire:click="marcarComoPago({{ $transacao->id }})" class="p-1.5 bg-emerald-50 text-emerald-600 rounded hover:bg-emerald-100 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></button>
                            @endif
                            <button wire:click="editar({{ $transacao->id }})" class="p-1.5 bg-slate-50 text-slate-600 rounded hover:bg-slate-100 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg></button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-10 text-sm text-slate-500 bg-white rounded-xl border border-slate-200 shadow-sm">Nenhum lançamento encontrado neste período.</div>
            @endforelse
            <div class="pt-4">{{ $transacoes->links() }}</div>
        </div>

        {{-- VISUALIZAÇÃO DESKTOP (TABELA) --}}
        <div class="hidden md:block bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-6 py-4">Descrição / Vínculo</th>
                            <th class="px-6 py-4 text-center">Tipo</th>
                            <th class="px-6 py-4 text-center">Vencimento</th>
                            <th class="px-6 py-4 text-right">Valor (R$)</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($transacoes as $transacao)
                            @php
                                $isDespesa = $transacao->tipo === 'Despesa Administrativa';
                                $corStatus = match ($transacao->status) {
                                    'Pago' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
                                    'Atrasado' => 'text-rose-700 bg-rose-50 border-rose-200',
                                    default => 'text-amber-700 bg-amber-50 border-amber-200'
                                };
                            @endphp
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-slate-900">{{ $transacao->titulo }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 flex gap-2 items-center">
                                        @if($transacao->cliente) <span title="Cliente">👤 {{ $transacao->cliente->nome }}</span> @endif
                                        @if($transacao->processo) <span title="Processo CNJ" class="font-mono">⚖️ {{ $transacao->processo->numero_processo }}</span> @endif
                                        @if(!$transacao->cliente && !$transacao->processo) <span class="italic opacity-50">Lançamento Avulso</span> @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center"><span class="text-[10px] font-bold text-slate-500 uppercase">{{ $transacao->tipo }}</span></td>
                                <td class="px-6 py-4 text-center"><div class="text-xs font-semibold text-slate-700">{{ \Carbon\Carbon::parse($transacao->data_vencimento)->format('d/m/Y') }}</div></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="text-sm font-black {{ $isDespesa ? 'text-slate-600' : 'text-emerald-600' }}">
                                        {{ $isDespesa ? '-' : '' }} R$ {{ number_format($transacao->valor, 2, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded border {{ $corStatus }}">{{ $transacao->status }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if($transacao->status !== 'Pago')
                                            <button wire:click="marcarComoPago({{ $transacao->id }})" class="p-1.5 text-emerald-500 hover:bg-emerald-50 hover:text-emerald-700 rounded transition" title="Marcar como Pago"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg></button>
                                        @endif
                                        <button wire:click="editar({{ $transacao->id }})" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition" title="Editar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg></button>
                                        <button onclick="confirm('Excluir este lançamento permanentemente?') || event.stopImmediatePropagation()" wire:click="excluir({{ $transacao->id }})" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded transition" title="Excluir"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">Nenhum lançamento encontrado neste período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">{{ $transacoes->links() }}</div>
        </div>

    </div>

    {{-- MODAL DE CADASTRO/EDIÇÃO DE LANÇAMENTO --}}
    @if($showModal)
        @teleport('body')
        <div class="fixed inset-0 z-[99999] flex items-start md:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" wire:click="fecharModal"></div>

            <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden" wire:key="form-fin-{{ $formId }}">

                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center shrink-0 bg-slate-50/50">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">{{ $isEditing ? 'Editar Lançamento' : 'Novo Lançamento Financeiro' }}</h2>
                        <p class="text-xs text-slate-500 mt-1">Preencha os dados do honorário, custa ou despesa.</p>
                    </div>
                    <button wire:click="fecharModal" class="text-slate-400 hover:text-slate-700 hover:bg-slate-200 p-2 rounded-full transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar">
                    <form wire:submit.prevent="salvar" class="space-y-6">

                        {{-- Tipo, Status e Data de Pagamento Dinâmica --}}
                        <div x-data="{ statusSelect: @entangle('status') }" class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Natureza do Lançamento</label>
                                    <select wire:model="tipo" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition cursor-pointer">
                                        @foreach($tiposLancamento as $tp) <option value="{{ $tp }}">{{ $tp }}</option> @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Status de Pagamento</label>
                                    <select wire:model="status" x-model="statusSelect" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition cursor-pointer">
                                        <option value="Pendente">Pendente / A Receber</option>
                                        <option value="Pago">Pago / Recebido</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Campo Data Pagamento (Aparece apenas se Status == Pago) --}}
                            <div x-show="statusSelect === 'Pago'" x-collapse class="mt-4 pt-4 border-t border-slate-200">
                                <div>
                                    <label class="block text-[11px] font-bold text-emerald-600 uppercase tracking-wider mb-1.5">Data em que foi Pago/Recebido</label>
                                    <input type="date" wire:model="data_pagamento" class="w-full sm:w-1/2 bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition">
                                </div>
                            </div>
                        </div>

                        {{-- Detalhes Principais --}}
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Descrição / Referência</label>
                            <input type="text" wire:model="titulo" placeholder="Ex: Entrada Alvará, Perícia..." class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition">
                            @error('titulo') <span class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[11px] font-bold text-emerald-600 uppercase tracking-wider mb-1.5">Valor (R$)</label>
                                <input type="text" wire:model="valor" x-mask:dynamic="$money($input, ',', '.', 2)" placeholder="0,00" class="w-full bg-emerald-50/30 border border-emerald-200 rounded-lg px-4 py-2.5 text-lg font-bold text-emerald-800 focus:bg-white focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition">
                                @error('valor') <span class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Data de Vencimento</label>
                                <input type="date" wire:model="data_vencimento" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition">
                                @error('data_vencimento') <span class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <hr class="border-slate-100">

                        {{-- Vínculos Opcionais (Com Autocomplete) --}}
                        <div>
                            <h3 class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-3">Vincular a (Opcional)</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                                {{-- Autocomplete: Cliente --}}
                                <div x-data="{ open: false }" class="relative">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Cliente</label>

                                    <div x-show="$wire.cliente_id" class="flex items-center justify-between w-full bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2.5 text-sm text-indigo-800 transition" style="display: none;">
                                        <span class="truncate font-bold" x-text="$wire.nomeClienteSelecionado"></span>
                                        <button type="button" @click="$wire.set('cliente_id', null); $wire.set('nomeClienteSelecionado', ''); $wire.set('buscaCliente', ''); open = false" class="p-1 hover:bg-indigo-200 rounded-full text-indigo-500 transition" title="Remover">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>

                                    <div x-show="!$wire.cliente_id" class="relative">
                                        <input type="text" wire:model.live.debounce.300ms="buscaCliente" @focus="open = true" @click.away="open = false" placeholder="Digite o nome..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                                        <div x-show="open" x-transition class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-xl max-h-48 overflow-y-auto custom-scrollbar" style="display: none;">
                                            @forelse($clientesLista as $cli)
                                                <div @click="$wire.set('cliente_id', {{ $cli->id }}); $wire.set('nomeClienteSelecionado', '{{ addslashes($cli->nome) }}'); open = false; $wire.set('buscaCliente', '')" class="px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-b border-slate-50 last:border-0 transition">
                                                    {{ $cli->nome }}
                                                </div>
                                            @empty
                                                <div class="px-4 py-3 text-xs text-slate-500 text-center">Nenhum cliente encontrado.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>

                                {{-- Autocomplete: Processo --}}
                                <div x-data="{ open: false }" class="relative">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Processo Vinculado</label>

                                    <div x-show="$wire.processo_id" class="flex items-center justify-between w-full bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2.5 text-sm text-indigo-800 transition" style="display: none;">
                                        <span class="truncate font-mono text-xs font-bold" x-text="$wire.nomeProcessoSelecionado"></span>
                                        <button type="button" @click="$wire.set('processo_id', null); $wire.set('nomeProcessoSelecionado', ''); $wire.set('buscaProcesso', ''); open = false" class="p-1 hover:bg-indigo-200 rounded-full text-indigo-500 transition" title="Remover">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>

                                    <div x-show="!$wire.processo_id" class="relative">
                                        <input type="text" wire:model.live.debounce.300ms="buscaProcesso" @focus="open = true" @click.away="open = false" placeholder="Nº do processo ou título..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                                        <div x-show="open" x-transition class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-xl max-h-48 overflow-y-auto custom-scrollbar" style="display: none;">
                                            @forelse($processosLista as $proc)
                                                <div @click="$wire.set('processo_id', {{ $proc->id }}); $wire.set('nomeProcessoSelecionado', '{{ $proc->numero_processo }} - {{ addslashes(mb_substr($proc->titulo, 0, 30)) }}...'); open = false; $wire.set('buscaProcesso', '')" class="px-4 py-2 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-b border-slate-50 last:border-0 transition flex flex-col gap-0.5">
                                                    <span class="font-mono text-[11px] font-bold text-slate-500">{{ $proc->numero_processo }}</span>
                                                    <span class="font-medium truncate">{{ $proc->titulo }}</span>
                                                </div>
                                            @empty
                                                <div class="px-4 py-3 text-xs text-slate-500 text-center">Nenhum processo encontrado.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Notas --}}
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Observações Internas</label>
                            <textarea wire:model="observacoes" rows="2" placeholder="Detalhes do acordo, conta para depósito..." class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 resize-y transition custom-scrollbar"></textarea>
                        </div>

                        <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                            <button type="button" wire:click="fecharModal" class="px-6 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-bold hover:bg-slate-50 transition">Cancelar</button>
                            <button type="submit" class="px-8 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 shadow-md shadow-emerald-200 transition">
                                {{ $isEditing ? 'Atualizar' : 'Salvar Lançamento' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endteleport
    @endif

</div>