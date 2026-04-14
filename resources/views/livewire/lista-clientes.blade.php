<?php
use App\Models\Cliente;
use App\Models\Processo;
use App\Models\ProcessoHistorico;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
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
    'isEditing' => false,

    // Controle do Modal de Cadastro de Cliente
    'showModal' => false,

    // Controle do Modal de Lista de Processos do Cliente
    'showProcessosModal' => false,
    'clienteSelecionadoNome' => '',
    'processosDoCliente' => [],

    // Controle do Modal de Detalhes do Processo
    'showProcessoDetalheModal' => false,
    'activeProcess' => null,
    'drawerObservacoes' => '', // NOVO: Para edição rápida de notas pelo perfil do cliente
]);

$macroFases = [
    'INICIAL' => ['Distribuído', 'Petição Inicial', 'Aguardando Citação'],
    'TRAMITAÇÃO' => ['Em Andamento', 'Concluso para Decisão', 'Instrução', 'Contestação/Réplica'],
    'AGENDAMENTOS' => ['Audiência Designada', 'Aguardando Audiência', 'Perícia Designada', 'Apresentação de Laudo'],
    'URGÊNCIA' => ['Prazo em Aberto', 'Urgência / Liminar', 'Aguardando Protocolo'],
    'DECISÃO' => ['Sentenciado', 'Em Grau de Recurso', 'Cumprimento de Sentença', 'Acordo/Pagamento'],
    'FINALIZADO' => ['Trânsito em Julgado', 'Suspenso / Sobrestado', 'Arquivado'],
];

$abrirModal = function () {
    $this->reset(['nome', 'cpf_cnpj', 'telefone', 'email', 'cep', 'logradouro', 'numero', 'bairro', 'cidade', 'estado', 'editingId', 'isEditing']);
    $this->showModal = true;
};

$fecharModal = function () {
    $this->showModal = false;
    $this->formId++;
};

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
        $this->showModal = true;
        $this->formId++;
    }
};

