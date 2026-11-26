<?php
/* ============================================================================
   FAMÍLIA E RESPONSÁVEIS VINCULADOS POR CPF
   ----------------------------------------------------------------------------
   Permite vincular Pai, Mãe e/ou Outro responsável a um ou mais alunos pelo
   CPF (TB_RESPONSAVEIS + TB_ALUNO_RESPONSAVEIS), reaproveitando o cadastro
   quando o CPF já existe no sistema e permitindo pesquisar todos os filhos
   de um pai/mãe/responsável.
   ============================================================================ */
if (!defined('ABSPATH')) {
    http_response_code(404);
    exit;
}

/** Tipos de vínculo familiar suportados por aluno. */
function fam_parentescos()
{
    return [
        'MAE'   => 'Mãe',
        'PAI'   => 'Pai',
        'OUTRO' => 'Outro responsável',
    ];
}

/** Rótulo legível do parentesco (incluindo descrição quando OUTRO). */
function fam_parentesco_rotulo($parentesco, $descricaoOutro = '')
{
    $p = strtoupper(trim((string)$parentesco));
    if ($p === 'OUTRO' && trim((string)$descricaoOutro) !== '') {
        return 'Outro (' . trim((string)$descricaoOutro) . ')';
    }
    return fam_parentescos()[$p] ?? 'Responsável';
}

/** Mantém apenas os dígitos do CPF (alias do helper central). */
function fam_cpf_limpo($cpf)
{
    return ch_digitos($cpf);
}

/** Valida CPF com dígitos verificadores (alias do helper central). */
function fam_cpf_valido($cpf)
{
    return ch_cpf_valido($cpf);
}

/** Bases antigas não tinham a flag de restrição: todo contato era liberado. */
function fam_tem_restricao()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.columns
              WHERE object_id = OBJECT_ID(N'dbo.TB_ALUNO_RESPONSAVEIS') AND name = N'RESTRITO_CONTATO'"
        );
    }
    return $tem;
}

/** Coluna segura para leituras em bancos migrados e não migrados. */
function fam_restricao_col($alias = '')
{
    $prefix = $alias !== '' ? rtrim((string)$alias, '.') . '.' : '';
    return fam_tem_restricao() ? $prefix . 'RESTRITO_CONTATO' : 'CAST(0 AS bit)';
}

/** Estrutura vazia para os 3 blocos familiares do formulário do aluno. */
function fam_estrutura_vazia()
{
    return [
        'MAE'   => ['CPF' => '', 'NOME' => '', 'TELEFONE' => '', 'EMAIL' => '', 'DESCRICAO_OUTRO' => '', 'CONTATO_PRINCIPAL' => 1, 'RESTRITO_CONTATO' => 0],
        'PAI'   => ['CPF' => '', 'NOME' => '', 'TELEFONE' => '', 'EMAIL' => '', 'DESCRICAO_OUTRO' => '', 'CONTATO_PRINCIPAL' => 0, 'RESTRITO_CONTATO' => 0],
        'OUTRO' => ['CPF' => '', 'NOME' => '', 'TELEFONE' => '', 'EMAIL' => '', 'DESCRICAO_OUTRO' => '', 'CONTATO_PRINCIPAL' => 0, 'RESTRITO_CONTATO' => 0],
    ];
}

/** Lista os responsáveis vinculados a um aluno. */
function fam_responsaveis_aluno($idAluno)
{
    $idAluno = (int)$idAluno;
    if ($idAluno <= 0) return [];
    return ch_rows(
        "SELECT ar.ID, ar.ID_ALUNO, ar.ID_RESPONSAVEL, ar.PARENTESCO,
                ar.DESCRICAO_OUTRO, ar.CONTATO_PRINCIPAL,
                " . fam_restricao_col('ar') . " AS RESTRITO_CONTATO,
                r.CPF, r.NOME, r.TELEFONE, r.EMAIL
           FROM TB_ALUNO_RESPONSAVEIS ar
           INNER JOIN TB_RESPONSAVEIS r ON r.ID = ar.ID_RESPONSAVEL
          WHERE ar.ID_ALUNO = ?
          ORDER BY ar.CONTATO_PRINCIPAL DESC,
                   CASE ar.PARENTESCO WHEN 'MAE' THEN 1 WHEN 'PAI' THEN 2 ELSE 3 END",
        [$idAluno]
    ) ?: [];
}

