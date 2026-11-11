<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

if (!ch_secretaria_educacao()) {
    ch_flash('Acesso restrito à Secretaria de Educação.', 'danger');
    header('Location: ' . BASEURL . 'index.php');
    exit;
}

$usuarios = ch_rows(
    'SELECT u.ID, u.NOME, u.NIVEL,
            (SELECT COUNT(*) FROM TB_ACESSO a
              WHERE a.id_usuario = u.ID AND ISNULL(a.ativo, 1) = 1) AS UNIDADES
       FROM TB_USUARIOS u
      WHERE u.NIVEL IN (2, 5) AND ISNULL(u.ATIVO, 1) = 1
      ORDER BY u.NIVEL, u.NOME'
);
