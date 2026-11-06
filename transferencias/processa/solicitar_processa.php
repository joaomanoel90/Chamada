<?php
/* ============================================================================
   ETAPA 1 — o diretor da escola de origem indica a escola de destino
   ----------------------------------------------------------------------------
   Aqui NÃO se escolhe turma nem data: isso é decisão de quem recebe. O aluno
   continua na turma atual, e a chamada segue normal, até o destino aceitar.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$idAluno = (int)($_POST['aluno_id'] ?? $_GET['aluno_id'] ?? 0);
$aluno   = ch_exige_aluno($idAluno);
$destino = ch_url('transferencias/index.php');

/* ch_exige_aluno também deixa entrar quem só tem o histórico do aluno. Para
   INDICAR a transferência é preciso ser gestor da escola em que ele está hoje. */
if (!ch_gestor() || !ch_pode_unidade($aluno['ID_UNIDADE'])) {
    ch_flash('Só a gestão da escola em que o aluno está matriculado pode iniciar a transferência.', 'danger');
    header('Location: ' . ch_url('alunos/view.php?id=' . $idAluno));
    exit;
}
if ((int)$aluno['ATIVO'] !== 1) {
    ch_flash('Este aluno está inativo. Reative o cadastro antes de transferir.', 'danger');
    header('Location: ' . ch_url('alunos/view.php?id=' . $idAluno));
    exit;
}

$pendente = tr_pendente($idAluno);
if ($pendente) {
    ch_flash('Já existe uma transferência aguardando para este aluno.', 'warning');
    header('Location: ' . ch_url('transferencias/ver.php?id=' . (int)$pendente['ID']));
    exit;
}

$unidades = tr_unidades_destino($aluno['ID_UNIDADE']);
$erros = [];
$d = ['ID_UNIDADE_DESTINO' => 0, 'MOTIVO' => '', 'DOCUMENTO' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('transferencias/solicitar.php?aluno_id=' . $idAluno));
        exit;
    }

    $d['ID_UNIDADE_DESTINO'] = (int)($_POST['id_unidade_destino'] ?? 0);
    $d['MOTIVO']    = strip_tags(trim((string)($_POST['motivo'] ?? '')));
    $d['DOCUMENTO'] = strip_tags(trim((string)($_POST['documento'] ?? '')));

    $escolhida = null;
    foreach ($unidades as $u) {
        if ((int)$u['id'] === $d['ID_UNIDADE_DESTINO']) { $escolhida = $u; break; }
    }
    if (!$escolhida) {
        $erros[] = 'Selecione a escola de destino. Não pode ser a escola atual do aluno.';
    }
    if ($d['MOTIVO'] === '') {
        $erros[] = 'Informe o motivo da transferência.';
    } elseif (mb_strlen($d['MOTIVO']) > 500) {
        $erros[] = 'O motivo deve ter no máximo 500 caracteres.';
    }
    if (mb_strlen($d['DOCUMENTO']) > 100) {
        $erros[] = 'A referência do documento deve ter no máximo 100 caracteres.';
    }

    /* Conferido de novo no servidor: outra pessoa pode ter aberto uma
       transferência para o mesmo aluno enquanto esta tela estava aberta. */
    if (!$erros && tr_pendente($idAluno)) {
        $erros[] = 'Já existe uma transferência aguardando para este aluno.';
    }

    if (!$erros) {
        $novoId = ch_insert_id(
            'INSERT INTO TB_TRANSFERENCIAS
                (ID_ALUNO, ID_TURMA_ORIGEM, ID_UNIDADE_DESTINO, MOTIVO, DOCUMENTO,
                 ID_USUARIO_SOLIC, SITUACAO)
             OUTPUT INSERTED.ID
             VALUES (?, ?, ?, ?, ?, ?, \'PENDENTE\')',
            [$idAluno, (int)$aluno['ID_TURMA'], $d['ID_UNIDADE_DESTINO'], $d['MOTIVO'],
             $d['DOCUMENTO'] !== '' ? $d['DOCUMENTO'] : null, ch_uid()]
        );
        if ($novoId > 0) {
            ch_flash('Transferência registrada. A escola ' . $escolhida['unidade']
                   . ' vai escolher a turma e a data de início. O aluno continua na turma atual até lá.');
            header('Location: ' . ch_url('transferencias/ver.php?id=' . $novoId));
            exit;
        }
        $erros[] = 'Não foi possível registrar a transferência.';
        if (ch_admin()) { $erros[] = ch_erro_sql(); }
    }
}