/** Carrega os responsáveis de um aluno no formato de formulário ['MAE'=>..., 'PAI'=>..., 'OUTRO'=>...]. */
function fam_carregar_para_form($idAluno)
{
    $est = fam_estrutura_vazia();
    $linhas = fam_responsaveis_aluno($idAluno);
    if ($linhas) {
        $est['MAE']['CONTATO_PRINCIPAL'] = 0;
    }
    foreach ($linhas as $l) {
        $p = $l['PARENTESCO'];
        if (!isset($est[$p])) continue;
        $est[$p] = [
            'CPF'               => ch_cpf_mask($l['CPF']),
            'NOME'              => (string)$l['NOME'],
            'TELEFONE'          => (string)($l['TELEFONE'] ?? ''),
            'EMAIL'             => (string)($l['EMAIL'] ?? ''),
            'DESCRICAO_OUTRO'   => (string)($l['DESCRICAO_OUTRO'] ?? ''),
            'CONTATO_PRINCIPAL' => (int)$l['CONTATO_PRINCIPAL'],
            'RESTRITO_CONTATO'  => (int)($l['RESTRITO_CONTATO'] ?? 0),
        ];
    }
    return $est;
}

/** Busca um responsável já cadastrado pelo CPF e lista os filhos já vinculados. */
function fam_buscar_por_cpf($cpf)
{
    $cpf = fam_cpf_limpo($cpf);
    if (strlen($cpf) !== 11) return null;
    $resp = ch_row(
        'SELECT ID, CPF, NOME, TELEFONE, EMAIL FROM TB_RESPONSAVEIS WHERE CPF = ?',
        [$cpf]
    );
    if (!$resp) return null;

    $f = ch_filtro_turma_ou_aee('t', 'a');
    $filhos = ch_rows(
        'SELECT a.ID, a.NOME, a.MATRICULA, a.ATIVO, ar.PARENTESCO, ar.DESCRICAO_OUTRO,
                t.NOME AS TURMA, t.SERIE, t.ANO_LETIVO, u.unidade AS UNIDADE
           FROM TB_ALUNO_RESPONSAVEIS ar
           INNER JOIN TB_ALUNOS a ON a.ID = ar.ID_ALUNO
           INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
           INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
          WHERE ar.ID_RESPONSAVEL = ?' . $f['sql'] . '
          ORDER BY a.ATIVO DESC, a.NOME',
        array_merge([(int)$resp['ID']], $f['params'])
    ) ?: [];

    $resp['FILHOS'] = $filhos;
    return $resp;
}

/** Irmãos / crianças vinculadas aos mesmos responsáveis deste aluno (no alcance de quem consulta). */
function fam_irmaos_aluno($idAluno)
{
    $idAluno = (int)$idAluno;
    if ($idAluno <= 0) return [];
    $f = ch_filtro_turma_ou_aee('t', 'a');
    return ch_rows(
        'SELECT DISTINCT a.ID, a.NOME, a.MATRICULA, a.ATIVO,
                t.NOME AS TURMA, t.SERIE, t.ANO_LETIVO, p.NOME AS PERIODO, u.unidade AS UNIDADE
           FROM TB_ALUNO_RESPONSAVEIS arRef
           INNER JOIN TB_ALUNO_RESPONSAVEIS arIrm ON arIrm.ID_RESPONSAVEL = arRef.ID_RESPONSAVEL
                                                  AND arIrm.ID_ALUNO <> arRef.ID_ALUNO
           INNER JOIN TB_ALUNOS a   ON a.ID = arIrm.ID_ALUNO
           INNER JOIN TB_TURMAS t   ON t.ID = a.ID_TURMA
           INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
           INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
          WHERE arRef.ID_ALUNO = ?' . $f['sql'] . '
          ORDER BY a.ATIVO DESC, a.NOME',
        array_merge([$idAluno], $f['params'])
    ) ?: [];
}