$salvar = function () {
    $this->validate([
        'nome' => 'required|min:3',
        'cpf_cnpj' => 'required|unique:clientes,cpf_cnpj,' . ($this->editingId ?? 'NULL'),
        'telefone' => 'required',
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
        'estado' => strtoupper($this->estado) // Força UF maiúscula
    ];

    if ($this->isEditing) {
        Cliente::find($this->editingId)->update($dados);
        session()->flash('message', 'Cliente atualizado com sucesso!');
    } else {
        Cliente::create($dados);
        session()->flash('message', 'Cliente cadastrado com sucesso!');
    }

    $this->fecharModal();
};

$excluir = function ($id) {
    Cliente::find($id)?->delete();
    session()->flash('message', 'Cliente removido com sucesso.');
};

$verProcessos = function ($id) {
    $cliente = Cliente::with('processos')->find($id);
    if ($cliente) {
        $this->clienteSelecionadoNome = $cliente->nome;
        $this->processosDoCliente = $cliente->processos;
        $this->showProcessosModal = true;
    }
};

$fecharProcessosModal = function () {
    $this->showProcessosModal = false;
};

// --- FUNÇÕES SINCRONIZADAS COM A TELA DE PROCESSOS ---

$openProcessoDetalhe = function ($id) {
    $this->activeProcess = Processo::with([
        'cliente',
        'advogado',
        'historico' => function ($q) {
            $q->with('user')->orderBy('created_at', 'desc');
        }
    ])->find($id);

    $this->drawerObservacoes = $this->activeProcess->observacoes;
    $this->showProcessoDetalheModal = true;
};

$closeProcessoDetalhe = function () {
    $this->showProcessoDetalheModal = false;
    $this->activeProcess = null;
};

$salvarObservacaoRapida = function () {
    if ($this->activeProcess && $this->drawerObservacoes !== $this->activeProcess->observacoes) {
        $this->activeProcess->update(['observacoes' => $this->drawerObservacoes]);
        ProcessoHistorico::create([
            'processo_id' => $this->activeProcess->id,
            'user_id' => Auth::id(),
            'acao' => 'Edição Rápida',
            'descricao' => 'As notas/observações internas foram atualizadas via perfil do cliente.'
        ]);
        $this->openProcessoDetalhe($this->activeProcess->id);
        session()->flash('message', 'NOTAS ATUALIZADAS!');
    }
};

$updateStatusProcesso = function ($novoStatus) {
    if ($this->activeProcess) {
        $statusAtualEnum = $this->activeProcess->status;
        $statusAntigoString = $statusAtualEnum instanceof \App\Enums\ProcessoStatus ? $statusAtualEnum->value : $statusAtualEnum;

        if ($statusAntigoString !== $novoStatus) {
            $this->activeProcess->update(['status' => $novoStatus]);
            ProcessoHistorico::create([
                'processo_id' => $this->activeProcess->id,
                'user_id' => Auth::id(),
                'acao' => 'Alteração de Fase',
                'descricao' => "Alterou de '{$statusAntigoString}' para '{$novoStatus}' via perfil do cliente."
            ]);

            $this->activeProcess->refresh();
            $cliente = Cliente::with('processos')->find($this->activeProcess->cliente_id);
            if ($cliente) {
                $this->processosDoCliente = $cliente->processos;
            }
            $this->openProcessoDetalhe($this->activeProcess->id);
            session()->flash('message', 'Status do processo atualizado!');
        }
    }
};

with(function () use ($macroFases) {
    $clientes = Cliente::with('processos')
        ->where('nome', 'like', "%{$this->search}%")
        ->orWhere('cpf_cnpj', 'like', "%{$this->search}%")
        ->orWhere('telefone', 'like', "%{$this->search}%")
        ->latest()
        ->paginate(10);

    return [
        'clientes' => $clientes,
        'totalClientes' => Cliente::count(),
        'clientesMes' => Cliente::whereMonth('created_at', now()->month)->count(),
        'macroFases' => $macroFases
    ];
});
?>

<div class="min-h-screen bg-slate-50 p-4 sm:p-6 lg:p-8 font-sans antialiased text-slate-900 w-full relative">

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #fcd34d; border-radius: 10px; }
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

        {{-- Cabeçalho da Tela --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end border-b border-slate-100 pb-5 mb-6 gap-5">
            <div class="w-full md:w-auto">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-7 bg-indigo-600 rounded-sm"></div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Base de Clientes</h1>
                </div>
                <p class="mt-1 text-sm text-slate-500 pl-3">Gestão centralizada de contatos e carteira.</p>
            </div>

            <div class="flex flex-col sm:flex-row w-full md:w-auto gap-3 items-center">
                {{-- Busca --}}
                <div class="relative w-full sm:w-64">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por nome ou CPF..."
                        class="w-full pl-10 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
                </div>

                {{-- Botão Novo --}}
                <button wire:click="abrirModal" class="w-full sm:w-auto px-6 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-2 shrink-0 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Novo Cliente
                </button>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <div class="bg-white border border-slate-200 border-l-4 border-l-indigo-600 rounded-xl p-5 flex items-center gap-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 005.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">Total de Clientes</p>
                    <h3 class="text-2xl font-black text-slate-800 leading-none">{{ $totalClientes }}</h3>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-emerald-500 rounded-xl p-5 flex items-center gap-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">Cadastros Este Mês</p>
                    <h3 class="text-2xl font-black text-slate-800 leading-none">{{ $clientesMes }}</h3>
                </div>
            </div>
        </div>

        {{-- VISUALIZAÇÃO MOBILE (CARDS) --}}
        <div class="block sm:hidden space-y-4">
            @forelse($clientes as $cliente)
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col gap-4 relative">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-center font-bold text-base text-slate-600 shrink-0">
                                {{ mb_substr($cliente->nome, 0, 1) }}
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-900 leading-tight">{{ $cliente->nome }}</div>
                                <div class="text-[10px] text-slate-500 mt-0.5 font-mono">ID: #{{ str_pad($cliente->id, 4, '0', STR_PAD_LEFT) }}</div>
                            </div>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button wire:click="editar({{ $cliente->id }})" class="p-2 text-slate-400 hover:text-indigo-600 bg-slate-50 hover:bg-indigo-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </button>
                            <button onclick="confirm('Tem certeza que deseja excluir este cliente permanentemente?') || event.stopImmediatePropagation()" wire:click="excluir({{ $cliente->id }})" class="p-2 text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-rose-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm border-t border-slate-100 pt-3">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Documento</span>
                            <span class="font-mono text-slate-700 text-xs">{{ $cliente->cpf_cnpj }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Telefone</span>
                            <div class="flex items-center gap-1.5">
                                <span class="text-slate-700 text-xs">{{ $cliente->telefone ?: 'Não inf.' }}</span>
                                @php $zap = preg_replace('/[^0-9]/', '', $cliente->telefone); @endphp
                                @if(strlen($zap) >= 10)
                                    <a href="https://wa.me/55{{ $zap }}" target="_blank" class="text-[#25D366] hover:text-[#1ebd57] transition" title="Chamar no WhatsApp">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($cliente->email || $cliente->cidade)
                        <div class="bg-slate-50 p-3 rounded-lg text-xs text-slate-600">
                            @if($cliente->email) <div class="truncate mb-1">{{ strtolower($cliente->email) }}</div> @endif
                            @if($cliente->cidade) <div class="font-bold text-slate-500 uppercase text-[10px] tracking-wide">📍 {{ $cliente->cidade }}/{{ $cliente->estado }}</div> @endif
                        </div>
                    @endif

                    <div>
                        @php $qntProcessos = $cliente->processos ? $cliente->processos->count() : 0; @endphp
                        @if($qntProcessos > 0)
                            <button wire:click="verProcessos({{ $cliente->id }})" class="w-full justify-center py-2.5 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-lg text-xs font-bold flex items-center gap-2 hover:bg-indigo-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Ver {{ $qntProcessos }} Processo{{ $qntProcessos > 1 ? 's' : '' }}
                            </button>
                        @else
                            <span class="text-[11px] text-slate-400 italic block text-center py-2 bg-slate-50 rounded-lg border border-slate-100">Nenhum Processo</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-10 px-4 text-sm text-slate-500 bg-white rounded-xl border border-slate-200 shadow-sm">
                    Nenhum cliente encontrado.
                </div>
            @endforelse
        </div>

        {{-- VISUALIZAÇÃO DESKTOP (TABELA) --}}
        <div class="hidden sm:block bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Nome do Cliente</th>
                        <th class="px-6 py-4">Documento</th>
                        <th class="px-6 py-4">Contato / Local</th>
                        <th class="px-6 py-4 text-center">Processos</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($clientes as $cliente)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg border border-slate-200 bg-white flex items-center justify-center font-bold text-sm text-slate-600 shrink-0 shadow-sm">
                                        {{ mb_substr($cliente->nome, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-slate-900">{{ $cliente->nome }}</div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 font-mono">ID: #{{ str_pad($cliente->id, 4, '0', STR_PAD_LEFT) }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span class="font-mono text-xs text-slate-700">{{ $cliente->cpf_cnpj }}</span>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-slate-900">{{ $cliente->telefone ?: 'Sem Telefone' }}</span>

                                    {{-- Botão Direto do WhatsApp --}}
                                    @php $zap = preg_replace('/[^0-9]/', '', $cliente->telefone); @endphp
                                    @if(strlen($zap) >= 10)
                                        <a href="https://wa.me/55{{ $zap }}" target="_blank" class="text-[#25D366] hover:text-[#1ebd57] transition" title="Abrir WhatsApp">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        </a>
                                    @endif
                                </div>

                                @if($cliente->email)
                                    <div class="text-[11px] text-slate-500 mt-0.5">{{ strtolower($cliente->email) }}</div>
                                @endif
                                @if($cliente->cidade)
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mt-1">
                                        📍 {{ $cliente->cidade }}/{{ $cliente->estado }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-center">
                                @php $qntProcessos = $cliente->processos ? $cliente->processos->count() : 0; @endphp
                                @if($qntProcessos > 0)
                                    <button wire:click="verProcessos({{ $cliente->id }})" class="px-3 py-1.5 inline-flex items-center gap-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-md text-[11px] font-bold hover:bg-indigo-100 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Ver {{ $qntProcessos }}
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400 italic">0 Processos</span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="editar({{ $cliente->id }})" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-md transition" title="Editar Cliente">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button onclick="confirm('Tem certeza que deseja excluir este cliente permanentemente?') || event.stopImmediatePropagation()" wire:click="excluir({{ $cliente->id }})" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition" title="Excluir Cliente">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">
                                Nenhum cliente encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginação --}}
        <div class="px-2 md:px-6 py-4 mt-4 md:mt-0 md:border-t border-slate-200 bg-transparent md:bg-slate-50 rounded-b-xl">
            {{ $clientes->links() }}
        </div>
    </div>

    {{-- MODAL DE CADASTRO/EDIÇÃO DE CLIENTE --}}
    @if($showModal)
        @teleport('body')
        <div class="fixed inset-0 z-[99999] flex items-start md:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="fecharModal"></div>

            <div class="relative w-full max-w-3xl bg-white rounded-xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden" wire:key="form-cli-{{ $formId }}">

                <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-center shrink-0 bg-slate-50/50">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">
                            {{ $isEditing ? 'Editar Cliente' : 'Cadastrar Novo Cliente' }}
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">Preencha as informações do cliente abaixo.</p>
                    </div>
                    <button wire:click="fecharModal" class="text-slate-400 hover:text-slate-700 hover:bg-slate-200 p-2 rounded-lg transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <form wire:submit.prevent="salvar" class="space-y-6">

                        <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-5">
                            <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider mb-2 border-b border-slate-100 pb-2">Informações Principais</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nome Completo / Razão Social</label>
                                    <input type="text" wire:model="nome" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                    @error('nome') <span class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">CPF / CNPJ</label>
                                    <input type="text" wire:model="cpf_cnpj" x-mask:dynamic="$input.length > 14 ? '99.999.999/9999-99' : '999.999.999-99'" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                    @error('cpf_cnpj') <span class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">E-mail</label>
                                    <input type="email" wire:model="email" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Telefone / WhatsApp</label>
                                    <input type="text" wire:model="telefone" x-mask="(99) 99999-9999" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                    @error('telefone') <span class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-5">
                            <h3 class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-2 border-b border-slate-100 pb-2">Endereço</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
                                <div class="sm:col-span-1">
                                    <div class="flex justify-between items-center mb-1.5">
                                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">CEP</label>
                                        <span wire:loading wire:target="buscarCep" class="text-[10px] text-indigo-600 font-bold bg-indigo-50 px-2 rounded">Buscando...</span>
                                    </div>
                                    <input type="text" wire:model.blur="cep" wire:change="buscarCep" x-mask="99999-999" class="w-full bg-indigo-50/50 border border-indigo-200 text-indigo-900 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Logradouro</label>
                                    <input type="text" wire:model="logradouro" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Número</label>
                                    <input type="text" wire:model="numero" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-5 gap-5">
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Bairro</label>
                                    <input type="text" wire:model="bairro" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Cidade</label>
                                    <input type="text" wire:model="cidade" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">UF</label>
                                    <input type="text" wire:model="estado" x-mask="aa" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm text-slate-800 text-center uppercase focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 flex justify-end gap-3 shrink-0 mt-6 border-t border-slate-100">
                            <button type="button" wire:click="fecharModal" class="px-6 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-bold hover:bg-slate-50 transition">Cancelar</button>
                            <button type="submit" class="px-8 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition">
                                {{ $isEditing ? 'Atualizar Cadastro' : 'Salvar Cadastro' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- MODAL DE LISTA DE PROCESSOS DO CLIENTE --}}
    @if($showProcessosModal)
        @teleport('body')
        <div class="fixed inset-0 z-[99998] flex items-start md:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="fecharProcessosModal"></div>

            <div x-data="{ buscaModal: '' }" class="relative w-full max-w-2xl bg-white rounded-xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">

                <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-start shrink-0 bg-slate-50/50">
                    <div class="pr-4">
                        <h2 class="text-xl font-bold text-slate-900 leading-tight mb-1">Processos Vinculados</h2>
                        <div class="text-sm text-slate-500">Cliente: <span class="font-bold text-indigo-600">{{ $clienteSelecionadoNome }}</span></div>
                    </div>
                    <button wire:click="fecharProcessosModal" class="text-slate-400 hover:text-slate-700 hover:bg-slate-200 p-1.5 rounded-lg transition shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    </button>
                </div>

                <div class="px-6 py-4 border-b border-slate-100 bg-white shrink-0">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input x-model="buscaModal" type="text" placeholder="Filtrar processos deste cliente..." class="w-full pl-10 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                    </div>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-3 bg-slate-50">
                    @forelse($processosDoCliente as $proc)
                        <div x-data="{ textoBusca: '{{ strtolower(($proc->numero_processo ?? '') . ' ' . ($proc->acao ?? '') . ' ' . ($proc->titulo ?? '')) }}' }"
                             x-show="buscaModal === '' || textoBusca.includes(buscaModal.toLowerCase())"
                             class="bg-white border border-slate-200 border-l-4 border-l-indigo-500 rounded-lg p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shadow-sm hover:shadow-md transition">

                            <div class="flex-1">
                                <div class="text-sm font-bold text-slate-900 mb-0.5">{{ $proc->titulo ?? 'Processo sem título' }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $proc->numero_processo ?? 'Número não cadastrado' }}</div>
                            </div>

                            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                                <span class="text-[10px] font-bold uppercase tracking-wider bg-slate-100 border border-slate-200 text-slate-600 px-2 py-1 rounded">
                                    {{ $proc->status instanceof \App\Enums\ProcessoStatus ? $proc->status->value : $proc->status }}
                                </span>

                                <button wire:click="openProcessoDetalhe({{ $proc->id }})" class="p-2 bg-white border border-slate-200 text-indigo-600 rounded-md hover:bg-indigo-50 hover:border-indigo-300 transition" title="Abrir Detalhes">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-sm text-slate-500 italic">
                            Nenhum processo vinculado.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- MODAL DE VISUALIZAÇÃO DE PROCESSO (SINCRONIZADO COM A TELA DE PROCESSOS) --}}
    @if($showProcessoDetalheModal && $activeProcess)
        @teleport('body')
        <div class="fixed inset-0 z-[99999] flex items-start sm:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" wire:click="closeProcessoDetalhe"></div>

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
                    <button wire:click="closeProcessoDetalhe" class="text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 p-2 rounded-full transition shrink-0">
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
                        <select wire:change="updateStatusProcesso($event.target.value)" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition cursor-pointer">
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
                            placeholder="Adicione notas, lembretes ou links aqui... (Salva automaticamente ao sair do campo)"
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
                    <button wire:click="closeProcessoDetalhe" class="px-6 py-2.5 bg-white border border-slate-300 text-slate-800 rounded-xl text-sm font-bold hover:bg-slate-100 transition">
                        Fechar Detalhes
                    </button>
                </div>

            </div>
        </div>
        @endteleport
    @endif

</div>