<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
require_once(__DIR__ . '/salvar_processa.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$atual = ch_row('SELECT ID, CONVERT(varchar(10), DATA, 23) AS DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM, ID_UNIDADE
                 FROM TB_FERIADOS WHERE ID = ?', [$id]);
if (!$atual) {
    ch_flash('Feriado não encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

$erros = [];
$unidades = ch_unidades_opcoes();
$d = $atual;
$d['ID_UNIDADE'] = (int)($atual['ID_UNIDADE'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$d, $erros] = feriado_validar($_POST, $id);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec('UPDATE TB_FERIADOS SET DATA = ?, DESCRICAO = ?, TIPO = ?, HORA_INICIO = ?, HORA_FIM = ?, ID_UNIDADE = ? WHERE ID = ?',
            [$d['DATA'], $d['DESCRICAO'], $d['TIPO'], $d['HORA_INICIO'], $d['HORA_FIM'],
             $d['ID_UNIDADE'] > 0 ? $d['ID_UNIDADE'] : null, $id]);
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Feriado atualizado com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível atualizar o feriado.';
    }
}