/**
 * Extrai e valida os dados de Mãe, Pai e Outro responsável enviados no POST
 * do cadastro/edição do aluno. Na edição, preserva a restrição já registrada
 * para cada CPF vinculado: a restrição é gerida fora deste formulário.
 *
 * Devolve o array normalizado no mesmo formato de fam_estrutura_vazia().
 */
function fam_extrair_e_validar($post, $cpfAlunoLimpo, &$erros, $idAluno = 0)
{
    $principalEscolhido = strtoupper(trim((string)($post['fam_contato_principal'] ?? 'MAE')));
    if (!isset(fam_parentescos()[$principalEscolhido])) {
        $principalEscolhido = 'MAE';
    }

    $fam = fam_estrutura_vazia();
    $cpfsUsados = [];
    $algumPreenchido = false;

    foreach (fam_parentescos() as $cod => $rotulo) {
        $pref = 'fam_' . strtolower($cod) . '_';
        $cpfBruto = trim((string)($post[$pref . 'cpf'] ?? ''));
        $cpfLimpo = fam_cpf_limpo($cpfBruto);
        $nome     = mb_substr(strip_tags(trim((string)($post[$pref . 'nome'] ?? ''))), 0, 150, 'UTF-8');
        $tel      = mb_substr(strip_tags(trim((string)($post[$pref . 'telefone'] ?? ''))), 0, 30, 'UTF-8');
        $email    = mb_substr(trim((string)($post[$pref . 'email'] ?? '')), 0, 120, 'UTF-8');
        $desc     = $cod === 'OUTRO'
            ? mb_substr(strip_tags(trim((string)($post[$pref . 'descricao'] ?? ''))), 0, 60, 'UTF-8')
            : '';

        $temAlgo = ($cpfBruto !== '' || $nome !== '' || $tel !== '' || $email !== '' || $desc !== '');
        $fam[$cod] = [
            'CPF'               => $cpfBruto,
            'CPF_LIMPO'         => $cpfLimpo,
            'NOME'              => $nome,
            'TELEFONE'          => $tel,
            'EMAIL'             => $email,
            'DESCRICAO_OUTRO'   => $desc,
            'CONTATO_PRINCIPAL' => $principalEscolhido === $cod ? 1 : 0,
            'RESTRITO_CONTATO'  => 0,
            'PREENCHIDO'        => $temAlgo ? 1 : 0,
        ];

        if (!$temAlgo) continue;
        $algumPreenchido = true;

        if ($cpfBruto === '') {
            $erros[] = "Informe o CPF de {$rotulo} para vincular pelo CPF.";
        } elseif (!preg_match('/^(?:[0-9]{11}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2})$/D', $cpfBruto) || !fam_cpf_valido($cpfLimpo)) {
            $erros[] = "CPF de {$rotulo} inválido. Confira os números digitados.";
        } elseif ($cpfAlunoLimpo !== '' && $cpfLimpo === $cpfAlunoLimpo) {
            $erros[] = "O CPF de {$rotulo} não pode ser igual ao CPF do próprio aluno.";
        } elseif (isset($cpfsUsados[$cpfLimpo])) {
            $erros[] = "O mesmo CPF não pode ser repetido em {$cpfsUsados[$cpfLimpo]} e {$rotulo}.";
        } else {
            $cpfsUsados[$cpfLimpo] = $rotulo;
            $fam[$cod]['CPF'] = ch_cpf_mask($cpfLimpo);

            /* A edição do cadastro não altera a restrição: ela é registrada
               pela tela própria, com motivo e histórico. Recupera o estado
               atual deste CPF para não reinserir o vínculo como liberado. */
            if ((int)$idAluno > 0 && fam_tem_restricao()) {
                $vinculoAtual = ch_row(
                    'SELECT ar.RESTRITO_CONTATO
                       FROM TB_ALUNO_RESPONSAVEIS ar
                       INNER JOIN TB_RESPONSAVEIS r ON r.ID = ar.ID_RESPONSAVEL
                      WHERE ar.ID_ALUNO = ? AND r.CPF = ?',
                    [(int)$idAluno, $cpfLimpo]
                );
                if ($vinculoAtual) {
                    $fam[$cod]['RESTRITO_CONTATO'] = (int)$vinculoAtual['RESTRITO_CONTATO'];
                }
            }
        }

        if ($tel === '') {
            $erros[] = "Informe o telefone de contato de {$rotulo}.";
        }
        if ($nome === '') {
            $erros[] = "Informe o nome completo de {$rotulo}.";
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = "E-mail de {$rotulo} inválido.";
        }
    }

    /* Garante que se o contato principal marcado estiver vazio mas outro
       responsável foi preenchido, o primeiro preenchido assume como principal. */
    if ($algumPreenchido && empty($fam[$principalEscolhido]['PREENCHIDO'])) {
        foreach (['MAE', 'PAI', 'OUTRO'] as $cod) {
            $fam[$cod]['CONTATO_PRINCIPAL'] = 0;
        }
        foreach (['MAE', 'PAI', 'OUTRO'] as $cod) {
            if (!empty($fam[$cod]['PREENCHIDO'])) {
                $fam[$cod]['CONTATO_PRINCIPAL'] = 1;
                break;
            }
        }
    }

    return $fam;
}

