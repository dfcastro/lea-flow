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
    // VISÃO E FILTROS
    'viewMode' => 'kanban', // 'kanban' ou 'table'
    'filtroAtivo' => 'todos',
    'search' => '',
    'ocultarArquivados' => true,

    // FORMULÁRIO DE CADASTRO/EDIÇÃO
    'numero_processo' => '',
    'cliente_id' => null,
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
    'is_urgent' => false,

    // CONTROLES DE ESTADO
    'formId' => 1,
    'isEditing' => false,
    'editingId' => null,
    'drawerOpen' => false,
    'activeProcess' => null,
    'showFormModal' => false,
]);

$tribunaisLista = ['TRF1', 'TRF6', 'JEF', 'TJMG', 'TRT3', 'STJ', 'STF'];
$varasLista = ['VARA PREVIDENCIÁRIA', 'JUIZADO ESPECIAL FEDERAL', 'VARA CÍVEL', 'VARA FEDERAL CÍVEL', 'VARA ÚNICA', 'VARA DO TRABALHO'];

// --- MAPA DO KANBAN ---
$macroFases = [
    'INICIAL' => ['Distribuído', 'Petição Inicial', 'Aguardando Citação'],
    'TRAMITAÇÃO' => ['Em Andamento', 'Concluso para Decisão', 'Instrução', 'Contestação/Réplica'],
    'AGENDAMENTOS' => ['Audiência Designada', 'Aguardando Audiência', 'Perícia Designada', 'Apresentação de Laudo'],
    'URGÊNCIA' => ['Prazo em Aberto', 'Urgência / Liminar', 'Aguardando Protocolo'],
    'DECISÃO' => ['Sentenciado', 'Em Grau de Recurso', 'Cumprimento de Sentença', 'Acordo/Pagamento'],
    'FINALIZADO' => ['Trânsito em Julgado', 'Suspenso / Sobrestado', 'Arquivado'],
];

$toggleView = function ($mode) {
    $this->viewMode = $mode;
};

$mudarFiltro = function ($filtro) {
    $this->filtroAtivo = $filtro;
    $this->resetPage();
};

$toggleArquivados = function () {
    $this->ocultarArquivados = !$this->ocultarArquivados;
};

// --- FUNÇÕES DO KANBAN (DRAG & DROP) ---
$moverProcesso = function ($processoId, $novaColuna) use ($macroFases) {
    $p = Processo::find($processoId);
    if ($p) {
        $novoStatus = $macroFases[$novaColuna][0] ?? 'Em Andamento';
        $statusAtual = $p->status instanceof \App\Enums\ProcessoStatus ? $p->status->value : $p->status;

        if ($statusAtual !== $novoStatus) {
            $p->update(['status' => $novoStatus]);
            ProcessoHistorico::create([
                'processo_id' => $p->id,
                'user_id' => Auth::id(),
                'acao' => 'Movimentação Kanban',
                'descricao' => "Arrastado para a fase {$novaColuna} (Status alterado para: {$novoStatus})"
            ]);
            $this->dispatch('notificacao', ['msg' => 'Processo movido para ' . $novaColuna]);
        }
    }
};

// --- FUNÇÕES DO MODAL (VISUALIZAR) ---
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

// --- FUNÇÕES DE FORMULÁRIO (CRIAR/EDITAR) ---
$abrirForm = function () {
    $this->reset(['numero_processo', 'cliente_id', 'cliente_nome_search', 'user_id', 'titulo', 'tribunal', 'tribunal_outro', 'vara', 'vara_outro', 'data_prazo', 'valor_causa', 'observacoes', 'isEditing', 'editingId']);
    $this->status = 'Distribuído';
    $this->is_urgent = false;
    $this->showFormModal = true;
};

$cancelarForm = function () {
    $this->showFormModal = false;
    $this->formId++;
};

$selecionarCliente = function ($id, $nome) {
    $this->cliente_id = $id;
    $this->cliente_nome_search = $nome;
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
        $this->is_urgent = (bool) $p->is_urgent;

        $this->drawerOpen = false;
        $this->showFormModal = true;
    }
};

