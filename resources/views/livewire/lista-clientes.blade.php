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
    'activeProcess' => null
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
        'estado' => $this->estado
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

$openProcessoDetalhe = function ($id) {
    $this->activeProcess = Processo::with(['cliente', 'advogado', 'historico.user'])->find($id);
    $this->showProcessoDetalheModal = true;
};

$closeProcessoDetalhe = function () {
    $this->showProcessoDetalheModal = false;
    $this->activeProcess = null;
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
                'descricao' => "Alterou de '{$statusAntigoString}' para '{$novoStatus}'"
            ]);
            $this->activeProcess->refresh();

            $cliente = Cliente::with('processos')->find($this->activeProcess->cliente_id);
            if ($cliente) {
                $this->processosDoCliente = $cliente->processos;
            }

            session()->flash('message', 'Status do processo atualizado!');
        }
    }
};

with(function () use ($macroFases) {
    $clientes = Cliente::with('processos')
        ->where('nome', 'like', "%{$this->search}%")
        ->orWhere('cpf_cnpj', 'like', "%{$this->search}%")
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

<div class="w-full px-4 sm:px-6 lg:px-8 pb-8 pt-2 font-sans antialiased text-slate-900">

    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="fixed top-5 right-5 z-[999999] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-md shadow-lg">
            <svg class="w-5 h-5 text-emerald-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <div class="font-bold text-emerald-800 uppercase tracking-wider text-[11px]">{{ session('message') }}</div>
        </div>
    @endif

    <div
        style="background-color: white; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); border: 1px solid #E2E8F0;">

        <div
            style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #E2E8F0; padding-bottom: 1.25rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 4px; height: 28px; background-color: #4F46E5; border-radius: 2px;"></div>
                    <h1
                        style="font-size: 1.75rem; font-weight: 700; color: #0F172A; margin: 0; letter-spacing: -0.025em;">
                        Clientes</h1>
                </div>
                <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #64748B; padding-left: 1rem;">Gestão de
                    carteira e informações de contato.</p>
            </div>

            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; background-color: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 0.75rem; height: 38px; width: 260px; transition: border-color 0.2s;"
                    onfocusin="this.style.borderColor='#4F46E5'" onfocusout="this.style.borderColor='#CBD5E1'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        style="color: #94A3B8;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar cliente..."
                        style="border: none; background: transparent; width: 100%; font-size: 0.875rem; color: #334155; outline: none; box-shadow: none; padding-left: 0.5rem;" />
                </div>

                <button wire:click="abrirModal"
                    style="background-color: #0F172A; color: white; height: 38px; padding: 0 1.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);"
                    onmouseover="this.style.backgroundColor='#1E293B'"
                    onmouseout="this.style.backgroundColor='#0F172A'">
                    + Novo Cliente
                </button>
            </div>
        </div>

        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, max-content)); gap: 1rem; margin-bottom: 2rem;">

            <div
                style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-left: 3px solid #4F46E5; border-radius: 8px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
                <div
                    style="width: 40px; height: 40px; border-radius: 6px; background-color: #EEF2FF; border: 1px solid #E0E7FF; color: #4F46E5; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 005.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                </div>
                <div>
                    <p
                        style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.15rem;">
                        Total de Clientes</p>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">
                        {{ $totalClientes }}</h3>
                </div>
            </div>

            <div
                style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-left: 3px solid #10B981; border-radius: 8px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
                <div
                    style="width: 40px; height: 40px; border-radius: 6px; background-color: #ECFDF5; border: 1px solid #D1FAE5; color: #10B981; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                </div>
                <div>
                    <p
                        style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.15rem;">
                        Cadastros Este Mês</p>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin: 0; line-height: 1;">
                        {{ $clientesMes }}</h3>
                </div>
            </div>
        </div>

        <div style="background: white; border-radius: 8px; border: 1px solid #E2E8F0; overflow: hidden;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid #E2E8F0; background: #F8FAFC;">
                            <th
                                style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">
                                Nome do Cliente</th>
                            <th
                                style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">
                                Documento</th>
                            <th
                                style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">
                                Contato / Local</th>
                            <th
                                style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">
                                Processos</th>
                            <th
                                style="padding: 1rem 1.5rem; font-size: 0.65rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">
                                Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientes as $cliente)
                            <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.2s;"
                                onmouseover="this.style.backgroundColor='#F8FAFC';"
                                onmouseout="this.style.backgroundColor='transparent';">

                                <td style="padding: 1rem 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div
                                            style="width: 32px; height: 32px; border-radius: 6px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; color: #475569; background: white;">
                                            {{ mb_substr($cliente->nome, 0, 1) }}
                                        </div>
                                        <div>
                                            <div style="font-size: 0.875rem; font-weight: 600; color: #0F172A;">
                                                {{ $cliente->nome }}</div>
                                            <div style="font-size: 0.65rem; color: #64748B; margin-top: 0.15rem;">ID:
                                                #{{ str_pad($cliente->id, 4, '0', STR_PAD_LEFT) }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td style="padding: 1rem 1.5rem;">
                                    <span
                                        style="font-family: monospace; font-size: 0.75rem; color: #334155;">{{ $cliente->cpf_cnpj }}</span>
                                </td>

                                <td style="padding: 1rem 1.5rem;">
                                    <div style="font-size: 0.75rem; color: #0F172A;">
                                        {{ $cliente->telefone ?: 'Sem Telefone' }}</div>
                                    @if($cliente->email)
                                        <div style="font-size: 0.65rem; color: #64748B; margin-top: 0.15rem;">
                                            {{ strtolower($cliente->email) }}</div>
                                    @endif
                                    @if($cliente->cidade)
                                        <div
                                            style="font-size: 0.60rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; margin-top: 0.15rem;">
                                            📍 {{ $cliente->cidade }}/{{ $cliente->estado }}</div>
                                    @endif
                                </td>

                                <td style="padding: 1rem 1.5rem;">
                                    @php $qntProcessos = $cliente->processos ? $cliente->processos->count() : 0; @endphp
                                    @if($qntProcessos > 0)
                                        <button wire:click="verProcessos({{ $cliente->id }})"
                                            style="background-color: #EEF2FF; border: 1px solid #C7D2FE; color: #4F46E5; font-size: 0.65rem; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; transition: background-color 0.2s;"
                                            onmouseover="this.style.backgroundColor='#E0E7FF';"
                                            onmouseout="this.style.backgroundColor='#EEF2FF';">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                </path>
                                            </svg>
                                            {{ $qntProcessos }} Processo{{ $qntProcessos > 1 ? 's' : '' }}
                                        </button>
                                    @else
                                        <span style="font-size: 0.65rem; color: #94A3B8; font-style: italic;">Nenhum
                                            Processo</span>
                                    @endif
                                </td>

                                <td style="padding: 1rem 1.5rem; text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                                        <button wire:click="editar({{ $cliente->id }})"
                                            style="background: white; border: 1px solid #E2E8F0; color: #475569; padding: 0.35rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;"
                                            onmouseover="this.style.backgroundColor='#EEF2FF'; this.style.borderColor='#C7D2FE'; this.style.color='#4F46E5';"
                                            onmouseout="this.style.backgroundColor='white'; this.style.borderColor='#E2E8F0'; this.style.color='#475569';"
                                            title="Editar Cliente">
                                            <svg width="14" height="14" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                        <button
                                            onclick="confirm('Tem certeza que deseja excluir este cliente permanentemente?') || event.stopImmediatePropagation()"
                                            wire:click="excluir({{ $cliente->id }})"
                                            style="background: white; border: 1px solid #E2E8F0; color: #E11D48; padding: 0.35rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;"
                                            onmouseover="this.style.backgroundColor='#FFF1F2'; this.style.borderColor='#FECDD3';"
                                            onmouseout="this.style.backgroundColor='white'; this.style.borderColor='#E2E8F0'; this.style.color='#E11D48';"
                                            title="Excluir Cliente">
                                            <svg width="14" height="14" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding: 4rem 2rem; text-align: center;">
                                    <div style="font-size: 0.75rem; color: #94A3B8;">Nenhum cliente encontrado.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="padding: 1rem 1.5rem; border-top: 1px solid #E2E8F0; background: #F8FAFC;">
                {{ $clientes->links() }}
            </div>
        </div>

    </div> @if($showModal)
        @teleport('body')
        <div
            style="position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center;">
            <div wire:click="fecharModal"
                style="position: absolute; inset: 0; background-color: rgba(15, 23, 42, 0.7); backdrop-filter: blur(2px);">
            </div>

            <div style="position: relative; width: 100%; max-width: 700px; background: white; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); padding: 2rem; max-height: 90vh; overflow-y: auto; margin: 1rem;"
                wire:key="form-cli-{{ $formId }}">

                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #E2E8F0; padding-bottom: 1rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin: 0;">
                        {{ $isEditing ? 'Editar Cliente' : 'Cadastrar Novo Cliente' }}
                    </h2>
                    <button wire:click="fecharModal"
                        style="background: transparent; border: none; color: #64748B; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0.25rem; border-radius: 4px; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#F1F5F9';"
                        onmouseout="this.style.backgroundColor='transparent';">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="salvar" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 2; min-width: 250px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Nome
                                Completo / Razão Social</label>
                            <input type="text" wire:model="nome"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                            @error('nome') <span
                                style="color: #E11D48; font-size: 0.65rem; margin-top: 0.25rem; display: block;">{{ $message }}</span>
                            @enderror
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">CPF
                                / CNPJ</label>
                            <input type="text" wire:model="cpf_cnpj"
                                x-mask:dynamic="$input.length > 14 ? '99.999.999/9999-99' : '999.999.999-99'"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                            @error('cpf_cnpj') <span
                                style="color: #E11D48; font-size: 0.65rem; margin-top: 0.25rem; display: block;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 250px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">E-mail</label>
                            <input type="email" wire:model="email"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                        </div>
                        <div style="flex: 1; min-width: 250px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Telefone
                                / WhatsApp</label>
                            <input type="text" wire:model="telefone" x-mask="(99) 99999-9999"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                            @error('telefone') <span
                                style="color: #E11D48; font-size: 0.65rem; margin-top: 0.25rem; display: block;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 120px; max-width: 150px;">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <label style="font-size: 0.7rem; font-weight: 600; color: #475569;">CEP</label>
                                <span wire:loading wire:target="buscarCep"
                                    style="font-size: 0.6rem; color: #4F46E5;">Buscando...</span>
                            </div>
                            <input type="text" wire:model.blur="cep" wire:change="buscarCep" x-mask="99999-999"
                                style="width: 100%; border: 1px solid #C7D2FE; background-color: #EEF2FF; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #4F46E5; outline: none;">
                        </div>
                        <div style="flex: 3; min-width: 250px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Logradouro</label>
                            <input type="text" wire:model="logradouro"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                        </div>
                        <div style="flex: 1; min-width: 80px; max-width: 100px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Número</label>
                            <input type="text" wire:model="numero"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 2; min-width: 200px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Bairro</label>
                            <input type="text" wire:model="bairro"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                        </div>
                        <div style="flex: 2; min-width: 200px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Cidade</label>
                            <input type="text" wire:model="cidade"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                        </div>
                        <div style="flex: 1; min-width: 60px; max-width: 80px;">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">UF</label>
                            <input type="text" wire:model="estado" x-mask="aa"
                                style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; text-transform: uppercase; text-align: center; outline: none; transition: 0.2s;"
                                onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                        <button type="button" wire:click="fecharModal"
                            style="background: white; border: 1px solid #CBD5E1; color: #475569; padding: 0.65rem 1.5rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; margin-right: 0.5rem; cursor: pointer; transition: background-color 0.2s;"
                            onmouseover="this.style.backgroundColor='#F1F5F9';"
                            onmouseout="this.style.backgroundColor='white';">Cancelar</button>
                        <button type="submit"
                            style="background-color: #0F172A; color: white; padding: 0.65rem 2rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; border: none; cursor: pointer; transition: background-color 0.2s;"
                            onmouseover="this.style.backgroundColor='#1E293B';"
                            onmouseout="this.style.backgroundColor='#0F172A'">{{ $isEditing ? 'Atualizar' : 'Salvar' }}</button>
                    </div>
                </form>
            </div>
        </div>
        @endteleport
    @endif

    @if($showProcessosModal)
        @teleport('body')
        <div
            style="position: fixed; inset: 0; z-index: 99998; display: flex; align-items: center; justify-content: center;">
            <div wire:click="fecharProcessosModal"
                style="position: absolute; inset: 0; background-color: rgba(15, 23, 42, 0.7); backdrop-filter: blur(2px);">
            </div>

            <div x-data="{ buscaModal: '' }"
                style="position: relative; width: 100%; max-width: 600px; background: white; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); padding: 2rem; max-height: 90vh; overflow-y: hidden; display: flex; flex-direction: column; margin: 1rem;">

                <div
                    style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #E2E8F0; padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin: 0;">Processos Vinculados
                        </h2>
                        <p style="font-size: 0.75rem; color: #64748B; margin-top: 0.25rem;">Cliente: <strong
                                style="color: #4F46E5;">{{ $clienteSelecionadoNome }}</strong></p>
                    </div>
                    <button wire:click="fecharProcessosModal"
                        style="background: transparent; border: none; color: #64748B; cursor: pointer; padding: 0.25rem; border-radius: 4px; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#F1F5F9';"
                        onmouseout="this.style.backgroundColor='transparent';">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <div style="margin-bottom: 1rem;">
                    <input x-model="buscaModal" type="text" placeholder="Filtrar processos..."
                        style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem 0.75rem; font-size: 0.875rem; color: #0F172A; outline: none; transition: 0.2s;"
                        onfocus="this.style.borderColor='#4F46E5';" onblur="this.style.borderColor='#CBD5E1';">
                </div>

                <div
                    style="display: flex; flex-direction: column; gap: 0.5rem; overflow-y: auto; padding-right: 0.5rem; flex: 1;">
                    @forelse($processosDoCliente as $proc)
                        <div x-data="{ textoBusca: '{{ strtolower(($proc->numero_processo ?? '') . ' ' . ($proc->acao ?? '') . ' ' . ($proc->titulo ?? '')) }}' }"
                            x-show="buscaModal === '' || textoBusca.includes(buscaModal.toLowerCase())"
                            style="border: 1px solid #E2E8F0; border-left: 3px solid #4F46E5; border-radius: 6px; background: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">

                            <div>
                                <div style="font-size: 0.875rem; font-weight: 600; color: #0F172A; margin-bottom: 0.15rem;">
                                    {{ $proc->titulo ?? 'Processo sem título' }}</div>
                                <div style="font-family: monospace; font-size: 0.75rem; color: #64748B;">
                                    {{ $proc->numero_processo ?? 'Número não cadastrado' }}</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span
                                    style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; background-color: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.2rem 0.5rem; border-radius: 4px; color: #475569;">{{ $proc->status instanceof \App\Enums\ProcessoStatus ? $proc->status->value : $proc->status }}</span>

                                <button wire:click="openProcessoDetalhe({{ $proc->id }})"
                                    style="background-color: white; border: 1px solid #CBD5E1; color: #0EA5E9; padding: 0.4rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;"
                                    onmouseover="this.style.backgroundColor='#F0F9FF'; this.style.borderColor='#BAE6FD'; this.style.color='#0284C7';"
                                    onmouseout="this.style.backgroundColor='white'; this.style.borderColor='#CBD5E1'; this.style.color='#0EA5E9';"
                                    title="Abrir Detalhes">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div
                            style="text-align: center; padding: 2rem 0; color: #64748B; font-size: 0.875rem; font-style: italic;">
                            Nenhum processo vinculado.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        @endteleport
    @endif

    @if($showProcessoDetalheModal && $activeProcess)
        @teleport('body')
        <div
            style="position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center;">
            <div style="position: absolute; inset: 0; background-color: rgba(17, 24, 39, 0.85); backdrop-filter: blur(2px);"
                wire:click="closeProcessoDetalhe"></div>

            <div
                style="position: relative; width: 100%; max-width: 700px; background: white; border-radius: 8px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; margin: 1rem; max-height: 95vh; display: flex; flex-direction: column; border-top: {{ $activeProcess->is_urgent ? '4px solid #E11D48' : '4px solid #4F46E5' }};">

                @if($activeProcess->is_urgent)
                    <div
                        style="background-color: #FFF1F2; color: #E11D48; padding: 0.75rem 2rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #FECDD3;">
                        <svg style="width: 16px; height: 16px;" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"></path>
                        </svg>
                        Processo Prioritário
                    </div>
                @endif

                <div
                    style="padding: 1.5rem 2rem; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: flex-start; flex-shrink: 0;">
                    <div>
                        <span
                            style="display: inline-block; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; background-color: #EEF2FF; border: 1px solid #E0E7FF; color: #4F46E5; margin-bottom: 0.5rem;">
                            {{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}
                        </span>
                        <h2 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin: 0; line-height: 1.3;">
                            {{ $activeProcess->titulo }}</h2>
                        <div style="font-family: monospace; font-size: 0.85rem; color: #64748B; margin-top: 0.25rem;">
                            {{ $activeProcess->numero_processo }}
                        </div>
                    </div>
                    <button wire:click="closeProcessoDetalhe"
                        style="background: transparent; border: none; color: #64748B; cursor: pointer; padding: 0.25rem; border-radius: 4px; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#F1F5F9';"
                        onmouseout="this.style.backgroundColor='transparent';">
                        <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                            </path>
                        </svg>
                    </button>
                </div>

                <div style="padding: 2rem; overflow-y: auto; flex: 1;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                        <div style="border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px;">
                            <span
                                style="display: block; font-size: 0.65rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">Cliente</span>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #4F46E5;">
                                {{ $activeProcess->cliente->nome ?? 'Não informado' }}</div>
                        </div>
                        <div style="border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px;">
                            <span
                                style="display: block; font-size: 0.65rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">Responsável</span>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #0F172A;">
                                {{ $activeProcess->advogado->name ?? 'Não atribuído' }}</div>
                        </div>
                    </div>

                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                        <div
                            style="border: 1px solid #D1FAE5; background-color: #ECFDF5; padding: 1rem; border-radius: 6px;">
                            <span
                                style="display: block; font-size: 0.65rem; font-weight: 600; color: #059669; text-transform: uppercase; margin-bottom: 0.25rem;">Valor
                                da Causa</span>
                            <div style="font-size: 1rem; font-weight: 700; color: #064E3B;">R$
                                {{ number_format($activeProcess->valor_causa, 2, ',', '.') }}</div>
                        </div>
                        <div style="border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px;">
                            <span
                                style="display: block; font-size: 0.65rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">Tribunal</span>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #0F172A;">
                                {{ $activeProcess->tribunal }}</div>
                        </div>
                        <div style="border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px;">
                            <span
                                style="display: block; font-size: 0.65rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">Vara</span>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #0F172A;">{{ $activeProcess->vara }}
                            </div>
                        </div>
                    </div>

                    <div
                        style="background-color: #F8FAFC; border-radius: 6px; border: 1px solid #E2E8F0; padding: 1rem; margin-bottom: 2rem;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label style="font-size: 0.75rem; font-weight: 600; color: #0F172A;">Atualizar Fase do
                                Processo</label>
                            @if($activeProcess->data_prazo)
                                <span
                                    style="color: #E11D48; font-size: 0.7rem; font-weight: 600; background-color: #FFF1F2; padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid #FECDD3;">
                                    Prazo: {{ $activeProcess->data_prazo->format('d/m/Y') }}
                                </span>
                            @endif
                        </div>
                        <select wire:change="updateStatusProcesso($event.target.value)"
                            style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0.65rem; font-size: 0.875rem; color: #0F172A; outline: none; cursor: pointer; transition: border-color 0.2s;"
                            onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='#CBD5E1'">
                            <option
                                value="{{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}"
                                selected>
                                Atual:
                                {{ $activeProcess->status instanceof \App\Enums\ProcessoStatus ? $activeProcess->status->value : $activeProcess->status }}
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
                                <h4
                                    style="font-size: 0.75rem; font-weight: 700; color: #0F172A; text-transform: uppercase; margin-bottom: 0.5rem; border-bottom: 1px solid #E2E8F0; padding-bottom: 0.25rem;">
                                    Notas Internas</h4>
                                <div
                                    style="font-size: 0.875rem; color: #475569; font-style: italic; background-color: #FEFCE8; padding: 1rem; border-radius: 6px; border: 1px solid #FEF08A;">
                                    "{{ $activeProcess->observacoes }}"
                                </div>
                            </div>
                        @endif

                        <div>
                            <h4
                                style="font-size: 0.75rem; font-weight: 700; color: #0F172A; text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px solid #E2E8F0; padding-bottom: 0.25rem;">
                                Histórico</h4>
                            <div
                                style="position: relative; padding-left: 0.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
                                <div
                                    style="position: absolute; left: 13px; top: 8px; bottom: 8px; width: 2px; background: #E2E8F0;">
                                </div>

                                @foreach($activeProcess->historico as $hist)
                                    <div style="position: relative; display: flex; gap: 1rem;">
                                        <div
                                            style="position: relative; z-index: 10; flex-shrink: 0; width: 12px; height: 12px; border-radius: 50%; background-color: {{ $hist->acao === 'Criação' ? '#10B981' : '#4F46E5' }}; border: 2px solid white; margin-top: 2px;">
                                        </div>
                                        <div style="padding-bottom: 0.5rem;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <p style="font-size: 0.85rem; font-weight: 600; color: #0F172A;">
                                                    {{ $hist->acao }}</p>
                                                <span
                                                    style="font-size: 0.65rem; color: #64748B;">{{ $hist->created_at->format('d/m/Y H:i') }}</span>
                                            </div>
                                            <p style="font-size: 0.8rem; color: #475569; margin-top: 0.15rem;">
                                                {{ $hist->descricao }}</p>
                                            <p
                                                style="font-size: 0.65rem; color: #94A3B8; margin-top: 0.25rem; font-weight: 500;">
                                                Por: {{ $hist->user->name ?? 'Sistema' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    style="background-color: #F8FAFC; padding: 1rem 2rem; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; flex-shrink: 0;">
                    <button wire:click="closeProcessoDetalhe"
                        style="padding: 0.65rem 2rem; background-color: white; border: 1px solid #CBD5E1; color: #0F172A; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#F1F5F9';"
                        onmouseout="this.style.backgroundColor='white';">
                        Fechar Detalhes
                    </button>
                </div>

            </div>
        </div>
        @endteleport
    @endif

</div>