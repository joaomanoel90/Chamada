<?php
/* ============================================================================
   BASE COMUM DO CADASTRO DE USUÁRIOS
   ----------------------------------------------------------------------------
   Fica separado do add_processa.php de propósito: aquele arquivo executa o
   INSERT quando incluído, então o edit_processa.php não pode aproveitá-lo para
   pegar só a função de validação — senão uma edição também cadastraria outro
   usuário. Aqui só há definição de função e a trava de acesso.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_admin();

/**
 * Confere os dados do formulário de usuário.
 * $idAtual = 0 no cadastro (senha obrigatória); > 0 na edição (senha opcional).
 * Preenche $d com os valores normalizados e devolve a lista de erros.
 */
function usuario_validar($post, &$d, $idAtual = 0)
{
    $erros = [];
    $d['nome']  = trim(strip_tags($post['nome'] ?? ''));
    $d['email'] = trim($post['email'] ?? '');
    $d['cpf']   = ch_cpf_mask($post['cpf'] ?? '');
    $d['nivel'] = (int)($post['nivel'] ?? 0);
    $d['ativo'] = !empty($post['ativo']) ? 1 : 0;
    $senha      = trim($post['senha'] ?? '');

    /* Turno de trabalho. Só faz sentido para professor; os demais perfis não
       usam esse valor ao montar a equipe da turma. */
    $turno = strtoupper(trim((string)($post['turno'] ?? '')));
    $d['turno'] = ($d['nivel'] === 3 && isset(eq_turnos()[$turno])) ? $turno : null;

    if ($d['nome'] === '' || mb_strlen($d['nome']) < 3) {
        $erros[] = 'Informe o nome completo (ao menos 3 letras).';
    } elseif (mb_strlen($d['nome']) > 255) {
        $erros[] = 'O nome pode ter no máximo 255 caracteres.';
    }

    if (strlen(ch_digitos($d['cpf'])) !== 11) {
        $erros[] = 'O CPF deve ter 11 dígitos.';
    }

    if ($d['email'] !== '') {
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido.';
        } elseif (mb_strlen($d['email']) > 150) {
            $erros[] = 'O e-mail pode ter no máximo 150 caracteres.';
        }
    }

    if (!in_array($d['nivel'], [1, 2, 3, 4, 5], true)) {
        $erros[] = 'Escolha o nível de acesso.';
    }

    /* O turno é o padrão ao montar a equipe, não uma obrigação: deixamos em
       branco quem ainda não teve o turno definido, e a gestão escolhe na turma. */
    if ($d['nivel'] === 3 && $turno !== '' && !isset(eq_turnos()[$turno])) {
        $erros[] = 'Turno inválido. Escolha manhã, tarde ou integral.';
    }

    if ($idAtual === 0 && $senha === '') {
        $erros[] = 'Defina uma senha.';
    }
    if ($senha !== '' && mb_strlen($senha) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    }

    // CPF duplicado — compara só os dígitos, para que "12345678901" e
    // "123.456.789-01" não passem como pessoas diferentes.
    if (strlen(ch_digitos($d['cpf'])) === 11) {
        $jaExiste = ch_val(
            "SELECT COUNT(*) FROM TB_USUARIOS
              WHERE REPLACE(REPLACE(REPLACE(CPF, '.', ''), '-', ''), ' ', '') = ?
                AND ID <> ?",
            [ch_digitos($d['cpf']), $idAtual]
        );
        if ((int)$jaExiste > 0) {
            $erros[] = 'Já existe um usuário cadastrado com este CPF.';
        }
    }

    return $erros;
}

/** Lista de erros em HTML, ou vazio. */
function usuario_erros_html($erros)
{
    if (!$erros) return '';
    $html = '<div class="alert alert-danger"><strong>Não foi possível salvar:</strong><ul class="mb-0 mt-1">';
    foreach ($erros as $e) $html .= '<li>' . ch_h($e) . '</li>';
    return $html . '</ul></div>';
}