$salvar = function () {
    $valorLimpo = str_replace(['.', ','], ['', '.'], $this->valor_causa);
    $this->validate(['numero_processo' => 'required', 'cliente_id' => 'required', 'titulo' => 'required', 'tribunal' => 'required', 'vara' => 'required']);

    $dados = [
        'numero_processo' => $this->numero_processo,
        'cliente_id' => $this->cliente_id,
        'user_id' => $this->user_id ?: null,
        'titulo' => $this->titulo,
        'tribunal' => ($this->tribunal === 'OUTROS') ? strtoupper(trim($this->tribunal_outro)) : $this->tribunal,
        'vara' => ($this->vara === 'OUTROS') ? strtoupper(trim($this->vara_outro)) : $this->vara,
        'status' => $this->status,
        'data_prazo' => $this->data_prazo ?: null,
        'valor_causa' => (float) $valorLimpo ?: 0,
        'observacoes' => $this->observacoes,
        'is_urgent' => $this->is_urgent ? true : false,
    ];

    if ($this->isEditing) {
        Processo::find($this->editingId)->update($dados);
        session()->flash('message', 'Processo atualizado com sucesso!');
    } else {
        $proc = Processo::create($dados);
        ProcessoHistorico::create([
            'processo_id' => $proc->id,
            'user_id' => Auth::id(),
            'acao' => 'Criação',
            'descricao' => 'Processo cadastrado no sistema.'
        ]);
        session()->flash('message', 'Processo criado com sucesso!');
    }
    $this->cancelarForm();
};

$excluir = function ($id) {
    Processo::find($id)?->delete();
    $this->drawerOpen = false;
    session()->flash('message', 'Processo excluído.');
};

// --- CARREGAMENTO DE DADOS ---
with(function () use ($macroFases, $tribunaisLista, $varasLista) {
    $query = Processo::with(['cliente', 'advogado'])
        ->where(function ($q) {
            $q->where('titulo', 'like', "%{$this->search}%")
                ->orWhere('numero_processo', 'like', "%{$this->search}%")
                ->orWhereHas('cliente', fn($q2) => $q2->where('nome', 'like', "%{$this->search}%"))
                ->orWhereHas('advogado', fn($q2) => $q2->where('name', 'like', "%{$this->search}%"));
        })
        ->when($this->filtroAtivo === 'meus', fn($q) => $q->where('user_id', Auth::id()))
        ->when($this->filtroAtivo === 'urgentes', function ($q) {
            $q->where(function ($query) {
                $query->where('is_urgent', true)
                    ->orWhereIn('status', ['Urgência / Liminar', 'Prazo em Aberto']);
            });
        })
        ->when($this->filtroAtivo === 'vencidos', fn($q) => $q->where('data_prazo', '<', now()->startOfDay()))
        ->when($this->ocultarArquivados, fn($q) => $q->where('status', '!=', 'Arquivado'));

    $kanbanBoard = [];
    foreach (array_keys($macroFases) as $coluna) {
        $kanbanBoard[$coluna] = [];
    }

    $todosProcessos = (clone $query)->latest()->get();

    foreach ($todosProcessos as $p) {
        $statusStr = $p->status instanceof \App\Enums\ProcessoStatus ? $p->status->value : $p->status;
        $colocador = 'INICIAL';

        foreach ($macroFases as $macro => $listaStatus) {
            if (in_array($statusStr, $listaStatus)) {
                $colocador = $macro;
                break;
            }
        }
        $kanbanBoard[$colocador][] = $p;
    }

    return [
        'processosPaginados' => $query->latest()->paginate(10),
        'kanbanBoard' => $kanbanBoard,
        'resultadosClientes' => Cliente::where('nome', 'like', "%{$this->cliente_nome_search}%")->limit(5)->get(),
        'listaAdvogados' => User::where('cargo', 'Advogado')->orderBy('name')->get(),
        'tribunaisLista' => $tribunaisLista,
        'varasLista' => $varasLista,
        'macroFases' => $macroFases
    ];
});
?>

