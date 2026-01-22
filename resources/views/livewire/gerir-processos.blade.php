<?php
use App\Models\Processo;
use App\Models\Cliente;
use App\Models\User;
use function Livewire\Volt\{state, with, usesPagination};

usesPagination();

state([
    'numero_processo' => '',
    'cliente_id' => '',
    'user_id' => '',
    'titulo' => '',
    'tribunal' => '',
    'vara' => '',
    'status' => 'Distribuído',
    'data_prazo' => '',
    'valor_causa' => '',
    'observacoes' => '',
    'search' => '',
    'formId' => 1,
    'isEditing' => false,
    'editingId' => null
]);

$cancelar = function () {
    $this->reset([
        'numero_processo',
        'cliente_id',
        'user_id',
        'titulo',
        'tribunal',
        'vara',
        'status',
        'data_prazo',
        'valor_causa',
        'observacoes',
        'isEditing',
        'editingId'
    ]);
    $this->formId++;
};

$editar = function ($id) {
    $p = Processo::find($id);
    if ($p) {
        $this->isEditing = true;
        $this->editingId = $p->id;
        $this->numero_processo = $p->numero_processo;
        $this->cliente_id = $p->cliente_id;
        $this->user_id = $p->user_id;
        $this->titulo = $p->titulo;
        $this->tribunal = $p->tribunal;
        $this->vara = $p->vara;
        $this->status = $p->status;
        $this->data_prazo = $p->data_prazo ? $p->data_prazo->format('Y-m-d') : '';
        $this->valor_causa = $p->valor_causa;
        $this->observacoes = $p->observacoes;
        $this->formId++;
    }
};

$salvar = function () {
    $this->validate([
        'numero_processo' => 'required|unique:processos,numero_processo,' . ($this->editingId ?? 'NULL'),
        'cliente_id' => 'required',
        'user_id' => 'required',
        'titulo' => 'required|min:5',
    ]);

    $dados = [
        'numero_processo' => $this->numero_processo,
        'cliente_id' => $this->cliente_id,
        'user_id' => $this->user_id,
        'titulo' => $this->titulo,
        'tribunal' => $this->tribunal,
        'vara' => $this->vara,
        'status' => $this->status,
        'data_prazo' => $this->data_prazo ?: null,
        'valor_causa' => $this->valor_causa ?: 0,
        'observacoes' => $this->observacoes,
    ];

    if ($this->isEditing) {
        Processo::find($this->editingId)->update($dados);
        session()->flash('message', 'PROCESSO ATUALIZADO COM SUCESSO!');
    } else {
        Processo::create($dados);
        session()->flash('message', 'PROCESSO REGISTRADO NO SISTEMA!');
    }

    $this->cancelar();
};

$excluir = function ($id) {
    Processo::find($id)?->delete();
    session()->flash('message', 'PROCESSO REMOVIDO DO SISTEMA!');
};

with(fn() => [
    'processos' => Processo::with(['cliente', 'advogado'])
        ->where(function ($query) {
            $query->where('titulo', 'like', "%{$this->search}%")
                ->orWhere('numero_processo', 'like', "%{$this->search}%")
                ->orWhereHas('cliente', function ($q) {
                    $q->where('nome', 'like', "%{$this->search}%");
                });
        })
        ->latest()
        ->paginate(10),

    'listaClientes' => Cliente::orderBy('nome')->get(),
    'listaAdvogados' => User::where('cargo', 'Advogado')->orderBy('name')->get(),
    'listaTribunais' => [
        'STF',
        'STJ',
        'TST',
        'TSE',
        'TJMG',
        'TJSP',
        'TJRJ',
        'TJES',
        'TJBA', // Adicione outros estaduais conforme necessidade
        'TRF1',
        'TRF2',
        'TRF3',
        'TRF4',
        'TRF5',
        'TRF6',
        'TRT3',
        'TRT2',
        'TRT1',
        'JESP' // Juizado Especial
    ],
    'listaVaras' => [
        'Vara Cível',
        '1ª Vara Cível',
        '2ª Vara Cível',
        '3ª Vara Cível',
        'Vara Criminal',
        '1ª Vara Criminal',
        '2ª Vara Criminal',
        'Vara de Família e Sucessões',
        'Vara da Fazenda Pública',
        'Juizado Especial Cível',
        'Juizado Especial Criminal',
        'Juizado Especial da Fazenda',
        'Vara do Trabalho',
        '1ª Vara do Trabalho',
        '2ª Vara do Trabalho',
        'Vara da Infância e Juventude',
        'Vara Única'
    ]
]);
?>

