<?php
/* Exceções do calendário: tornar letivo um fim de semana/feriado ou dispensar
   um dia útil. A abrangência pode ser todas as unidades, uma unidade ou uma
   turma; a gestão escolar só altera exceções do seu alcance. */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
if (!ch_csrf_ok()) {
    header('Location: ../index.php');
    exit;
}

$acao = (string)($_POST['acao'] ?? '');
$ano = (int)($_POST['ano'] ?? 0);
$id = (int)($_POST['id'] ?? 0);
$calendario = strtoupper(trim((string)($_POST['calendario'] ?? '')));
$data = ch_data_iso((string)($_POST['data'] ?? ''));
$tipo = strtoupper(trim((string)($_POST['tipo'] ?? '')));
$motivo = trim((string)($_POST['motivo'] ?? ''));
$escopo = strtolower(trim((string)($_POST['escopo'] ?? 'global')));
$idUnidade = (int)($_POST['id_unidade'] ?? 0);
$idTurma = (int)($_POST['id_turma'] ?? 0);
$voltar = '../index.php?ano=' . ($ano > 0 ? $ano : (int)date('Y'));
if (isset(ch_calendarios_opcoes()[$calendario])) {
    $voltar .= '&calendario=' . rawurlencode($calendario);
}
$erros = [];
$ok = false;
$mensagem = '';
$acaoAuditoria = '';
$registroId = 0;
$antes = null;
$depois = null;

$comEscopo = ch_excecoes_tem_escopo();
if (!ch_tabela_excecoes_calendario() || !ch_tabela_calendarios()) {
    $erros[] = 'Banco desatualizado. Rode sql_inicial/ATUALIZAR_CALENDARIO_ESCOLAR.sql.';
}

/* Abrangência efetiva e autorização. Uma exceção de turma herda a unidade. */
$unidadeEscopo = 0;
$turmaEscopo = 0;
$turmaInfo = null;
if (!in_array($escopo, ['global', 'unidade', 'turma'], true)) {
    $erros[] = 'Abrangência inválida.';
} elseif ($escopo === 'global') {
    if (!$comEscopo && !ch_rede()) {
        $erros[] = 'Banco desatualizado. Rode sql_inicial/ATUALIZAR_EXCECOES_POR_UNIDADE_TURMA.sql.';
    } elseif (!ch_rede()) {
        $erros[] = 'Somente a gestão da rede pode criar exceção para todas as unidades.';
    }
} elseif ($escopo === 'unidade') {
    if (!$comEscopo) {
        $erros[] = 'Banco desatualizado. Rode sql_inicial/ATUALIZAR_EXCECOES_POR_UNIDADE_TURMA.sql.';
    } elseif ($idUnidade <= 0) {
        $erros[] = 'Escolha a unidade da exceção.';
    } elseif (!ch_val('SELECT id FROM dbo.TB_UNIDADES WHERE id = ?', [$idUnidade])) {
        $erros[] = 'Unidade não encontrada.';
    } elseif (!ch_pode_unidade($idUnidade)) {
        $erros[] = 'Você não tem acesso a esta unidade.';
    } else {
        $unidadeEscopo = $idUnidade;
    }
} elseif ($idTurma <= 0) {
    $erros[] = 'Escolha a turma da exceção.';
} elseif (!$comEscopo) {
    $erros[] = 'Banco desatualizado. Rode sql_inicial/ATUALIZAR_EXCECOES_POR_UNIDADE_TURMA.sql.';
} else {
    $turmaInfo = ch_turma($idTurma);
    if (!$turmaInfo) {
        $erros[] = 'Turma não encontrada.';
    } elseif (!ch_pode_turma($idTurma)) {
        $erros[] = 'Você não tem acesso a esta turma.';
    } else {
        $unidadeEscopo = (int)$turmaInfo['ID_UNIDADE'];
        $turmaEscopo = $idTurma;
    }
}

/* Colunas de escopo são opcionais para instalações anteriores à migração. */
$colEscopoAtual = $comEscopo
    ? ', ISNULL(e.ID_UNIDADE, 0) AS ID_UNIDADE, ISNULL(e.ID_TURMA, 0) AS ID_TURMA'
    : ', CAST(0 AS int) AS ID_UNIDADE, CAST(0 AS int) AS ID_TURMA';