/** Devolve o responsável principal preenchido na estrutura familiar, ou null. */
function fam_contato_principal_dados($fam, $incluirRestritos = true)
{
    if ($incluirRestritos) {
        foreach (['MAE', 'PAI', 'OUTRO'] as $cod) {
            if (!empty($fam[$cod]['PREENCHIDO']) && !empty($fam[$cod]['CONTATO_PRINCIPAL'])) {
                return $fam[$cod];
            }
        }
        foreach (['MAE', 'PAI', 'OUTRO'] as $cod) {
            if (!empty($fam[$cod]['PREENCHIDO'])) {
                return $fam[$cod];
            }
        }
        return null;
    }

    /* Preferência para os campos legados do aluno: um contato liberado no
       crachá, mesmo que o principal marcado seja restrito. */
    foreach (['MAE', 'PAI', 'OUTRO'] as $cod) {
        if (!empty($fam[$cod]['PREENCHIDO']) && empty($fam[$cod]['RESTRITO_CONTATO'])
            && !empty($fam[$cod]['CONTATO_PRINCIPAL'])) {
            return $fam[$cod];
        }
    }
    foreach (['MAE', 'PAI', 'OUTRO'] as $cod) {
        if (!empty($fam[$cod]['PREENCHIDO']) && empty($fam[$cod]['RESTRITO_CONTATO'])) {
            return $fam[$cod];
        }
    }
    return null;
}

/** Contatos liberados para impressão no crachá (restritos ficam de fora). */
function fam_contatos_cracha($idAluno)
{
    $liberados = [];
    foreach (fam_responsaveis_aluno($idAluno) as $resp) {
        if (empty($resp['RESTRITO_CONTATO'])) {
            $liberados[] = $resp;
        }
    }
    return $liberados;
}

/** Tabela de histórico das ações de restrição já foi criada pela migração? */
function fam_tem_historico_restricao()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.objects
              WHERE object_id = OBJECT_ID(N'dbo.TB_RESTRICAO_FAMILIA_HISTORICO') AND type = 'U'"
        );
    }
    return $tem;
}

/**
 * Histórico append-only de restringir/liberar contatos de um aluno.
 * Cada linha guarda data, ação, motivo e quem fez — mesmo que o vínculo tenha
 * sido recriado por uma edição posterior do cadastro.
 */
