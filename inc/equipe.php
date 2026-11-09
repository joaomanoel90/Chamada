<?php
/**
 * ============================================================================
 * EQUIPE POR TURNO, SUBSTITUTOS E SAÚDE DO ALUNO
 * ----------------------------------------------------------------------------
 * Sistema de Chamada · Prefeitura Municipal de Angatuba
 *
 * Este arquivo concentra três coisas que antes não existiam:
 *
 * 1. TURNO. Manhã, tarde e integral. O turno do professor (TB_USUARIOS.TURNO)
 *    é o PADRÃO ao montar a equipe; o turno que ele cobre naquela turma
 *    (TB_TURMA_PROFESSORES.TURNO) pode ser diferente, e quem decide é a gestão.
 *
 * 2. RESPONSÁVEL POR TURNO. Pode haver um responsável pela manhã e outro pela
 *    tarde. Quem é integral responde pelos dois turnos — e por isso não pode
 *    dividir a responsabilidade com alguém de turno específico. O índice
 *    único do banco pega o caso "dois da manhã"; a sobreposição entre turnos
 *    diferentes é pega por eq_validar_equipe(), aqui.
 *
 * 3. SUBSTITUTO COM PRAZO. Entra com data de início, data de fim e o professor
 *    que está cobrindo. Herda o turno de quem substitui. Fora do prazo não vê
 *    a turma; dentro do prazo tem os mesmos poderes. Quem está sendo coberto
 *    continua na equipe: a substituição soma, não troca.
 *
 * SAÚDE: alergia, restrição alimentar e medicação ficam na ficha do aluno e
 * aparecem em destaque na chamada e no relatório. Ver eq_caixa_saude().
 * ============================================================================
 */

/* ==========================================================================
   TURNO
   ========================================================================== */

/** Os turnos possíveis, na ordem em que aparecem nas telas. */
function eq_turnos()
{
    return ['MANHA' => 'Manhã', 'TARDE' => 'Tarde', 'INTEGRAL' => 'Integral'];
}

function eq_turno_rotulo($turno)
{
    $t = eq_turnos();
    $turno = strtoupper((string)$turno);
    return $t[$turno] ?? 'Integral';
}

/** Cor Bootstrap do selo do turno. */
function eq_turno_cor($turno)
{
    switch (strtoupper((string)$turno)) {
        case 'MANHA': return 'warning';
        case 'TARDE': return 'info';
        default:      return 'primary';
    }
}

function eq_turno_selo($turno)
{
    return '<span class="badge bg-' . eq_turno_cor($turno) . '">'
         . ch_h(eq_turno_rotulo($turno)) . '</span>';
}

/** Que partes do dia esse turno cobre. Integral cobre as duas. */
function eq_turno_cobre($turno)
{
    return strtoupper((string)$turno) === 'INTEGRAL' ? ['MANHA', 'TARDE'] : [strtoupper((string)$turno)];
}

/** Dois turnos se sobrepõem? INTEGRAL × MANHA = sim. MANHA × TARDE = não. */
function eq_turno_sobrepoe($a, $b)
{
    return (bool)array_intersect(eq_turno_cobre($a), eq_turno_cobre($b));
}

/** Turno de trabalho do professor. NULL quando não definido. */
function eq_professor_turno($idUsuario)
{
    $t = ch_val('SELECT TURNO FROM TB_USUARIOS WHERE ID = ?', [(int)$idUsuario]);
    $t = strtoupper(trim((string)$t));
    return isset(eq_turnos()[$t]) ? $t : null;
}

/** Turno em que a turma funciona, vindo do período dela. */
function eq_turma_turno($idTurma)
{
    $t = ch_val(
        'SELECT p.TURNO FROM TB_TURMAS t INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO WHERE t.ID = ?',
        [(int)$idTurma]
    );
    $t = strtoupper(trim((string)$t));
    $todos = eq_turnos();
    return isset($todos[$t]) ? $t : 'INTEGRAL';
}

/* ==========================================================================
   PAPEL
   ========================================================================== */

