<?php

use App\Models\Processo;
use App\Models\ProcessoHistorico;
use App\Models\Cliente;
use App\Models\User;
use App\Enums\ProcessoStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use function Livewire\Volt\{state, with, usesPagination};

usesPagination();

state([
    // VISÃO E FILTROS
    'viewMode' => 'table', // 'kanban' ou 'table'
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
    
    // NOVO: Estado para a edição rápida de notas
    'drawerObservacoes' => '',
]);

$tribunaisLista = ['TRF1', 'TRF6', 'JEF', 'TJMG', 'TRT3', 'STJ', 'STF'];
$varasLista = ['VARA PREVIDENCIÁRIA', 'JUIZADO ESPECIAL FEDERAL', 'VARA CÍVEL', 'VARA FEDERAL CÍVEL', 'VARA ÚNICA', 'VARA DO TRABALHO'];

$macroFases = [
    'INICIAL' => ['Distribuído', 'Petição Inicial', 'Aguardando Citação'],
    'TRAMITAÇÃO' => ['Em Andamento', 'Concluso para Decisão', 'Instrução', 'Contestação/Réplica'],
    'AGENDAMENTOS' => ['Audiência Designada', 'Aguardando Audiência', 'Perícia Designada', 'Apresentação de Laudo'],
    'URGÊNCIA' => ['Prazo em Aberto', 'Urgência / Liminar', 'Aguardando Protocolo'],
    'DECISÃO' => ['Sentenciado', 'Em Grau de Recurso', 'Cumprimento de Sentença', 'Acordo/Pagamento'],
    'FINALIZADO' => ['Trânsito em Julgado', 'Suspenso / Sobrestado', 'Arquivado'],
];

$toggleView = function ($mode) { $this->viewMode = $mode; };
$mudarFiltro = function ($filtro) { $this->filtroAtivo = $filtro; $this->resetPage(); };
$toggleArquivados = function () { $this->ocultarArquivados = !$this->ocultarArquivados; };

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

$openDrawer = function ($id) {
    $this->activeProcess = Processo::with(['cliente', 'advogado', 'historico' => function($q) {
        $q->with('user')->orderBy('created_at', 'desc');
    }])->find($id);
    
    // Alimenta a variável do bloco de notas rápido com o texto atual do banco
    $this->drawerObservacoes = $this->activeProcess->observacoes;
    $this->drawerOpen = true;

    // Registra no cache que o utilizador atual viu este processo AGORA.
    cache()->put('user_' . Auth::id() . '_viewed_proc_' . $id, now(), now()->addDays(3));
};

$closeDrawer = function () {
    $this->drawerOpen = false;
    $this->activeProcess = null;
};

// Função de Salvamento Automático das Notas
$salvarObservacaoRapida = function () {
    if ($this->activeProcess && $this->drawerObservacoes !== $this->activeProcess->observacoes) {
        
        $this->activeProcess->update(['observacoes' => $this->drawerObservacoes]);
        
        ProcessoHistorico::create([
            'processo_id' => $this->activeProcess->id,
            'user_id' => Auth::id(),
            'acao' => 'Edição Rápida',
            'descricao' => 'As notas/observações internas foram atualizadas no painel.'
        ]);
        
        // Atualiza a visualização do drawer
        $this->openDrawer($this->activeProcess->id);
        
        session()->flash('message', 'NOTAS ATUALIZADAS COM SUCESSO!');
    }
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
                'descricao' => "Status atualizado de '{$statusAntigoString}' para '{$novoStatus}'."
            ]);
            
            $this->openDrawer($this->activeProcess->id);
            session()->flash('message', 'STATUS ATUALIZADO!');
        }
    }
};

$abrirForm = function () {
    $this->reset(['numero_processo', 'cliente_id', 'cliente_nome_search', 'user_id', 'titulo', 'tribunal', 'tribunal_outro', 'vara', 'vara_outro', 'data_prazo', 'valor_causa', 'observacoes', 'isEditing', 'editingId']);
    $this->status = 'Distribuído';
    $this->is_urgent = false;
    $this->showFormModal = true;
};

