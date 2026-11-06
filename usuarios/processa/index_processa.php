<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_admin();

$usuarios = ch_rows(
    'SELECT ID, NOME, CPF, EMAIL, NIVEL, ISNULL(ATIVO, 1) AS ATIVO,
            CONVERT(varchar(10), CREATED, 23) AS CRIADO
       FROM TB_USUARIOS
      ORDER BY NOME'
);

/* Quantas unidades o diretor tem liberadas e em quantas turmas o professor
   está — ajuda a ver de imediato quem está cadastrado mas sem alcance. */
$alcance = [];
foreach ($usuarios as $u) {
    $id = (int)$u['ID'];
    if ((int)$u['NIVEL'] === 2) {
        $alcance[$id] = (int)ch_val('SELECT COUNT(*) FROM TB_ACESSO WHERE id_usuario = ? AND ativo = 1', [$id]);
    } elseif ((int)$u['NIVEL'] === 3) {
        $alcance[$id] = (int)ch_val('SELECT COUNT(*) FROM TB_TURMA_PROFESSORES WHERE ID_USUARIO = ? AND ATIVO = 1', [$id]);
    }
}