function fam_historico_restricoes($idAluno)
{
    $idAluno = (int)$idAluno;
    if ($idAluno <= 0 || !fam_tem_historico_restricao()) return [];
    $colDesc = fam_tem_coluna_historico_restricao() ? 'h.DESCRICAO_OUTRO' : "CAST(NULL AS nvarchar(60))";
    return ch_rows(
        'SELECT TOP 50 h.ID, h.ACAO, h.PARENTESCO, ' . $colDesc . ' AS DESCRICAO_OUTRO,
                h.NOME_RESPONSAVEL, h.MOTIVO,
                CONVERT(varchar(16), h.CRIADO_EM, 120) AS CRIADO_EM_TXT,
                ISNULL(u.NOME, N\'Usuário removido\') AS USUARIO_NOME
           FROM TB_RESTRICAO_FAMILIA_HISTORICO h
           LEFT JOIN TB_USUARIOS u ON u.ID = h.ID_USUARIO
          WHERE h.ID_ALUNO = ?
          ORDER BY h.CRIADO_EM DESC, h.ID DESC',
        [$idAluno]
    ) ?: [];
}

/** Bases sem a coluna de descrição (versão inicial da migração) continuam legíveis. */
function fam_tem_coluna_historico_restricao()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.columns
              WHERE object_id = OBJECT_ID(N'dbo.TB_RESTRICAO_FAMILIA_HISTORICO')
                AND name = N'DESCRICAO_OUTRO'"
        );
    }
    return $tem;
}

/** Texto da observação gerada ao restringir um contato do crachá. */
function fam_restricao_texto_observacao($parentesco, $descricaoOutro, $nome, $motivo)
{
    $rotulo = fam_parentesco_rotulo($parentesco, (string)$descricaoOutro);
    $nome   = trim((string)$nome);
    $motivo = trim(strip_tags((string)$motivo));
    $texto  = 'Restrição familiar registrada para ' . $rotulo
        . ($nome !== '' ? ' (' . $nome . ')' : '')
        . '. Gerar outro crachá para este aluno, suprimindo a informação do contato restrito. '
        . 'Os crachás anteriores devem ser substituídos.';
    if ($motivo !== '') $texto .= ' Motivo informado: ' . $motivo . '.';
    return mb_substr($texto, 0, 5000, 'UTF-8');
}

/**
 * Aplica restringir/liberar a um vínculo aluno/responsável e grava o histórico.
 * Deve rodar dentro da transação aberta pela tela de restrição. Não grava a
 * observação: quem decide é o processador, que também tratava a auditoria.
 * Devolve ok, ja_estava (evita observação duplicada) e erro, quando há.
 */