$cancelarForm = function () {
    $this->showFormModal = false;
    $this->formId++;

    if ($this->isEditing && $this->editingId) {
        $this->openDrawer($this->editingId);
        $this->isEditing = false;
        $this->editingId = null;
    }
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

        $valTribunal = mb_strtoupper(trim($p->tribunal), 'UTF-8');
        if (in_array($valTribunal, $tribunaisLista)) {
            $this->tribunal = $valTribunal;
        } else { 
            $this->tribunal = 'OUTROS'; 
            $this->tribunal_outro = $p->tribunal; 
        }

        $valVara = mb_strtoupper(trim($p->vara), 'UTF-8');
        if (in_array($valVara, $varasLista)) {
            $this->vara = $valVara;
        } else { 
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
    
    $this->validate([
        'numero_processo' => 'required', 
        'cliente_id' => 'required', 
        'titulo' => 'required', 
        'tribunal' => 'required',
        'tribunal_outro' => 'required_if:tribunal,OUTROS',
        'vara' => 'required',
        'vara_outro' => 'required_if:vara,OUTROS'
    ]);

    $dados = [
        'numero_processo' => $this->numero_processo,
        'cliente_id' => $this->cliente_id,
        'user_id' => $this->user_id ?: null,
        'titulo' => $this->titulo,
        'tribunal' => ($this->tribunal === 'OUTROS') ? mb_strtoupper(trim($this->tribunal_outro), 'UTF-8') : $this->tribunal,
        'vara' => ($this->vara === 'OUTROS') ? mb_strtoupper(trim($this->vara_outro), 'UTF-8') : $this->vara,
        'status' => $this->status,
        'data_prazo' => $this->data_prazo ?: null,
        'valor_causa' => (float) $valorLimpo ?: 0,
        'observacoes' => $this->observacoes,
        'is_urgent' => $this->is_urgent ? true : false,
    ];

    if ($this->isEditing) {
        $proc = Processo::find($this->editingId);
        $proc->fill($dados);
        
        if ($proc->isDirty()) {
            $camposAlterados = array_keys($proc->getDirty());
            $dicionario = [
                'titulo' => 'Título', 'numero_processo' => 'CNJ', 'cliente_id' => 'Cliente',
                'user_id' => 'Responsável', 'tribunal' => 'Tribunal', 'vara' => 'Vara',
                'status' => 'Status', 'data_prazo' => 'Data de Prazo', 'valor_causa' => 'Valor da Causa',
                'observacoes' => 'Observações', 'is_urgent' => 'Urgência'
            ];

            $mudancas = [];
            foreach ($camposAlterados as $campo) {
                if (isset($dicionario[$campo])) {
                    $mudancas[] = $dicionario[$campo];
                }
            }

            $proc->save();

            if (count($mudancas) > 0) {
                ProcessoHistorico::create([
                    'processo_id' => $proc->id,
                    'user_id' => Auth::id(),
                    'acao' => 'Edição de Dados',
                    'descricao' => 'Campos atualizados: ' . implode(', ', $mudancas) . '.'
                ]);
            }
            session()->flash('message', 'Processo atualizado com sucesso!');
        }
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

with(function () use ($macroFases, $tribunaisLista, $varasLista) {
    $query = Processo::with(['cliente', 'advogado', 'historico'])
        ->where(function ($q) {
            $q->where('titulo', 'like', "%{$this->search}%")
                ->orWhere('numero_processo', 'like', "%{$this->search}%")
                ->orWhereHas('cliente', fn($q2) => $q2->where('nome', 'like', "%{$this->search}%"))
                ->orWhereHas('advogado', fn($q2) => $q2->where('name', 'like', "%{$this->search}%"));
        })
        ->when($this->filtroAtivo === 'meus', fn($q) => $q->where('user_id', Auth::id()))
        ->when($this->filtroAtivo === 'urgentes', function ($q) {
            $q->where(function ($query) {
                $query->where('is_urgent', true)->orWhereIn('status', ['Urgência / Liminar', 'Prazo em Aberto']);
            });
        })
        ->when($this->filtroAtivo === 'vencidos', fn($q) => $q->where('data_prazo', '<', now()->startOfDay()))
        ->when($this->ocultarArquivados, fn($q) => $q->where('status', '!=', 'Arquivado'));

    $kanbanQuery = clone $query;
    $totalKanban = $kanbanQuery->count();
    $processosKanban = $kanbanQuery->latest('updated_at')->limit(50)->get();

    $kanbanBoard = [];
    foreach (array_keys($macroFases) as $coluna) { $kanbanBoard[$coluna] = []; }

    foreach ($processosKanban as $p) {
        $statusStr = $p->status instanceof \App\Enums\ProcessoStatus ? $p->status->value : $p->status;
        $colocador = 'INICIAL';
        foreach ($macroFases as $macro => $listaStatus) {
            if (in_array($statusStr, $listaStatus)) { $colocador = $macro; break; }
        }
        $kanbanBoard[$colocador][] = $p;
    }

    return [
        'processosPaginados' => $query->latest()->paginate(10),
        'kanbanBoard' => $kanbanBoard,
        'totalKanban' => $totalKanban, 
        'resultadosClientes' => Cliente::where('nome', 'like', "%{$this->cliente_nome_search}%")->limit(5)->get(),
        'listaAdvogados' => User::where('cargo', 'Advogado')->orderBy('name')->get(),
        'tribunaisLista' => $tribunaisLista,
        'varasLista' => $varasLista,
        'macroFases' => $macroFases,
        'userIdAtual' => Auth::id()
    ];
});
?>

<div class="min-h-screen bg-slate-50 p-4 sm:p-6 lg:p-8 font-sans antialiased text-slate-900 w-full relative">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <style>
        .hide-scroll { -ms-overflow-style: none; scrollbar-width: none; }
        .hide-scroll::-webkit-scrollbar { display: none; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #fcd34d; border-radius: 10px; }
    </style>

    {{-- Notificações --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="fixed top-5 right-5 z-[999999] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-xl shadow-lg">
            <svg class="w-5 h-5 text-emerald-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <div class="font-bold text-emerald-800 uppercase tracking-wider text-[11px]">{{ session('message') }}</div>
        </div>
    @endif

    <div x-data="{ show: false, msg: '' }" @notificacao.window="msg = $event.detail.msg; show = true; setTimeout(() => show = false, 3000)"
         x-show="show" style="display: none;"
         class="fixed bottom-5 right-5 z-[999999] bg-slate-900 text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <span class="text-[10px] font-black uppercase tracking-widest" x-text="msg"></span>
    </div>

    {{-- Container Principal --}}
    <div class="bg-white rounded-2xl p-4 sm:p-8 shadow-sm border border-slate-200 w-full overflow-hidden mb-10">
        
        {{-- Cabeçalho da Tela --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end border-b border-slate-100 pb-5 mb-6 gap-5">
            <div class="w-full md:w-auto">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-7 bg-indigo-600 rounded-sm"></div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Processos</h1>
                </div>
                <p class="mt-1 text-sm text-slate-500 pl-3">Gestão e controlo de fases processuais.</p>
            </div>

            <div class="flex flex-col sm:flex-row w-full md:w-auto gap-3 items-center">
                {{-- Busca --}}
                <div class="relative w-full sm:w-64">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar CNJ, Cliente..."
                        class="w-full pl-10 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
                </div>

                {{-- Alternar Visualização --}}
                <div class="flex bg-slate-100 p-1 rounded-lg w-full sm:w-auto justify-center">
                    <button wire:click="toggleView('kanban')" class="flex-1 sm:flex-none px-4 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition {{ $viewMode === 'kanban' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">Board</button>
                    <button wire:click="toggleView('table')" class="flex-1 sm:flex-none px-4 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition {{ $viewMode === 'table' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">Lista</button>
                </div>

                {{-- Botão Novo --}}
                <button wire:click="abrirForm" class="w-full sm:w-auto px-6 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Novo Processo
                </button>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="flex flex-wrap items-center gap-2 mb-8">
            <button wire:click="mudarFiltro('todos')" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition {{ $filtroAtivo === 'todos' ? 'bg-slate-900 border-slate-900 text-white' : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100' }}">Todos</button>
            <button wire:click="mudarFiltro('meus')" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition {{ $filtroAtivo === 'meus' ? 'bg-indigo-50 border-indigo-200 text-indigo-600' : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100' }}">Meus Processos</button>
            <button wire:click="mudarFiltro('urgentes')" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition {{ $filtroAtivo === 'urgentes' ? 'bg-rose-50 border-rose-200 text-rose-600' : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100' }}">Urgentes</button>
            <button wire:click="mudarFiltro('vencidos')" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition {{ $filtroAtivo === 'vencidos' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100' }}">Prazos Vencidos</button>

            <div class="hidden sm:block w-px h-5 bg-slate-200 mx-1"></div>

            <button wire:click="toggleArquivados" class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border transition flex items-center gap-1.5 {{ $ocultarArquivados ? 'bg-slate-100 border-slate-300 text-slate-600' : 'bg-slate-50 border-slate-200 text-slate-500' }}">
                @if($ocultarArquivados)
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                    Ocultar Arquivos
                @else
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    Ver Arquivados
                @endif
            </button>
        </div>

        {{-- ÁREA DE EXIBIÇÃO --}}
        <div>
            @if($viewMode === 'kanban')

                @if($totalKanban > 50)
                    <div class="mb-5 bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-start sm:items-center gap-3 text-amber-700 shadow-sm transition-all">
                        <svg class="w-5 h-5 shrink-0 text-amber-500 mt-0.5 sm:mt-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-xs font-medium leading-relaxed">
                            Mostrando os <strong>50</strong> processos com movimentação mais recente (de <strong>{{ $totalKanban }}</strong> no total). 
                            Para visualizar o acervo completo, utilize a visão em <button wire:click="toggleView('table')" class="font-black underline hover:text-amber-900 transition">Lista</button>.
                        </p>
                    </div>
                @endif

                <div class="hide-scroll flex flex-col xl:flex-row gap-6 xl:gap-4 xl:overflow-x-auto pb-6 items-stretch xl:items-start">
                    @foreach($kanbanBoard as $colunaNome => $processosColuna)
                        @php
                            $corTema = match ($colunaNome) {
                                'INICIAL' => ['border-blue-500', 'bg-blue-50', 'bg-blue-500'],
                                'TRAMITAÇÃO' => ['border-emerald-500', 'bg-emerald-50', 'bg-emerald-500'],
                                'AGENDAMENTOS' => ['border-amber-500', 'bg-amber-50', 'bg-amber-500'],
                                'URGÊNCIA' => ['border-rose-500', 'bg-rose-50', 'bg-rose-500'],
                                'DECISÃO' => ['border-violet-500', 'bg-violet-50', 'bg-violet-500'],
                                'FINALIZADO' => ['border-slate-400', 'bg-slate-100', 'bg-slate-400'],
                                default => ['border-slate-300', 'bg-slate-50', 'bg-slate-400']
                            };
                        @endphp

                        <div class="w-full xl:flex-shrink-0 xl:flex-1 xl:min-w-[180px] xl:max-w-[250px] {{ $corTema[1] }} border border-slate-200 border-t-4 {{ $corTema[0] }} rounded-xl flex flex-col xl:max-h-[70vh] shadow-sm">
                            
                            <div class="px-4 py-3 border-b border-black/5 flex justify-between items-center bg-white/60 rounded-t-lg">
                                <h3 class="text-[10px] font-extrabold text-slate-800 uppercase tracking-widest">{{ $colunaNome }}</h3>
                                <span class="bg-white border border-slate-200 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ count($processosColuna) }}</span>
                            </div>

                            <div data-coluna="{{ $colunaNome }}" class="hide-scroll p-3 flex-1 overflow-y-auto space-y-3 min-h-[120px]" 
                                x-data x-init="Sortable.create($el, { group: 'kanban', animation: 150, ghostClass: 'opacity-50', onEnd: function(evt) { if(evt.to.dataset.coluna !== evt.from.dataset.coluna) { @this.call('moverProcesso', evt.item.dataset.id, evt.to.dataset.coluna); } } })">

                                @forelse($processosColuna as $proc)
                                    @php 
                                        $isVencido = $proc->data_prazo && $proc->data_prazo->startOfDay() < now()->startOfDay();
                                        
                                        $ultimoHist = $proc->historico->sortByDesc('created_at')->first();
                                        $dataLeitura = cache('user_' . $userIdAtual . '_viewed_proc_' . $proc->id);
                                        
                                        $modificadoRecente = $ultimoHist 
                                            && $ultimoHist->user_id !== $userIdAtual 
                                            && $ultimoHist->created_at > now()->subHours(48)
                                            && (!$dataLeitura || \Carbon\Carbon::parse($dataLeitura) < $ultimoHist->created_at);
                                    @endphp

                                    <div data-id="{{ $proc->id }}" class="bg-white rounded-xl p-3.5 relative cursor-grab border border-slate-200 hover:shadow-md transition transform hover:-translate-y-0.5">
                                        <div class="absolute left-0 top-0 bottom-0 w-1.5 rounded-l-xl {{ $proc->is_urgent ? 'bg-rose-500' : $corTema[2] }} opacity-80"></div>
                                        
                                        @if($modificadoRecente)
                                            <span class="absolute -top-1.5 -right-1.5 flex h-3.5 w-3.5" title="Alterado recentemente por {{ $ultimoHist->user->name ?? 'outro usuário' }}">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-amber-500 border-2 border-white items-center justify-center text-[7px] text-white font-bold">!</span>
                                            </span>
                                        @endif

                                        <div class="pl-2.5">
                                            <div class="flex justify-between items-start mb-2">
                                                <span class="text-[9px] font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded truncate max-w-[130px]">
                                                    {{ $proc->status instanceof \App\Enums\ProcessoStatus ? $proc->status->value : $proc->status }}
                                                </span>
                                                <button wire:click.stop="openDrawer({{ $proc->id }})" class="text-slate-400 hover:text-slate-800 hover:bg-slate-100 p-1 rounded transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                </button>
                                            </div>
                                            <h4 class="text-xs font-bold text-slate-800 leading-snug mb-1.5 line-clamp-2" title="{{ $proc->titulo }}">{{ $proc->titulo }}</h4>
                                            <div class="font-mono text-[10px] text-slate-500 mb-3">{{ $proc->numero_processo }}</div>
                                            <div class="flex justify-between items-center border-t border-slate-100 pt-2.5">
                                                <div class="flex items-center gap-1.5 w-full" title="{{ $proc->cliente?->nome }}">
                                                    <div class="w-4 h-4 rounded bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center text-[8px] font-bold shrink-0">{{ mb_substr($proc->cliente?->nome ?? '?', 0, 1) }}</div>
                                                    <span class="text-[10px] font-semibold text-slate-600 truncate max-w-[100px]">{{ $proc->cliente?->nome }}</span>
                                                </div>
                                                @if($proc->data_prazo)
                                                    <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded shrink-0 {{ $isVencido ? 'text-rose-600 bg-rose-50 border border-rose-200' : 'text-slate-500 bg-slate-50' }}">
                                                        {{ $isVencido ? 'VENCIDO' : $proc->data_prazo->format('d/m') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="flex flex-col items-center justify-center py-6 text-center opacity-50">
                                        <svg class="w-5 h-5 text-slate-400 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-slate-500">Arraste para cá</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>

            @else
                
                {{-- VISUALIZAÇÃO MOBILE (CARDS) - Visível apenas em telas pequenas (< 640px) --}}
                <div class="block sm:hidden space-y-4">
                    @forelse($processosPaginados as $proc)
                        @php 
                            $isVencido = $proc->data_prazo && $proc->data_prazo->startOfDay() < now()->startOfDay(); 
                            
                            $ultimoHist = $proc->historico->sortByDesc('created_at')->first();
                            $dataLeitura = cache('user_' . $userIdAtual . '_viewed_proc_' . $proc->id);
                            
                            $modificadoRecente = $ultimoHist 
                                && $ultimoHist->user_id !== $userIdAtual 
                                && $ultimoHist->created_at > now()->subHours(48)
                                && (!$dataLeitura || \Carbon\Carbon::parse($dataLeitura) < $ultimoHist->created_at);
                        @endphp

                        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col relative border-l-4 {{ $proc->is_urgent ? 'border-l-rose-500' : 'border-l-transparent' }}">
                            
                            @if($modificadoRecente)
                                <span class="absolute -top-1.5 -right-1.5 flex h-3.5 w-3.5" title="Alterado recentemente">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-amber-500 border-2 border-white items-center justify-center text-[7px] text-white font-bold">!</span>
                                </span>
                            @endif

                            <div class="mb-3">
                                <h4 class="text-sm font-bold text-slate-900 leading-snug">{{ $proc->titulo }}</h4>
                                <div class="text-[11px] font-mono text-slate-500 mt-1">{{ $proc->numero_processo }}</div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-xs border-t border-slate-100 pt-3 mb-3">
                                <div>
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Cliente</span>
                                    <span class="font-semibold text-indigo-600 truncate block">{{ $proc->cliente?->nome ?? 'Não informado' }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Status</span>
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border border-slate-200 bg-slate-50 text-slate-600 inline-block">
                                        {{ $proc->status instanceof \App\Enums\ProcessoStatus ? $proc->status->value : $proc->status }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex justify-between items-center border-t border-slate-100 pt-3">
                                <div>
                                    @if($proc->data_prazo)
                                        <span class="text-[10px] font-bold uppercase px-2 py-1 rounded {{ $isVencido ? 'text-rose-600 bg-rose-50 border border-rose-200' : 'text-slate-500 bg-slate-50 border border-slate-100' }}">
                                            Prazo: {{ $proc->data_prazo->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-[10px] font-bold text-slate-400 uppercase">S/ Prazo</span>
                                    @endif
                                </div>
                                <button wire:click="openDrawer({{ $proc->id }})" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-indigo-100 transition flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    Ver
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 px-4 text-sm text-slate-500 bg-white rounded-xl border border-slate-200 shadow-sm">
                            Nenhum processo encontrado.
                        </div>
                    @endforelse
                </div>

                {{-- VISUALIZAÇÃO DESKTOP (TABELA) - Visível a partir de 640px --}}
                <div class="hidden sm:block bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                    <th class="px-6 py-4">Processo</th>
                                    <th class="px-6 py-4">Cliente / Local</th>
                                    <th class="px-6 py-4">Responsável</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4">Prazo</th>
                                    <th class="px-6 py-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($processosPaginados as $proc)
                                    @php 
                                        $isVencido = $proc->data_prazo && $proc->data_prazo->startOfDay() < now()->startOfDay(); 
                                        
                                        $ultimoHist = $proc->historico->sortByDesc('created_at')->first();
                                        $dataLeitura = cache('user_' . $userIdAtual . '_viewed_proc_' . $proc->id);
                                        
                                        $modificadoRecente = $ultimoHist 
                                            && $ultimoHist->user_id !== $userIdAtual 
                                            && $ultimoHist->created_at > now()->subHours(48)
                                            && (!$dataLeitura || \Carbon\Carbon::parse($dataLeitura) < $ultimoHist->created_at);
                                    @endphp
                                    <tr class="hover:bg-slate-50 transition border-l-4 {{ $proc->is_urgent ? 'border-l-rose-500' : 'border-l-transparent' }}">
                                        <td class="px-6 py-4 relative">
                                            @if($modificadoRecente)
                                                <span class="absolute left-1 top-4 flex h-2 w-2" title="Alterado recentemente">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                                </span>
                                            @endif
                                            <div class="text-sm font-semibold text-slate-900 max-w-xs truncate pl-2">{{ $proc->titulo }}</div>
                                            <div class="text-[11px] font-mono text-slate-500 mt-0.5 pl-2">{{ $proc->numero_processo }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-xs font-semibold text-slate-900">{{ $proc->cliente?->nome }}</div>
                                            <div class="text-[10px] text-slate-500 mt-0.5 uppercase tracking-wide">{{ $proc->tribunal }} • {{ $proc->vara }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-xs text-slate-600">{{ $proc->advogado->name ?? 'Não atribuído' }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded border border-slate-200 bg-white text-slate-600">
                                                {{ $proc->status instanceof \App\Enums\ProcessoStatus ? $proc->status->value : $proc->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            @if($proc->data_prazo)
                                                <span class="text-[10px] font-bold uppercase px-2.5 py-1 rounded {{ $isVencido ? 'text-rose-600 bg-rose-50 border border-rose-200' : 'text-slate-500 bg-slate-50' }}">
                                                    {{ $proc->data_prazo->format('d/m/Y') }}
                                                </span>
                                            @else
                                                <span class="text-xs text-slate-400 italic">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <button wire:click="openDrawer({{ $proc->id }})" class="px-3 py-1.5 bg-white border border-slate-300 text-indigo-600 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-indigo-50 transition">Ver</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">Nenhum processo encontrado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            @endif

            <div class="px-2 sm:px-6 py-4 mt-4 sm:mt-0 sm:border-t border-slate-200 bg-transparent sm:bg-slate-50 rounded-b-xl">
                {{ $processosPaginados->links() }}
            </div>
        </div>
    </div> 

    {{-- MODAL DE FORMULÁRIO (CRIAR/EDITAR PROCESSO) --}}
    @if($showFormModal)
        <div class="fixed inset-0 z-[99999] flex items-start sm:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" wire:click="cancelarForm"></div>

            <div class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden" wire:key="form-proc-{{ $formId }}">
                
                <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center shrink-0">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">
                            {{ $isEditing ? 'Editar Processo' : 'Cadastrar Novo Processo' }}
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">Preencha as informações do processo abaixo.</p>
                    </div>
                    <button wire:click="cancelarForm" class="text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 p-2 rounded-full transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-8 overflow-y-auto">
                    <form wire:submit.prevent="salvar" class="flex flex-col gap-8">

                        <div>
                            <h3 class="text-[10px] font-extrabold text-indigo-600 uppercase tracking-widest mb-4">Vínculos</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                <div class="sm:col-span-2 relative">
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Vincular Cliente</label>
                                    <input type="text" wire:model.live="cliente_nome_search" wire:input="$set('cliente_id', null)" placeholder="Busque pelo nome..." 
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                    <input type="hidden" wire:model="cliente_id">

                                    @if(strlen($cliente_nome_search) > 2 && $cliente_id == null)
                                        <div class="absolute z-10 w-full bg-white border border-slate-200 rounded-xl mt-1 shadow-lg overflow-hidden">
                                            @foreach($resultadosClientes as $cli)
                                                <div wire:click="selecionarCliente({{ $cli->id }}, '{{ $cli->nome }}')" class="px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0">
                                                    {{ $cli->nome }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @error('cliente_id') <span class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Responsável</label>
                                    <select wire:model="user_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition cursor-pointer">
                                        <option value="">SELECIONE...</option>
                                        @foreach($listaAdvogados as $adv) <option value="{{ $adv->id }}">{{ $adv->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="border-slate-100">

                        <div>
                            <h3 class="text-[10px] font-extrabold text-emerald-600 uppercase tracking-widest mb-4">Detalhes da Ação</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">CNJ (Número)</label>
                                    <input type="text" wire:model="numero_processo" x-mask="9999999-99.9999.9.99.9999" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                    @error('numero_processo') <span class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Título / Ação</label>
                                    <input type="text" wire:model="titulo" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                    @error('titulo') <span class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Tribunal</label>
                                    <select wire:model.live="tribunal" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition cursor-pointer">
                                        <option value="">SELECIONE...</option>
                                        @foreach($tribunaisLista as $tri) <option value="{{ $tri }}">{{ $tri }}</option> @endforeach
                                        <option value="OUTROS">OUTROS...</option>
                                    </select>
                                    @error('tribunal') <span class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                                    
                                    @if($tribunal === 'OUTROS')
                                        <input type="text" wire:model="tribunal_outro" placeholder="Especifique o tribunal..." class="w-full mt-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900 focus:outline-none">
                                        @error('tribunal_outro') <span class="text-rose-500 text-[10px] mt-1.5 block font-semibold">Por favor, especifique o tribunal.</span> @enderror
                                    @endif
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Vara</label>
                                    <select wire:model.live="vara" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition cursor-pointer">
                                        <option value="">SELECIONE...</option>
                                        @foreach($varasLista as $v) <option value="{{ $v }}">{{ $v }}</option> @endforeach
                                        <option value="OUTROS">OUTROS...</option>
                                    </select>
                                    @error('vara') <span class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                                    
                                    @if($vara === 'OUTROS')
                                        <input type="text" wire:model="vara_outro" placeholder="Especifique a vara..." class="w-full mt-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900 focus:outline-none">
                                        @error('vara_outro') <span class="text-rose-500 text-[10px] mt-1.5 block font-semibold">Por favor, especifique a vara.</span> @enderror
                                    @endif
                                </div>
                            </div>
                        </div>

                        <hr class="border-slate-100">

                        <div>
                            <h3 class="text-[10px] font-extrabold text-amber-600 uppercase tracking-widest mb-4">Controlo e Prazos</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Status</label>
                                    <select wire:model="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition cursor-pointer">
                                        @foreach($macroFases as $macro => $lista)
                                            <optgroup label="{{ $macro }}">
                                                @foreach($lista as $st) <option value="{{ $st }}">{{ $st }}</option> @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Data do Prazo</label>
                                    <input type="date" wire:model="data_prazo" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Valor da Causa</label>
                                    <input type="text" wire:model="valor_causa" x-mask:dynamic="$money($input, ',', '.', 2)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition {{ $is_urgent ? 'bg-rose-50/50 border-rose-300 ring-1 ring-rose-300' : '' }}">
                                    <input type="checkbox" wire:model="is_urgent" class="mt-0.5 w-4 h-4 text-rose-600 border-slate-300 rounded focus:ring-rose-500">
                                    <div>
                                        <span class="block text-sm font-bold text-slate-900">Marcar como Processo Urgente</span>
                                        <span class="block text-[10px] text-slate-500 mt-1 leading-snug">Destaca este processo visualmente com uma borda vermelha no painel.</span>
                                    </div>
                                </label>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-2">Observações Internas</label>
                                <textarea wire:model="observacoes" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 resize-y transition"></textarea>
                            </div>
                        </div>

                        <div class="pt-6 mt-2 flex justify-end gap-3 border-t border-slate-100">
                            <button type="button" wire:click="cancelarForm" class="px-6 py-2.5 text-slate-500 rounded-xl text-sm font-bold hover:bg-slate-50 hover:text-slate-800 transition">Cancelar</button>
                            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition">
                                {{ $isEditing ? 'Atualizar Processo' : 'Salvar Processo' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL DE VISUALIZAÇÃO DE PROCESSO (DRAWER SOBREPOSTO) --}}
    @if($drawerOpen && $activeProcess)
        <div class="fixed inset-0 z-[99999] flex items-start sm:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" wire:click="closeDrawer"></div>

            <div class="relative w-full sm:max-w-2xl bg-white sm:rounded-2xl shadow-2xl flex flex-col max-h-[95vh] overflow-hidden transform transition-all {{ $activeProcess->is_urgent ? 'border-t-4 border-rose-500' : 'border-t-4 border-indigo-600' }}">

                @if($activeProcess->is_urgent)
                    <div class="bg-rose-50 text-rose-700 px-6 py-2.5 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest border-b border-rose-100 shrink-0">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        Processo Prioritário
                    </div>
                @endif

                <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-start shrink-0">
                    <div class="pr-4">
                        <span class="inline-block px-2.5 py-1 rounded bg-indigo-50 border border-indigo-100 text-[9px] font-black uppercase tracking-widest text-indigo-600 mb-3">
                            {{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}
                        </span>
                        <h2 class="text-xl font-bold text-slate-900 leading-tight mb-1.5">{{ $activeProcess->titulo }}</h2>
                        <div class="font-mono text-sm text-slate-500">{{ $activeProcess->numero_processo }}</div>
                    </div>
                    <button wire:click="closeDrawer" class="text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 p-2 rounded-full transition shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-8 overflow-y-auto flex-1 space-y-8">
                    
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Cliente</span>
                            <div class="text-sm font-bold text-indigo-600">{{ $activeProcess->cliente->nome ?? 'Não informado' }}</div>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Responsável</span>
                            <div class="text-sm font-bold text-slate-900">{{ $activeProcess->advogado->name ?? 'Não atribuído' }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 pt-6 border-t border-slate-100">
                        <div class="sm:col-span-1 col-span-2">
                            <span class="block text-[10px] font-bold text-emerald-500 uppercase tracking-widest mb-1">Valor da Causa</span>
                            <div class="text-base font-extrabold text-emerald-700">R$ {{ number_format($activeProcess->valor_causa, 2, ',', '.') }}</div>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Tribunal</span>
                            <div class="text-sm font-bold text-slate-900">{{ $activeProcess->tribunal }}</div>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Vara</span>
                            <div class="text-sm font-bold text-slate-900">{{ $activeProcess->vara }}</div>
                        </div>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-6">
                        <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                            <label class="text-xs font-bold text-slate-700 uppercase tracking-widest">Atualizar Fase</label>
                            @if($activeProcess->data_prazo)
                                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 bg-rose-100 px-2.5 py-1 rounded">
                                    Prazo: {{ $activeProcess->data_prazo->format('d/m/Y') }}
                                </span>
                            @endif
                        </div>
                        <select wire:change="updateStatus($event.target.value)" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition cursor-pointer">
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

                    {{-- Bloco de Notas Editável Sincronizado --}}
                    <div>
                        <div class="flex justify-between items-center mb-2 border-b border-slate-100 pb-2">
                            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Notas Internas</h4>
                            <div class="flex items-center">
                                <span wire:loading wire:target="salvarObservacaoRapida" class="text-[9px] text-indigo-500 font-bold uppercase tracking-widest mr-2">Salvando...</span>
                            </div>
                        </div>
                        <textarea 
                            wire:model="drawerObservacoes" 
                            wire:change="salvarObservacaoRapida"
                            rows="4" 
                            placeholder="Adicione notas, lembretes ou links aqui... (Salva automaticamente ao clicar fora)"
                            class="w-full text-sm text-amber-900 bg-amber-50/50 p-4 rounded-xl border border-amber-200 focus:bg-amber-50 focus:border-amber-400 focus:ring-2 focus:ring-amber-200 transition-all resize-y custom-scrollbar break-words"
                        ></textarea>
                        <p class="text-[9px] text-slate-400 mt-1.5 text-right">A edição é salva automaticamente ao clicar fora do campo.</p>
                    </div>

                    <div>
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Histórico do Processo</h4>
                        <div class="relative pl-3 space-y-6">
                            <div class="absolute left-4 top-2 bottom-2 w-px bg-slate-200"></div>

                            @foreach($activeProcess->historico as $hist)
                                <div class="relative flex gap-5">
                                    <div class="relative z-10 w-2.5 h-2.5 rounded-full ring-4 ring-white mt-1.5 shrink-0 {{ $hist->acao === 'Criação' ? 'bg-emerald-500' : 'bg-indigo-500' }}"></div>
                                    <div class="pb-1 w-full">
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                                            <p class="text-sm font-bold text-slate-900">{{ $hist->acao }}</p>
                                            <span class="text-[10px] text-slate-400 font-medium">{{ $hist->created_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $hist->descricao }}</p>
                                        <p class="text-[9px] text-slate-400 mt-2 font-bold uppercase tracking-widest">Por: {{ $hist->user->name ?? 'Sistema' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 px-8 py-5 border-t border-slate-100 flex justify-end gap-3 shrink-0">
                    <button wire:click="editar({{ $activeProcess->id }})" class="px-6 py-2.5 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-xl text-sm font-bold transition">Editar Tudo</button>
                    <button onclick="confirm('Tem certeza que deseja excluir este processo?') || event.stopImmediatePropagation()" wire:click="excluir({{ $activeProcess->id }})" class="px-6 py-2.5 text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl text-sm font-bold transition">Excluir</button>
                </div>

            </div>
        </div>
    @endif
</div>