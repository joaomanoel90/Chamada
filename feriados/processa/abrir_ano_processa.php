<?php
/* Abre um ano letivo definindo, de uma vez, o período dos dois calendários
   globais. Somente a rede (níveis 1 e 4) altera. */
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

$ano = (int)($_POST['ano'] ?? 0);
$periodos = [];
$erros = [];

foreach (ch_calendarios_opcoes() as $codigo => $rotulo) {
    $chave = strtolower($codigo);
    $inicio = ch_data_iso((string)($_POST['inicio_' . $chave] ?? ''));
    $fim = ch_data_iso((string)($_POST['fim_' . $chave] ?? ''));
    $periodos[$codigo] = ['INICIO' => $inicio, 'FIM' => $fim, 'ROTULO' => $rotulo];

    if (!$inicio || !$fim) {
        $erros[] = 'Informe início e fim válidos para ' . $rotulo . '.';
    } elseif ((int)substr($inicio, 0, 4) !== $ano || (int)substr($fim, 0, 4) !== $ano) {
        $erros[] = 'As datas de ' . $rotulo . ' devem ser do ano letivo informado.';
    } elseif ($fim < $inicio) {
        $erros[] = 'O fim de ' . $rotulo . ' não pode ser anterior ao início.';
    }
}

if ($ano < 2000 || $ano > 2100) {
    $erros[] = 'Ano letivo inválido.';
}
if (!$erros && (!ch_tabela_calendarios() || !ch_calendario_tem_fim())) {
    $erros[] = 'Banco desatualizado. Rode sql_inicial/ATUALIZAR_CALENDARIO_ESCOLAR.sql.';
}

if (!$erros) {
    sqlsrv_begin_transaction(ch_db());
    $salvos = [];
    $falhou = false;

    foreach ($periodos as $codigo => $p) {
        $atual = ch_row(
            'SELECT ID, CONVERT(varchar(10), INICIO_ANO, 23) AS INICIO,
                    CONVERT(varchar(10), FIM_ANO, 23) AS FIM
               FROM dbo.TB_CALENDARIOS WHERE ANO_LETIVO = ? AND CALENDARIO = ?',
            [$ano, $codigo]
        );
        $antes = $atual ? ['INICIO' => (string)$atual['INICIO'], 'FIM' => (string)$atual['FIM']] : null;

        if ($atual) {
            $id = (int)$atual['ID'];
            $ok = ch_exec(
                'UPDATE dbo.TB_CALENDARIOS SET INICIO_ANO = ?, FIM_ANO = ?, UPDATED = GETDATE() WHERE ID = ?',
                [$p['INICIO'], $p['FIM'], $id]
            );
        } else {
            $id = (int)ch_insert_id(
                'INSERT INTO dbo.TB_CALENDARIOS (ANO_LETIVO, CALENDARIO, INICIO_ANO, FIM_ANO)
                 OUTPUT INSERTED.ID VALUES (?, ?, ?, ?)',
                [$ano, $codigo, $p['INICIO'], $p['FIM']]
            );
            $ok = $id > 0;
        }

        if (!$ok || $id <= 0) {
            $falhou = true;
            break;
        }
        $salvos[] = [
            'ID' => $id,
            'CALENDARIO' => $codigo,
            'ANTES' => $antes,
            'DEPOIS' => ['ANO_LETIVO' => $ano, 'INICIO' => $p['INICIO'], 'FIM' => $p['FIM']],
        ];
    }

    if ($falhou) {
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível abrir o ano letivo.';
    } else {
        sqlsrv_commit(ch_db());
        foreach ($salvos as $item) {
            ch_auditar('Calendário Escolar',
                $item['ANTES'] ? 'Atualizar período letivo' : 'Abrir ano letivo',
                'CALENDARIO', $item['ID'], ['ANTES' => $item['ANTES'], 'DEPOIS' => $item['DEPOIS']]);
        }
        ch_flash('Ano letivo ' . $ano . ' aberto para os dois calendários.');
        header('Location: ../index.php?ano=' . $ano);
        exit;
    }
}

if ($erros) {
    ch_flash(implode(' ', $erros), 'danger');
}
header('Location: ../index.php?ano=' . ($ano > 0 ? $ano : (int)date('Y')));
exit;