function eq_papeis()
{
    return [
        'REGENTE'    => 'Responsável',
        'AUXILIAR'   => 'Apoio',
        'SUBSTITUTO' => 'Substituto',
    ];
}

function eq_papel_rotulo($papel)
{
    $p = eq_papeis();
    $papel = strtoupper((string)$papel);
    return $p[$papel] ?? 'Apoio';
}

function eq_papel_cor($papel)
{
    switch (strtoupper((string)$papel)) {
        case 'REGENTE':    return 'success';
        case 'SUBSTITUTO': return 'danger';
        default:           return 'secondary';
    }
}

/* ==========================================================================
   LEITURA DO FORMULÁRIO
   --------------------------------------------------------------------------
   A tela manda arrays paralelos: eq_prof[], eq_papel[], eq_turno[],
   eq_subst[], eq_ini[], eq_fim[], eq_motivo[]. Linha sem professor escolhido
   é descartada em silêncio — é a linha em branco que sobra no fim da lista.
   ========================================================================== */

function eq_ler_post($post)
{
    $profs = (array)($post['eq_prof'] ?? []);
    $papeis = (array)($post['eq_papel'] ?? []);
    $turnos = (array)($post['eq_turno'] ?? []);
    $substs = (array)($post['eq_subst'] ?? []);
    $inis   = (array)($post['eq_ini'] ?? []);
    $fins   = (array)($post['eq_fim'] ?? []);
    $motivs = (array)($post['eq_motivo'] ?? []);
    $obs    = (array)($post['eq_obs'] ?? []);

    $todos = eq_turnos();
    $ps = eq_papeis();
    $membros = [];

    foreach ($profs as $i => $idProf) {
        $idProf = (int)$idProf;
        if ($idProf <= 0) continue;

        $papel = strtoupper(trim((string)($papeis[$i] ?? 'AUXILIAR')));
        if (!isset($ps[$papel])) $papel = 'AUXILIAR';

        $turno = strtoupper(trim((string)($turnos[$i] ?? '')));
        if (!isset($todos[$turno])) {
            /* Sem turno explícito: usa o do professor e, na falta dele, o da turma. */
            $turno = eq_professor_turno($idProf) ?: 'INTEGRAL';
        }

        $m = [
            'VER_OBSERVACOES' => ((string)($obs[$i] ?? '0') === '1') ? 1 : 0,
            'ID_USUARIO'     => $idProf,
            'PAPEL'          => $papel,
            'TURNO'          => $turno,
            'ID_SUBSTITUIDO' => (int)($substs[$i] ?? 0),
            'DATA_INICIO'    => ch_data_iso($inis[$i] ?? '', ''),
            'DATA_FIM'       => ch_data_iso($fins[$i] ?? '', ''),
            'MOTIVO'         => mb_substr(strip_tags(trim((string)($motivs[$i] ?? ''))), 0, 300),
        ];

        if ($papel !== 'SUBSTITUTO') {
            /* A CHECK do banco só aceita a permissão em substituto. Zerar aqui
               evita que uma escala antiga, que virou regente, derrube o save. */
            $m['VER_OBSERVACOES'] = 0;
            /* Não deixa prazo órfão de uma escala antiga. */
            $m['ID_SUBSTITUIDO'] = 0;
            $m['DATA_INICIO'] = '';
            $m['DATA_FIM'] = '';
            $m['MOTIVO'] = '';
        } else {
            /* O substituto cobre o turno de quem substitui, não o turno dele. */
            $coberto = null;
            foreach ($membros as $ant) {
                if ($ant['ID_USUARIO'] === $m['ID_SUBSTITUIDO'] && $ant['PAPEL'] !== 'SUBSTITUTO') {
                    $coberto = $ant;
                    break;
                }
            }
            if ($coberto) $m['TURNO'] = $coberto['TURNO'];
        }
        $membros[] = $m;
    }
    return $membros;
}

/* ==========================================================================
   VALIDAÇÃO DA EQUIPE
   --------------------------------------------------------------------------
   É aqui que a regra "um responsável por turno" é fechada. O banco garante o
   caso simples; a sobreposição integral × manhã/tarde só dá para conferir
   comparando as linhas entre si, e é o que esta função faz.
   ========================================================================== */

