<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

$nivel = $_SESSION['usuarioNiveisAcessoId'] ?? 0;

if ($nivel == 3) {

    $diaAtual = (int) date('d');

    // permitido apenas do dia 20 ao 25
    if ($diaAtual < 20 || $diaAtual > 25) {

        // mensagem
        // $_SESSION['loginErro'] =
        //     "Usuários desta unidade podem acessar o sistema apenas entre os dias 20 e 25 de cada mês.";

        // // remove login
        // unset(
        //     $_SESSION['usuarioId'],
        //     $_SESSION['usuarioNome'],
        //     $_SESSION['usuarioNiveisAcessoId'],
        //     $_SESSION['usuarioCPF'],
        //     $_SESSION['usuarioSenha']
        // );

        // header("Location: login.php");
        // exit;
    }
}
