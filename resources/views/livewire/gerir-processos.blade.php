<?php
use App\Models\Processo;
use App\Models\ProcessoHistorico; // Importante importar
use App\Models\Cliente;
use App\Models\User;
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
    'filtroAtivo' => 'todos' // Novo estado para os filtros
]);

$tribunaisLista = ['TRF1', 'TRF6', 'JEF', 'TJMG', 'TRT3', 'STJ', 'STF'];
$varasLista = ['VARA PREVIDENCIÁRIA', 'JUIZADO ESPECIAL FEDERAL', 'VARA CÍVEL', 'VARA FEDERAL CÍVEL', 'VARA ÚNICA', 'VARA DO TRABALHO'];

// --- FUNÇÕES DO MODAL & HISTÓRICO ---
$openDrawer = function ($id) {
    // Carrega também o histórico agora
    $this->activeProcess = Processo::with(['cliente', 'advogado', 'historico.user'])->find($id);
    $this->drawerOpen = true;
};

$closeDrawer = function () {
    $this->drawerOpen = false;
    $this->activeProcess = null;
};

$updateStatus = function ($novoStatus) {
    if ($this->activeProcess && $this->activeProcess->status !== $novoStatus) {
        $statusAntigo = $this->activeProcess->status;

        // Atualiza Status
        $this->activeProcess->update(['status' => $novoStatus]);

        // Grava no Histórico
        ProcessoHistorico::create([
            'processo_id' => $this->activeProcess->id,
            'user_id' => Auth::id(), // Pega o usuário logado
            'acao' => 'Alteração de Fase',
            'descricao' => "Alterou de '{$statusAntigo}' para '{$novoStatus}'"
        ]);

        $this->activeProcess->refresh();
        session()->flash('message', 'STATUS ATUALIZADO E REGISTRADO NO HISTÓRICO!');
    }
};

$mudarFiltro = function ($filtro) {
    $this->filtroAtivo = $filtro;
    $this->resetPage(); // Volta para página 1 ao filtrar
};
// ------------------------------------

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

        $this->status = $p->status;
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
        // Cria registro inicial no histórico
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
                ->orWhereHas('cliente', fn($q) => $q->where('nome', 'like', "%{$this->search}%"));
        })
        // LÓGICA DOS FILTROS
        ->when($this->filtroAtivo === 'meus', fn($q) => $q->where('user_id', Auth::id()))
        ->when($this->filtroAtivo === 'urgentes', fn($q) => $q->whereIn('status', ['Urgência / Liminar', 'Prazo em Aberto', 'Audiência Designada']))
        ->when($this->filtroAtivo === 'vencidos', fn($q) => $q->where('data_prazo', '<', now()))
        ->latest()->paginate(10),
    'resultadosClientes' => Cliente::where('nome', 'like', "%{$this->cliente_nome_search}%")->limit(5)->get(),
    'listaAdvogados' => User::where('cargo', 'Advogado')->orderBy('name')->get(),
    'tribunais' => $tribunaisLista,
    'varas' => $varasLista
]);
?>