/** Devolve a lista de erros. Lista vazia = equipe válida. */
function eq_validar_equipe($membros)
{
    $e = [];
    if (!$membros) return $e;

    /* ---- existem, estão ativos e são professores? ---- */
    $ids = array_values(array_unique(array_map(function ($m) { return (int)$m['ID_USUARIO']; }, $membros)));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $validos = [];
    foreach (ch_rows("SELECT ID, NOME FROM TB_USUARIOS WHERE ID IN ({$ph}) AND NIVEL = 3 AND ISNULL(ATIVO, 1) = 1", $ids) ?: [] as $r) {
        $validos[(int)$r['ID']] = $r['NOME'];
    }
    foreach ($ids as $id) {
        if (!isset($validos[$id])) {
            $e[] = 'Há professor(a) inválido, inativo ou sem perfil de professor na equipe.';
            break;
        }
    }

    /* ---- ninguém duas vezes na mesma turma ---- */
    if (count($ids) !== count($membros)) {
        $e[] = 'O mesmo professor apareceu duas vezes na equipe. Cada pessoa entra uma única vez.';
    }

    /* ---- responsáveis: um por turno, sem sobreposição ---- */
    $resp = [];
    foreach ($membros as $m) {
        if ($m['PAPEL'] === 'REGENTE') $resp[] = $m;
    }
    for ($i = 0; $i < count($resp); $i++) {
        for ($j = $i + 1; $j < count($resp); $j++) {
            if (eq_turno_sobrepoe($resp[$i]['TURNO'], $resp[$j]['TURNO'])) {
                $a = $validos[$resp[$i]['ID_USUARIO']] ?? 'professor(a)';
                $b = $validos[$resp[$j]['ID_USUARIO']] ?? 'professor(a)';
                $e[] = $a . ' e ' . $b . ' ficariam responsáveis pelo mesmo turno. '
                     . 'Só pode haver um responsável por turno: use Manhã e Tarde para pessoas diferentes, '
                     . 'ou deixe um deles como Apoio.';
                break 2;
            }
        }
    }

    /* ---- substitutos ---- */
    $fixos = [];
    foreach ($membros as $m) {
        if ($m['PAPEL'] !== 'SUBSTITUTO') $fixos[$m['ID_USUARIO']] = $m;
    }
    $janelas = [];
    foreach ($membros as $m) {
        if ($m['PAPEL'] !== 'SUBSTITUTO') continue;
        $nome = $validos[$m['ID_USUARIO']] ?? 'O substituto';

        if (!isset($fixos[$m['ID_SUBSTITUIDO']])) {
            $e[] = $nome . ': escolha qual professor da equipe está sendo substituído.';
        } elseif ($m['ID_SUBSTITUIDO'] === $m['ID_USUARIO']) {
            $e[] = $nome . ' não pode substituir a si mesmo.';
        }
        if ($m['DATA_INICIO'] === '' || $m['DATA_FIM'] === '') {
            $e[] = $nome . ': a substituição precisa de data de início e data de fim.';
        } elseif ($m['DATA_FIM'] < $m['DATA_INICIO']) {
            $e[] = $nome . ': a data de fim da substituição não pode ser antes do início.';
        }
        if ($m['MOTIVO'] === '') {
            $e[] = $nome . ': informe o motivo da substituição (licença, férias, afastamento...).';
        }

        /* Duas pessoas cobrindo o mesmo professor no mesmo período deixaria
           a escala ambígua. Períodos diferentes, tudo bem. */
        if ($m['ID_SUBSTITUIDO'] > 0 && $m['DATA_INICIO'] !== '' && $m['DATA_FIM'] !== '') {
            foreach ($janelas as $j) {
                if ($j['ID_SUBSTITUIDO'] === $m['ID_SUBSTITUIDO']
                    && $m['DATA_INICIO'] <= $j['DATA_FIM'] && $m['DATA_FIM'] >= $j['DATA_INICIO']) {
                    $alvo = $validos[$m['ID_SUBSTITUIDO']] ?? 'o mesmo professor';
                    $e[] = 'Há duas substituições de ' . $alvo . ' com datas que se cruzam. '
                         . 'Ajuste os períodos para não se sobreporem.';
                    break 2;
                }
            }
            $janelas[] = $m;
        }
    }
    return array_values(array_unique($e));
}

