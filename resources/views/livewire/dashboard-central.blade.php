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
    
    // Controle dos Modais
    public $showAgendaModal = false;
    public $showPautaModal = false; 
    public $showVisualizacaoModal = false; // NOVO: Modal de Leitura
    
    public $pautaGeradaTexto = '';  
    public $isEditing = false;
    public $editingId = null;
    public $eventoVisualizacao = null; // Guarda os dados do evento clicado
    
    // Formulário
    public $agenda_titulo = '';
    public $agenda_tipo = 'Audiência';
    public $agenda_subtipo = ''; 
    public $agenda_link_reuniao = ''; 
    public $agenda_data_hora_inicio = '';
    public $agenda_data_hora_fim = '';
    public $agenda_user_id = '';
    public $agenda_processo_id = '';
    public $agenda_observacoes = '';

    public function updatedFiltroAdvogado() { $this->dispatch('atualiza-calendario', ['eventos' => $this->buscarEventos()]); }
    public function updatedFiltroTipo() { $this->dispatch('atualiza-calendario', ['eventos' => $this->buscarEventos()]); }

    public function buscarEventos()
    {
        $advId = ($this->filtroAdvogado === 'todos') ? null : $this->filtroAdvogado;
        $tipo = ($this->filtroTipo === 'todos') ? null : $this->filtroTipo;

        return Agenda::with(['processo.cliente', 'user'])
            ->when($advId, fn($q) => $q->where('user_id', $advId))
            ->when($tipo, fn($q) => $q->where('tipo', $tipo))
            ->get()
            ->map(function($a) use ($advId) {
                $prefixoAdvogado = ($advId === null && $a->user) ? '[' . explode(' ', $a->user->name)[0] . '] ' : '';
                
                return [
                    'id' => $a->id,
                    'title' => $prefixoAdvogado . $a->titulo,
                    'start' => $a->data_hora_inicio->format('Y-m-d\TH:i:s'),
                    'end' => $a->data_hora_fim ? $a->data_hora_fim->format('Y-m-d\TH:i:s') : null,
                    'className' => 'evt-' . strtolower(preg_replace('/[áàãâä]/ui', 'a', preg_replace('/[éèêë]/ui', 'e', preg_replace('/[íìîï]/ui', 'i', preg_replace('/[óòõôö]/ui', 'o', preg_replace('/[úùûü]/ui', 'u', preg_replace('/[ç]/ui', 'c', $a->tipo))))))),
                    'allDay' => false,
                    // Dados extras para o Hover (Tippy.js)
                    'extendedProps' => [
                        'tipo' => $a->tipo,
                        'subtipo' => $a->subtipo,
                        'cliente' => $a->processo && $a->processo->cliente ? $a->processo->cliente->nome : null,
                        'advogado' => $a->user ? $a->user->name : 'Não Atribuído'
                    ]
                ];
            })->toArray();
    }

    // --- NOVA LÓGICA DE VISUALIZAÇÃO (Google Style) ---
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

    public function excluirEventoPelaVisualizacao()
    {
        if ($this->eventoVisualizacao) {
            $this->eventoVisualizacao->delete();
            $this->fecharVisualizacao();
            session()->flash('message', 'Compromisso excluído!');
            $this->dispatch('atualiza-calendario', ['eventos' => $this->buscarEventos()]);
        }
    }
    // --------------------------------------------------

    public function abrirModalAgenda($dataPreSelecionada = null)
    {
        $this->reset(['agenda_titulo', 'agenda_processo_id', 'agenda_observacoes', 'editingId', 'agenda_subtipo', 'agenda_link_reuniao']);
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
        $evento = Agenda::find($id);
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
            $this->agenda_processo_id = $evento->processo_id;
            $this->agenda_observacoes = $evento->observacoes;
            
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

    public function fecharModalAgenda() { $this->showAgendaModal = false; }

    public function salvarEvento()
    {
        if (empty($this->agenda_user_id)) $this->agenda_user_id = Auth::id();

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
        if ($horaAtual >= 12 && $horaAtual < 18) $saudacao = 'Boa tarde';
        elseif ($horaAtual >= 18) $saudacao = 'Boa noite';
        
        $primeiroNome = explode(' ', $user->name ?? 'Usuário')[0];
        $tituloCargo = $user->cargo === 'Advogado' ? 'Dr(a). ' : '';
        $mensagemBoasVindas = "{$saudacao}, {$tituloCargo}{$primeiroNome}";

        $queryBase = function($q) use ($advId) {
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

        return [
            'mensagemBoasVindas' => $mensagemBoasVindas,
            'processosAtivos' => $processosAtivos,
            'prazosSemana' => $prazosSemana,
            'urgentes' => $urgentes,
            'audienciasSemana' => $audienciasSemana,
            'eventosIniciais' => $this->buscarEventos(), 
            'proximosEventos' => $proximosEventos,
            'listaAdvogados' => User::where('cargo', 'Advogado')->orderBy('name')->get(),
            'listaProcessos' => Processo::whereNotIn('status', ['Arquivado'])->orderBy('titulo')->get(),
        ];
    }
};
?>

<div class="w-full px-4 sm:px-6 lg:px-8 pb-8 pt-2 font-sans antialiased text-slate-900">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/scale.css" />

    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="fixed top-5 right-5 z-[999999] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-md shadow-lg">
            <svg class="w-5 h-5 text-emerald-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span class="text-xs font-bold text-slate-700 uppercase tracking-widest">{{ session('message') }}</span>
        </div>
    @endif
    
    <div x-data="{ show: false, msg: '' }" @notificacao.window="msg = $event.detail.msg; show = true; setTimeout(() => show = false, 3000)"
         x-show="show" style="display: none;"
         class="fixed bottom-5 right-5 z-[999999] bg-slate-900 text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3 animate-in fade-in slide-in-from-bottom-5">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <span class="text-[10px] font-black uppercase tracking-widest" x-text="msg"></span>
    </div>

    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-4 mb-8 relative z-0">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                {{ $mensagemBoasVindas }} <span class="animate-bounce origin-bottom inline-block">👋</span>
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">Bem-vindo(a) ao seu painel. Aqui está o resumo da sua agenda hoje.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 bg-white p-1.5 rounded-xl border border-slate-200 shadow-sm">
            @if(Auth::user()->cargo !== 'Advogado')
                <select wire:model.live="filtroAdvogado" class="bg-transparent border-none text-xs font-bold text-slate-600 focus:ring-0 cursor-pointer pl-3 pr-8 py-2">
                    <option value="todos">Toda a Equipe</option>
                    @foreach($listaAdvogados as $adv) <option value="{{ $adv->id }}">{{ $adv->name }}</option> @endforeach
                </select>
                <div class="w-px h-5 bg-slate-200 hidden sm:block"></div>
            @endif
            
            <button wire:click="gerarPautaPreview" title="Gerar Pauta para copiar ou enviar"
                    class="flex items-center gap-2 bg-white border border-emerald-500 hover:bg-emerald-50 text-emerald-600 px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all shadow-sm active:scale-95">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                Gerar Pauta
            </button>

            <button wire:click="abrirModalAgenda" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all shadow-sm active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Agendar
            </button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; position: relative; z-index: 0;">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex justify-between items-center hover:shadow-md transition-shadow" style="border-left: 4px solid #4F46E5;">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Processos Ativos</p>
                <h3 class="text-3xl font-black text-slate-800 leading-none">{{ $processosAtivos }}</h3>
            </div>
            <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" style="background-color: #EEF2FF; color: #4F46E5;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex justify-between items-center hover:shadow-md transition-shadow" style="border-left: 4px solid #E11D48;">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Prazos na Semana</p>
                <h3 class="text-3xl font-black text-slate-800 leading-none">{{ $prazosSemana }}</h3>
            </div>
            <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" style="background-color: #FFF1F2; color: #E11D48;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex justify-between items-center hover:shadow-md transition-shadow" style="border-left: 4px solid #D97706;">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Audiências (7 dias)</p>
                <h3 class="text-3xl font-black text-slate-800 leading-none">{{ $audienciasSemana }}</h3>
            </div>
            <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" style="background-color: #FFFBEB; color: #D97706;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex justify-between items-center hover:shadow-md transition-shadow" style="border-left: 4px solid #475569;">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Processos Urgentes</p>
                <h3 class="text-3xl font-black text-slate-800 leading-none">{{ $urgentes }}</h3>
            </div>
            <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" style="background-color: #F8FAFC; color: #475569;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 relative z-0">
        
        <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex flex-wrap justify-between items-center gap-3">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest">Calendário</h2>
                <div class="flex gap-2 flex-wrap">
                    <button wire:click="$set('filtroTipo', 'todos')" class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroTipo === 'todos' ? 'bg-slate-800 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:bg-slate-100' }}">Todos</button>
                    <button wire:click="$set('filtroTipo', 'Audiência')" class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroTipo === 'Audiência' ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-white text-slate-500 border border-slate-200 hover:bg-amber-50 hover:text-amber-700' }}">Audiências</button>
                    <button wire:click="$set('filtroTipo', 'Atendimento')" class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroTipo === 'Atendimento' ? 'bg-blue-100 text-blue-800 border-blue-200' : 'bg-white text-slate-500 border border-slate-200 hover:bg-blue-50 hover:text-blue-700' }}">Atendimentos</button>
                    <button wire:click="$set('filtroTipo', 'Prazo')" class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider transition-colors {{ $filtroTipo === 'Prazo' ? 'bg-rose-100 text-rose-800 border-rose-200' : 'bg-white text-slate-500 border border-slate-200 hover:bg-rose-50 hover:text-rose-700' }}">Prazos</button>
                </div>
            </div>
            <div class="p-5 flex-1">
                <div wire:ignore>
                    <div id="fullcalendar-wrapper"></div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-4 flex flex-col gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col h-full overflow-hidden">
                
                <div class="p-5 border-b border-slate-100 bg-indigo-50/30 flex justify-between items-center shrink-0">
                    <h2 class="text-sm font-black text-indigo-900 uppercase tracking-widest flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Próximos Compromissos
                    </h2>
                </div>
                
                <div class="p-5 flex-1 overflow-y-auto max-h-[600px] space-y-4">
                    @forelse($proximosEventos as $evento)
                        @php
                            $corTema = match($evento->tipo) {
                                'Audiência' => 'amber',
                                'Atendimento' => 'blue',
                                'Prazo' => 'rose',
                                default => 'slate',
                            };
                            $isHoje = $evento->data_hora_inicio->isToday();
                            $isAmanha = $evento->data_hora_inicio->isTomorrow();
                        @endphp
                        
                        <div wire:click="visualizarEvento({{ $evento->id }})" class="group relative bg-white border border-slate-100 p-4 rounded-xl hover:border-{{ $corTema }}-300 hover:shadow-md transition-all cursor-pointer">
                            <div class="absolute left-0 top-3 bottom-3 w-1 bg-{{ $corTema }}-400 rounded-r-full"></div>
                            <div class="pl-2">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-{{ $corTema }}-600 bg-{{ $corTema }}-50 px-2.5 py-1 rounded flex items-center gap-1.5">
                                        <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        @if($isHoje) Hoje às {{ $evento->data_hora_inicio->format('H:i') }}
                                        @elseif($isAmanha) Amanhã às {{ $evento->data_hora_inicio->format('H:i') }}
                                        @else {{ $evento->data_hora_inicio->format('d/m/Y \à\s H:i') }}
                                        @endif
                                    </span>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase">
                                        @if($evento->tipo === 'Atendimento') Atendimento 
                                        @else {{ $evento->tipo }} @endif 
                                        @if($evento->subtipo) | {{ $evento->subtipo }} @endif
                                    </span>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900 leading-snug group-hover:text-{{ $corTema }}-700 transition">{{ $evento->titulo }}</h3>
                                
                                @if($evento->processo)
                                    <p class="text-[11px] text-slate-500 mt-1 truncate">
                                        {{ $evento->processo->cliente ? $evento->processo->cliente->nome . ' • ' : '' }} 
                                        CNJ: {{ $evento->processo->numero_processo }}
                                    </p>
                                @endif

                                @if($filtroAdvogado === 'todos' && $evento->user)
                                    <div class="mt-2 inline-flex items-center gap-1.5 text-[9px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-md uppercase tracking-wider">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        {{ explode(' ', $evento->user->name)[0] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Nenhum compromisso agendado.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if($showVisualizacaoModal && $eventoVisualizacao)
        @teleport('body')
            <div style="position: fixed; z-index: 999999; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center;">
                <div class="absolute inset-0 backdrop-blur-sm transition-opacity" style="background-color: rgba(15, 23, 42, 0.75);" wire:click="fecharVisualizacao"></div>

                <div class="relative w-full bg-white rounded-2xl shadow-2xl text-left overflow-hidden animate-in fade-in zoom-in duration-200 m-4" style="max-width: 500px;">
                    
                    @php
                        $corModal = match($eventoVisualizacao->tipo) {
                            'Audiência' => '#F59E0B',
                            'Atendimento' => '#3B82F6',
                            'Prazo' => '#E11D48',
                            default => '#64748B',
                        };
                    @endphp

                    <div style="height: 6px; background-color: {{ $corModal }}; width: 100%;"></div>
                    
                    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-start gap-4">
                        <div>
                            <span style="color: {{ $corModal }}; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 0.25rem;">
                                {{ $eventoVisualizacao->tipo }} @if($eventoVisualizacao->subtipo) • {{ $eventoVisualizacao->subtipo }} @endif
                            </span>
                            <h2 class="text-xl font-black text-slate-900 leading-tight">{{ $eventoVisualizacao->titulo }}</h2>
                            <p class="text-sm font-bold text-slate-500 mt-1 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $eventoVisualizacao->data_hora_inicio->format('d/m/Y \à\s H:i') }}
                                @if($eventoVisualizacao->data_hora_fim)
                                    até {{ $eventoVisualizacao->data_hora_fim->format('H:i') }}
                                @endif
                            </p>
                        </div>
                        <button type="button" wire:click="fecharVisualizacao" class="p-1 text-slate-400 hover:bg-slate-100 rounded-md transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-5">
                        
                        @if($eventoVisualizacao->processo)
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 text-slate-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Cliente vinculado</p>
                                    <p class="text-sm font-bold text-slate-800">{{ $eventoVisualizacao->processo->cliente ? $eventoVisualizacao->processo->cliente->nome : 'N/A' }}</p>
                                    <p class="text-xs text-slate-500 font-mono mt-0.5">CNJ: {{ $eventoVisualizacao->processo->numero_processo }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-3">
                            <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center shrink-0 text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <div>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Responsável</p>
                                <p class="text-sm font-bold text-slate-800">{{ $eventoVisualizacao->user ? $eventoVisualizacao->user->name : 'N/A' }}</p>
                            </div>
                        </div>

                        @if($eventoVisualizacao->link_reuniao)
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center shrink-0 text-blue-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Sala Virtual</p>
                                    <a href="{{ $eventoVisualizacao->link_reuniao }}" target="_blank" class="text-sm font-bold text-blue-600 hover:underline break-all">{{ $eventoVisualizacao->link_reuniao }}</a>
                                </div>
                            </div>
                        @endif

                        @if($eventoVisualizacao->observacoes)
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center shrink-0 text-slate-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Observações</p>
                                    <div class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100 whitespace-pre-wrap">{{ $eventoVisualizacao->observacoes }}</div>
                                </div>
                            </div>
                        @endif

                    </div>

                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
                        <button type="button" onclick="confirm('Tem certeza que deseja excluir este evento?') || event.stopImmediatePropagation()" wire:click="excluirEventoPelaVisualizacao" class="px-4 py-2 text-xs font-black uppercase tracking-widest text-rose-600 hover:bg-rose-100 rounded-lg transition-colors">Excluir</button>
                        <button type="button" wire:click="abrirEdicaoPelaVisualizacao" class="px-6 py-2 text-xs font-black uppercase tracking-widest bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 hover:text-slate-900 rounded-lg shadow-sm transition-all">Editar Evento</button>
                    </div>

                </div>
            </div>
        @endteleport
    @endif

    @if($showPautaModal)
        @teleport('body')
            <div style="position: fixed; z-index: 999999; top: 0; left: 0; right: 0; bottom: 0;">
                <div class="absolute inset-0 backdrop-blur-sm transition-opacity" style="background-color: rgba(15, 23, 42, 0.75);" wire:click="$set('showPautaModal', false)"></div>
                <div class="absolute inset-0 overflow-y-auto flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full bg-white rounded-2xl shadow-2xl text-left overflow-hidden animate-in fade-in zoom-in duration-200" style="max-width: 500px;">
                        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                            <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Resumo da Pauta
                            </h3>
                            <button type="button" wire:click="$set('showPautaModal', false)" class="text-slate-400 hover:text-rose-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="p-5">
                            <textarea readonly rows="14" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-mono text-slate-800 focus:ring-0 resize-none p-4 shadow-inner whitespace-pre-wrap">{{ $pautaGeradaTexto }}</textarea>
                        </div>
                        <div class="p-5 border-t border-slate-100 bg-slate-50 flex gap-3" x-data="{ copied: false }">
                            <button @click="navigator.clipboard.writeText($wire.pautaGeradaTexto); copied = true; setTimeout(() => copied = false, 2000)" class="flex-1 flex items-center justify-center gap-2 py-3 bg-white border border-slate-200 hover:border-slate-300 rounded-xl text-[10px] font-black text-slate-700 uppercase tracking-widest transition shadow-sm active:scale-95">
                                <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2h2v4l.586-.586z"/></svg>
                                <svg x-show="copied" x-cloak class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span x-show="!copied">Copiar</span>
                                <span x-show="copied" class="text-emerald-600">Copiado!</span>
                            </button>
                            <button @click="window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent($wire.pautaGeradaTexto), '_blank')" class="flex-[2] flex items-center justify-center gap-2 py-3 bg-[#25D366] hover:bg-[#1ebd57] text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-sm active:scale-95">
                                Enviar para o WhatsApp
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    @if($showAgendaModal)
        @teleport('body')
            <div style="position: fixed; z-index: 999999; top: 0; left: 0; right: 0; bottom: 0;">
                <div class="absolute inset-0 backdrop-blur-sm transition-opacity" style="background-color: rgba(15, 23, 42, 0.75);" wire:click="fecharModalAgenda"></div>
                <div class="absolute inset-0 overflow-y-auto flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full bg-white rounded-2xl shadow-2xl text-left overflow-hidden animate-in fade-in zoom-in duration-200" style="max-width: 420px;">
                        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                            <h3 class="text-base font-black text-slate-800 uppercase tracking-tight">{{ $isEditing ? 'Editar Compromisso' : 'Novo Compromisso' }}</h3>
                            <button type="button" wire:click="fecharModalAgenda" class="text-slate-400 hover:text-rose-500 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                        </div>
                        <form wire:submit.prevent="salvarEvento" class="p-5 space-y-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Descrição / Nome do Cliente</label>
                                <input type="text" wire:model="agenda_titulo" class="w-full h-11 px-3 rounded-lg border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all text-sm font-bold text-slate-800">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Compromisso</label>
                                    <select wire:model.live="agenda_tipo" class="w-full h-11 px-2 rounded-lg border-slate-200 bg-slate-50 font-bold text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500">
                                        <option value="Atendimento">Atendimento</option>
                                        <option value="Audiência">Audiência</option>
                                        <option value="Prazo">Prazo / Tarefa</option>
                                        <option value="Interno">Aviso Interno</option>
                                    </select>
                                </div>
                                @if(Auth::user()->cargo !== 'Advogado')
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Responsável</label>
                                        <select wire:model="agenda_user_id" class="w-full h-11 px-2 rounded-lg border-slate-200 bg-slate-50 font-bold text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500">
                                            <option value="">Selecione...</option>
                                            @foreach($listaAdvogados as $adv) <option value="{{ $adv->id }}">{{ explode(' ', $adv->name)[0] }}</option> @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>
                            @if($agenda_tipo === 'Atendimento')
                                <div class="space-y-1 animate-in fade-in slide-in-from-top-2">
                                    <label class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Formato</label>
                                    <select wire:model="agenda_subtipo" class="w-full h-11 px-3 rounded-lg border-blue-200 bg-blue-50 font-bold text-sm text-blue-900 focus:ring-2 focus:ring-blue-500">
                                        <option value="Presencial">Presencial no Escritório</option>
                                        <option value="Ligação / WhatsApp">Ligação / WhatsApp</option>
                                        <option value="Videochamada">Videochamada</option>
                                    </select>
                                </div>
                            @elseif($agenda_tipo === 'Audiência')
                                <div class="space-y-1 animate-in fade-in slide-in-from-top-2">
                                    <label class="text-[10px] font-black text-amber-500 uppercase tracking-widest">Fase</label>
                                    <select wire:model="agenda_subtipo" class="w-full h-11 px-3 rounded-lg border-amber-200 bg-amber-50 font-bold text-sm text-amber-900 focus:ring-2 focus:ring-amber-500">
                                        <option value="Inicial">Inicial</option>
                                        <option value="Instrução">Instrução</option>
                                        <option value="Conciliação">Conciliação</option>
                                    </select>
                                </div>
                            @endif
                            @if(in_array($agenda_tipo, ['Audiência', 'Atendimento']))
                                <div class="space-y-1 animate-in fade-in slide-in-from-top-2">
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Link da Sala Virtual</label>
                                    <input type="url" wire:model="agenda_link_reuniao" class="w-full h-11 px-3 rounded-lg border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-slate-500 transition-all text-xs font-medium text-slate-800">
                                </div>
                            @endif
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Início</label>
                                    <input type="datetime-local" wire:model="agenda_data_hora_inicio" class="w-full h-11 px-2 rounded-lg border-slate-200 bg-slate-50 font-bold text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Fim</label>
                                    <input type="datetime-local" wire:model="agenda_data_hora_fim" class="w-full h-11 px-2 rounded-lg border-slate-200 bg-slate-50 font-bold text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Vincular Processo</label>
                                <select wire:model="agenda_processo_id" class="w-full h-11 px-3 rounded-lg border-slate-200 bg-slate-50 font-bold text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Apenas Agendamento Avulso</option>
                                    @foreach($listaProcessos as $proc) <option value="{{ $proc->id }}">{{ $proc->numero_processo }}</option> @endforeach
                                </select>
                            </div>
                            <div class="pt-2 flex justify-end gap-2">
                                <button type="submit" class="w-full h-11 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-black uppercase tracking-widest text-xs transition-all shadow-sm active:scale-95">
                                    {{ $isEditing ? 'Salvar Alterações' : 'Agendar Compromisso' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <style>
        .fc { --fc-border-color: #f1f5f9; --fc-today-bg-color: #f8faff; font-family: inherit; }
        .fc-toolbar-title { font-weight: 900 !important; font-size: 1.1rem !important; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em; }
        .fc-button { border-radius: 8px !important; font-weight: 800 !important; text-transform: uppercase !important; font-size: 0.65rem !important; padding: 6px 12px !important; letter-spacing: 0.05em !important; box-shadow: none !important; transition: all 0.2s !important; }
        .fc-button-primary { background: white !important; color: #64748b !important; border: 1px solid #e2e8f0 !important; }
        .fc-button-primary:hover { background: #f1f5f9 !important; color: #0f172a !important; border-color: #cbd5e1 !important; }
        .fc-button-active { background: #1e293b !important; border-color: #1e293b !important; color: white !important; }
        .fc-theme-standard th { border: none !important; padding-bottom: 10px; }
        .fc-col-header-cell-cushion { text-transform: uppercase; font-size: 0.65rem; font-weight: 900; letter-spacing: 0.1em; color: #94a3b8; }
        .fc-daygrid-day-number { font-weight: 700; color: #64748b; font-size: 0.8rem; padding: 8px !important; }
        .fc-day-today { background-color: #fafafa !important; }
        .fc-day-today .fc-daygrid-day-number { color: #4f46e5 !important; background: #eef2ff; border-radius: 6px; }

        .fc-event { border: none !important; padding: 3px 6px !important; border-radius: 4px !important; margin-top: 2px !important; font-size: 0.7rem !important; cursor: pointer; }
        .fc-event-time { font-weight: 900 !important; opacity: 0.8; margin-right: 4px; }
        .fc-event-title { font-weight: 700 !important; }
        
        .evt-audiencia { background-color: #FEF3C7 !important; color: #B45309 !important; border-left: 3px solid #F59E0B !important; }
        .evt-prazo { background-color: #FFE4E6 !important; color: #BE123C !important; border-left: 3px solid #E11D48 !important; }
        .evt-atendimento { background-color: #DBEAFE !important; color: #1D4ED8 !important; border-left: 3px solid #3B82F6 !important; }
        .evt-interno { background-color: #F1F5F9 !important; color: #475569 !important; border-left: 3px solid #94A3B8 !important; }
    </style>

    <script>
        document.addEventListener('livewire:initialized', function () {
            let calendar;

            function initCalendar() {
                const el = document.getElementById('fullcalendar-wrapper');
                if(!el) return;
                
                calendar = new FullCalendar.Calendar(el, {
                    initialView: 'dayGridMonth',
                    locale: 'pt-br',
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                    events: @json($eventosIniciais),
                    contentHeight: 'auto',
                    aspectRatio: 1.5,
                    selectable: true,
                    editable: true, 
                    dayMaxEvents: 3,
                    displayEventTime: true,
                    eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
                    
                    // Lógica do Modal de Visualização (Click)
                    dateClick: (info) => @this.call('abrirModalAgenda', info.dateStr),
                    eventClick: (info) => @this.call('visualizarEvento', info.event.id),
                    
                    // Lógica do Hover Escuro (Tippy.js)
                    eventDidMount: function(info) {
                        let props = info.event.extendedProps;
                        let time = info.timeText ? `<strong style="color: #fff;">${info.timeText}</strong><br>` : '';
                        let subtipo = props.subtipo ? ` (${props.subtipo})` : '';
                        let cliente = props.cliente ? `<br><span style="color: #94A3B8;">👤 ${props.cliente}</span>` : '';
                        let adv = props.advogado ? `<br><span style="color: #818CF8;">👨‍⚖️ ${props.advogado}</span>` : '';

                        let content = `
                            <div style="text-align: left; padding: 4px; font-family: sans-serif;">
                                ${time}
                                <span style="font-size: 13px; font-weight: bold; color: #F8FAFC;">${info.event.title}</span><br>
                                <span style="font-size: 11px; color: #CBD5E1; text-transform: uppercase;">${props.tipo}${subtipo}</span>
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

                    eventDrop: function(info) {
                        let start = info.event.startStr;
                        let end = info.event.endStr ? info.event.endStr : null;
                        @this.call('atualizarDataEvento', info.event.id, start, end);
                    }
                });
                calendar.render();
            }

            initCalendar();

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