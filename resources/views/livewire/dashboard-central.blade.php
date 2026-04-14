<?php

use App\Models\Processo;
use App\Models\Agenda;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public $filtroAdvogado = 'todos';
    public $filtroTipo = 'todos';
    public $filtroPeriodo = 'proximos_7_dias';

    // Controle dos Modais
    public $showAgendaModal = false;
    public $showPautaModal = false;
    public $showVisualizacaoModal = false;
    public $showExcluirModal = false; // NOVO MODAL DE EXCLUSÃO

    public $pautaGeradaTexto = '';
    public $isEditing = false;
    public $editingId = null;
    public $eventoVisualizacao = null;

    // Formulário
    public $agenda_titulo = '';
    public $agenda_tipo = 'Audiência';
    public $agenda_subtipo = '';
    public $agenda_link_reuniao = '';
    public $agenda_data_hora_inicio = '';
    public $agenda_data_hora_fim = '';
    public $agenda_user_id = '';
    public $agenda_observacoes = '';

    // Busca Inteligente de Processos
    public $agenda_processo_id = '';
    public $processo_busca = '';
    public $resultadosProcessos = [];

    public function updatedFiltroAdvogado()
    {
        $this->dispatch('atualiza-calendario', ['eventos' => $this->buscarEventos()]);
    }
    public function updatedFiltroTipo()
    {
        $this->dispatch('atualiza-calendario', ['eventos' => $this->buscarEventos()]);
    }
    public function updatedFiltroPeriodo()
    {
    }

    public function updatedProcessoBusca($value)
    {
        $this->agenda_processo_id = null;

        if (strlen($value) > 2) {
            $this->resultadosProcessos = Processo::with('cliente')
                ->where('titulo', 'like', "%{$value}%")
                ->orWhere('numero_processo', 'like', "%{$value}%")
                ->orWhereHas('cliente', fn($q) => $q->where('nome', 'like', "%{$value}%"))
                ->limit(5)
                ->get();
        } else {
            $this->resultadosProcessos = [];
        }
    }

    public function selecionarProcesso($id)
    {
        $this->agenda_processo_id = $id;

        $proc = Processo::with('cliente')->find($id);
        if ($proc) {
            $clienteNome = $proc->cliente ? $proc->cliente->nome : 'Sem Cliente';
            $this->processo_busca = $clienteNome . ' - ' . $proc->titulo;
        }

        $this->resultadosProcessos = [];
    }

    public function limparProcesso()
    {
        $this->agenda_processo_id = null;
        $this->processo_busca = '';
        $this->resultadosProcessos = [];
    }

    public function buscarEventos()
    {
        $advId = ($this->filtroAdvogado === 'todos') ? null : $this->filtroAdvogado;
        $tipo = ($this->filtroTipo === 'todos') ? null : $this->filtroTipo;

        return Agenda::with(['processo.cliente', 'user'])
            ->when($advId, fn($q) => $q->where('user_id', $advId))
            ->when($tipo, fn($q) => $q->where('tipo', $tipo))
            ->get()
            ->map(function ($a) use ($advId) {
                $prefixoAdvogado = ($advId === null && $a->user) ? '[' . explode(' ', $a->user->name)[0] . '] ' : '';

                return [
                    'id' => $a->id,
                    'title' => $prefixoAdvogado . $a->titulo,
                    'start' => $a->data_hora_inicio->format('Y-m-d\TH:i:s'),
                    'end' => $a->data_hora_fim ? $a->data_hora_fim->format('Y-m-d\TH:i:s') : null,
                    'className' => 'evt-' . strtolower(preg_replace('/[áàãâä]/ui', 'a', preg_replace('/[éèêë]/ui', 'e', preg_replace('/[íìîï]/ui', 'i', preg_replace('/[óòõôö]/ui', 'o', preg_replace('/[úùûü]/ui', 'u', preg_replace('/[ç]/ui', 'c', $a->tipo))))))),
                    'allDay' => false,
                    'extendedProps' => [
                        'tipo' => $a->tipo,
                        'subtipo' => $a->subtipo,
                        'cliente' => $a->processo && $a->processo->cliente ? $a->processo->cliente->nome : null,
                        'advogado' => $a->user ? $a->user->name : 'Não Atribuído'
                    ]
                ];
            })->toArray();
    }

    public function visualizarEvento($id)
    {
        $evento = Agenda::with(['processo.cliente', 'user'])->find($id);
        if ($evento) {
            $this->eventoVisualizacao = $evento;
            $this->showVisualizacaoModal = true;
        }
    }

    public function fecharVisualizacao()
    {
        $this->showVisualizacaoModal = false;
        $this->eventoVisualizacao = null;
    }

    public function abrirEdicaoPelaVisualizacao()
    {
        $id = $this->eventoVisualizacao->id;
        $this->fecharVisualizacao();
        $this->editarEvento($id);
    }

    public function abrirModalExclusao()
    {
        $this->showExcluirModal = true;
    }

    public function fecharModalExclusao()
    {
        $this->showExcluirModal = false;
    }

    public function confirmarExclusaoEvento()
    {
        if ($this->eventoVisualizacao) {
            $this->eventoVisualizacao->delete();
            $this->fecharModalExclusao();
            $this->fecharVisualizacao();
            session()->flash('message', 'Compromisso excluído!');
            $this->dispatch('atualiza-calendario', ['eventos' => $this->buscarEventos()]);
        }
    }

    public function abrirModalAgenda($dataPreSelecionada = null)
    {
        $this->reset(['agenda_titulo', 'agenda_observacoes', 'editingId', 'agenda_subtipo', 'agenda_link_reuniao']);
        $this->limparProcesso();
        $this->isEditing = false;
        $this->agenda_tipo = 'Audiência';

        $horaBase = $dataPreSelecionada
            ? Carbon::parse($dataPreSelecionada)->setTime(now()->addHour()->hour, 0)
            : now()->addHour()->startOfHour();

        $this->agenda_data_hora_inicio = $horaBase->format('Y-m-d\TH:i');
        $this->agenda_data_hora_fim = $horaBase->copy()->addHours(1)->format('Y-m-d\TH:i');

        if (Auth::user()->cargo === 'Advogado') {
            $this->agenda_user_id = Auth::id();
        } else {
            $this->agenda_user_id = ($this->filtroAdvogado !== 'todos') ? $this->filtroAdvogado : '';
        }

        $this->showAgendaModal = true;
    }

    public function editarEvento($id)
    {
        $evento = Agenda::with('processo.cliente')->find($id);
        if ($evento) {
            $this->isEditing = true;
            $this->editingId = $evento->id;
            $this->agenda_titulo = $evento->titulo;
            $this->agenda_tipo = $evento->tipo;
            $this->agenda_subtipo = $evento->subtipo;
            $this->agenda_link_reuniao = $evento->link_reuniao;
            $this->agenda_data_hora_inicio = $evento->data_hora_inicio->format('Y-m-d\TH:i');
            $this->agenda_data_hora_fim = $evento->data_hora_fim ? $evento->data_hora_fim->format('Y-m-d\TH:i') : '';
            $this->agenda_user_id = $evento->user_id;
            $this->agenda_observacoes = $evento->observacoes;

            $this->agenda_processo_id = $evento->processo_id;
            if ($evento->processo) {
                $clienteNome = $evento->processo->cliente ? $evento->processo->cliente->nome : 'Sem Cliente';
                $this->processo_busca = $clienteNome . ' - ' . $evento->processo->titulo;
            } else {
                $this->processo_busca = '';
            }
            $this->resultadosProcessos = [];

            $this->showAgendaModal = true;
        }
    }

    public function atualizarDataEvento($id, $start, $end)
    {
        $evento = Agenda::find($id);
        if ($evento) {
            $evento->update([
                'data_hora_inicio' => Carbon::parse($start),
                'data_hora_fim' => $end ? Carbon::parse($end) : null,
            ]);
            $this->js("Livewire.dispatch('notificacao', { msg: 'Data atualizada!' })");
            $this->dispatch('atualiza-calendario', ['eventos' => $this->buscarEventos()]);
        }
    }

    public function fecharModalAgenda()
    {
        $this->showAgendaModal = false;
    }

    public function salvarEvento()
    {
        if (empty($this->agenda_user_id))
            $this->agenda_user_id = Auth::id();

        $this->validate([
            'agenda_titulo' => 'required|string|max:255',
            'agenda_tipo' => 'required|string',
            'agenda_data_hora_inicio' => 'required|date',
            'agenda_user_id' => 'required',
        ]);

        $dados = [
            'titulo' => $this->agenda_titulo,
            'tipo' => $this->agenda_tipo,
            'subtipo' => in_array($this->agenda_tipo, ['Audiência', 'Atendimento']) ? $this->agenda_subtipo : null,
            'link_reuniao' => $this->agenda_link_reuniao,
            'data_hora_inicio' => $this->agenda_data_hora_inicio,
            'data_hora_fim' => $this->agenda_data_hora_fim ?: null,
            'user_id' => $this->agenda_user_id,
            'processo_id' => $this->agenda_processo_id ?: null,
            'observacoes' => $this->agenda_observacoes,
            'status' => 'Pendente',
        ];

        if ($this->isEditing) {
            Agenda::find($this->editingId)->update($dados);
            session()->flash('message', 'Compromisso atualizado!');
        } else {
            Agenda::create($dados);
            session()->flash('message', 'Compromisso salvo!');
        }

        $this->fecharModalAgenda();
        $this->dispatch('atualiza-calendario', ['eventos' => $this->buscarEventos()]);
    }

    public function gerarPautaPreview()
    {
        $advId = Auth::user()->cargo === 'Advogado' ? Auth::id() : $this->filtroAdvogado;

        if ($advId === 'todos') {
            $this->js("alert('Por favor, selecione um advogado específico no filtro do topo para gerar a pauta dele(a).');");
            return;
        }

        $advogado = User::find($advId);

        $eventosHoje = Agenda::with('processo.cliente')
            ->where('user_id', $advId)
            ->whereDate('data_hora_inicio', now()->toDateString())
            ->orderBy('data_hora_inicio', 'asc')
            ->get();

        if ($eventosHoje->isEmpty()) {
            $this->js("alert('Nenhum compromisso agendado para HOJE para este advogado.');");
            return;
        }

        $texto = "📅 *PAUTA DO DIA - " . now()->format('d/m/Y') . "*\n";
        $texto .= "👨‍⚖️ *Advogado(a): " . $advogado->name . "*\n\n";

        foreach ($eventosHoje as $ev) {
            $sub = "";
            if ($ev->subtipo) {
                $sub = $ev->tipo === 'Atendimento' ? " (" . mb_strtoupper($ev->subtipo) . ")" : " DE " . mb_strtoupper($ev->subtipo);
            }

            $texto .= "🔹 *" . mb_strtoupper($ev->tipo) . $sub . "*\n";
            $texto .= "⏰ *" . $ev->data_hora_inicio->format('H:i') . " HORAS*\n";
            $texto .= "📝 *Título:* " . $ev->titulo . "\n";

            if ($ev->processo) {
                $texto .= "⚖️ *Processo:* " . $ev->processo->numero_processo . "\n";
                $nomeCliente = $ev->processo->cliente ? $ev->processo->cliente->nome : 'N/A';
                $texto .= "👤 *Cliente:* " . $nomeCliente . "\n";
            }
            if ($ev->link_reuniao) {
                $texto .= "🔗 *Link da Sala:* " . $ev->link_reuniao . "\n";
            }
            if ($ev->observacoes) {
                $texto .= "⚠️ *Obs:* " . $ev->observacoes . "\n";
            }
            $texto .= "\n----------------------\n\n";
        }

        $this->pautaGeradaTexto = $texto;
        $this->showPautaModal = true;
    }

    public function with(): array
    {
        $user = Auth::user();
        $advId = ($user->cargo === 'Advogado') ? $user->id : $this->filtroAdvogado;

        $horaAtual = now()->hour;
        $saudacao = 'Bom dia';
        if ($horaAtual >= 12 && $horaAtual < 18)
            $saudacao = 'Boa tarde';
        elseif ($horaAtual >= 18)
            $saudacao = 'Boa noite';

        $primeiroNome = explode(' ', $user->name ?? 'Usuário')[0];
        $tituloCargo = $user->cargo === 'Advogado' ? 'Dr(a). ' : '';
        $mensagemBoasVindas = "{$saudacao}, {$tituloCargo}{$primeiroNome}";

        $queryBase = function ($q) use ($advId) {
            $q->when($advId !== 'todos', fn($q2) => $q2->where('user_id', $advId))
                ->when($this->filtroTipo !== 'todos', fn($q3) => $q3->where('tipo', $this->filtroTipo));
        };

        $processosAtivos = Processo::when($advId !== 'todos', fn($q) => $q->where('user_id', $advId))->whereNotIn('status', ['Arquivado'])->count();
        $prazosSemana = Processo::when($advId !== 'todos', fn($q) => $q->where('user_id', $advId))->whereBetween('data_prazo', [now()->startOfDay(), now()->addDays(7)->endOfDay()])->count();
        $urgentes = Processo::when($advId !== 'todos', fn($q) => $q->where('user_id', $advId))->where('is_urgent', true)->count();
        $audienciasSemana = Agenda::when($advId !== 'todos', fn($q) => $q->where('user_id', $advId))->where('tipo', 'Audiência')->whereBetween('data_hora_inicio', [now()->startOfDay(), now()->addDays(7)->endOfDay()])->count();

        $proximosEventos = Agenda::with(['processo.cliente', 'user'])
            ->where($queryBase)
            ->whereDate('data_hora_inicio', '>=', now()->startOfDay())
            ->orderBy('data_hora_inicio', 'asc')
            ->limit(6)
            ->get();

        $queryMobile = Agenda::with(['processo.cliente', 'user'])->where($queryBase);

        match ($this->filtroPeriodo) {
            'hoje' => $queryMobile->whereDate('data_hora_inicio', now()->toDateString()),
            'proximos_7_dias' => $queryMobile->whereBetween('data_hora_inicio', [now()->startOfDay(), now()->addDays(7)->endOfDay()]),
            'este_mes' => $queryMobile->whereMonth('data_hora_inicio', now()->month)->whereYear('data_hora_inicio', now()->year),
            'anteriores' => $queryMobile->whereBetween('data_hora_inicio', [now()->subDays(30)->startOfDay(), now()->subDay()->endOfDay()]),
            default => $queryMobile->whereDate('data_hora_inicio', '>=', now()->startOfDay())
        };

        $eventosMobile = $queryMobile->orderBy('data_hora_inicio', 'asc')
            ->get()
            ->groupBy(fn($e) => $e->data_hora_inicio->format('Y-m-d'));

        return [
            'mensagemBoasVindas' => $mensagemBoasVindas,
            'processosAtivos' => $processosAtivos,
            'prazosSemana' => $prazosSemana,
            'urgentes' => $urgentes,
            'audienciasSemana' => $audienciasSemana,
            'eventosIniciais' => $this->buscarEventos(),
            'proximosEventos' => $proximosEventos,
            'eventosMobile' => $eventosMobile,
            'listaAdvogados' => User::where('cargo', 'Advogado')->orderBy('name')->get(),
        ];
    }
};
?>