/** O responsável que vai para TB_TURMAS.ID_PROFESSOR (campo legado).
 *  Prefere quem cobre o turno da própria turma. */
function eq_regente_legado($membros, $turnoTurma = 'INTEGRAL')
{
    $resp = [];
    foreach ($membros as $m) {
        if ($m['PAPEL'] === 'REGENTE') $resp[] = $m;
    }
    if (!$resp) return 0;
    foreach ($resp as $m) {
        if ($m['TURNO'] === $turnoTurma) return (int)$m['ID_USUARIO'];
    }
    foreach ($resp as $m) {
        if ($m['TURNO'] === 'INTEGRAL') return (int)$m['ID_USUARIO'];
    }
    return (int)$resp[0]['ID_USUARIO'];
}

/* ==========================================================================
   CONSULTA DA EQUIPE
   ========================================================================== */

/** Equipe completa da turma. Datas vêm como texto ISO (o sqlsrv devolve
 *  DateTime para coluna date, e o resto do sistema trabalha com string). */
function eq_equipe($idTurma, $incluirInativos = false)
{
    $sql = "SELECT tp.ID, tp.ID_USUARIO, tp.PAPEL, tp.TURNO, tp.ATIVO,
                   CONVERT(varchar(10), tp.DATA_INICIO, 23) AS DATA_INICIO,
                   CONVERT(varchar(10), tp.DATA_FIM, 23)    AS DATA_FIM,
                   tp.ID_SUBSTITUIDO, tp.MOTIVO,
                   tp.VER_OBSERVACOES, tp.OBS_LIBERADA_POR,
                   CONVERT(varchar(19), tp.OBS_LIBERADA_EM, 120) AS OBS_LIBERADA_EM,
                   u.NOME, s.NOME AS NOME_SUBSTITUIDO, lib.NOME AS OBS_LIBERADA_NOME
            FROM TB_TURMA_PROFESSORES tp
            INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
            LEFT  JOIN TB_USUARIOS s ON s.ID = tp.ID_SUBSTITUIDO
            LEFT  JOIN TB_USUARIOS lib ON lib.ID = tp.OBS_LIBERADA_POR
            WHERE tp.ID_TURMA = ?";
    if (!$incluirInativos) {
        $sql .= ' AND tp.ATIVO = 1 AND ISNULL(u.ATIVO, 1) = 1';
    }
    $sql .= " ORDER BY CASE tp.PAPEL WHEN 'REGENTE' THEN 0 WHEN 'AUXILIAR' THEN 1 ELSE 2 END,
                       CASE tp.TURNO WHEN 'MANHA' THEN 0 WHEN 'TARDE' THEN 1 ELSE 2 END,
                       u.NOME";
    return ch_rows($sql, [(int)$idTurma]) ?: [];
}

/** Esse membro vale nesta data? Só o substituto tem prazo. */
function eq_vigente($membro, $data = null)
{
    if (strtoupper((string)$membro['PAPEL']) !== 'SUBSTITUTO') return true;
    $data = $data === null ? ch_hoje() : ch_data_iso($data, '');
    if ($data === '') return false;
    $ini = (string)($membro['DATA_INICIO'] ?? '');
    $fim = (string)($membro['DATA_FIM'] ?? '');
    if ($ini === '' || $fim === '') return false;
    return $data >= $ini && $data <= $fim;
}

/** Situação do prazo, para escrever na tela. */
function eq_situacao_prazo($membro, $data = null)
{
    if (strtoupper((string)$membro['PAPEL']) !== 'SUBSTITUTO') return '';
    $data = $data === null ? ch_hoje() : ch_data_iso($data, '');
    $ini = (string)($membro['DATA_INICIO'] ?? '');
    $fim = (string)($membro['DATA_FIM'] ?? '');
    if ($ini === '' || $fim === '') return 'sem prazo';
    if ($data < $ini)  return 'começa em ' . ch_data_br($ini);
    if ($data > $fim)  return 'encerrada em ' . ch_data_br($fim);
    return 'até ' . ch_data_br($fim);
}

