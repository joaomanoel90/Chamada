<?php
/* ============================================================================
   NOVO USUÁRIO
   ----------------------------------------------------------------------------
   Este arquivo era uma cópia do processador de EDIÇÃO: exigia ?id= na URL e
   mandava de volta para a listagem quando não havia id — ou seja, o botão
   "Novo Usuário" nunca abria o formulário. E no POST fazia UPDATE em vez de
   INSERT, sem gravar o nível. Reescrito como cadastro.
   ============================================================================ */
require_once(__DIR__ . '/comum.php');

$erros = [];
$d = ['nome' => '', 'email' => '', 'cpf' => '', 'nivel' => 0, 'ativo' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: add.php');
        exit;
    }

    $erros = usuario_validar($_POST, $d, 0);

    if (!$erros) {
        $ok = ch_exec(
            'INSERT INTO TB_USUARIOS (NOME, SENHA, NIVEL, CPF, EMAIL, ATIVO, CREATED, USUARIO)
             VALUES (?, ?, ?, ?, ?, ?, GETDATE(), ?)',
            [$d['nome'], md5(trim($_POST['senha'])), $d['nivel'], $d['cpf'],
             $d['email'] !== '' ? $d['email'] : null, $d['ativo'], ch_uid()]
        );
        if ($ok) {
            ch_flash('Usuário "' . $d['nome'] . '" cadastrado como ' . ch_nivel_nome($d['nivel']) . '.');
            if ($d['nivel'] === 2) {
                ch_flash('Agora libere as unidades deste diretor em "Acesso Unidades".', 'info');
            } elseif ($d['nivel'] === 3) {
                ch_flash('Agora inclua este professor na equipe de uma turma.', 'info');
            }
            header('Location: index.php');
            exit;
        }
        $erros[] = 'Não foi possível gravar o usuário. Tente novamente.';
    }
}