<div class="min-h-screen bg-[#F8FAFC] p-4 sm:p-6 lg:p-8 font-sans antialiased text-slate-900">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <style>
        .hide-scroll {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .hide-scroll::-webkit-scrollbar {
            display: none;
        }
        .kanban-board {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
    </style>

    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="fixed top-5 right-5 z-[999999] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-md shadow-lg">
            <svg class="w-5 h-5 text-emerald-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <div class="font-bold text-emerald-800 uppercase tracking-wider text-[11px]">{{ session('message') }}</div>
        </div>
    @endif

    <div x-data="{ show: false, msg: '' }"
        @notificacao.window="msg = $event.detail.msg; show = true; setTimeout(() => show = false, 3000)" x-show="show"
        style="display: none;"
        class="fixed bottom-5 right-5 z-[999999] bg-slate-900 text-white px-5 py-3 rounded-md shadow-lg flex items-center gap-3">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span class="text-[11px] font-bold uppercase tracking-wider" x-text="msg"></span>
    </div>

    <div style="background-color: white; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #E2E8F0;">

        <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #E2E8F0; padding-bottom: 1.25rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 4px; height: 28px; background-color: #4F46E5; border-radius: 2px;"></div>
                    <h1 style="font-size: 1.75rem; font-weight: 700; color: #0F172A; margin: 0; letter-spacing: -0.025em;">Processos</h1>
                </div>
                <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #64748B; padding-left: 1rem;">Gestão e controle de fases processuais.</p>
            </div>

            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; background-color: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 0.75rem; height: 38px; width: 260px; transition: border-color 0.2s;" onfocusin="this.style.borderColor='#4F46E5'" onfocusout="this.style.borderColor='#CBD5E1'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #94A3B8;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar CNJ, Cliente..."
                        style="border: none; background: transparent; width: 100%; font-size: 0.875rem; color: #334155; outline: none; box-shadow: none; padding-left: 0.5rem;" />
                </div>

                <div style="display: flex; background: #F1F5F9; padding: 4px; border-radius: 6px; height: 38px; align-items: center;">
                    <button wire:click="toggleView('kanban')" style="height: 100%; padding: 0 1rem; border-radius: 4px; border: none; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: 0.2s; {{ $viewMode === 'kanban' ? 'background: white; color: #4F46E5; box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'background: transparent; color: #64748B;' }}">
                        Board
                    </button>
                    <button wire:click="toggleView('table')" style="height: 100%; padding: 0 1rem; border-radius: 4px; border: none; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: 0.2s; {{ $viewMode === 'table' ? 'background: white; color: #4F46E5; box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'background: transparent; color: #64748B;' }}">
                        Lista
                    </button>
                </div>

                <button wire:click="abrirForm" style="background-color: #0F172A; color: white; height: 38px; padding: 0 1.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#1E293B'" onmouseout="this.style.backgroundColor='#0F172A'">
                    + Novo Processo
                </button>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 2rem;">
            <button wire:click="mudarFiltro('todos')" style="padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid {{ $filtroAtivo === 'todos' ? '#0F172A' : '#E2E8F0' }}; background: {{ $filtroAtivo === 'todos' ? '#0F172A' : '#F8FAFC' }}; color: {{ $filtroAtivo === 'todos' ? 'white' : '#64748B' }}; cursor: pointer; transition: 0.2s;">Todos</button>
            <button wire:click="mudarFiltro('meus')" style="padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid {{ $filtroAtivo === 'meus' ? '#C7D2FE' : '#E2E8F0' }}; background: {{ $filtroAtivo === 'meus' ? '#EEF2FF' : '#F8FAFC' }}; color: {{ $filtroAtivo === 'meus' ? '#4F46E5' : '#64748B' }}; cursor: pointer; transition: 0.2s;">Meus Processos</button>
            <button wire:click="mudarFiltro('urgentes')" style="padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid {{ $filtroAtivo === 'urgentes' ? '#FECDD3' : '#E2E8F0' }}; background: {{ $filtroAtivo === 'urgentes' ? '#FFF1F2' : '#F8FAFC' }}; color: {{ $filtroAtivo === 'urgentes' ? '#E11D48' : '#64748B' }}; cursor: pointer; transition: 0.2s;">Urgentes</button>
            <button wire:click="mudarFiltro('vencidos')" style="padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid {{ $filtroAtivo === 'vencidos' ? '#FDE68A' : '#E2E8F0' }}; background: {{ $filtroAtivo === 'vencidos' ? '#FEFCE8' : '#F8FAFC' }}; color: {{ $filtroAtivo === 'vencidos' ? '#B45309' : '#64748B' }}; cursor: pointer; transition: 0.2s;">Prazos Vencidos</button>

            <div style="width: 1px; background: #E2E8F0; margin: 0 0.5rem;"></div>

            <button wire:click="toggleArquivados" style="padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid {{ $ocultarArquivados ? '#CBD5E1' : '#E2E8F0' }}; background: {{ $ocultarArquivados ? '#F1F5F9' : '#F8FAFC' }}; color: {{ $ocultarArquivados ? '#475569' : '#64748B' }}; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 0.5rem;">
                @if($ocultarArquivados)
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                    Arquivados Ocultos
                @else
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    Mostrando Arquivados
                @endif
            </button>
        </div>

        <div>
            @if($viewMode === 'kanban')

                <div class="hide-scroll kanban-board" style="display: flex; gap: 0.75rem; width: 100%; overflow-x: auto; padding-bottom: 2rem; min-height: 65vh; align-items: flex-start;">

                    @foreach($kanbanBoard as $colunaNome => $processosColuna)
                        @php
                            $corColuna = match ($colunaNome) {
                                'INICIAL' => '#3B82F6',
                                'TRAMITAÇÃO' => '#10B981',
                                'AGENDAMENTOS' => '#F59E0B',
                                'URGÊNCIA' => '#E11D48',
                                'DECISÃO' => '#8B5CF6',
                                'FINALIZADO' => '#64748B',
                                default => '#CBD5E1'
                            };
                            $bgColuna = match ($colunaNome) {
                                'INICIAL' => '#EFF6FF',
                                'TRAMITAÇÃO' => '#ECFDF5',
                                'AGENDAMENTOS' => '#FFFBEB',
                                'URGÊNCIA' => '#FFF1F2',
                                'DECISÃO' => '#FAF5FF',
                                'FINALIZADO' => '#F8FAFC',
                                default => '#F1F5F9'
                            };
                        @endphp

                        <div style="flex: 1; min-width: 180px; max-width: 250px; background-color: {{ $bgColuna }}; border-radius: 6px; border: 1px solid #E2E8F0; border-top: 3px solid {{ $corColuna }}; display: flex; flex-direction: column; max-height: calc(100vh - 150px); cursor: default;">

                            <div style="padding: 0.5rem 0.65rem; border-bottom: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.6); border-top-left-radius: 4px; border-top-right-radius: 4px;">
                                <h3 style="font-size: 0.6rem; font-weight: 800; color: #0F172A; text-transform: uppercase; letter-spacing: 0.05em; margin: 0;">{{ $colunaNome }}</h3>
                                <span style="background: white; border: 1px solid #E2E8F0; color: #64748B; font-size: 0.55rem; font-weight: 800; padding: 0.1rem 0.35rem; border-radius: 999px;">{{ count($processosColuna) }}</span>
                            </div>

                            <div data-coluna="{{ $colunaNome }}" class="hide-scroll" x-data x-init="Sortable.create($el, { group: 'kanban', animation: 150, ghostClass: 'opacity-50', onEnd: function(evt) { if(evt.to.dataset.coluna !== evt.from.dataset.coluna) { @this.call('moverProcesso', evt.item.dataset.id, evt.to.dataset.coluna); } } })" style="padding: 0.5rem; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 0.5rem; min-height: 100px;">

                                @forelse($processosColuna as $proc)
                                    @php $isVencido = $proc->data_prazo && $proc->data_prazo->startOfDay() < now()->startOfDay(); @endphp

                                    <div data-id="{{ $proc->id }}" style="background: white; border-radius: 6px; padding: 0.65rem; border: 1px solid {{ $proc->is_urgent ? '#FECDD3' : '#E2E8F0' }}; box-shadow: 0 1px 2px {{ $proc->is_urgent ? 'rgba(225, 29, 72, 0.1)' : 'rgba(0,0,0,0.02)' }}; position: relative; cursor: grab; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.02)';">

                                        @if($proc->is_urgent)
                                            <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #E11D48;"></div>
                                        @else
                                            <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: {{ $corColuna }}; opacity: 0.5;"></div>
                                        @endif

                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.35rem; padding-left: 2px;">
                                            <span style="font-size: 0.45rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #4F46E5; background: #EEF2FF; padding: 0.15rem 0.25rem; border-radius: 4px; border: 1px solid #E0E7FF; max-width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                {{ $proc->status instanceof \App\Enums\ProcessoStatus ? $proc->status->value : $proc->status }}
                                            </span>
                                            <button wire:click.stop="openDrawer({{ $proc->id }})" style="background: transparent; border: none; color: #94A3B8; cursor: pointer; padding: 0.1rem; border-radius: 4px; transition: 0.2s;" onmouseover="this.style.backgroundColor='#F1F5F9'; this.style.color='#0F172A';" onmouseout="this.style.backgroundColor='transparent'; this.style.color='#94A3B8';">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            </button>
                                        </div>

                                        <h4 style="font-size: 0.7rem; font-weight: 700; color: #0F172A; line-height: 1.2; margin: 0 0 0.15rem 0; padding-left: 2px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="{{ $proc->titulo }}">
                                            {{ $proc->titulo }}
                                        </h4>
                                        <div style="font-family: monospace; font-size: 0.55rem; color: #64748B; margin-bottom: 0.5rem; padding-left: 2px;">
                                            {{ $proc->numero_processo }}
                                        </div>

                                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E2E8F0; padding-top: 0.4rem; margin-left: 2px;">
                                            <div style="display: flex; align-items: center; gap: 0.3rem;" title="{{ $proc->cliente?->nome }}">
                                                <div style="width: 16px; height: 16px; border-radius: 4px; background: #F8FAFC; border: 1px solid #E2E8F0; color: #475569; font-size: 0.45rem; font-weight: 800; display: flex; align-items: center; justify-content: center;">
                                                    {{ substr($proc->cliente?->nome ?? '?', 0, 1) }}
                                                </div>
                                                <span style="font-size: 0.5rem; font-weight: 600; color: #475569; max-width: 70px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $proc->cliente?->nome }}</span>
                                            </div>

                                            @if($proc->data_prazo)
                                                <span style="font-size: 0.45rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.1rem 0.25rem; border-radius: 4px; {{ $isVencido ? 'color: #E11D48; background: #FFF1F2; border: 1px solid #FECDD3;' : 'color: #64748B; background: #F8FAFC;' }}">
                                                    {{ $isVencido ? 'VENCIDO' : $proc->data_prazo->format('d/m') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                @empty
                                    <div style="display: flex; flex-direction: column; items-center; justify-content: center; text-align: center; padding: 1.5rem 0; opacity: 0.5;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #94A3B8; margin: 0 auto 0.25rem auto;" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                        <span style="font-size: 0.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #64748B;">Arraste para cá</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>

            @else
                <div style="background: white; border-radius: 8px; border: 1px solid #E2E8F0; overflow: hidden;">
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left;">
                            <thead>
                                <tr style="border-bottom: 1px solid #E2E8F0; background: #F8FAFC;">
                                    <th style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Processo</th>
                                    <th style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Cliente / Local</th>
                                    <th style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Responsável</th>
                                    <th style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">Status</th>
                                    <th style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Prazo</th>
                                    <th style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($processosPaginados as $proc)
                                    @php $isVencido = $proc->data_prazo && $proc->data_prazo->startOfDay() < now()->startOfDay(); @endphp
                                    <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.2s; border-left: 3px solid {{ $proc->is_urgent ? '#E11D48' : 'transparent' }};" onmouseover="this.style.backgroundColor='#F8FAFC';" onmouseout="this.style.backgroundColor='transparent';">
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-size: 0.85rem; font-weight: 600; color: #0F172A; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $proc->titulo }}</div>
                                            <div style="font-family: monospace; font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;">{{ $proc->numero_processo }}</div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-size: 0.75rem; font-weight: 600; color: #0F172A;">{{ $proc->cliente?->nome }}</div>
                                            <div style="font-size: 0.65rem; color: #64748B; margin-top: 0.15rem; text-transform: uppercase;">{{ $proc->tribunal }} • {{ $proc->vara }}</div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-size: 0.75rem; color: #475569;">{{ $proc->advogado->name ?? 'Não atribuído' }}</div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem; text-align: center;">
                                            <span style="font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; background: white; border: 1px solid #E2E8F0; color: #475569; padding: 0.25rem 0.6rem; border-radius: 6px;">
                                                {{ $proc->status instanceof \App\Enums\ProcessoStatus ? $proc->status->value : $proc->status }}
                                            </span>
                                        </td>
                                        <td style="padding: 1rem 1.5rem;">
                                            @if($proc->data_prazo)
                                                <span style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.25rem 0.6rem; border-radius: 4px; {{ $isVencido ? 'color: #E11D48; background: #FFF1F2; border: 1px solid #FECDD3;' : 'color: #64748B; background: #F8FAFC;' }}">
                                                    {{ $proc->data_prazo->format('d/m/Y') }}
                                                </span>
                                            @else
                                                <span style="font-size: 0.75rem; color: #94A3B8; font-style: italic;">-</span>
                                            @endif
                                        </td>
                                        <td style="padding: 1rem 1.5rem; text-align: right;">
                                            <button wire:click="openDrawer({{ $proc->id }})" style="background: white; border: 1px solid #CBD5E1; color: #0EA5E9; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: 0.2s;" onmouseover="this.style.backgroundColor='#F0F9FF'; this.style.borderColor='#BAE6FD'; this.style.color='#0284C7';" onmouseout="this.style.backgroundColor='white'; this.style.borderColor='#CBD5E1'; this.style.color='#0EA5E9';">
                                                Ver
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" style="padding: 4rem 2rem; text-align: center;">
                                            <div style="font-size: 0.75rem; color: #94A3B8;">Nenhum processo encontrado.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div style="padding: 1rem 1.5rem; border-top: 1px solid #E2E8F0; background: #F8FAFC;">
                        {{ $processosPaginados->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div> @if($showFormModal)
        @teleport('body')
        <div style="position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center;">
            <div style="position: absolute; inset: 0; background-color: rgba(15, 23, 42, 0.7); backdrop-filter: blur(2px);" wire:click="cancelarForm"></div>

            <div style="position: relative; width: 100%; max-width: 800px; background: white; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); padding: 2rem; max-height: 90vh; overflow-y: auto; margin: 1rem;" wire:key="form-proc-{{ $formId }}">

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #E2E8F0; padding-bottom: 1rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin: 0;">
                        {{ $isEditing ? 'Editar Processo' : 'Cadastrar Novo Processo' }}
                    </h2>
                    <button wire:click="cancelarForm" style="background: transparent; border: none; color: #64748B; cursor: pointer; padding: 0.25rem; border-radius: 4px; transition: 0.2s;" onmouseover="this.style.backgroundColor='#F1F5F9';" onmouseout="this.style.backgroundColor='transparent';">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form wire:submit.prevent="salvar" style="display: flex; flex-direction: column; gap: 1.25rem;">

                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 2; min-width: 250px; position: relative;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #4F46E5; margin-bottom: 0.25rem;">Vincular Cliente</label>

                            <input type="text" wire:model.live="cliente_nome_search" wire:input="$set('cliente_id', null)" placeholder="Busque pelo nome..." style="width: 100%; border: 1px solid #C7D2FE; background: #EEF2FF; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #4F46E5; outline: none;">
                            <input type="hidden" wire:model="cliente_id">

                            @if(strlen($cliente_nome_search) > 2 && $cliente_id == null)
                                <div style="position: absolute; z-index: 100; width: 100%; background: white; border: 1px solid #E2E8F0; border-radius: 6px; margin-top: 0.25rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden;">
                                    @foreach($resultadosClientes as $cli)
                                        <div wire:click="selecionarCliente({{ $cli->id }}, '{{ $cli->nome }}')" style="padding: 0.75rem 1rem; border-bottom: 1px solid #F1F5F9; font-size: 0.85rem; color: #0F172A; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#F8FAFC';" onmouseout="this.style.backgroundColor='white';">
                                            {{ $cli->nome }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @error('cliente_id') <span style="color: #E11D48; font-size: 0.65rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Responsável</label>
                            <select wire:model="user_id" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; cursor: pointer; transition: 0.2s;" onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                                <option value="">SELECIONE...</option>
                                @foreach($listaAdvogados as $adv) <option value="{{ $adv->id }}">{{ $adv->name }}</option> @endforeach
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">CNJ (Número)</label>
                            <input type="text" wire:model="numero_processo" x-mask="9999999-99.9999.9.99.9999" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;" onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                            @error('numero_processo') <span style="color: #E11D48; font-size: 0.65rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                        </div>
                        <div style="flex: 2; min-width: 250px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Título / Ação</label>
                            <input type="text" wire:model="titulo" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;" onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                            @error('titulo') <span style="color: #E11D48; font-size: 0.65rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Tribunal</label>
                            <select wire:model.live="tribunal" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; cursor: pointer; transition: 0.2s;" onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                                <option value="">SELECIONE...</option>
                                @foreach($tribunaisLista as $tri) <option value="{{ $tri }}">{{ $tri }}</option> @endforeach
                                <option value="OUTROS">OUTROS...</option>
                            </select>
                            @if($tribunal === 'OUTROS')
                                <input type="text" wire:model="tribunal_outro" placeholder="Especifique..." style="width: 100%; border: 1px solid #FEF08A; background: #FEFCE8; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #A16207; outline: none; margin-top: 0.5rem;">
                            @endif
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Vara</label>
                            <select wire:model.live="vara" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; cursor: pointer; transition: 0.2s;" onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                                <option value="">SELECIONE...</option>
                                @foreach($varasLista as $v) <option value="{{ $v }}">{{ $v }}</option> @endforeach
                                <option value="OUTROS">OUTROS...</option>
                            </select>
                            @if($vara === 'OUTROS')
                                <input type="text" wire:model="vara_outro" placeholder="Especifique..." style="width: 100%; border: 1px solid #FEF08A; background: #FEFCE8; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #A16207; outline: none; margin-top: 0.5rem;">
                            @endif
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Status</label>
                            <select wire:model="status" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; cursor: pointer; transition: 0.2s;" onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                                @foreach($macroFases as $macro => $lista)
                                    <optgroup label="{{ $macro }}">
                                        @foreach($lista as $st) <option value="{{ $st }}">{{ $st }}</option> @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #E11D48; margin-bottom: 0.25rem;">Data do Prazo</label>
                            <input type="date" wire:model="data_prazo" style="width: 100%; border: 1px solid #FECDD3; background: #FFF1F2; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #E11D48; outline: none;">
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #059669; margin-bottom: 0.25rem;">Valor da Causa</label>
                            <input type="text" wire:model="valor_causa" x-mask:dynamic="$money($input, ',', '.', 2)" style="width: 100%; border: 1px solid #A7F3D0; background: #ECFDF5; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #059669; outline: none;">
                        </div>
                    </div>

                    <div>
                        <label style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border: 1px solid #E2E8F0; background: #F8FAFC; border-radius: 6px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.backgroundColor='#F1F5F9';">
                            <input type="checkbox" wire:model="is_urgent" style="width: 18px; height: 18px; accent-color: #E11D48; cursor: pointer;">
                            <div>
                                <span style="display: block; font-size: 0.85rem; font-weight: 600; color: #0F172A;">Marcar como Urgente</span>
                                <span style="display: block; font-size: 0.7rem; color: #64748B;">Destaca este processo visualmente.</span>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Observações Internas</label>
                        <textarea wire:model="observacoes" rows="3" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; resize: vertical; transition: 0.2s;" onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';"></textarea>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                        <button type="button" wire:click="cancelarForm" style="background: white; border: 1px solid #CBD5E1; color: #475569; padding: 0.65rem 1.5rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; margin-right: 0.5rem; cursor: pointer; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#F1F5F9';" onmouseout="this.style.backgroundColor='white';">Cancelar</button>
                        <button type="submit" style="background-color: #0F172A; color: white; padding: 0.65rem 2rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; border: none; cursor: pointer; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#1E293B';" onmouseout="this.style.backgroundColor='#0F172A';">
                            {{ $isEditing ? 'Atualizar' : 'Salvar' }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
        @endteleport
    @endif

    @if($drawerOpen && $activeProcess)
        @teleport('body')
        <div style="position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center;">

            <div style="position: absolute; inset: 0; background-color: rgba(17, 24, 39, 0.7); backdrop-filter: blur(2px);" wire:click="closeDrawer"></div>

            <div style="position: relative; width: 100%; max-width: 700px; background: white; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; margin: 1rem; max-height: 95vh; display: flex; flex-direction: column; border-top: {{ $activeProcess->is_urgent ? '4px solid #E11D48' : '4px solid #4F46E5' }};">

                @if($activeProcess->is_urgent)
                    <div style="background-color: #FFF1F2; color: #E11D48; padding: 0.75rem 2rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #FECDD3;">
                        <svg style="width: 16px; height: 16px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        Processo Prioritário
                    </div>
                @endif

                <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: flex-start; flex-shrink: 0;">
                    <div>
                        <span style="display: inline-block; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; background-color: #EEF2FF; border: 1px solid #E0E7FF; color: #4F46E5; margin-bottom: 0.5rem;">
                            {{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}
                        </span>
                        <h2 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin: 0; line-height: 1.3;">{{ $activeProcess->titulo }}</h2>
                        <div style="font-family: monospace; font-size: 0.85rem; color: #64748B; margin-top: 0.25rem;">
                            {{ $activeProcess->numero_processo }}
                        </div>
                    </div>
                    <button wire:click="closeDrawer" style="background: transparent; border: none; color: #64748B; cursor: pointer; padding: 0.25rem; border-radius: 4px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#F1F5F9';" onmouseout="this.style.backgroundColor='transparent';">
                        <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    </button>
                </div>

                <div style="padding: 2rem; overflow-y: auto; flex: 1;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                        <div style="border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px;">
                            <span style="display: block; font-size: 0.65rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">Cliente</span>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #4F46E5;">{{ $activeProcess->cliente->nome ?? 'Não informado' }}</div>
                        </div>
                        <div style="border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px;">
                            <span style="display: block; font-size: 0.65rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">Responsável</span>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #0F172A;">{{ $activeProcess->advogado->name ?? 'Não atribuído' }}</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                        <div style="border: 1px solid #D1FAE5; background-color: #ECFDF5; padding: 1rem; border-radius: 6px;">
                            <span style="display: block; font-size: 0.65rem; font-weight: 600; color: #059669; text-transform: uppercase; margin-bottom: 0.25rem;">Valor da Causa</span>
                            <div style="font-size: 1rem; font-weight: 700; color: #064E3B;">R$ {{ number_format($activeProcess->valor_causa, 2, ',', '.') }}</div>
                        </div>
                        <div style="border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px;">
                            <span style="display: block; font-size: 0.65rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">Tribunal</span>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #0F172A;">{{ $activeProcess->tribunal }}</div>
                        </div>
                        <div style="border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px;">
                            <span style="display: block; font-size: 0.65rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">Vara</span>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #0F172A;">{{ $activeProcess->vara }}</div>
                        </div>
                    </div>

                    <div style="background-color: #F8FAFC; border-radius: 6px; border: 1px solid #E2E8F0; padding: 1rem; margin-bottom: 2rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label style="font-size: 0.75rem; font-weight: 600; color: #0F172A;">Atualizar Fase do Processo</label>
                            @if($activeProcess->data_prazo)
                                <span style="color: #E11D48; font-size: 0.7rem; font-weight: 600; background-color: #FFF1F2; padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid #FECDD3;">
                                    Prazo: {{ $activeProcess->data_prazo->format('d/m/Y') }}
                                </span>
                            @endif
                        </div>
                        <select wire:change="updateStatus($event.target.value)" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem; font-size: 0.875rem; color: #0F172A; outline: none; cursor: pointer; transition: border-color 0.2s;" onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='#CBD5E1'">
                            <option value="{{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}" selected>
                                Atual: {{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}
                            </option>
                            <optgroup label="---------">
                                @foreach($macroFases as $macro => $lista)
                                    <optgroup label="{{ $macro }}">
                                        @foreach($lista as $st) <option value="{{ $st }}">{{ $st }}</option> @endforeach
                                    </optgroup>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        @if($activeProcess->observacoes)
                            <div>
                                <h4 style="font-size: 0.75rem; font-weight: 700; color: #0F172A; text-transform: uppercase; margin-bottom: 0.5rem; border-bottom: 1px solid #E2E8F0; padding-bottom: 0.25rem;">Notas Internas</h4>
                                <div style="font-size: 0.875rem; color: #713F12; font-style: italic; background-color: #FEFCE8; padding: 1rem; border-radius: 6px; border: 1px solid #FEF08A;">
                                    "{{ $activeProcess->observacoes }}"
                                </div>
                            </div>
                        @endif

                        <div>
                            <h4 style="font-size: 0.75rem; font-weight: 700; color: #0F172A; text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px solid #E2E8F0; padding-bottom: 0.25rem;">Histórico</h4>
                            <div style="position: relative; padding-left: 0.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
                                <div style="position: absolute; left: 13px; top: 8px; bottom: 8px; width: 2px; background: #E2E8F0;"></div>

                                @foreach($activeProcess->historico as $hist)
                                    <div style="position: relative; display: flex; gap: 1rem;">
                                        <div style="position: relative; z-index: 10; flex-shrink: 0; width: 12px; height: 12px; border-radius: 50%; background-color: {{ $hist->acao === 'Criação' ? '#10B981' : '#4F46E5' }}; border: 2px solid white; margin-top: 2px;"></div>
                                        <div style="padding-bottom: 0.5rem;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <p style="font-size: 0.85rem; font-weight: 600; color: #0F172A;">{{ $hist->acao }}</p>
                                                <span style="font-size: 0.65rem; color: #64748B;">{{ $hist->created_at->format('d/m/Y H:i') }}</span>
                                            </div>
                                            <p style="font-size: 0.8rem; color: #475569; margin-top: 0.15rem;">{{ $hist->descricao }}</p>
                                            <p style="font-size: 0.65rem; color: #94A3B8; margin-top: 0.25rem; font-weight: 500;">Por: {{ $hist->user->name ?? 'Sistema' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div style="background-color: #F8FAFC; padding: 1rem 2rem; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 0.5rem; flex-shrink: 0;">
                    <button wire:click="editar({{ $activeProcess->id }})" style="padding: 0.65rem 1.5rem; background-color: white; border: 1px solid #CBD5E1; color: #0F172A; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: 0.2s;" onmouseover="this.style.backgroundColor='#F1F5F9';" onmouseout="this.style.backgroundColor='white';">Editar</button>
                    <button onclick="confirm('Tem certeza que deseja excluir este processo?') || event.stopImmediatePropagation()" wire:click="excluir({{ $activeProcess->id }})" style="padding: 0.65rem 1.5rem; background-color: white; border: 1px solid #FECDD3; color: #E11D48; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: 0.2s;" onmouseover="this.style.backgroundColor='#FFF1F2';" onmouseout="this.style.backgroundColor='white';">Excluir</button>
                </div>

            </div>
        </div>
        @endteleport
    @endif
</div>