/** Só quem vale na data. */
function eq_equipe_em($idTurma, $data = null)
{
    $fora = [];
    foreach (eq_equipe($idTurma) as $m) {
        if (eq_vigente($m, $data)) $fora[] = $m;
    }
    return $fora;
}

/** O responsável por um turno numa data. Devolve o membro ou null.
 *  Quem é integral responde pela manhã e pela tarde. */
function eq_responsavel($idTurma, $turno = null, $data = null)
{
    $turno = $turno === null ? eq_turma_turno($idTurma) : $turno;
    $integral = null;
    foreach (eq_equipe_em($idTurma, $data) as $m) {
        if ($m['PAPEL'] !== 'REGENTE') continue;
        if ($m['TURNO'] === $turno) return $m;
        if ($m['TURNO'] === 'INTEGRAL') $integral = $m;
    }
    return $integral;
}

/** Texto da equipe para listas e cabeçalhos.
 *  Ex.: "Ana (responsável · manhã), Bruno (apoio · integral)" */
function eq_texto($idTurma, $data = null)
{
    $partes = [];
    foreach (eq_equipe_em($idTurma, $data) as $m) {
        $det = strtolower(eq_papel_rotulo($m['PAPEL'])) . ' · ' . strtolower(eq_turno_rotulo($m['TURNO']));
        if ($m['PAPEL'] === 'SUBSTITUTO' && !empty($m['NOME_SUBSTITUIDO'])) {
            $det .= ', cobre ' . $m['NOME_SUBSTITUIDO'];
        }
        $partes[] = $m['NOME'] . ' (' . $det . ')';
    }
    return implode(', ', $partes);
}

/** Substituições valendo hoje (ou na data), para os avisos da tela. */
function eq_substitutos_em($idTurma, $data = null)
{
    $r = [];
    foreach (eq_equipe_em($idTurma, $data) as $m) {
        if ($m['PAPEL'] === 'SUBSTITUTO') $r[] = $m;
    }
    return $r;
}

/* ==========================================================================
   ACESSO — o prazo do substituto vale como porta de entrada
   --------------------------------------------------------------------------
   ch_turmas_ids() e ch_na_equipe() em inc/chamada.php delegam para cá. É o
   ponto único por onde o acesso do professor passa, então o prazo é respeitado
   em todas as telas de uma vez: chamada, observações, relatório e retificação.
   ========================================================================== */

/** Turmas que o usuário alcança nesta data. */
function eq_turmas_ids_em($idUsuario = null, $data = null)
{
    $idUsuario = $idUsuario === null ? ch_uid() : (int)$idUsuario;
    if ($idUsuario <= 0) return [];
    $data = $data === null ? ch_hoje() : ch_data_iso($data, '');

    $rows = ch_rows(
        "SELECT ID_TURMA FROM TB_TURMA_PROFESSORES
         WHERE ID_USUARIO = ? AND ATIVO = 1
           AND (PAPEL <> 'SUBSTITUTO'
                OR (DATA_INICIO IS NOT NULL AND DATA_FIM IS NOT NULL
                    AND ? >= DATA_INICIO AND ? <= DATA_FIM))",
        [$idUsuario, $data, $data]
    );
    return array_map(function ($r) { return (int)$r['ID_TURMA']; }, $rows ?: []);
}

/** O usuário está na equipe desta turma, valendo nesta data? */
function eq_na_equipe_em($idTurma, $idUsuario = null, $data = null)
{
    $idUsuario = $idUsuario === null ? ch_uid() : (int)$idUsuario;
    if ($idUsuario <= 0) return false;
    $data = $data === null ? ch_hoje() : ch_data_iso($data, '');
    return (bool)ch_val(
        "SELECT ID FROM TB_TURMA_PROFESSORES
         WHERE ID_TURMA = ? AND ID_USUARIO = ? AND ATIVO = 1
           AND (PAPEL <> 'SUBSTITUTO'
                OR (DATA_INICIO IS NOT NULL AND DATA_FIM IS NOT NULL
                    AND ? >= DATA_INICIO AND ? <= DATA_FIM))",
        [(int)$idTurma, $idUsuario, $data, $data]
    );
}

