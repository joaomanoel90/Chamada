<?php
/* Exceções globais do calendário: tornar letivo um fim de semana/feriado ou
   dispensar um dia útil. Somente a rede (níveis 1 e 4) altera. */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_rede();

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
$voltar = '../index.php?ano=' . ($ano > 0 ? $ano : (int)date('Y'));
$erros = [];
$ok = false;
$mensagem = '';
$acaoAuditoria = '';
$antes = null;
$depois = null;

if (!ch_tabela_excecoes_calendario() || !ch_tabela_calendarios()) {
    $erros[] = 'Banco desatualizado. Rode sql_inicial/ATUALIZAR_CALENDARIO_ESCOLAR.sql.';
}

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
                ? ch_row('SELECT ID, ID_CALENDARIO, CONVERT(varchar(10), DATA, 23) AS DATA, TIPO, MOTIVO
                            FROM dbo.TB_CALENDARIO_EXCECOES WHERE ID = ?', [$id])
                : null;
            if ($id > 0 && !$atual) {
                $erros[] = 'Exceção não encontrada.';
            } elseif ($atual && (int)$atual['ID_CALENDARIO'] !== $idCalendario) {
                $erros[] = 'A exceção não pertence ao calendário informado.';
            } elseif (!$erros) {
                $antes = $atual ? [
                    'DATA' => (string)$atual['DATA'],
                    'TIPO' => (string)$atual['TIPO'],
                    'MOTIVO' => (string)($atual['MOTIVO'] ?? ''),
                ] : null;
                $parametros = [$data, $tipo, $motivo !== '' ? $motivo : null];
                if ($atual) {
                    $parametros[] = $id;
                    $ok = ch_exec(
                        'UPDATE dbo.TB_CALENDARIO_EXCECOES SET DATA = ?, TIPO = ?, MOTIVO = ? WHERE ID = ?',
                        $parametros
                    );
                    $acaoAuditoria = 'Editar exceção';
                    $registroId = $id;
                    $mensagem = 'Exceção atualizada.';
                } else {
                    $registroId = (int)ch_insert_id(
                        'INSERT INTO dbo.TB_CALENDARIO_EXCECOES (ID_CALENDARIO, DATA, TIPO, MOTIVO)
                         OUTPUT INSERTED.ID VALUES (?, ?, ?, ?)',
                        array_merge([$idCalendario], $parametros)
                    );
                    $ok = $registroId > 0;
                    $acaoAuditoria = 'Criar exceção';
                    $mensagem = 'Exceção criada.';
                }
            }
        }
    }
    $depois = ['CALENDARIO' => $calendario, 'DATA' => $data, 'TIPO' => $tipo, 'MOTIVO' => $motivo];
} elseif ($acao === 'excluir') {
    $row = ch_row(
        'SELECT e.ID, CONVERT(varchar(10), e.DATA, 23) AS DATA, e.TIPO, e.MOTIVO, c.CALENDARIO, c.ANO_LETIVO
           FROM dbo.TB_CALENDARIO_EXCECOES e
           JOIN dbo.TB_CALENDARIOS c ON c.ID = e.ID_CALENDARIO
          WHERE e.ID = ?',
        [$id]
    );
    if (!$row || (int)$row['ANO_LETIVO'] !== $ano) {
        $erros[] = 'Exceção não encontrada.';
    } else {
        $calendario = (string)$row['CALENDARIO'];
        $tipo = (string)$row['TIPO'];
        $motivo = (string)($row['MOTIVO'] ?? '');
        $data = (string)$row['DATA'];
        $antes = ['CALENDARIO' => $calendario, 'DATA' => $data, 'TIPO' => $tipo, 'MOTIVO' => $motivo];
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
    if (!$erros) $erros[] = 'Não foi possível salvar a exceção (a data já pode ter exceção neste calendário).';
    ch_flash(implode(' ', $erros), 'danger');
}
header('Location: ' . $voltar);
exit;