if ($acao === 'salvar') {
    if ($ano < 2000 || $ano > 2100) {
        $erros[] = 'Ano letivo inválido.';
    }
    if (!isset(ch_calendarios_opcoes()[$calendario])) {
        $erros[] = 'Calendário inválido.';
    }
    if (!$data) {
        $erros[] = 'Informe uma data válida.';
    } elseif ((int)substr($data, 0, 4) !== $ano) {
        $erros[] = 'A data deve ser do ano letivo informado.';
    }
    if (!in_array($tipo, ['LETIVO', 'NAO_LETIVO'], true)) {
        $erros[] = 'Tipo de exceção inválido.';
    }
    if (mb_strlen($motivo) > 150) {
        $erros[] = 'O motivo deve ter no máximo 150 caracteres.';
    }
    if ($turmaEscopo > 0 && $turmaInfo && (int)$turmaInfo['ANO_LETIVO'] !== $ano) {
        $erros[] = 'A turma escolhida não pertence ao ano letivo informado.';
    }
    if ($turmaEscopo > 0 && $turmaInfo && ch_calendario_da_turma($turmaInfo) !== $calendario) {
        $erros[] = 'A turma escolhida não usa este calendário.';
    }

    if (!$erros) {
        $colFim = ch_calendario_tem_fim()
            ? 'CONVERT(varchar(10), FIM_ANO, 23)'
            : 'CAST(NULL AS varchar(10))';
        $cal = ch_row(
            'SELECT ID, CONVERT(varchar(10), INICIO_ANO, 23) AS INICIO, ' . $colFim . ' AS FIM
               FROM dbo.TB_CALENDARIOS WHERE ANO_LETIVO = ? AND CALENDARIO = ?',
            [$ano, $calendario]
        );
        if (!$cal) {
            $erros[] = 'Defina o período letivo deste calendário antes de criar exceções.';
        } elseif ($cal['INICIO'] !== null && $data < (string)$cal['INICIO']) {
            $erros[] = 'A data é anterior ao início do período letivo deste calendário.';
        } elseif ($cal['FIM'] !== null && $data > (string)$cal['FIM']) {
            $erros[] = 'A data é posterior ao fim do período letivo deste calendário.';
        } else {
            $idCalendario = (int)$cal['ID'];
            $atual = $id > 0
                ? ch_row(
                    'SELECT e.ID, e.ID_CALENDARIO, CONVERT(varchar(10), e.DATA, 23) AS DATA, e.TIPO, e.MOTIVO'
                    . $colEscopoAtual . '
                       FROM dbo.TB_CALENDARIO_EXCECOES e WHERE e.ID = ?',
                    [$id]
                )
                : null;
            if ($id > 0 && !$atual) {
                $erros[] = 'Exceção não encontrada.';
            } elseif ($atual && (int)$atual['ID_CALENDARIO'] !== $idCalendario) {
                $erros[] = 'A exceção não pertence ao calendário informado.';
            } elseif ($atual && !ch_pode_gerenciar_excecao($atual['ID_UNIDADE'], $atual['ID_TURMA'])) {
                $erros[] = 'Você não tem permissão para alterar esta exceção.';
            } elseif (!$erros) {
                $antes = $atual ? [
                    'CALENDARIO' => $calendario,
                    'DATA' => (string)$atual['DATA'],
                    'TIPO' => (string)$atual['TIPO'],
                    'MOTIVO' => (string)($atual['MOTIVO'] ?? ''),
                    'ESCOPO' => ch_excecao_escopo_dados($atual['ID_UNIDADE'], $atual['ID_TURMA'])['ROTULO'],
                ] : null;
                $parametros = [$data, $tipo, $motivo !== '' ? $motivo : null];
                /* NULL, e não zero: as FKs não aceitam um ID fictício. */
                $paramUnidade = $unidadeEscopo > 0 ? $unidadeEscopo : null;
                $paramTurma = $turmaEscopo > 0 ? $turmaEscopo : null;
                if ($atual) {
                    $sql = 'UPDATE dbo.TB_CALENDARIO_EXCECOES SET DATA = ?, TIPO = ?, MOTIVO = ?'
                         . ($comEscopo ? ', ID_UNIDADE = ?, ID_TURMA = ?' : '') . ' WHERE ID = ?';
                    if ($comEscopo) {
                        $parametros[] = $paramUnidade;
                        $parametros[] = $paramTurma;
                    }
                    $parametros[] = $id;
                    $ok = ch_exec($sql, $parametros);
                    $acaoAuditoria = 'Editar exceção';
                    $registroId = $id;
                    $mensagem = 'Exceção atualizada.';
                } else {
                    $colunas = 'ID_CALENDARIO, DATA, TIPO, MOTIVO';
                    $marcadores = '?, ?, ?, ?';
                    $valores = array_merge([$idCalendario], $parametros);
                    if ($comEscopo) {
                        $colunas .= ', ID_UNIDADE, ID_TURMA';
                        $marcadores .= ', ?, ?';
                        $valores[] = $paramUnidade;
                        $valores[] = $paramTurma;
                    }
                    $registroId = (int)ch_insert_id(
                        'INSERT INTO dbo.TB_CALENDARIO_EXCECOES (' . $colunas . ')
                         OUTPUT INSERTED.ID VALUES (' . $marcadores . ')',
                        $valores
                    );
                    $ok = $registroId > 0;
                    $acaoAuditoria = 'Criar exceção';
                    $mensagem = 'Exceção criada.';
                }
            }
        }
    }
    $depois = [
        'CALENDARIO' => $calendario,
        'DATA' => $data,
        'TIPO' => $tipo,
        'MOTIVO' => $motivo,
        'ESCOPO' => ch_excecao_escopo_dados($unidadeEscopo, $turmaEscopo)['ROTULO'],
    ];
} elseif ($acao === 'excluir') {
    $row = ch_row(
        'SELECT e.ID, CONVERT(varchar(10), e.DATA, 23) AS DATA, e.TIPO, e.MOTIVO'
        . $colEscopoAtual . ', c.CALENDARIO, c.ANO_LETIVO
           FROM dbo.TB_CALENDARIO_EXCECOES e
           JOIN dbo.TB_CALENDARIOS c ON c.ID = e.ID_CALENDARIO
          WHERE e.ID = ?',
        [$id]
    );
    if (!$row || (int)$row['ANO_LETIVO'] !== $ano) {
        $erros[] = 'Exceção não encontrada.';
    } elseif (!ch_pode_gerenciar_excecao($row['ID_UNIDADE'], $row['ID_TURMA'])) {
        $erros[] = 'Você não tem permissão para excluir esta exceção.';
    } else {
        $calendario = (string)$row['CALENDARIO'];
        $tipo = (string)$row['TIPO'];
        $motivo = (string)($row['MOTIVO'] ?? '');
        $data = (string)$row['DATA'];
        $unidadeEscopo = (int)$row['ID_UNIDADE'];
        $turmaEscopo = (int)$row['ID_TURMA'];
        $antes = [
            'CALENDARIO' => $calendario, 'DATA' => $data, 'TIPO' => $tipo, 'MOTIVO' => $motivo,
            'ESCOPO' => ch_excecao_escopo_dados($unidadeEscopo, $turmaEscopo)['ROTULO'],
        ];
        $ok = ch_exec('DELETE FROM dbo.TB_CALENDARIO_EXCECOES WHERE ID = ?', [$id]);
        $mensagem = 'Exceção excluída.';
        $acaoAuditoria = 'Excluir exceção';
        $registroId = $id;
    }
} else {
    $erros[] = 'Ação inválida.';
}

if (!$erros && $ok) {
    ch_auditar('Calendário Escolar', $acaoAuditoria, 'EXCECAO', $registroId, [
        'ANTES' => $antes,
        'DEPOIS' => $depois,
    ]);
    ch_flash($mensagem);
} else {
    if (!$erros) $erros[] = 'Não foi possível salvar a exceção (a data já pode ter exceção nesta abrangência).';
    ch_flash(implode(' ', $erros), 'danger');
}
header('Location: ' . $voltar);
exit;