/** Professores para o select da equipe, já com o turno de cada um. */
function eq_professores_opcoes()
{
    return ch_rows(
        'SELECT ID, NOME, TURNO FROM TB_USUARIOS WHERE NIVEL = 3 AND ISNULL(ATIVO, 1) = 1 ORDER BY NOME'
    ) ?: [];
}

/* ==========================================================================
   SAÚDE DO ALUNO
   --------------------------------------------------------------------------
   Texto livre: a escola escreve como o laudo diz. Nada aqui é diagnóstico
   nosso — é o que a família informou, repassado a quem está com a criança.
   ========================================================================== */

/** Os campos de saúde, na ordem em que aparecem. */
function eq_saude_campos()
{
    return [
        'ALERGIAS' => [
            'rotulo' => 'Alergias',
            'icone'  => 'fa-triangle-exclamation',
            'cor'    => 'danger',
            'ajuda'  => 'Alimento, medicamento, picada de inseto, contato. Escreva como a família ou o laudo informou.',
        ],
        'RESTRICOES_ALIMENTARES' => [
            'rotulo' => 'Restrições alimentares',
            'icone'  => 'fa-utensils',
            'cor'    => 'warning',
            'ajuda'  => 'O que não pode ser servido na merenda: sem lactose, sem glúten, dieta específica.',
        ],
        'MEDICACAO' => [
            'rotulo' => 'Medicação e cuidados de saúde',
            'icone'  => 'fa-kit-medical',
            'cor'    => 'info',
            'ajuda'  => 'Medicamento em uso, horário, o que fazer em caso de crise, contato de emergência.',
        ],
    ];
}

/** Só os campos preenchidos, já prontos para exibir. */
function eq_saude_alertas($aluno)
{
    $r = [];
    foreach (eq_saude_campos() as $campo => $def) {
        $txt = trim((string)($aluno[$campo] ?? ''));
        if ($txt === '') continue;
        $r[] = [
            'campo'  => $campo,
            'rotulo' => $def['rotulo'],
            'icone'  => $def['icone'],
            'cor'    => $def['cor'],
            'texto'  => $txt,
        ];
    }
    return $r;
}

function eq_tem_saude($aluno)
{
    return (bool)eq_saude_alertas($aluno);
}

/** Tem alergia especificamente? Usado para o destaque mais forte. */
function eq_tem_alergia($aluno)
{
    return trim((string)($aluno['ALERGIAS'] ?? '')) !== '';
}

/** Resumo curto, para a lista de chamada. */
function eq_saude_resumo($aluno)
{
    $partes = [];
    foreach (eq_saude_alertas($aluno) as $a) {
        $partes[] = $a['rotulo'] . ': ' . $a['texto'];
    }
    return implode(' | ', $partes);
}

/**
 * A caixa de aviso de saúde. É de propósito chamativa: quem está fazendo a
 * chamada precisa ver antes de servir a merenda ou dar um remédio.
 *
 * $compacto = true devolve uma linha só, para caber ao lado do nome do aluno
 * na lista de chamada. $compacto = false devolve o cartão completo, para a
 * ficha e o relatório.
 */