function fam_restricao_aplicar($idAluno, $idVinculo, $acao, $motivo)
{
    $res = [
        'ok'          => false,
        'ja_estava'   => false,
        'erro'        => '',
        'parentesco'  => '',
        'descricao_outro' => '',
        'nome'        => '',
        'motivo'      => trim(strip_tags((string)$motivo)),
        'historico_id'=> 0,
    ];

    if (!fam_tem_restricao() || !fam_tem_historico_restricao()) {
        $res['erro'] = 'A restrição familiar não está instalada neste banco. Rode sql_inicial/ATUALIZAR_RESTRICAO_CONTATO_FAMILIA.sql.';
        return $res;
    }
    $acao = strtoupper(trim((string)$acao));
    if (!in_array($acao, ['RESTRINGIR', 'LIBERAR'], true)) {
        $res['erro'] = 'Ação inválida.';
        return $res;
    }
    $idAluno  = (int)$idAluno;
    $idVinculo = (int)$idVinculo;
    if ($idAluno <= 0 || $idVinculo <= 0) {
        $res['erro'] = 'Contato inválido.';
        return $res;
    }
    if ($res['motivo'] === '' || mb_strlen($res['motivo']) > 500) {
        $res['erro'] = 'Informe o motivo da ação (até 500 caracteres).';
        return $res;
    }

    $linha = ch_row(
        'SELECT ar.ID, ar.PARENTESCO, ar.DESCRICAO_OUTRO, ar.RESTRITO_CONTATO, r.NOME
           FROM TB_ALUNO_RESPONSAVEIS ar WITH (UPDLOCK, HOLDLOCK)
           INNER JOIN TB_RESPONSAVEIS r ON r.ID = ar.ID_RESPONSAVEL
          WHERE ar.ID = ? AND ar.ID_ALUNO = ?',
        [$idVinculo, $idAluno]
    );
    if (!$linha) {
        $res['erro'] = 'Este contato não está mais vinculado ao aluno. Atualize a página antes de tentar novamente.';
        return $res;
    }

    $res['parentesco']     = (string)$linha['PARENTESCO'];
    $res['descricao_outro'] = (string)($linha['DESCRICAO_OUTRO'] ?? '');
    $res['nome']           = (string)$linha['NOME'];

    $desejado = $acao === 'RESTRINGIR' ? 1 : 0;
    $atual    = (int)$linha['RESTRITO_CONTATO'];
    if ($atual === $desejado) {
        $res['ok'] = true;
        $res['ja_estava'] = true;
        return $res;
    }

    $n = ch_afetadas(
        'UPDATE TB_ALUNO_RESPONSAVEIS
            SET RESTRITO_CONTATO = ?
          WHERE ID = ? AND ID_ALUNO = ? AND RESTRITO_CONTATO = ?',
        [$desejado, $idVinculo, $idAluno, $atual]
    );
    if ($n !== 1) {
        $res['erro'] = 'Outra pessoa alterou este contato enquanto você editava. Atualize a página antes de tentar novamente.';
        return $res;
    }

    $comDesc = fam_tem_coluna_historico_restricao();
    $histId = ch_insert_id(
        'INSERT INTO TB_RESTRICAO_FAMILIA_HISTORICO
            (ID_ALUNO, ID_ALUNO_RESPONSAVEIS, PARENTESCO' . ($comDesc ? ', DESCRICAO_OUTRO' : '') . ',
             NOME_RESPONSAVEL, ACAO, MOTIVO, ID_USUARIO)
         OUTPUT INSERTED.ID
         VALUES (?, ?, ?' . ($comDesc ? ', ?' : '') . ', ?, ?, ?, ?)',
        array_merge(
            [$idAluno, $idVinculo, $res['parentesco']],
            $comDesc ? [$res['descricao_outro'] !== '' ? mb_substr($res['descricao_outro'], 0, 60, 'UTF-8') : null] : [],
            [mb_substr($res['nome'], 0, 150, 'UTF-8'), $acao, $res['motivo'], ch_uid()]
        )
    );
    if ($histId <= 0) {
        $res['erro'] = 'Não foi possível registrar o histórico da restrição.';
        return $res;
    }

    $res['ok'] = true;
    $res['historico_id'] = (int)$histId;
    return $res;
}

/**
 * Grava/atualiza TB_RESPONSAVEIS (por CPF) e sincroniza TB_ALUNO_RESPONSAVEIS
 * para o aluno informado. Deve rodar dentro de uma transação aberta. A restrição
 * familiar já registrada vem em $fam e é preservada na reinserção do vínculo.
 */
