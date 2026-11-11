<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$modoObservacoes = isset($_GET['observacoes']);
if (!ch_diretor()) {
    ch_flash('Somente o Diretor Escolar pode autorizar permissões por unidade.', 'danger');
    header('Location: ' . BASEURL . 'index.php');
    exit;
}

$unidadesDiretor = ch_unidades_ids();
$secretarios = [];
$tabelaPermissao = $modoObservacoes ? 'TB_SECRETARIO_OBSERVACOES' : 'TB_SECRETARIO_RELATORIOS';
$colunaContagem = 'UNIDADES_LIBERADAS';
if ($unidadesDiretor) {
    $ph = implode(',', array_fill(0, count($unidadesDiretor), '?'));
    $secretarios = ch_rows(
        "SELECT DISTINCT u.ID, u.NOME,
                (SELECT COUNT(*) FROM {$tabelaPermissao} p
                  INNER JOIN TB_ACESSO a2
                          ON a2.id_usuario = p.ID_USUARIO
                         AND a2.id_unidade = p.ID_UNIDADE
                         AND ISNULL(a2.ativo, 1) = 1
                 WHERE p.ID_USUARIO = u.ID AND p.PERMITIDO >= 1
                   AND p.ID_UNIDADE IN ({$ph})) AS {$colunaContagem}
           FROM TB_USUARIOS u
           INNER JOIN TB_ACESSO a ON a.id_usuario = u.ID AND ISNULL(a.ativo, 1) = 1
          WHERE u.NIVEL = 5 AND ISNULL(u.ATIVO, 1) = 1
            AND a.id_unidade IN ({$ph})
          ORDER BY u.NOME",
        array_merge($unidadesDiretor, $unidadesDiretor)
    );
}