function eq_caixa_saude($aluno, $compacto = false)
{
    $alertas = eq_saude_alertas($aluno);
    if (!$alertas) return '';

    if ($compacto) {
        $temAlergia = eq_tem_alergia($aluno);
        $cor = $temAlergia ? 'danger' : 'warning';
        $icone = $temAlergia ? 'fa-triangle-exclamation' : 'fa-circle-info';
        $h = '<div class="alert alert-' . $cor . ' py-1 px-2 mb-0 small eq-saude-compacta">'
           . '<i class="fa-solid ' . $icone . ' me-1"></i>';
        $partes = [];
        foreach ($alertas as $a) {
            $partes[] = '<strong>' . ch_h($a['rotulo']) . ':</strong> ' . ch_h($a['texto']);
        }
        return $h . implode(' &nbsp;·&nbsp; ', $partes) . '</div>';
    }

    $h = '<div class="eq-saude">';
    foreach ($alertas as $a) {
        $h .= '<div class="alert alert-' . $a['cor'] . ' d-flex align-items-start mb-2" role="alert">'
            . '<i class="fa-solid ' . $a['icone'] . ' fa-lg me-2 mt-1"></i>'
            . '<div><strong>' . ch_h($a['rotulo']) . '</strong><br>'
            . nl2br(ch_h($a['texto'])) . '</div></div>';
    }
    return $h . '</div>';
}
/**
 * A saúde no relatório anual, em versão que SOBREVIVE À IMPRESSÃO.
 *
 * Não usa .alert de propósito: o CSS de impressão de relatorios/ver.php esconde
 * .alert (é interface, não conteúdo) e o aviso desapareceria do papel. Aqui é
 * um bloco de conteúdo, com moldura própria, que sai na tela e no PDF.
 */
function eq_saude_ficha($aluno)
{
    $alertas = eq_saude_alertas($aluno);
    if (!$alertas) return '';
    $h = '<div class="eq-saude-ficha">'
       . '<div class="eq-saude-ficha-titulo">Saúde e alimentação — atenção</div>';
    foreach ($alertas as $a) {
        $h .= '<div class="eq-saude-ficha-item"><strong>' . ch_h($a['rotulo']) . ':</strong> '
            . nl2br(ch_h($a['texto'])) . '</div>';
    }
    return $h . '</div>';
}
/* ==========================================================================
   SUBSTITUTO E OBSERVAÇÕES DO PROFESSOR
   --------------------------------------------------------------------------
   Regra pedida pela escola: o substituto registra observação normalmente e
   sempre lê o que ELE mesmo escreveu, mas só lê o que os OUTROS escreveram se
   a gestão liberar. A liberação é por substituição — cada prazo novo começa
   bloqueado, mesmo para quem já foi liberado antes.

   O texto da observação aparece em seis telas. Esconder só a listagem deixaria
   o conteúdo acessível pela ficha do aluno ou pelo relatório, então todas
   passam pelo mesmo filtro daqui. São dois formatos:

     eq_obs_filtro()        para consulta que já junta TB_ALUNOS (tem a turma
                            de cada linha e decide linha a linha)
     eq_obs_filtro_aluno()  para consulta de um aluno só, sem essa junção

   Membro fixo da equipe não é afetado: continua vendo tudo, como sempre viu.
   ========================================================================== */

/**
 * Turmas em que o usuário é substituto VIGENTE e sem liberação de leitura.
 *
 * Uma turma sai da lista se ele também for membro fixo dela: nesse caso o
 * vínculo fixo manda, e a substituição não pode rebaixar o acesso.
 *
 * Substituição fora do prazo não entra na lista porque ele já não alcança a
 * turma — quem resolve isso é eq_turmas_ids_em(), usado por ch_turmas_ids().
 */
