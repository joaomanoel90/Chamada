<?php
/* ============================================================================
   EDITAR USUÁRIO
   ----------------------------------------------------------------------------
   Acrescentado o que faltava: trava de administrador AQUI no processador (antes
   a checagem ficava no edit.php depois do cabeçalho, quando o header() já não
   tinha efeito — um professor conseguia alterar usuários por URL direta),
   conferência do CSRF, validação dos campos e proteção para o administrador não
   se rebaixar nem se desativar sozinho e ficar trancado fora do sistema.
   ============================================================================ */
require_once(__DIR__ . '/comum.php'); // prelude + usuario_validar()

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

/* CREATED e MODIFIED saem como TEXTO. O sqlsrv devolve objeto DateTime para
   coluna datetime, e a tela faz substr((string)...): no SQL Server real isso
   derrubava a página com "Object of class DateTime could not be converted to
   string". Converter no SELECT é a regra do projeto para datas. */
$usuario = ch_row(
    'SELECT ID, NOME, SENHA, NIVEL, CPF, EMAIL, ATIVO, USUARIO, TURNO, CATEGORIA,
            CONVERT(varchar(10), CREATED, 23)  AS CREATED,
            CONVERT(varchar(10), MODIFIED, 23) AS MODIFIED
     FROM TB_USUARIOS WHERE ID = ?',
    [$id]
);
if (!$usuario) {
    ch_flash('Usuário não encontrado.', 'warning');
    header('Location: index.php');
    exit;
}

$erros = [];
$d = [
    'nome'  => $usuario['NOME'],
    'email' => $usuario['EMAIL'] ?? '',
    'cpf'   => $usuario['CPF'],
    'nivel' => (int)$usuario['NIVEL'],
    'ativo' => (int)($usuario['ATIVO'] ?? 1),
    'turno' => $usuario['TURNO'] ?? '',
    'categoria' => $usuario['CATEGORIA'] ?? '',
    'materias'  => array_values(eq_professor_materias($id)),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: edit.php?id=' . $id);
        exit;
    }

    $erros = usuario_validar($_POST, $d, $id);

    // O próprio administrador não pode perder o acesso sem querer.
    if ($id === ch_uid()) {
        if ($d['nivel'] !== 1) $erros[] = 'Você não pode alterar o seu próprio nível de acesso.';
        if (!$d['ativo'])      $erros[] = 'Você não pode desativar o seu próprio usuário.';
    }

    // Não deixa a rede ficar sem nenhum administrador ativo.
    if (!$erros && ($d['nivel'] !== 1 || !$d['ativo'])) {
        $outros = (int)ch_val(
            'SELECT COUNT(*) FROM TB_USUARIOS WHERE NIVEL = 1 AND ISNULL(ATIVO, 1) = 1 AND ID <> ?',
            [$id]
        );
        if ($outros === 0) {
            $erros[] = 'Este é o único administrador ativo. Cadastre outro administrador antes de alterá-lo.';
        }
    }

    if (!$erros) {
        $senha = trim((string)($_POST['senha'] ?? ''));
        sqlsrv_begin_transaction(ch_db());
        if ($senha !== '') {
            $hash = ch_auth_password_hash($senha);
            if ($hash === false) {
                error_log('Falha ao gerar hash ao atualizar senha do usuário #' . $id . '.');
                $ok = false;
            } else {
                $ok = ch_exec(
                    'UPDATE TB_USUARIOS
                        SET NOME = ?, SENHA = ?, NIVEL = ?, CPF = ?, EMAIL = ?, ATIVO = ?, TURNO = ?, CATEGORIA = ?, MODIFIED = GETDATE()
                      WHERE ID = ?',
                    [$d['nome'], $hash, $d['nivel'], $d['cpf'],
                     $d['email'] !== '' ? $d['email'] : null, $d['ativo'], $d['turno'], $d['categoria'], $id]
                );
            }
        } else {
            $ok = ch_exec(
                'UPDATE TB_USUARIOS
                    SET NOME = ?, NIVEL = ?, CPF = ?, EMAIL = ?, ATIVO = ?, TURNO = ?, CATEGORIA = ?, MODIFIED = GETDATE()
                  WHERE ID = ?',
                [$d['nome'], $d['nivel'], $d['cpf'],
                 $d['email'] !== '' ? $d['email'] : null, $d['ativo'], $d['turno'], $d['categoria'], $id]
            );
        }
        /* Matérias que o professor pode lecionar: sincroniza (só professor). */
        if ($ok && $d['nivel'] === 3) {
            $atuais = ch_rows('SELECT NOME FROM TB_PROFESSOR_MATERIAS WHERE ID_USUARIO = ?', [$id]) ?: [];
            $novos = [];
            foreach ($d['materias'] as $nome) {
                $novos[mb_strtolower($nome, 'UTF-8')] = $nome;
            }
            foreach ($atuais as $a) {
                $chave = mb_strtolower((string)$a['NOME'], 'UTF-8');
                if (!isset($novos[$chave])) {
                    if (!ch_exec('DELETE FROM TB_PROFESSOR_MATERIAS WHERE ID_USUARIO = ? AND NOME = ?', [$id, $a['NOME']])) {
                        $ok = false;
                        break;
                    }
                }
            }
            if ($ok) {
                $jaTem = [];
                foreach ($atuais as $a) {
                    $jaTem[mb_strtolower((string)$a['NOME'], 'UTF-8')] = true;
                }
                foreach ($novos as $chave => $nome) {
                    if (!isset($jaTem[$chave])) {
                        if (!ch_exec('INSERT INTO TB_PROFESSOR_MATERIAS (ID_USUARIO, NOME) VALUES (?, ?)', [$id, $nome])) {
                            $ok = false;
                            break;
                        }
                    }
                }
            }
        }
        if ($ok) {
            $ok = (bool)sqlsrv_commit(ch_db());
        } else {
            sqlsrv_rollback(ch_db());
        }
        if ($ok) {
            ch_auditar('Usuários', 'Atualizar usuário', 'USUARIO', $id, [
                'ANTES' => [
                    'NOME'  => (string)$usuario['NOME'],
                    'NIVEL' => (int)$usuario['NIVEL'],
                    'ATIVO' => (int)($usuario['ATIVO'] ?? 1),
                ],
                'DEPOIS' => [
                    'NOME'  => $d['nome'],
                    'NIVEL' => $d['nivel'],
                    'ATIVO' => $d['ativo'],
                ],
                'SENHA_ALTERADA' => $senha !== '' ? 1 : 0,
            ]);
            ch_flash('Usuário "' . $d['nome'] . '" atualizado.' . ($senha !== '' ? ' A senha foi alterada.' : ''));
            header('Location: index.php');
            exit;
        }
        $erros[] = 'Não foi possível gravar as alterações. Tente novamente.';
    }
}