function fam_salvar_vinculos_aluno($idAluno, $fam)
{
    $idAluno = (int)$idAluno;
    if ($idAluno <= 0) return false;

    $parentescosAtivos = [];
    foreach (['MAE', 'PAI', 'OUTRO'] as $cod) {
        $item = $fam[$cod] ?? null;
        if (empty($item['PREENCHIDO'])) continue;

        $cpfLimpo = fam_cpf_limpo($item['CPF_LIMPO'] ?? $item['CPF'] ?? '');
        if (strlen($cpfLimpo) !== 11) return false;

        $existente = ch_row('SELECT ID, TELEFONE, EMAIL FROM TB_RESPONSAVEIS WHERE CPF = ?', [$cpfLimpo]);
        if ($existente) {
            $idResp = (int)$existente['ID'];
            $telSalvar   = $item['TELEFONE'] !== '' ? $item['TELEFONE'] : ($existente['TELEFONE'] ?? null);
            $emailSalvar = $item['EMAIL'] !== '' ? $item['EMAIL'] : ($existente['EMAIL'] ?? null);
            if (!ch_exec(
                'UPDATE TB_RESPONSAVEIS
                    SET NOME = ?, TELEFONE = ?, EMAIL = ?, MODIFIED = GETDATE()
                  WHERE ID = ?',
                [$item['NOME'], $telSalvar, $emailSalvar, $idResp]
            )) {
                return false;
            }
        } else {
            $idResp = ch_insert_id(
                'INSERT INTO TB_RESPONSAVEIS (CPF, NOME, TELEFONE, EMAIL)
                 OUTPUT INSERTED.ID
                 VALUES (?, ?, ?, ?)',
                [
                    $cpfLimpo,
                    $item['NOME'],
                    $item['TELEFONE'] !== '' ? $item['TELEFONE'] : '',
                    $item['EMAIL'] !== '' ? $item['EMAIL'] : null,
                ]
            );
            if ($idResp <= 0) return false;
        }

        $descOutro = $cod === 'OUTRO' && $item['DESCRICAO_OUTRO'] !== '' ? $item['DESCRICAO_OUTRO'] : null;
        $principal = !empty($item['CONTATO_PRINCIPAL']) ? 1 : 0;
        $restrito = !empty($item['RESTRITO_CONTATO']) ? 1 : 0;
        $comRestricao = fam_tem_restricao();

        /* Remove qualquer linha antiga conflitante deste aluno para este parentesco ou este responsável. */
        if (!ch_exec(
            'DELETE FROM TB_ALUNO_RESPONSAVEIS
              WHERE ID_ALUNO = ? AND (PARENTESCO = ? OR ID_RESPONSAVEL = ?)',
            [$idAluno, $cod, $idResp]
        )) {
            return false;
        }
        if (!ch_exec(
            'INSERT INTO TB_ALUNO_RESPONSAVEIS
                (ID_ALUNO, ID_RESPONSAVEL, PARENTESCO, DESCRICAO_OUTRO, CONTATO_PRINCIPAL'
                . ($comRestricao ? ', RESTRITO_CONTATO' : '') . ')
             VALUES (?, ?, ?, ?, ?' . ($comRestricao ? ', ?' : '') . ')',
            array_merge([$idAluno, $idResp, $cod, $descOutro, $principal], $comRestricao ? [$restrito] : [])
        )) {
            return false;
        }

        $parentescosAtivos[] = $cod;
    }

    /* Remove parentescos que foram limpos na edição. */
    if (!$parentescosAtivos) {
        return ch_exec('DELETE FROM TB_ALUNO_RESPONSAVEIS WHERE ID_ALUNO = ?', [$idAluno]);
    }
    $ph = implode(',', array_fill(0, count($parentescosAtivos), '?'));
    return ch_exec(
        "DELETE FROM TB_ALUNO_RESPONSAVEIS WHERE ID_ALUNO = ? AND PARENTESCO NOT IN ({$ph})",
        array_merge([$idAluno], $parentescosAtivos)
    );
}


/**
 * Gera botão/link direto para conversa no WhatsApp a partir de um telefone.
 */
function fam_whatsapp_botao($telefone, $mensagem = '', $rotulo = 'WhatsApp')
{
    $d = preg_replace('/\D/', '', (string)$telefone);
    if (strlen($d) < 10 || strlen($d) > 13) {
        return '';
    }
    if (strlen($d) === 10 || strlen($d) === 11) {
        $d = '55' . $d;
    }
    $url = 'https://wa.me/' . $d;
    if ($mensagem !== '') {
        $url .= '?text=' . rawurlencode($mensagem);
    }
    $textoRotulo = $rotulo !== '' ? ' ' . ch_h($rotulo) : '';
    return '<a href="' . ch_h($url) . '" target="_blank" rel="noopener noreferrer" '
        . 'class="btn btn-sm btn-outline-success py-0 px-2 ms-1 align-baseline text-decoration-none" '
        . 'title="Conversar no WhatsApp"><i class="fab fa-whatsapp"></i>' . $textoRotulo . '</a>';
}

function ch_whatsapp_link($telefone, $mensagem = '', $rotulo = 'WhatsApp')
{
    return fam_whatsapp_botao($telefone, $mensagem, $rotulo);
}