<div class="space-y-8 text-left animate-fadeIn">
    <div class="bg-white rounded-2xl shadow-sm border {{ $isEditing ? 'border-indigo-400 ring-2 ring-indigo-50' : 'border-gray-100' }} transition-all duration-500"
        wire:key="container-form-{{ $formId }}">
        <div class="p-8">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-xl bg-gray-900 text-white shadow-lg shadow-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 tracking-tighter  uppercase">
                            {{ $isEditing ? 'Editar Processo' : 'Novo Processo' }}
                        </h2>
                        <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest">Controle de Processos
                            • L&A Flow
                        </p>
                    </div>
                </div>
                @if($isEditing)
                    <button wire:click="cancelar"
                        class="text-xs font-black text-red-500 uppercase tracking-widest hover:underline">Sair da
                        Edição</button>
                @endif
            </div>

            <form wire:submit.prevent="salvar" class="grid grid-cols-1 md:grid-cols-12 gap-x-6 gap-y-6">
                <div class="md:col-span-12 border-b border-gray-50 pb-2">
                    <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest">01. Dados
                        Principais</span>
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="Nº PROCESSO (CNJ)" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="numero_processo" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold"
                        x-mask="9999999-99.9999.9.99.9999" />
                    <x-input-error :messages="$errors->get('numero_processo')" class="mt-1" />
                </div>

                <div class="md:col-span-8">
                    <x-input-label value="TÍTULO DA AÇÃO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="titulo" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold uppercase" />
                    <x-input-error :messages="$errors->get('titulo')" class="mt-1" />
                </div>

                <div class="md:col-span-6">
                    <x-input-label value="CLIENTE" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <select wire:model="cliente_id"
                        class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold text-xs uppercase">
                        <option value="">SELECIONE O CLIENTE...</option>
                        @foreach($listaClientes as $cli) <option value="{{ $cli->id }}">{{ $cli->nome }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('cliente_id')" class="mt-1" />
                </div>

                <div class="md:col-span-6">
                    <x-input-label value="ADVOGADO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <select wire:model="user_id"
                        class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold text-xs uppercase">
                        <option value="">SELECIONE O ADVOGADO...</option>
                        @foreach($listaAdvogados as $adv) <option value="{{ $adv->id }}">{{ $adv->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-1" />
                </div>

                <div class="md:col-span-12 border-b border-gray-50 pb-2 mt-4">
                    <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest">02. Localização e
                        Status</span>
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="TRIBUNAL" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <select wire:model="tribunal"
                        class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold text-xs uppercase">
                        <option value="">SELECIONE...</option>
                        @foreach($listaTribunais as $tri)
                            <option value="{{ $tri }}">{{ $tri }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('tribunal')" class="mt-1" />
                </div>

                <div class="md:col-span-4">
                    <x-input-label value="VARA / JUÍZO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <select wire:model="vara"
                        class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold text-xs uppercase">
                        <option value="">SELECIONE...</option>
                        @foreach($listaVaras as $v)
                            <option value="{{ $v }}">{{ $v }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('vara')" class="mt-1" />
                </div>

                <div class="md:col-span-4 text-left">
                    <x-input-label value="STATUS" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <select wire:model="status"
                        class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold text-xs uppercase">
                        <optgroup label="🔵 INICIAL">
                            <option value="Distribuído">Distribuído</option>
                            <option value="Petição Inicial">Petição Inicial</option>
                            <option value="Aguardando Citação">Aguardando Citação</option>
                        </optgroup>
                        <optgroup label="🟢 TRAMITAÇÃO">
                            <option value="Em Andamento">Em Andamento</option>
                            <option value="Contestação/Réplica">Contestação/Réplica</option>
                            <option value="Concluso para Decisão">Concluso para Decisão</option>
                            <option value="Instrução">Instrução</option>
                        </optgroup>
                        <optgroup label="🟡 AGENDAMENTOS">
                            <option value="Audiência Designada">Audiência Designada</option>
                            <option value="Aguardando Audiência">Aguardando Audiência</option>
                            <option value="Perícia Designada">Perícia Designada</option>
                            <option value="Apresentação de Laudo">Apresentação de Laudo</option>
                        </optgroup>
                        <optgroup label="🔴 URGÊNCIA">
                            <option value="Prazo em Aberto">Prazo em Aberto</option>
                            <option value="Urgência / Liminar">Urgência / Liminar</option>
                            <option value="Aguardando Protocolo">Aguardando Protocolo</option>
                        </optgroup>
                        <optgroup label="🟣 DECISÃO/EXECUÇÃO">
                            <option value="Sentenciado">Sentenciado</option>
                            <option value="Em Grau de Recurso">Em Grau de Recurso</option>
                            <option value="Cumprimento de Sentença">Cumprimento de Sentença</option>
                            <option value="Acordo/Pagamento">Acordo/Pagamento</option>
                        </optgroup>
                        <optgroup label="⚪ FINALIZADO">
                            <option value="Trânsito em Julgado">Trânsito em Julgado</option>
                            <option value="Suspenso / Sobrestado">Suspenso / Sobrestado</option>
                            <option value="Arquivado">Arquivado</option>
                        </optgroup>
                    </select>
                </div>

                <div class="md:col-span-4 text-left">
                    <x-input-label value="PRÓXIMO PRAZO" class="text-[10px] font-bold text-rose-500 uppercase" />
                    <x-text-input wire:model="data_prazo" type="date"
                        class="w-full mt-1 bg-rose-50 border-none shadow-inner font-bold text-rose-700" />
                </div>

                <div class="md:col-span-4 text-left">
                    <x-input-label value="VALOR CAUSA (R$)" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="valor_causa" type="number" step="0.01"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold" />
                </div>

                <div class="md:col-span-12 text-left">
                    <x-input-label value="OBSERVAÇÕES" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <textarea wire:model="observacoes" rows="2"
                        class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 text-sm font-medium"></textarea>
                </div>

                <div class="md:col-span-12 flex justify-end items-center gap-6 mt-4">
                    @if($isEditing)
                        <button type="button" wire:click="cancelar"
                            class="text-xs font-black text-gray-400 hover:text-gray-600 uppercase tracking-widest">Cancelar</button>
                    @endif
                    <button type="submit"
                        class="bg-gray-900 text-white font-black py-4 px-12 rounded-xl shadow-xl hover:bg-indigo-600 transition-all uppercase text-[10px] tracking-widest">{{ $isEditing ? 'Confirmar Alterações' : 'Finalizar Registro' }}</button>
                </div>
            </form>

            @if (session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                    class="mt-6 flex items-center p-4 border-l-4 border-emerald-500 bg-emerald-50 rounded-xl shadow-sm">
                    <div class="ml-3 font-black text-emerald-800 uppercase tracking-widest text-[10px]">
                        {{ session('message') }}
                    </div>
                </div>
            @endif
        </div>


    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-left">
        <div class="px-8 py-5 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
            <div class="flex items-center gap-3 w-full md:w-1/2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="BUSCAR POR PROCESSO, Nº OU CLIENTE..."
                    class="border-none bg-transparent focus:ring-0 text-xs font-black w-full uppercase tracking-widest text-gray-600 placeholder-gray-300" />
            </div>
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                Total: {{ $processos->total() }}
            </span>
        </div>
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-left">
                        Processo</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">
                        Fase / Prazo</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">
                        Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($processos as $proc)
                    <tr class="hover:bg-gray-50/50 transition" wire:key="row-{{ $proc->id }}-{{ $formId }}">
                        <td class="px-6 py-4 text-left">
                            <div class="font-black text-gray-900 tracking-tighter uppercase">{{ $proc->titulo }}</div>
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                {{ $proc->numero_processo }} • <span
                                    class="text-indigo-600 font-black uppercase">{{ $proc->cliente->nome }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex flex-col items-center gap-1">
                                <span
                                    class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border {{ $proc->cor }}">{{ $proc->status }}</span>
                                @if($proc->data_prazo)<span
                                class="text-[9px] font-black {{ $proc->data_prazo->isPast() ? 'text-rose-500' : 'text-gray-400' }}">{{ $proc->data_prazo->format('d/m/Y') }}</span>@endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right text-left">
                            <div class="flex justify-end items-center gap-6">
                                <button wire:click="editar({{ $proc->id }})"
                                    class="text-indigo-600 font-black text-[10px] uppercase tracking-widest hover:underline">Editar</button>
                                <button onclick="confirm('Excluir processo?') || event.stopImmediatePropagation()"
                                    wire:click="excluir({{ $proc->id }})"
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
        <div class="p-4 bg-gray-50/50">{{ $processos->links() }}</div>
    </div>
</div>