function eq_obs_turmas_bloqueadas($idUsuario = null, $data = null)
{
    static $cache = [];

    $idUsuario = $idUsuario === null ? ch_uid() : (int)$idUsuario;
    if ($idUsuario <= 0) return [];

    $data  = $data === null ? ch_hoje() : ch_data_iso($data, ch_hoje());
    $chave = $idUsuario . '|' . $data;
    if (isset($cache[$chave])) return $cache[$chave];

    /* Gestão enxerga a rede ou as unidades liberadas; não faz sentido
       bloquear leitura para quem pode editar a equipe. */
    $nivel = $idUsuario === ch_uid()
        ? ch_nivel()
        : (int)ch_val('SELECT NIVEL FROM TB_USUARIOS WHERE ID = ?', [$idUsuario]);
    if ($nivel === 1 || $nivel === 2) return $cache[$chave] = [];

    $rows = ch_rows(
        "SELECT ID_TURMA, PAPEL, ISNULL(VER_OBSERVACOES, 0) AS VER_OBSERVACOES,
                CONVERT(varchar(10), DATA_INICIO, 23) AS DATA_INICIO,
                CONVERT(varchar(10), DATA_FIM, 23)    AS DATA_FIM
         FROM TB_TURMA_PROFESSORES
         WHERE ID_USUARIO = ? AND ATIVO = 1",
        [$idUsuario]
    ) ?: [];

    $fixas = [];
    $bloqueadas = [];
    foreach ($rows as $r) {
        $t = (int)$r['ID_TURMA'];
        if ($r['PAPEL'] !== 'SUBSTITUTO') { $fixas[$t] = true; continue; }

        $ini = (string)($r['DATA_INICIO'] ?? '');
        $fim = (string)($r['DATA_FIM'] ?? '');
        if ($ini === '' || $fim === '' || $data < $ini || $data > $fim) continue;

        if ((int)$r['VER_OBSERVACOES'] === 0) $bloqueadas[$t] = true;
    }

    $lista = array_values(array_diff(array_keys($bloqueadas), array_keys($fixas)));
    sort($lista);
    return $cache[$chave] = $lista;
}

/** O usuário está com alguma leitura de observação bloqueada hoje? */
function eq_obs_tem_bloqueio()
{
    return eq_obs_turmas_bloqueadas() !== [];
}

/**
 * Filtro para consulta que junta TB_ALUNOS.
 *
 * Devolve "e' do autor, ou a turma não está bloqueada". Assim o substituto
 * continua vendo as próprias observações mesmo na turma bloqueada, e vê tudo
 * nas turmas onde é membro fixo ou foi liberado.
 *
 * Lista vazia = nenhum bloqueio = SQL vazio, sem custo para os demais perfis.
 */
function eq_obs_filtro($aliasObs = 'o', $aliasAluno = 'a')
{
    $turmas = eq_obs_turmas_bloqueadas();
    if (!$turmas) return ['sql' => '', 'params' => []];

    $ph = implode(',', array_fill(0, count($turmas), '?'));
    return [
        'sql'    => " AND ({$aliasObs}.ID_USUARIO = ? OR {$aliasAluno}.ID_TURMA NOT IN ({$ph}))",
        'params' => array_merge([ch_uid()], $turmas),
    ];
}

/**
 * Filtro para consulta de UM aluno, sem junção com TB_ALUNOS.
 *
 * Aluno transferido pertenceu a mais de uma turma no ano. Se QUALQUER uma
 * delas estiver bloqueada para o substituto, a consulta inteira fica restrita
 * às observações dele. É de propósito conservador: o relatório anual cobre o
 * ano nas duas escolas, e não dá para liberar metade do texto por engano.
 */
function eq_obs_filtro_aluno($idAluno, $aliasObs = 'o')
{
    $turmas = eq_obs_turmas_bloqueadas();
    if (!$turmas) return ['sql' => '', 'params' => []];

    $idAluno = (int)$idAluno;
    if ($idAluno <= 0) return ['sql' => '', 'params' => []];

    $doAluno = [(int)ch_val('SELECT ID_TURMA FROM TB_ALUNOS WHERE ID = ?', [$idAluno])];
    foreach (tr_vinculos($idAluno) as $v) { $doAluno[] = (int)$v['ID_TURMA']; }

    if (!array_intersect($turmas, array_unique($doAluno))) {
        return ['sql' => '', 'params' => []];
    }
    return ['sql' => " AND {$aliasObs}.ID_USUARIO = ?", 'params' => [ch_uid()]];
}


/** Texto do aviso mostrado ao substituto bloqueado. */
function eq_obs_aviso_bloqueio()
{
    if (!eq_obs_tem_bloqueio()) return '';
    return 'Você está como substituto nesta turma. As observações que você registrar '
         . 'ficam visíveis para você; as registradas pelos outros professores só '
         . 'aparecem se a direção liberar a leitura.';
}