<div class="space-y-8 text-left animate-fadeIn font-sans relative">

    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="fixed top-5 right-5 z-[80] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-r-xl shadow-2xl animate-bounce-in">
            <div class="text-emerald-500 mr-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg></div>
            <div class="font-black text-emerald-800 uppercase tracking-widest text-[10px]">{{ session('message') }}</div>
        </div>
    @endif

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
                        </optgroup>
                        <optgroup label="🟡 AGENDAMENTOS">
                            <option>Audiência Designada</option>
                            <option>Perícia Designada</option>
                        </optgroup>
                        <optgroup label="🔴 URGÊNCIA">
                            <option>Prazo em Aberto</option>
                            <option>Urgência / Liminar</option>
                        </optgroup>
                        <optgroup label="🟣 DECISÃO">
                            <option>Sentenciado</option>
                            <option>Acordo/Pagamento</option>
                        </optgroup>
                        <optgroup label="⚪ FINALIZADO">
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

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-left mt-8">

        <div class="px-8 py-5 border-b border-gray-50">
            <div class="flex flex-wrap gap-2 mb-4">
                <button wire:click="mudarFiltro('todos')"
                    class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border transition {{ $filtroAtivo === 'todos' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-500 border-gray-200 hover:border-gray-400' }}">Todos</button>
                <button wire:click="mudarFiltro('meus')"
                    class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border transition {{ $filtroAtivo === 'meus' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-200 hover:border-indigo-300' }}">Meus
                    Processos</button>
                <button wire:click="mudarFiltro('urgentes')"
                    class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border transition {{ $filtroAtivo === 'urgentes' ? 'bg-rose-500 text-white border-rose-500' : 'bg-white text-gray-500 border-gray-200 hover:border-rose-300' }}">Urgentes</button>
                <button wire:click="mudarFiltro('vencidos')"
                    class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border transition {{ $filtroAtivo === 'vencidos' ? 'bg-rose-500 text-white border-orange-500' : 'bg-white text-gray-500 border-gray-200 hover:border-orange-300' }}">Prazos
                    Vencidos</button>
            </div>

            <div class="flex items-center justify-between bg-gray-50/50 p-2 rounded-xl">
                <div class="flex items-center gap-3 w-full md:w-1/2 px-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2"></path>
                    </svg>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="BUSCAR..."
                        class="border-none bg-transparent focus:ring-0 text-xs font-black w-full uppercase tracking-widest text-gray-600 placeholder-gray-300" />
                </div>
                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-2">Total:
                    {{ $processos->total() }}</span>
            </div>
        </div>

        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                <tr>
                    <th class="px-6 py-4 text-left">Processo / Cliente</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($processos as $proc)
                    <tr class="hover:bg-gray-50 transition border-l-4 border-transparent hover:border-indigo-500 cursor-pointer"
                        wire:click="openDrawer({{ $proc->id }})">
                        <td class="px-6 py-4 text-left">
                            <div class="font-black text-gray-900 tracking-tighter uppercase">{{ $proc->titulo }}</div>
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                {{ $proc->numero_processo }} • <span
                                    class="text-indigo-600">{{ $proc->cliente?->nome }}</span></div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $statusColor = match (true) {
                                    in_array($proc->status, ['Prazo em Aberto', 'Urgência / Liminar']) => 'bg-rose-100 text-rose-700 border-rose-200',
                                    in_array($proc->status, ['Audiência Designada', 'Perícia Designada']) => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                    in_array($proc->status, ['Em Andamento', 'Instrução']) => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    in_array($proc->status, ['Sentenciado', 'Acordo/Pagamento']) => 'bg-purple-100 text-purple-700 border-purple-200',
                                    default => 'bg-blue-100 text-blue-700 border-blue-200'
                                };
                            @endphp
                            <span
                                class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border {{ $statusColor }}">{{ $proc->status }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button
                                class="text-indigo-600 font-black text-[10px] uppercase tracking-widest hover:underline">ABRIR</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 bg-gray-50/50">{{ $processos->links() }}</div>
    </div>

    @if($drawerOpen && $activeProcess)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            style="background-color: rgba(0, 0, 0, 0.8);">
            <div class="absolute inset-0" wire:click="closeDrawer"></div>

            <div
                class="bg-white w-full max-w-md max-h-[90vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col relative animate-scale-up z-[110]">

                <div class="bg-gray-900 px-6 py-5 flex justify-between items-center shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="bg-indigo-600 text-white px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest">Processo
                                Ativo</span>
                            <span
                                class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">#{{ $activeProcess->id }}</span>
                        </div>
                        <h2 class="text-lg font-black text-white uppercase tracking-tight truncate max-w-sm mt-1">
                            {{ $activeProcess->titulo }}</h2>
                    </div>
                    <button wire:click="closeDrawer"
                        class="text-gray-400 hover:text-white transition bg-white/10 p-2 rounded-full hover:bg-white/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                            </path>
                        </svg>
                    </button>
                </div>

                <div class="p-6 bg-white overflow-y-auto flex-1">

                    <div class="grid grid-cols-2 gap-4 mb-5 pb-5 border-b border-gray-100">
                        <div class="flex flex-col gap-1">
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Cliente</span>
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-[10px] font-bold">
                                    {{ substr($activeProcess->cliente->nome, 0, 1) }}</div>
                                <span class="text-xs font-bold text-gray-900 uppercase truncate"
                                    title="{{ $activeProcess->cliente->nome }}">{{ $activeProcess->cliente->nome }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Advogado</span>
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-6 h-6 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-[10px] font-bold">
                                    {{ substr($activeProcess->advogado->name, 0, 1) }}</div>
                                <span class="text-xs font-bold text-gray-900 uppercase truncate"
                                    title="{{ $activeProcess->advogado->name }}">{{ $activeProcess->advogado->name }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6 bg-indigo-50 p-4 rounded-xl border border-indigo-100">
                        <label class="block text-[10px] font-black text-indigo-900 uppercase tracking-widest mb-2">Atualizar
                            Status</label>
                        <select wire:change="updateStatus($event.target.value)"
                            class="w-full rounded-lg border-indigo-200 text-xs font-bold text-gray-900 uppercase focus:ring-indigo-600 focus:border-indigo-600 py-2.5 bg-white shadow-sm cursor-pointer">
                            <option value="{{ $activeProcess->status }}" selected>ATUAL: {{ $activeProcess->status }}
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
                            </optgroup>
                            <optgroup label="🟡 AGENDAMENTOS">
                                <option>Audiência Designada</option>
                                <option>Perícia Designada</option>
                            </optgroup>
                            <optgroup label="🔴 URGÊNCIA">
                                <option>Prazo em Aberto</option>
                                <option>Urgência / Liminar</option>
                            </optgroup>
                            <optgroup label="🟣 DECISÃO">
                                <option>Sentenciado</option>
                                <option>Acordo/Pagamento</option>
                            </optgroup>
                            <optgroup label="⚪ FINALIZADO">
                                <option>Arquivado</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs mb-5">
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="block text-[9px] font-bold text-gray-400 uppercase">Tribunal</span>
                            <span class="font-black text-gray-800">{{ $activeProcess->tribunal }}</span>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="block text-[9px] font-bold text-gray-400 uppercase">Vara</span>
                            <span class="font-black text-gray-800 truncate max-w-[150px]">{{ $activeProcess->vara }}</span>
                        </div>
                        <div
                            class="col-span-2 p-3 bg-emerald-50 rounded-lg border border-emerald-100 flex justify-between items-center">
                            <span class="block text-[9px] font-bold text-emerald-600 uppercase">Valor da Causa</span>
                            <span class="font-black text-emerald-700 text-sm">R$
                                {{ number_format($activeProcess->valor_causa, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($activeProcess->data_prazo)
                        <div class="mb-5 p-3 bg-rose-50 border border-rose-100 rounded-lg flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="bg-rose-100 p-1.5 rounded text-rose-600"><svg class="w-4 h-4" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                        </path>
                                    </svg></div>
                                <div>
                                    <span class="block text-[9px] font-bold text-rose-400 uppercase">Vencimento</span>
                                    <span
                                        class="text-xs font-black text-rose-600">{{ $activeProcess->data_prazo->format('d/m/Y') }}</span>
                                </div>
                            </div>
                            <span
                                class="text-[9px] font-bold text-rose-500 bg-white px-2 py-1 rounded border border-rose-200 uppercase">{{ $activeProcess->data_prazo->diffForHumans() }}</span>
                        </div>
                    @endif

                    <div class="border-t border-gray-100 pt-4">
                        <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-2">Observações
                            Internas</span>
                        @if($activeProcess->observacoes)
                            <div
                                class="p-3 bg-yellow-50 border border-yellow-100 rounded-lg text-xs font-medium text-yellow-800 italic leading-relaxed">
                                "{{ $activeProcess->observacoes }}"
                            </div>
                        @else
                            <div
                                class="p-3 bg-gray-50 border border-gray-100 rounded-lg text-xs font-medium text-gray-400 italic text-center">
                                Nenhuma observação.</div>
                        @endif
                    </div>

                    <div class="border-t border-gray-100 pt-6 mt-6">
                        <h4 class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-4">Histórico de
                            Movimentações</h4>
                        <div class="space-y-4">
                            @foreach($activeProcess->historico as $hist)
                                <div class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                                        @if(!$loop->last)
                                        <div class="w-0.5 h-full bg-gray-100 mt-1"></div> @endif
                                    </div>
                                    <div class="pb-2">
                                        <p class="text-[10px] font-bold text-gray-900 uppercase">{{ $hist->acao }}</p>
                                        <p class="text-[9px] text-gray-500">{{ $hist->descricao }}</p>
                                        <p class="text-[8px] font-bold text-gray-400 mt-1">
                                            {{ $hist->created_at->format('d/m/Y H:i') }} • {{ $hist->user->name }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex gap-3 shrink-0">
                    <button wire:click="editar({{ $activeProcess->id }})"
                        class="flex-1 py-2.5 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 uppercase hover:bg-gray-100 transition shadow-sm">Editar
                        Completo</button>
                    <button onclick="confirm('Tem certeza?') || event.stopImmediatePropagation()"
                        wire:click="excluir({{ $activeProcess->id }})"
                        class="flex-1 py-2.5 bg-rose-50 border border-rose-200 rounded-lg text-xs font-bold text-rose-600 uppercase hover:bg-rose-100 transition shadow-sm">Excluir</button>
                </div>
            </div>
        </div>
    @endif

</div>