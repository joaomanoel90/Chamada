<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
require_once(__DIR__ . '/salvar_processa.php');
ch_db();
ch_require_gestao_alunos();

$turmasDisponiveis = aluno_turmas_disponiveis();
$erros = [];
$aluno = null;
$cpf = '';
$d = ['ID_TURMA' => 0, 'DATA_RETORNO' => ch_hoje()];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('alunos/retorno.php'));
        exit;
    }

    $cpf = aluno_cpf_normalizar($_POST['cpf'] ?? '');
    if (!aluno_cpf_valido($cpf)) {
        $erros[] = 'Informe um CPF válido para localizar o cadastro existente.';
    } else {
        $aluno = ch_row(
            'SELECT a.ID, a.NOME, a.CPF, a.MATRICULA, a.ATIVO,
                    CONVERT(varchar(10), a.NASCIMENTO, 23) AS NASCIMENTO,
                    CONVERT(varchar(10), s.DATA_SAIDA, 23) AS DATA_SAIDA,
                    s.ESCOLA_DESTINO, s.MUNICIPIO_DESTINO, s.UF_DESTINO,
                    s.ID AS ID_SAIDA
             FROM TB_ALUNOS a
             INNER JOIN TB_ALUNO_SAIDAS_EXTERNAS s ON s.ID_ALUNO = a.ID
             WHERE a.CPF = ? AND a.ATIVO = 0 AND s.DATA_RETORNO IS NULL',
            [$cpf]
        );
        if (!$aluno) {
            $erros[] = 'Não foi encontrado cadastro com saída externa aguardando retorno para esse CPF. Se o aluno ainda não tem cadastro, use Novo Aluno.';
        }
    }

    if ($aluno && ($_POST['acao'] ?? '') === 'retornar') {
        $d['ID_TURMA'] = (int)($_POST['id_turma'] ?? 0);
        $d['DATA_RETORNO'] = ch_data_iso($_POST['data_retorno'] ?? '');
        $turma = null;
        foreach ($turmasDisponiveis as $opcao) {
            if ((int)$opcao['ID'] === $d['ID_TURMA']
                && ch_pode_unidade((int)$opcao['ID_UNIDADE'])) {
                $turma = $opcao;
                break;
            }
        }
        $dataMinima = date('Y-m-d', strtotime($aluno['DATA_SAIDA'] . ' +1 day'));
        if (!$turmasDisponiveis) {
            $erros[] = 'Não há turmas ativas nas unidades vinculadas ao seu usuário. Solicite à gestão que vincule uma unidade com turma ativa.';
        } elseif (!$turma) {
            $erros[] = 'Selecione uma turma ativa de uma unidade vinculada ao seu usuário.';
        }
        if (!$d['DATA_RETORNO']) {
            $erros[] = 'Informe a data de início da nova matrícula.';
        } elseif ($d['DATA_RETORNO'] < $dataMinima) {
            $erros[] = 'O retorno deve ser posterior ao último dia frequentado na escola anterior (' . ch_data_br($aluno['DATA_SAIDA']) . ').';
        } elseif ($d['DATA_RETORNO'] < $aluno['NASCIMENTO']) {
            $erros[] = 'A data de retorno não pode ser anterior ao nascimento.';
        }
        if (!$erros && $turma) {
            $ocupadas = (int)ch_val(
                'SELECT COUNT(*) FROM TB_ALUNOS WHERE ID_TURMA = ? AND ATIVO = 1',
                [(int)$turma['ID']]
            );
            if ($ocupadas >= (int)$turma['CAPACIDADE']) {
                $erros[] = 'A turma ' . $turma['NOME'] . ' já atingiu a capacidade de ' . (int)$turma['CAPACIDADE'] . ' alunos.';
            }
        }

        if (!$erros && $turma) {
            sqlsrv_begin_transaction(ch_db());
            $saidaAtualizada = ch_afetadas(
                'UPDATE TB_ALUNO_SAIDAS_EXTERNAS
                 SET DATA_RETORNO = ?, ID_TURMA_RETORNO = ?, ID_USUARIO_RETORNO = ?, RETORNADA_EM = GETDATE()
                 WHERE ID = ? AND DATA_RETORNO IS NULL',
                [$d['DATA_RETORNO'], (int)$turma['ID'], ch_uid(), (int)$aluno['ID_SAIDA']]
            );
            $alunoAtualizado = $saidaAtualizada === 1
                ? ch_afetadas(
                    'UPDATE TB_ALUNOS SET ID_TURMA = ?, DATA_MATRICULA = ?, ATIVO = 1
                     WHERE ID = ? AND ATIVO = 0',
                    [(int)$turma['ID'], $d['DATA_RETORNO'], (int)$aluno['ID']]
                )
                : false;
            if ($saidaAtualizada === 1 && $alunoAtualizado === 1) {
                if (sqlsrv_commit(ch_db())) {
                    ch_flash('Retorno registrado no cadastro existente. A matrícula e a turma foram atualizadas, sem duplicar o aluno.');
                    header('Location: ' . ch_url('alunos/view.php?id=' . (int)$aluno['ID']));
                    exit;
                }
            }

            sqlsrv_rollback(ch_db());
            $erros[] = 'O cadastro mudou durante o processo ou não foi possível registrar o retorno. Atualize a página e tente novamente.';
            if (ch_admin()) { $erros[] = ch_erro_sql(); }
        }
    }
}
