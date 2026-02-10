<?php

namespace App\Enums;

enum ProcessoStatus: string
{
    // Definimos os casos com o texto exato que tens no banco de dados
    case DISTRIBUIDO = 'Distribuído';
    case PETICAO_INICIAL = 'Petição Inicial';
    case AGUARDANDO_CITACAO = 'Aguardando Citação';
    case EM_ANDAMENTO = 'Em Andamento';
    case CONTESTACAO_REPLICA = 'Contestação/Réplica';
    case CONCLUSO_DECISAO = 'Concluso para Decisão';
    case INSTRUCAO = 'Instrução';
    case AUDIENCIA_DESIGNADA = 'Audiência Designada';
    case AGUARDANDO_AUDIENCIA = 'Aguardando Audiência';
    case PERICIA_DESIGNADA = 'Perícia Designada';
    case APRESENTACAO_LAUDO = 'Apresentação de Laudo';
    case PRAZO_ABERTO = 'Prazo em Aberto';
    case URGENCIA_LIMINAR = 'Urgência / Liminar';
    case AGUARDANDO_PROTOCOLO = 'Aguardando Protocolo';
    case SENTENCIADO = 'Sentenciado';
    case EM_GRAU_RECURSO = 'Em Grau de Recurso';
    case CUMPRIMENTO_SENTENCA = 'Cumprimento de Sentença';
    case ACORDO_PAGAMENTO = 'Acordo/Pagamento';
    case TRANSITO_JULGADO = 'Trânsito em Julgado';
    case SUSPENSO_SOBRESTADO = 'Suspenso / Sobrestado';
    case ARQUIVADO = 'Arquivado';

    // Método para retornar a cor baseada no status
    public function color(): string
    {
        return match ($this) {
            self::DISTRIBUIDO,
            self::PETICAO_INICIAL,
            self::AGUARDANDO_CITACAO => 'bg-indigo-50 text-indigo-700 border-indigo-200',

            self::EM_ANDAMENTO,
            self::CONTESTACAO_REPLICA,
            self::CONCLUSO_DECISAO,
            self::INSTRUCAO => 'bg-emerald-50 text-emerald-700 border-emerald-200',

            self::AUDIENCIA_DESIGNADA,
            self::AGUARDANDO_AUDIENCIA => 'bg-amber-50 text-amber-700 border-amber-200',

            self::PERICIA_DESIGNADA,
            self::APRESENTACAO_LAUDO => 'bg-orange-50 text-orange-700 border-orange-200',

            self::PRAZO_ABERTO,
            self::URGENCIA_LIMINAR,
            self::AGUARDANDO_PROTOCOLO => 'bg-rose-50 text-rose-700 border-rose-200',

            self::SENTENCIADO,
            self::EM_GRAU_RECURSO,
            self::CUMPRIMENTO_SENTENCA,
            self::ACORDO_PAGAMENTO => 'bg-purple-50 text-purple-700 border-purple-200',

            self::TRANSITO_JULGADO,
            self::SUSPENSO_SOBRESTADO,
            self::ARQUIVADO => 'bg-gray-100 text-gray-500 border-gray-200',

            // Caso não caia em nenhum (fallback)
            default => 'bg-gray-50 text-gray-400 border-gray-100',
        };
    }
}