<div
    class="min-h-screen bg-slate-50 p-4 sm:p-6 lg:p-8 font-sans antialiased text-slate-900 w-full space-y-6 lg:space-y-8">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/scale.css" />

    {{-- Notificações --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="fixed top-5 right-5 z-[999999] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-xl shadow-lg">
            <svg class="w-5 h-5 text-emerald-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span class="text-xs font-bold text-slate-700 uppercase tracking-widest">{{ session('message') }}</span>
        </div>
    @endif

    <div x-data="{ show: false, msg: '' }"
        @notificacao.window="msg = $event.detail.msg; show = true; setTimeout(() => show = false, 3000)" x-show="show"
        style="display: none;"
        class="fixed bottom-5 right-5 z-[999999] bg-slate-900 text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span class="text-[10px] font-black uppercase tracking-widest" x-text="msg"></span>
    </div>

    {{-- Cabeçalho Principal --}}
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-end gap-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                {{ $mensagemBoasVindas }} <span class="animate-bounce inline-block">👋</span>
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">Resumo da sua agenda e pendências.</p>
        </div>

        <div
            class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full xl:w-auto bg-white p-2 rounded-xl border border-slate-200 shadow-sm">
            @if(Auth::user()->cargo !== 'Advogado')
                <select wire:model.live="filtroAdvogado"
                    class="bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer px-3 py-2.5 w-full sm:w-auto">
                    <option value="todos">Toda a Equipe</option>
                    @foreach($listaAdvogados as $adv) <option value="{{ $adv->id }}">{{ $adv->name }}</option> @endforeach
                </select>
                <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
            @endif

            <button wire:click="gerarPautaPreview" title="Gerar Pauta para o WhatsApp"
                class="flex items-center justify-center gap-2 bg-white border border-emerald-500 hover:bg-emerald-50 text-emerald-600 px-4 py-2.5 rounded-lg text-[11px] font-black uppercase tracking-widest transition-all w-full sm:w-auto">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                </svg>
                Gerar Pauta
            </button>

            <button wire:click="abrirModalAgenda"
                class="flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-[11px] font-black uppercase tracking-widest transition-all w-full sm:w-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Agendar
            </button>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card: Ativos --}}
        <div
            class="bg-white rounded-xl shadow-sm border border-slate-200 border-l-4 border-l-indigo-600 p-5 flex justify-between items-center hover:shadow-md transition-shadow">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Processos Ativos</p>
                <h3 class="text-3xl font-black text-slate-800 leading-none">{{ $processosAtivos }}</h3>
            </div>
            <div class="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
            </div>
        </div>

        {{-- Card: Prazos --}}
        <div
            class="bg-white rounded-xl shadow-sm border border-slate-200 border-l-4 border-l-rose-500 p-5 flex justify-between items-center hover:shadow-md transition-shadow">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Prazos na Semana</p>
                <h3 class="text-3xl font-black text-slate-800 leading-none">{{ $prazosSemana }}</h3>
            </div>
            <div class="w-12 h-12 rounded-lg bg-rose-50 text-rose-500 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        {{-- Card: Audiências --}}
        <div
            class="bg-white rounded-xl shadow-sm border border-slate-200 border-l-4 border-l-amber-500 p-5 flex justify-between items-center hover:shadow-md transition-shadow">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Audiências (7 dias)</p>
                <h3 class="text-3xl font-black text-slate-800 leading-none">{{ $audienciasSemana }}</h3>
            </div>
            <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z">
                    </path>
                </svg>
            </div>
        </div>

        {{-- Card: Urgentes --}}
        <div
            class="bg-white rounded-xl shadow-sm border border-slate-200 border-l-4 border-l-slate-600 p-5 flex justify-between items-center hover:shadow-md transition-shadow">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Processos Urgentes</p>
                <h3 class="text-3xl font-black text-slate-800 leading-none">{{ $urgentes }}</h3>
            </div>
            <div class="w-12 h-12 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
        </div>
    </div>

    {{-- Filtros do Calendário e Agenda --}}
    <div
        class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <span class="hidden sm:inline">Calendário de </span>Compromissos
        </h2>

        <div class="flex flex-wrap gap-2 w-full sm:w-auto pb-1 sm:pb-0">
            <button wire:click="$set('filtroTipo', 'todos')"
                class="shrink-0 px-3 py-1.5 rounded-md text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroTipo === 'todos' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-500 border border-slate-200 hover:bg-slate-100' }}">Todos</button>
            <button wire:click="$set('filtroTipo', 'Audiência')"
                class="shrink-0 px-3 py-1.5 rounded-md text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroTipo === 'Audiência' ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-slate-50 text-slate-500 border border-slate-200 hover:bg-amber-50 hover:text-amber-700' }}">Audiências</button>
            <button wire:click="$set('filtroTipo', 'Atendimento')"
                class="shrink-0 px-3 py-1.5 rounded-md text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroTipo === 'Atendimento' ? 'bg-blue-100 text-blue-800 border-blue-200' : 'bg-slate-50 text-slate-500 border border-slate-200 hover:bg-blue-50 hover:text-blue-700' }}">Atendimentos</button>
            <button wire:click="$set('filtroTipo', 'Prazo')"
                class="shrink-0 px-3 py-1.5 rounded-md text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroTipo === 'Prazo' ? 'bg-rose-100 text-rose-800 border-rose-200' : 'bg-slate-50 text-slate-500 border border-slate-200 hover:bg-rose-50 hover:text-rose-700' }}">Prazos</button>
        </div>
    </div>

    {{-- LAYOUT HÍBRIDO: AGENDA VERTICAL (MOBILE) vs CALENDÁRIO (DESKTOP) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- VISÃO MOBILE: AGENDA VERTICAL (TIMELINE) --}}
        <div class="block lg:hidden col-span-1 space-y-8">

            <div class="bg-white p-2 rounded-xl border border-slate-200 shadow-sm mb-6">
                <div class="flex flex-wrap gap-2 w-full">
                    <button wire:click="$set('filtroPeriodo', 'anteriores')"
                        class="flex-1 px-2 py-2 rounded-lg text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroPeriodo === 'anteriores' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-500 hover:bg-slate-100' }}">
                        Anteriores
                    </button>
                    <button wire:click="$set('filtroPeriodo', 'hoje')"
                        class="flex-1 px-2 py-2 rounded-lg text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroPeriodo === 'hoje' ? 'bg-indigo-600 text-white' : 'bg-slate-50 text-slate-500 hover:bg-slate-100' }}">
                        Hoje
                    </button>
                    <button wire:click="$set('filtroPeriodo', 'proximos_7_dias')"
                        class="flex-1 px-2 py-2 rounded-lg text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroPeriodo === 'proximos_7_dias' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-500 hover:bg-slate-100' }}">
                        7 Dias
                    </button>
                    <button wire:click="$set('filtroPeriodo', 'este_mes')"
                        class="flex-1 px-2 py-2 rounded-lg text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroPeriodo === 'este_mes' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-500 hover:bg-slate-100' }}">
                        Este Mês
                    </button>
                </div>
            </div>

            @forelse($eventosMobile as $data => $eventosDoDia)
                @php
                    $dataObj = \Carbon\Carbon::parse($data);
                    $isHoje = $dataObj->isToday();
                    $isAmanha = $dataObj->isTomorrow();
                    $diasSemana = ['Sunday' => 'Domingo', 'Monday' => 'Segunda-feira', 'Tuesday' => 'Terça-feira', 'Wednesday' => 'Quarta-feira', 'Thursday' => 'Quinta-feira', 'Friday' => 'Sexta-feira', 'Saturday' => 'Sábado'];
                    $diaSemana = $diasSemana[$dataObj->format('l')];
                @endphp

                <div>
                    <div class="flex items-baseline gap-2 mb-4 px-1 border-b border-slate-200 pb-1">
                        <h3 class="text-lg font-black tracking-tight {{ $isHoje ? 'text-indigo-600' : 'text-slate-900' }}">
                            @if($isHoje) Hoje
                            @elseif($isAmanha) Amanhã
                            @else {{ $dataObj->format('d/m') }}
                            @endif
                        </h3>
                        <span class="text-xs font-bold text-slate-400 uppercase">{{ $diaSemana }}</span>
                    </div>

                    <div class="space-y-3">
                        @foreach($eventosDoDia as $evento)
                            @php
                                $temaClasses = match ($evento->tipo) {
                                    'Audiência' => ['bg' => 'bg-amber-400', 'text' => 'text-amber-600', 'hover' => 'group-hover:text-amber-600'],
                                    'Atendimento' => ['bg' => 'bg-blue-400', 'text' => 'text-blue-600', 'hover' => 'group-hover:text-blue-600'],
                                    'Prazo' => ['bg' => 'bg-rose-400', 'text' => 'text-rose-600', 'hover' => 'group-hover:text-rose-600'],
                                    default => ['bg' => 'bg-slate-400', 'text' => 'text-slate-600', 'hover' => 'group-hover:text-slate-600'],
                                };
                            @endphp

                            <div wire:click="visualizarEvento({{ $evento->id }})" wire:key="mobile-evt-{{ $evento->id }}"
                                class="flex gap-3 cursor-pointer group">

                                <div class="w-12 shrink-0 text-right flex flex-col pt-1">
                                    <span
                                        class="text-sm font-black text-slate-800">{{ $evento->data_hora_inicio->format('H:i') }}</span>
                                    @if($evento->data_hora_fim)
                                        <span
                                            class="text-[10px] font-bold text-slate-400">{{ $evento->data_hora_fim->format('H:i') }}</span>
                                    @endif
                                </div>

                                <div
                                    class="flex-1 bg-white border border-slate-200 rounded-xl p-4 shadow-sm group-hover:shadow-md transition relative overflow-hidden">
                                    <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $temaClasses['bg'] }}"></div>

                                    <div class="pl-1.5">
                                        <p
                                            class="text-[9px] font-black uppercase tracking-widest {{ $temaClasses['text'] }} mb-1.5">
                                            {{ $evento->tipo }} {{ $evento->subtipo ? '• ' . $evento->subtipo : '' }}
                                        </p>
                                        <h4
                                            class="text-sm font-bold text-slate-900 leading-tight {{ $temaClasses['hover'] }} transition">
                                            {{ $evento->titulo }}</h4>

                                        @if($evento->processo)
                                            <div
                                                class="flex items-center gap-2 mt-3 bg-slate-50 p-2 rounded-lg border border-slate-100">
                                                <div
                                                    class="w-5 h-5 rounded bg-white border border-slate-200 flex items-center justify-center text-[8px] font-bold text-slate-500 shrink-0 shadow-sm">
                                                    {{ mb_substr($evento->processo->cliente?->nome ?? '?', 0, 1) }}
                                                </div>
                                                <div class="overflow-hidden">
                                                    <p class="text-[10px] font-bold text-slate-700 truncate">
                                                        {{ $evento->processo->cliente?->nome ?? 'Cliente não vinculado' }}</p>
                                                    <p class="text-[9px] text-slate-400 font-mono truncate">CNJ:
                                                        {{ $evento->processo->numero_processo }}</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-10 text-center mt-4">
                    <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Nenhum evento neste período.</p>
                </div>
            @endforelse
        </div>

        {{-- VISÃO DESKTOP: CALENDÁRIO FULLCALENDAR --}}
        <div
            class="hidden lg:flex lg:col-span-8 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex-col">
            <div class="p-5 flex-1 relative">
                <div wire:ignore>
                    <div id="fullcalendar-wrapper" data-component-id="{{ $this->getId() }}"></div>
                </div>
                <script type="application/json" id="calendar-events-data">
                    {!! json_encode($eventosIniciais, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
                </script>
            </div>
        </div>

        {{-- VISÃO DESKTOP: PRÓXIMOS COMPROMISSOS --}}
        <div
            class="hidden lg:flex lg:col-span-4 flex-col h-[740px] bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-indigo-100 bg-indigo-50/50 shrink-0">
                <h2 class="text-xs font-black text-indigo-800 uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Próximos Compromissos
                </h2>
            </div>

            <div class="p-4 flex-1 overflow-y-auto space-y-3 bg-slate-50/30">
                @forelse($proximosEventos as $evento)
                    @php
                        $temaClasses = match ($evento->tipo) {
                            'Audiência' => ['bg' => 'bg-amber-400', 'border' => 'hover:border-amber-300', 'text' => 'text-amber-600', 'bg_light' => 'bg-amber-50', 'border_light' => 'border-amber-100', 'hover' => 'group-hover:text-amber-700'],
                            'Atendimento' => ['bg' => 'bg-blue-400', 'border' => 'hover:border-blue-300', 'text' => 'text-blue-600', 'bg_light' => 'bg-blue-50', 'border_light' => 'border-blue-100', 'hover' => 'group-hover:text-blue-700'],
                            'Prazo' => ['bg' => 'bg-rose-400', 'border' => 'hover:border-rose-300', 'text' => 'text-rose-600', 'bg_light' => 'bg-rose-50', 'border_light' => 'border-rose-100', 'hover' => 'group-hover:text-rose-700'],
                            default => ['bg' => 'bg-slate-400', 'border' => 'hover:border-slate-300', 'text' => 'text-slate-600', 'bg_light' => 'bg-slate-50', 'border_light' => 'border-slate-100', 'hover' => 'group-hover:text-slate-700'],
                        };
                        $isHoje = $evento->data_hora_inicio->isToday();
                        $isAmanha = $evento->data_hora_inicio->isTomorrow();
                    @endphp

                    <div wire:click="visualizarEvento({{ $evento->id }})" wire:key="desktop-evt-{{ $evento->id }}"
                        class="group relative bg-white border border-slate-200 p-4 rounded-xl {{ $temaClasses['border'] }} hover:shadow-md transition-all cursor-pointer">
                        <div
                            class="absolute left-0 top-3 bottom-3 w-1.5 {{ $temaClasses['bg'] }} rounded-r-full opacity-80">
                        </div>

                        <div class="pl-2">
                            <div class="flex justify-between items-start mb-2">
                                <span
                                    class="text-[9px] font-black uppercase tracking-widest {{ $temaClasses['text'] }} {{ $temaClasses['bg_light'] }} border {{ $temaClasses['border_light'] }} px-2 py-1 rounded flex items-center gap-1.5">
                                    <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    @if($isHoje) Hoje às {{ $evento->data_hora_inicio->format('H:i') }}
                                    @elseif($isAmanha) Amanhã às {{ $evento->data_hora_inicio->format('H:i') }}
                                    @else {{ $evento->data_hora_inicio->format('d/m \à\s H:i') }}
                                    @endif
                                </span>
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                                    @if($evento->tipo === 'Atendimento') Atend.
                                    @else {{ $evento->tipo }} @endif
                                </span>
                            </div>

                            <h3
                                class="text-sm font-bold text-slate-800 leading-snug {{ $temaClasses['hover'] }} transition">
                                {{ $evento->titulo }}</h3>

                            @if($evento->processo)
                                <p class="text-[11px] font-medium text-slate-500 mt-1 truncate">
                                    {{ $evento->processo->cliente ? $evento->processo->cliente->nome : 'Processo Vinculado' }}
                                </p>
                            @endif

                            @if($filtroAdvogado === 'todos' && $evento->user)
                                <div
                                    class="mt-2 inline-flex items-center gap-1.5 text-[9px] font-bold text-slate-500 bg-slate-50 border border-slate-100 px-2 py-1 rounded-md uppercase tracking-wider">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    {{ explode(' ', $evento->user->name)[0] }}
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-center h-full">
                        <div
                            class="w-12 h-12 bg-white border border-slate-200 rounded-full flex items-center justify-center mb-3 shadow-sm">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                        </div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Sua agenda está livre.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- MODAL DE VISUALIZAÇÃO DE EVENTO --}}
    @if($showVisualizacaoModal && $eventoVisualizacao)
        @teleport('body')
        <div
            class="fixed inset-0 z-[999999] flex items-start sm:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm transition-opacity"
                wire:click="fecharVisualizacao"></div>

            <div class="relative w-full max-w-xl bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden m-auto">

                @php
                    $corModal = match ($eventoVisualizacao->tipo) {
                        'Audiência' => '#F59E0B',
                        'Atendimento' => '#3B82F6',
                        'Prazo' => '#E11D48',
                        default => '#64748B',
                    };
                @endphp

                <div style="height: 4px; background-color: {{ $corModal }}; width: 100%;"></div>

                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-start gap-4 bg-slate-50/50">
                    <div>
                        <span
                            style="color: {{ $corModal }}; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 0.25rem;">
                            {{ $eventoVisualizacao->tipo }} @if($eventoVisualizacao->subtipo) •
                            {{ $eventoVisualizacao->subtipo }} @endif
                        </span>
                        <h2 class="text-xl font-black text-slate-900 leading-tight">{{ $eventoVisualizacao->titulo }}</h2>
                        <p class="text-sm font-bold text-slate-500 mt-1 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ $eventoVisualizacao->data_hora_inicio->format('d/m/Y \à\s H:i') }}
                            @if($eventoVisualizacao->data_hora_fim)
                                até {{ $eventoVisualizacao->data_hora_fim->format('H:i') }}
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="fecharVisualizacao"
                        class="p-1.5 text-slate-400 hover:bg-slate-200 rounded-lg transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    @if($eventoVisualizacao->processo)
                        <div class="flex gap-4 p-4 border border-slate-200 rounded-xl bg-slate-50">
                            <div
                                class="w-10 h-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center shrink-0 text-slate-500 shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Cliente vinculado</p>
                                <p class="text-sm font-bold text-slate-800 leading-snug">
                                    {{ $eventoVisualizacao->processo->cliente ? $eventoVisualizacao->processo->cliente->nome : 'N/A' }}
                                </p>
                                <p class="text-[11px] text-slate-500 font-mono mt-0.5">CNJ:
                                    {{ $eventoVisualizacao->processo->numero_processo }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="flex gap-4">
                        <div
                            class="w-10 h-10 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0 text-indigo-500 shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                        </div>
                        <div class="flex-1 border-b border-slate-100 pb-4">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Responsável</p>
                            <p class="text-sm font-bold text-slate-800">
                                {{ $eventoVisualizacao->user ? $eventoVisualizacao->user->name : 'N/A' }}</p>
                        </div>
                    </div>

                    @if($eventoVisualizacao->link_reuniao)
                        <div class="flex gap-4">
                            <div
                                class="w-10 h-10 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center shrink-0 text-blue-500 shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1">
                                    </path>
                                </svg>
                            </div>
                            <div class="flex-1 border-b border-slate-100 pb-4">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Sala Virtual
                                </p>
                                <a href="{{ $eventoVisualizacao->link_reuniao }}" target="_blank"
                                    class="text-sm font-bold text-blue-600 hover:underline break-all">{{ $eventoVisualizacao->link_reuniao }}</a>
                            </div>
                        </div>
                    @endif

           @if($eventoVisualizacao->observacoes)
                        <div class="flex gap-4 items-start bg-amber-50/30 p-4 rounded-2xl border border-amber-100/50">
                            <div class="w-10 h-10 rounded-xl bg-white border border-amber-200 flex items-center justify-center shrink-0 text-amber-500 shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0"> 
                                <p class="text-[10px] font-bold text-amber-700/70 uppercase tracking-widest mb-1.5">Notas & Observações</p>
                                {{-- O segredo está aqui: NENHUM espaço antes das chaves --}}
                                <div class="text-sm text-slate-700 whitespace-pre-wrap break-words max-h-40 overflow-y-auto pr-2 custom-scrollbar">{{ $eventoVisualizacao->observacoes }}</div>
                            </div>
                        </div>
                        
                        <style>
                            .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                            .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                            .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #fcd34d; border-radius: 10px; }
                        </style>
                    @endif

                </div>

      {{-- Preparação do Texto Universal para o WhatsApp / Copiar --}}
                    @php
                        $textoWpp = "📌 *Lembrete de Compromisso - Lacerda & Associados*\n\n";
                        $textoWpp .= "🔹 *" . mb_strtoupper($eventoVisualizacao->tipo) . ($eventoVisualizacao->subtipo ? ' (' . mb_strtoupper($eventoVisualizacao->subtipo) . ')' : '') . "*\n";
                        $textoWpp .= "📅 *Data:* " . $eventoVisualizacao->data_hora_inicio->format('d/m/Y') . "\n";
                        $textoWpp .= "⏰ *Horário:* " . $eventoVisualizacao->data_hora_inicio->format('H:i') . "\n";
                        $textoWpp .= "📝 *Assunto:* " . $eventoVisualizacao->titulo . "\n";
                        
                        if($eventoVisualizacao->processo) {
                            $textoWpp .= "⚖️ *Processo:* " . $eventoVisualizacao->processo->numero_processo . "\n";
                            $clienteNome = $eventoVisualizacao->processo->cliente ? $eventoVisualizacao->processo->cliente->nome : 'Não informado';
                            $textoWpp .= "👤 *Cliente:* " . $clienteNome . "\n";
                        }

                        $textoWpp .= "👨‍⚖️ *Responsável:* " . ($eventoVisualizacao->user ? $eventoVisualizacao->user->name : 'Não Atribuído') . "\n";

                        if($eventoVisualizacao->link_reuniao) {
                            $textoWpp .= "🔗 *Link da Sala:* " . $eventoVisualizacao->link_reuniao . "\n";
                        }
                        
                        // NOVA PARTE: Puxando as observações
                        if($eventoVisualizacao->observacoes) {
                            $textoWpp .= "\n⚠️ *Observações:*\n" . $eventoVisualizacao->observacoes . "\n";
                        }
                        
                        $textoWppEncoded = urlencode($textoWpp);
                    @endphp

                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex flex-col xl:flex-row justify-between gap-4 rounded-b-xl" x-data="{ copied: false }">
                        
                        {{-- Botões de Partilha (Esquerda) --}}
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button type="button" @click="navigator.clipboard.writeText({{ json_encode($textoWpp) }}); copied = true; setTimeout(() => copied = false, 2000)" class="w-full sm:w-auto px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg shadow-sm transition-all text-center flex items-center justify-center gap-2">
                                <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2h2v4l.586-.586z"/></svg>
                                <svg x-show="copied" x-cloak class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span x-show="!copied">Copiar</span>
                                <span x-show="copied" class="text-emerald-600">Copiado!</span>
                            </button>

                            <button type="button" @click="window.open('https://api.whatsapp.com/send?text={{ $textoWppEncoded }}', '_blank')" class="w-full sm:w-auto px-4 py-2.5 text-[10px] font-black uppercase tracking-widest bg-[#25D366] text-white hover:bg-[#1ebd57] rounded-lg shadow-sm transition-all text-center flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                </svg>
                                WhatsApp
                            </button>
                        </div>

                        {{-- Botões de Ação do Sistema (Direita) --}}
                        <div class="flex flex-col-reverse sm:flex-row gap-2 w-full xl:w-auto mt-4 xl:mt-0 pt-4 xl:pt-0 border-t xl:border-0 border-slate-200">
                            <button type="button" wire:click="abrirModalExclusao" class="w-full sm:w-auto px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-rose-600 bg-white border border-rose-200 hover:bg-rose-50 rounded-lg transition-colors text-center">Excluir</button>
                            <button type="button" wire:click="abrirEdicaoPelaVisualizacao" class="w-full sm:w-auto px-6 py-2.5 text-xs font-bold uppercase tracking-wider bg-slate-900 text-white hover:bg-slate-800 rounded-lg shadow-md transition-all text-center">Editar</button>
                        </div>
                        
                    </div>

            </div>
        </div>
        @endteleport
    @endif

    {{-- MODAL DE CONFIRMAÇÃO DE EXCLUSÃO (NOVO) --}}
    @if($showExcluirModal && $eventoVisualizacao)
        @teleport('body')
        <div class="fixed inset-0 z-[9999999] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity"
                wire:click="fecharModalExclusao"></div>

            <div
                class="relative bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm text-center m-auto transform transition-all">
                <div
                    class="w-16 h-16 bg-rose-100 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                </div>
                <h3 class="text-xl font-black text-slate-900 mb-2">Excluir Compromisso?</h3>
                <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                    Esta ação não pode ser desfeita. O evento <strong
                        class="text-slate-700">"{{ $eventoVisualizacao->titulo }}"</strong> será removido permanentemente da
                    agenda.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button" wire:click="fecharModalExclusao"
                        class="flex-1 px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold uppercase tracking-wider transition">Cancelar</button>
                    <button type="button" wire:click="confirmarExclusaoEvento"
                        class="flex-1 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-md shadow-rose-200 transition">Sim,
                        Excluir</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- MODAL DE RESUMO (PAUTA) --}}
    @if($showPautaModal)
        @teleport('body')
        <div
            class="fixed inset-0 z-[999999] flex items-start sm:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm transition-opacity"
                wire:click="$set('showPautaModal', false)"></div>

            <div class="relative w-full bg-white rounded-xl shadow-2xl text-left overflow-hidden max-w-lg m-auto">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        Resumo da Pauta
                    </h3>
                    <button type="button" wire:click="$set('showPautaModal', false)"
                        class="text-slate-400 hover:text-slate-600 hover:bg-slate-200 p-1.5 rounded-md transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-5">
                    <textarea readonly rows="14"
                        class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-mono text-slate-800 focus:ring-0 resize-none p-4 shadow-inner whitespace-pre-wrap">{{ $pautaGeradaTexto }}</textarea>
                </div>
                <div class="p-5 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row gap-3"
                    x-data="{ copied: false }">
                    <button
                        @click="navigator.clipboard.writeText($wire.pautaGeradaTexto); copied = true; setTimeout(() => copied = false, 2000)"
                        class="w-full sm:flex-1 flex items-center justify-center gap-2 py-3 bg-white border border-slate-300 hover:border-slate-400 rounded-lg text-[10px] font-bold text-slate-700 uppercase tracking-widest transition shadow-sm">
                        <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2h2v4l.586-.586z" />
                        </svg>
                        <svg x-show="copied" x-cloak class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-show="!copied">Copiar</span>
                        <span x-show="copied" class="text-emerald-600">Copiado!</span>
                    </button>
                    <button
                        @click="window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent($wire.pautaGeradaTexto), '_blank')"
                        class="w-full sm:flex-[2] flex items-center justify-center gap-2 py-3 bg-[#25D366] hover:bg-[#1ebd57] text-white rounded-lg text-[10px] font-bold uppercase tracking-widest transition shadow-md">
                        Enviar para o WhatsApp
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- MODAL DE CADASTRO E EDIÇÃO DE AGENDA --}}
    @if($showAgendaModal)
        @teleport('body')
        <div
            class="fixed inset-0 z-[999999] flex items-start sm:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm transition-opacity"
                wire:click="fecharModalAgenda"></div>

            <div class="relative w-full bg-white rounded-xl shadow-2xl text-left overflow-hidden max-w-lg m-auto">
                <div class="bg-slate-50 px-6 py-5 border-b border-slate-200 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 tracking-tight">
                            {{ $isEditing ? 'Editar Compromisso' : 'Novo Compromisso' }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Preencha os dados do evento.</p>
                    </div>
                    <button type="button" wire:click="fecharModalAgenda"
                        class="text-slate-400 hover:text-slate-700 hover:bg-slate-200 p-1.5 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <form wire:submit.prevent="salvarEvento" class="space-y-5">

                        <div>
                            <label
                                class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Descrição
                                / Título</label>
                            <input type="text" wire:model="agenda_titulo"
                                class="w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            @error('agenda_titulo') <span
                            class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                        {{-- CAMPO DE PROCESSO --}}
                        <div class="relative">
                            <label
                                class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Vincular
                                a um Processo</label>

                            <div class="relative">
                                <input type="text" wire:model.live.debounce.300ms="processo_busca"
                                    placeholder="Digite cliente, título ou CNJ..."
                                    class="w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all pr-10">

                                @if($agenda_processo_id)
                                    <button type="button" wire:click="limparProcesso"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-rose-500">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                @else
                                    <div
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            @if(!empty($resultadosProcessos) && empty($agenda_processo_id))
                                <div
                                    class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden max-h-60 overflow-y-auto">
                                    @foreach($resultadosProcessos as $proc)
                                        <div wire:click="selecionarProcesso({{ $proc->id }})"
                                            class="px-4 py-3 border-b border-slate-100 hover:bg-indigo-50 cursor-pointer transition-colors last:border-0">
                                            <div class="text-sm font-bold text-slate-800 truncate">{{ $proc->titulo }}</div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span
                                                    class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded truncate max-w-[120px]">{{ $proc->cliente?->nome ?? 'S/ Cliente' }}</span>
                                                <span
                                                    class="text-[10px] font-mono text-slate-400">{{ $proc->numero_processo }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif(strlen($processo_busca) > 2 && empty($resultadosProcessos) && empty($agenda_processo_id))
                                <div
                                    class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl px-4 py-3 text-sm text-slate-500 italic">
                                    Nenhum processo encontrado...
                                </div>
                            @endif
                        </div>
                       
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label
                                    class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tipo
                                    de Compromisso</label>
                                <select wire:model.live="agenda_tipo"
                                    class="w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all cursor-pointer">
                                    <option value="Atendimento">Atendimento</option>
                                    <option value="Audiência">Audiência</option>
                                    <option value="Prazo">Prazo / Tarefa</option>
                                    <option value="Interno">Aviso Interno</option>
                                </select>
                            </div>

                            @if(Auth::user()->cargo !== 'Advogado')
                                <div>
                                    <label
                                        class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Responsável</label>
                                    <select wire:model="agenda_user_id"
                                        class="w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all cursor-pointer">
                                        <option value="">Selecione...</option>
                                        @foreach($listaAdvogados as $adv) <option value="{{ $adv->id }}">{{ $adv->name }}
                                        </option> @endforeach
                                    </select>
                                    @error('agenda_user_id') <span
                                        class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endif
                        </div>

                        @if($agenda_tipo === 'Atendimento')
                            <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                                <label class="block text-[11px] font-bold text-blue-600 uppercase tracking-wider mb-1.5">Formato
                                    do Atendimento</label>
                                <select wire:model="agenda_subtipo"
                                    class="w-full rounded-lg border-blue-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all cursor-pointer">
                                    <option value="Presencial">Presencial no Escritório</option>
                                    <option value="Ligação / WhatsApp">Ligação / WhatsApp</option>
                                    <option value="Videochamada">Videochamada</option>
                                </select>
                            </div>
                        @elseif($agenda_tipo === 'Audiência')
                            <div class="bg-amber-50/50 p-4 rounded-xl border border-amber-100">
                                <label class="block text-[11px] font-bold text-amber-600 uppercase tracking-wider mb-1.5">Fase
                                    da Audiência</label>
                                <select wire:model="agenda_subtipo" class="w-full rounded-lg border-amber-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all cursor-pointer">
    <option value="Conciliação / Mediação">Conciliação / Mediação</option>
    <option value="Inicial">Inicial</option>
    <option value="Instrução e Julgamento (AIJ)">Instrução e Julgamento (AIJ)</option>
    <option value="Una">Una</option>
    <option value="Justificação">Justificação</option>
    <option value="Custódia">Custódia (Criminal)</option>
    <option value="Sessão de Julgamento / Sustentação Oral">Sessão de Julgamento (Tribunal)</option>
    <option value="Outra">Outra</option>
</select>
                            </div>
                        @endif

                        @if(in_array($agenda_tipo, ['Audiência', 'Atendimento']))
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Link
                                    da Sala Virtual</label>
                                <input type="url" wire:model="agenda_link_reuniao" placeholder="https://..."
                                    class="w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label
                                    class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Data
                                    de Início</label>
                                <input type="datetime-local" wire:model="agenda_data_hora_inicio"
                                    class="w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                                @error('agenda_data_hora_inicio') <span
                                    class="text-rose-500 text-[10px] mt-1 block font-semibold">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Data
                                    de Término</label>
                                <input type="datetime-local" wire:model="agenda_data_hora_fim"
                                    class="w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            </div>
                        </div>
                             {{-- CAMPO DE OBSERVAÇÕES --}}
                            <div class="pt-2">
                                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">
                                    Observações / Notas Internas
                                </label>
                                <textarea wire:model="agenda_observacoes" rows="3" placeholder="Lembretes, detalhes da pauta, links adicionais..." 
                                    class="w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all resize-y"></textarea>
                            </div>
                        <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                            <button type="button" wire:click="fecharModalAgenda"
                                class="px-6 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-bold hover:bg-slate-50 transition">Cancelar</button>
                            <button type="submit"
                                class="px-8 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition">
                                {{ $isEditing ? 'Atualizar Evento' : 'Agendar Evento' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    <style>
        .fc {
            --fc-border-color: #e2e8f0;
            --fc-today-bg-color: #f8faff;
            font-family: inherit;
        }

        .fc-toolbar-title {
            font-weight: 800 !important;
            font-size: 1.25rem !important;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.025em;
        }

        .fc-button {
            border-radius: 8px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            font-size: 0.65rem !important;
            padding: 6px 12px !important;
            letter-spacing: 0.05em !important;
            box-shadow: none !important;
            transition: all 0.2s !important;
        }

        .fc-button-primary {
            background: white !important;
            color: #64748b !important;
            border: 1px solid #cbd5e1 !important;
        }

        .fc-button-primary:hover {
            background: #f8fafc !important;
            color: #0f172a !important;
        }

        .fc-button-active {
            background: #0f172a !important;
            border-color: #0f172a !important;
            color: white !important;
        }

        .fc-theme-standard th {
            border: none !important;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9 !important;
            background: #f8fafc;
        }

        .fc-col-header-cell-cushion {
            text-transform: uppercase;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .fc-daygrid-day-number {
            font-weight: 600;
            color: #475569;
            font-size: 0.8rem;
            padding: 8px !important;
        }

        .fc-day-today {
            background-color: #fafafa !important;
        }

        .fc-day-today .fc-daygrid-day-number {
            color: #4f46e5 !important;
            background: #eef2ff;
            border-radius: 6px;
            font-weight: 800;
        }

        .fc-event {
            border: none !important;
            padding: 2px 6px !important;
            border-radius: 4px !important;
            margin-bottom: 3px !important;
            font-size: 0.65rem !important;
            cursor: pointer;
            transition: transform 0.1s;
        }

        .fc-event:hover {
            transform: translateY(-1px);
            shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .fc-event-time {
            font-weight: 800 !important;
            opacity: 0.7;
            margin-right: 4px;
        }

        .fc-event-title {
            font-weight: 600 !important;
        }

        .evt-audiencia {
            background-color: #FEF3C7 !important;
            color: #B45309 !important;
            border-left: 3px solid #F59E0B !important;
        }

        .evt-prazo {
            background-color: #FFE4E6 !important;
            color: #BE123C !important;
            border-left: 3px solid #E11D48 !important;
        }

        .evt-atendimento {
            background-color: #DBEAFE !important;
            color: #1D4ED8 !important;
            border-left: 3px solid #3B82F6 !important;
        }

        .evt-interno {
            background-color: #F1F5F9 !important;
            color: #475569 !important;
            border-left: 3px solid #94A3B8 !important;
        }
    </style>

    <script>
        document.addEventListener('livewire:initialized', function () {

            const el = document.getElementById('fullcalendar-wrapper');
            if (!el) return;

            const componentId = el.dataset.componentId;
            const component = Livewire.find(componentId);

            let initialEvents = [];
            try {
                const dataEl = document.getElementById('calendar-events-data');
                if (dataEl) {
                    initialEvents = JSON.parse(dataEl.textContent);
                }
            } catch (e) {
                console.error('Erro ao carregar eventos:', e);
            }

            let calendar = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: 'today dayGridMonth,timeGridWeek'
                },
                events: initialEvents,
                height: 700,
                selectable: true,
                editable: true,
                dayMaxEvents: 3,
                displayEventTime: true,
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },

                dateClick: (info) => {
                    if (component) component.call('abrirModalAgenda', info.dateStr);
                },
                eventClick: (info) => {
                    if (component) component.call('visualizarEvento', info.event.id);
                },

                eventDidMount: function (info) {
                    let props = info.event.extendedProps;
                    let time = info.timeText ? `<strong style="color: #fff;">${info.timeText}</strong><br>` : '';
                    let subtipo = props.subtipo ? ` (${props.subtipo})` : '';
                    let cliente = props.cliente ? `<br><span style="color: #94A3B8; font-size: 10px;">👤 ${props.cliente}</span>` : '';
                    let adv = props.advogado ? `<br><span style="color: #818CF8; font-size: 10px;">👨‍⚖️ ${props.advogado}</span>` : '';

                    let content = `
                        <div style="text-align: left; padding: 4px; font-family: 'Inter', sans-serif;">
                            ${time}
                            <span style="font-size: 12px; font-weight: bold; color: #F8FAFC;">${info.event.title}</span><br>
                            <span style="font-size: 10px; color: #CBD5E1; text-transform: uppercase; letter-spacing: 0.05em; font-weight: bold;">${props.tipo}${subtipo}</span>
                            ${cliente}
                            ${adv}
                        </div>
                    `;

                    tippy(info.el, {
                        content: content,
                        allowHTML: true,
                        placement: 'top',
                        animation: 'scale',
                        theme: 'translucent',
                        delay: [150, 50],
                    });
                },

                eventDrop: function (info) {
                    let start = info.event.startStr;
                    let end = info.event.endStr ? info.event.endStr : null;
                    if (component) component.call('atualizarDataEvento', info.event.id, start, end);
                }
            });
            calendar.render();

            let timeout;
            window.addEventListener('resize', function () {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    if (calendar && window.innerWidth >= 1024) {
                        calendar.updateSize();
                    }
                }, 100);
            });

            Livewire.on('atualiza-calendario', (data) => {
                if (calendar) {
                    let novosEventos = data[0]?.eventos || data?.eventos || [];
                    calendar.removeAllEventSources();
                    calendar.addEventSource(novosEventos);
                }
            });
        });
    </script>
</div>