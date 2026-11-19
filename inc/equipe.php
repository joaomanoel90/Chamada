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
    if (in_array($nivel, [1, 2, 4], true)) return $cache[$chave] = [];

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

/* ==========================================================================
   MATÉRIAS DA TURMA (TB_TURMA_DISCIPLINAS)
   --------------------------------------------------------------------------
   Cada turma tem as suas matérias, com o número de aulas semanais e o
   professor que as dá. As matérias são definidas no formulário da turma e
   ajustadas no painel de atribuição — os dois caminhos usam estas funções.
   ========================================================================== */

/** Matérias da turma, com o nome do professor responsável. */
function eq_disciplinas($idTurma)
{
    return ch_rows(
        'SELECT d.ID, d.NOME, d.AULAS, d.ID_PROFESSOR, u.NOME AS PROFESSOR
         FROM TB_TURMA_DISCIPLINAS d
         LEFT JOIN TB_USUARIOS u ON u.ID = d.ID_PROFESSOR
         WHERE d.ID_TURMA = ? ORDER BY d.NOME',
        [(int)$idTurma]
    ) ?: [];
}

/** Quem pode dar matéria nesta turma: a equipe fixa (não o substituto). */
function eq_professores_disciplina($idTurma)
{
    return ch_rows(
        "SELECT DISTINCT u.ID, u.NOME
         FROM TB_TURMA_PROFESSORES tp
         INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
         WHERE tp.ID_TURMA = ? AND tp.ATIVO = 1 AND ISNULL(u.ATIVO, 1) = 1
           AND u.NIVEL = 3 AND tp.PAPEL <> 'SUBSTITUTO'
         ORDER BY u.NOME",
        [(int)$idTurma]
    ) ?: [];
}

/** Lê as linhas de matéria do POST (formulário da turma e painel). */
function eq_disciplinas_ler_post($post)
{
    $nomes = (array)($post['disc_nome'] ?? []);
    $aulas = (array)($post['disc_aulas'] ?? []);
    $lista = [];
    foreach ($nomes as $i => $nome) {
        $nome = mb_substr(strip_tags(trim((string)$nome)), 0, 80);
        if ($nome === '') continue;
        $lista[] = [
            'NOME'  => $nome,
            'AULAS' => (int)($aulas[$i] ?? 0),
        ];
    }
    return $lista;
}

/**
 * Valida as matérias da turma (nome da lista + total de aulas). O total não
 * pode ficar menor que as aulas já atribuídas aos professores.
 * $idTurma = 0 para turma nova (sem atribuições ainda).
 */
function eq_disciplinas_validar($lista, $idTurma = 0)
{
    $erros = [];
    $vistos = [];
    $idTurma = (int)$idTurma;
    foreach ($lista as $d) {
        $chave = mb_strtolower($d['NOME'], 'UTF-8');
        if (isset($vistos[$chave])) {
            $erros[] = 'A matéria "' . $d['NOME'] . '" aparece mais de uma vez.';
        }
        $vistos[$chave] = true;
        if (!eq_materia_valida($d['NOME'])) {
            $erros[] = 'A matéria "' . $d['NOME'] . '" não está na lista de matérias. Escolha da lista.';
        }
        if ($d['AULAS'] < 1 || $d['AULAS'] > 40) {
            $erros[] = 'A matéria "' . $d['NOME'] . '" precisa de 1 a 40 aulas por semana.';
        } elseif ($idTurma > 0) {
            $idDisc = (int)ch_val(
                'SELECT ID FROM TB_TURMA_DISCIPLINAS WHERE ID_TURMA = ? AND NOME = ?',
                [$idTurma, $d['NOME']]
            );
            if ($idDisc > 0) {
                $atribuidas = eq_disciplina_atribuidas($idDisc);
                if ($d['AULAS'] < $atribuidas) {
                    $erros[] = 'A matéria "' . $d['NOME'] . '" já tem ' . $atribuidas
                             . ' aula(s) atribuída(s). O total não pode ficar menor que isso.';
                }
            }
        }
    }
    return $erros;
}

/**
 * Sincroniza as matérias da turma com a lista (nome + total de aulas).
 * Deve ser chamado DENTRO de uma transação. Matéria que saiu da lista é
 * apagada (com as atribuições dela); as demais são atualizadas ou
 * inseridas. O nome é a identidade (UQ_TURMA_DISCIPLINA). Os professores
 * são atribuídos no painel, por aulas.
 */
function eq_disciplinas_salvar($idTurma, $lista)
{
    $idTurma = (int)$idTurma;
    $atuais = ch_rows('SELECT ID, NOME FROM TB_TURMA_DISCIPLINAS WHERE ID_TURMA = ?', [$idTurma]) ?: [];
    $porNome = [];
    foreach ($atuais as $a) {
        $porNome[mb_strtolower((string)$a['NOME'], 'UTF-8')] = (int)$a['ID'];
    }
    $novos = [];
    foreach ($lista as $d) {
        $novos[mb_strtolower($d['NOME'], 'UTF-8')] = $d;
    }
    /* Quem saiu da lista é removido (com as atribuições dela). */
    foreach ($porNome as $chave => $idDisc) {
        if (!isset($novos[$chave])) {
            if (!ch_exec('DELETE FROM TB_ATRIBUICOES WHERE ID_DISCIPLINA = ?', [$idDisc])) {
                return false;
            }
            if (!ch_exec('DELETE FROM TB_TURMA_DISCIPLINAS WHERE ID = ? AND ID_TURMA = ?', [$idDisc, $idTurma])) {
                return false;
            }
        }
    }
    /* Atualiza ou insere. */
    foreach ($novos as $chave => $d) {
        if (isset($porNome[$chave])) {
            $ok = ch_exec(
                'UPDATE TB_TURMA_DISCIPLINAS SET AULAS = ? WHERE ID = ? AND ID_TURMA = ?',
                [$d['AULAS'], $porNome[$chave], $idTurma]
            );
        } else {
            $ok = ch_exec(
                'INSERT INTO TB_TURMA_DISCIPLINAS (ID_TURMA, NOME, AULAS) VALUES (?, ?, ?)',
                [$idTurma, $d['NOME'], $d['AULAS']]
            );
        }
        if (!$ok) return false;
    }
    return true;
}

/* ==========================================================================
   CATEGORIA DO CONCURSO E MATÉRIAS DO PROFESSOR
   --------------------------------------------------------------------------
   No começo do ano o professor ainda não tem turma nem equipe vinculada a
   ele. Para o painel de atribuição saber em quais turmas ele pode ser
   atribuído, o cadastro dele guarda a categoria do concurso (PDI / PEB I /
   PEB II) e as matérias que ele pode lecionar. Sem categoria = todas as
   etapas; sem matérias cadastradas = ele ainda não pode lecionar nenhuma
   matéria (só recebe atribuição de matéria que esteja no cadastro dele).
   ========================================================================== */

/** Categorias do concurso e o que cada uma significa. */
function eq_categorias_concurso()
{
    return [
        'PDI_CRECHE'   => 'PDI — Creche (1 período)',
        'APOIO_CRECHE' => 'Professor de Apoio — Creche (dia todo)',
        'PDI'          => 'PDI — Pré-escola (legado)',
        'PEB_I'        => 'PEB I — Pré I/II e Anos Iniciais',
        'PEB_II'       => 'PEB II — Anos Finais / Matérias específicas',
        'AEE'          => 'Professor de AEE — Educação Especial',
    ];
}

/** Categorias de turma regular para o filtro da tela de Atribuição. */
function eq_categorias_turma()
{
    return [
        'PDI_CRECHE' => 'Creche (Berçário I ao Maternal II)',
        'PRE_ESCOLA' => 'Pré-escola (Pré I e Pré II — PEB I)',
        'FUND_I'     => 'Anos Iniciais (1º ao 5º ano — PEB I)',
        'PEB_II'     => 'Anos Finais (6º ao 9º ano — PEB II)',
    ];
}

/** Etapas correspondentes ao filtro de categoria de turma na tela de Atribuição. */
function eq_etapas_filtro_turma($codigoFiltro)
{
    return match ($codigoFiltro) {
        'PDI_CRECHE' => ['INFANTIL_CRECHE'],
        'PRE_ESCOLA', 'PDI' => ['INFANTIL_PRE_ESCOLA'],
        'FUND_I'     => ['FUNDAMENTAL_1_5'],
        'PEB_I'      => ['INFANTIL_PRE_ESCOLA', 'FUNDAMENTAL_1_5'],
        'PEB_II'     => ['FUNDAMENTAL_6_9'],
        default      => [],
    };
}

/** Etapas de ensino que cada categoria pode lecionar diretamente. NULL = sem restrição. */
function eq_categoria_etapas($categoria)
{
    return match ($categoria) {
        'PDI_CRECHE'   => ['INFANTIL_CRECHE'],
        'APOIO_CRECHE' => ['INFANTIL_CRECHE'],
        'PDI'          => ['INFANTIL_PRE_ESCOLA'],
        'PEB_I'        => ['INFANTIL_PRE_ESCOLA', 'FUNDAMENTAL_1_5'],
        'PEB_II'       => ['FUNDAMENTAL_6_9'],
        'AEE'          => [],
        default        => null,
    };
}

/** A categoria do professor (NULL = sem restrição). */
function eq_professor_categoria($idUsuario)
{
    $cat = ch_val('SELECT CATEGORIA FROM TB_USUARIOS WHERE ID = ?', [(int)$idUsuario]);
    return ($cat === null || $cat === '') ? null : (string)$cat;
}

/**
 * Matérias que o professor pode lecionar, como mapa
 * [nome em minúsculas => nome original]. Vazio = sem restrição.
 */
function eq_professor_materias($idUsuario)
{
    $linhas = ch_rows(
        'SELECT NOME FROM TB_PROFESSOR_MATERIAS WHERE ID_USUARIO = ? ORDER BY NOME',
        [(int)$idUsuario]
    ) ?: [];
    $mapa = [];
    foreach ($linhas as $l) {
        $mapa[mb_strtolower((string)$l['NOME'], 'UTF-8')] = (string)$l['NOME'];
    }
    return $mapa;
}

/** O professor pode ser atribuído a uma turma desta etapa? */
function eq_professor_pode_turma($categoria, $etapa)
{
    $etapas = eq_categoria_etapas($categoria);
    if ($etapas === null) return true;                       // sem restrição
    if ($etapa === null || $etapa === '') return false;      // turma sem etapa: só pela equipe
    return in_array($etapa, $etapas, true);
}

/**
 * O professor pode lecionar esta matéria? Só se ela estiver no cadastro
 * dele (TB_PROFESSOR_MATERIAS). Lista vazia = ele ainda não pode lecionar
 * nenhuma matéria.
 */
function eq_professor_pode_materia($materias, $nomeMateria)
{
    if (!$materias) return false;
    return isset($materias[mb_strtolower((string)$nomeMateria, 'UTF-8')]);
}

/**
 * Professores elegíveis para dar matéria numa turma: quem está na equipe
 * ($idsEquipe, mapa id => true) OU tem categoria compatível com a etapa.
 * Devolve mapa id => ['NOME' => ..., 'MATERIAS' => mapa de matérias].
 */
function eq_elegiveis_mapa($idsEquipe, $etapa)
{
    $idsEquipe = (array)$idsEquipe;
    $profs = ch_rows(
        'SELECT ID, NOME, CATEGORIA FROM TB_USUARIOS
         WHERE NIVEL = 3 AND ISNULL(ATIVO, 1) = 1 ORDER BY NOME'
    ) ?: [];
    $elegiveis = [];
    foreach ($profs as $p) {
        $id = (int)$p['ID'];
        if (!isset($idsEquipe[$id]) && !eq_professor_pode_turma($p['CATEGORIA'] ?? null, $etapa)) {
            continue;
        }
        $elegiveis[$id] = ['NOME' => (string)$p['NOME'], 'MATERIAS' => []];
    }
    if ($elegiveis) {
        $ph = implode(',', array_fill(0, count($elegiveis), '?'));
        $linhas = ch_rows(
            "SELECT ID_USUARIO, NOME FROM TB_PROFESSOR_MATERIAS WHERE ID_USUARIO IN ({$ph}) ORDER BY NOME",
            array_keys($elegiveis)
        ) ?: [];
        foreach ($linhas as $l) {
            $elegiveis[(int)$l['ID_USUARIO']]['MATERIAS'][mb_strtolower((string)$l['NOME'], 'UTF-8')] = (string)$l['NOME'];
        }
    }
    return $elegiveis;
}

/** Elegíveis como lista (para os selects), na ordem alfabética. */
function eq_professores_elegiveis($idTurma, $etapa)
{
    $idsEquipe = [];
    foreach (eq_professores_disciplina($idTurma) as $m) {
        $idsEquipe[(int)$m['ID']] = true;
    }
    $lista = [];
    foreach (eq_elegiveis_mapa($idsEquipe, $etapa) as $id => $e) {
        $lista[] = ['ID' => $id, 'NOME' => $e['NOME']];
    }
    return $lista;
}

/**
 * Garante o professor na equipe da turma (para ele conseguir lançar no
 * diário). Se ainda não tem linha, entra como AUXILIAR com o turno dele;
 * se a linha existe mas está desativada (e não é substituto), reativa.
 * Deve ser chamado DENTRO de uma transação.
 */
function eq_professor_garantir_equipe($idTurma, $idUsuario)
{
    $idTurma = (int)$idTurma;
    $idUsuario = (int)$idUsuario;
    $turno = ch_val('SELECT TURNO FROM TB_USUARIOS WHERE ID = ?', [$idUsuario]);
    $turno = ($turno && isset(eq_turnos()[$turno])) ? $turno : 'INTEGRAL';
    $linha = ch_row(
        'SELECT ID, PAPEL, ATIVO FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ID_USUARIO = ?',
        [$idTurma, $idUsuario]
    );
    if (!$linha) {
        return ch_exec(
            "INSERT INTO TB_TURMA_PROFESSORES (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ATIVO)
             VALUES (?, ?, 'AUXILIAR', ?, 1)",
            [$idTurma, $idUsuario, $turno]
        );
    }
    if (!(int)$linha['ATIVO']) {
        return ch_exec(
            "UPDATE TB_TURMA_PROFESSORES
                SET PAPEL = 'AUXILIAR', TURNO = ?, ATIVO = 1, VINCULO_DIRETO = 0,
                    ID_SUBSTITUIDO = NULL, DATA_INICIO = NULL, DATA_FIM = NULL, MOTIVO = NULL,
                    VER_OBSERVACOES = 0, OBS_LIBERADA_POR = NULL, OBS_LIBERADA_EM = NULL
              WHERE ID = ?",
            [$turno, (int)$linha['ID']]
        );
    }
    return true;
}

/* ==========================================================================
   LISTA DE MATÉRIAS E ATRIBUIÇÕES POR AULAS
   --------------------------------------------------------------------------
   As matérias vêm de uma lista fixa (para não digitar errado). A turma
   define o TOTAL de aulas de cada matéria; a tabela TB_ATRIBUICOES divide
   essas aulas entre os professores. O painel mostra quantas aulas ainda
   faltam ser atribuídas, e cada professor tem teto de 36 h/a por semana.
   ========================================================================== */

/** Lista de matérias oferecida nos selects (cadastro, turma e painel). */
function eq_materias_lista()
{
    return [
        'Língua Portuguesa', 'Matemática', 'Ciências', 'História', 'Geografia',
        'Artes', 'Educação Física', 'Inglês', 'Espanhol', 'Ensino Religioso',
        'Informática', 'Filosofia', 'Sociologia', 'Projeto de Vida',
        'Linguagem e Comunicação', 'Motricidade', 'Socialização', 'Música',
        'Recreação', 'Higiene e Saúde', 'Alimentação', 'Psicomotricidade',
        'Expressão Corporal', 'Natureza e Sociedade', 'Identidade e Autonomia',
        'Leitura e Escrita',
    ];
}

/**
 * Opções dos selects de matéria: a lista fixa MAIS os nomes que já estão
 * em uso no banco (para não quebrar turmas/professores antigos).
 */
function eq_materias_opcoes()
{
    $opcoes = eq_materias_lista();
    $vistos = [];
    foreach ($opcoes as $n) { $vistos[mb_strtolower($n, 'UTF-8')] = true; }
    $extras = ch_rows(
        'SELECT DISTINCT NOME FROM TB_TURMA_DISCIPLINAS
         UNION
         SELECT DISTINCT NOME FROM TB_PROFESSOR_MATERIAS'
    ) ?: [];
    foreach ($extras as $e) {
        $nome = (string)$e['NOME'];
        if (!isset($vistos[mb_strtolower($nome, 'UTF-8')])) {
            $opcoes[] = $nome;
            $vistos[mb_strtolower($nome, 'UTF-8')] = true;
        }
    }
    sort($opcoes, SORT_STRING | SORT_FLAG_CASE);
    return $opcoes;
}

/** O nome de matéria é válido? (lista fixa ou já em uso no banco) */
function eq_materia_valida($nome)
{
    $nome = trim((string)$nome);
    if ($nome === '') return false;
    foreach (eq_materias_opcoes() as $op) {
        if (mb_strtolower($op, 'UTF-8') === mb_strtolower($nome, 'UTF-8')) return true;
    }
    return false;
}

/** Teto de aulas semanais por professor (h/a). */
function eq_carga_maxima()
{
    return 36;
}

/** Atribuições de uma matéria: quem dá quantas aulas. */
function eq_atribuicoes($idDisciplina)
{
    return ch_rows(
        'SELECT a.ID, a.ID_PROFESSOR, a.AULAS, u.NOME AS PROFESSOR
         FROM TB_ATRIBUICOES a
         INNER JOIN TB_USUARIOS u ON u.ID = a.ID_PROFESSOR
         WHERE a.ID_DISCIPLINA = ? ORDER BY u.NOME',
        [(int)$idDisciplina]
    ) ?: [];
}

/** Quantas aulas da matéria já foram atribuídas (soma dos professores). */
function eq_disciplina_atribuidas($idDisciplina)
{
    return (int)ch_val(
        'SELECT ISNULL(SUM(AULAS), 0) FROM TB_ATRIBUICOES WHERE ID_DISCIPLINA = ?',
        [(int)$idDisciplina]
    );
}

/** Quantas aulas ainda faltam ser atribuídas na matéria (NULL = total indefinido). */
function eq_disciplina_restam($idDisciplina, $total)
{
    if ($total === null || $total === '') return null;
    return max(0, (int)$total - eq_disciplina_atribuidas($idDisciplina));
}

/** Carga semanal do professor no ano letivo (soma de todas as atribuições). */
function eq_professor_carga($idProfessor, $anoLetivo)
{
    return (int)ch_val(
        'SELECT ISNULL(SUM(a.AULAS), 0)
         FROM TB_ATRIBUICOES a
         INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
         INNER JOIN TB_TURMAS t ON t.ID = d.ID_TURMA
         WHERE a.ID_PROFESSOR = ? AND t.ANO_LETIVO = ? AND t.ATIVO = 1',
        [(int)$idProfessor, (int)$anoLetivo]
    );
}

/**
 * Mantém TB_TURMA_DISCIPLINAS.ID_PROFESSOR como o professor com mais aulas
 * na matéria (legado, para telas antigas). Deve ser chamado DENTRO de
 * transação, depois de mexer nas atribuições.
 */
function eq_disciplina_sincronizar_responsavel($idDisciplina)
{
    $idDisciplina = (int)$idDisciplina;
    $topo = ch_row(
        'SELECT TOP 1 ID_PROFESSOR FROM TB_ATRIBUICOES
         WHERE ID_DISCIPLINA = ? ORDER BY AULAS DESC, ID_PROFESSOR',
        [$idDisciplina]
    );
    return ch_exec(
        'UPDATE TB_TURMA_DISCIPLINAS SET ID_PROFESSOR = ? WHERE ID = ?',
        [$topo ? (int)$topo['ID_PROFESSOR'] : null, $idDisciplina]
    );
}

/**
 * Atribui $aulas aulas de uma matéria a um professor (cria ou soma).
 * $elegiveis é o mapa de eq_elegiveis_mapa() para a turma (equipe ou
 * categoria compatível). Valida: elegível, matéria cadastrada para ele,
 * aulas dentro do que falta e teto de 36 h/a no ano.
 * Deve ser chamado DENTRO de uma transação. Devolve [ok, erros].
 */
function eq_atribuir($idDisciplina, $idProfessor, $aulas, $elegiveis, $anoLetivo)
{
    $erros = [];
    $idDisciplina = (int)$idDisciplina;
    $idProfessor = (int)$idProfessor;
    $aulas = (int)$aulas;
    $disc = ch_row(
        'SELECT d.ID, d.NOME, d.AULAS, d.ID_TURMA FROM TB_TURMA_DISCIPLINAS d WHERE d.ID = ?',
        [$idDisciplina]
    );
    if (!$disc) {
        return [false, ['Matéria não encontrada.']];
    }
    if ($aulas < 1 || $aulas > 40) {
        $erros[] = 'Informe de 1 a 40 aulas para atribuir.';
    }
    if (!isset($elegiveis[$idProfessor])) {
        $erros[] = 'Este professor não pode ser atribuído a esta turma (equipe ou categoria do concurso).';
    } elseif (!eq_professor_pode_materia($elegiveis[$idProfessor]['MATERIAS'], $disc['NOME'])) {
        $erros[] = 'O professor ' . $elegiveis[$idProfessor]['NOME']
                 . ' não está cadastrado para lecionar "' . $disc['NOME'] . '".';
    }
    /* O que já está atribuído a ele nesta matéria (para somar, não duplicar). */
    $jaTem = (int)ch_val(
        'SELECT ISNULL(AULAS, 0) FROM TB_ATRIBUICOES WHERE ID_DISCIPLINA = ? AND ID_PROFESSOR = ?',
        [$idDisciplina, $idProfessor]
    );
    /* Não pode passar do total da matéria. */
    if (!$erros && $disc['AULAS'] !== null && $disc['AULAS'] !== '') {
        $restam = (int)$disc['AULAS'] - eq_disciplina_atribuidas($idDisciplina) + $jaTem;
        if ($aulas > $restam) {
            $erros[] = 'A matéria "' . $disc['NOME'] . '" tem ' . $restam . ' aula(s) ainda não atribuída(s).';
        }
    }
    /* Teto de 36 h/a no ano letivo. */
    if (!$erros) {
        $carga = eq_professor_carga($idProfessor, $anoLetivo) - $jaTem;
        if ($carga + $aulas > eq_carga_maxima()) {
            $erros[] = 'O professor ' . ($elegiveis[$idProfessor]['NOME'] ?? '')
                     . ' já tem ' . $carga . ' h/a neste ano. O máximo é ' . eq_carga_maxima() . ' h/a.';
        }
    }
    if ($erros) {
        return [false, $erros];
    }
    $ok = $jaTem > 0
        ? ch_exec('UPDATE TB_ATRIBUICOES SET AULAS = ? WHERE ID_DISCIPLINA = ? AND ID_PROFESSOR = ?',
                  [$aulas, $idDisciplina, $idProfessor])
        : ch_exec('INSERT INTO TB_ATRIBUICOES (ID_DISCIPLINA, ID_PROFESSOR, AULAS) VALUES (?, ?, ?)',
                  [$idDisciplina, $idProfessor, $aulas]);
    if (!$ok) {
        return [false, ['Não foi possível gravar a atribuição.']];
    }
    /* Quem recebe aulas entra na equipe (senão não lança no diário). */
    if (!eq_professor_garantir_equipe((int)$disc['ID_TURMA'], $idProfessor)) {
        return [false, ['Não foi possível incluir o professor na equipe da turma.']];
    }
    if (!eq_disciplina_sincronizar_responsavel($idDisciplina)) {
        return [false, ['Não foi possível atualizar o professor responsável.']];
    }
    return [true, []];
}

/** Remove uma atribuição. Deve ser chamado DENTRO de uma transação. */
function eq_atribuicao_remover($idAtribuicao)
{
    $idAtribuicao = (int)$idAtribuicao;
    $linha = ch_row(
        'SELECT a.ID_DISCIPLINA, a.ID_PROFESSOR, d.ID_TURMA
           FROM TB_ATRIBUICOES a
           INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
          WHERE a.ID = ?',
        [$idAtribuicao]
    );
    if (!$linha) {
        return false;
    }
    if (!ch_exec('DELETE FROM TB_ATRIBUICOES WHERE ID = ?', [$idAtribuicao])) {
        return false;
    }
    if (!eq_disciplina_sincronizar_responsavel((int)$linha['ID_DISCIPLINA'])) {
        return false;
    }

    /* Se o professor não tem mais nenhuma atribuição de matéria nesta turma
       e não foi atribuído diretamente sem matéria (VINCULO_DIRETO = 1),
       retira-o da equipe titular (exceto se for substituto ativo). */
    $aindaTem = (int)ch_val(
        'SELECT COUNT(*) FROM TB_ATRIBUICOES a
          INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
          WHERE d.ID_TURMA = ? AND a.ID_PROFESSOR = ?',
        [(int)$linha['ID_TURMA'], (int)$linha['ID_PROFESSOR']]
    );
    if ($aindaTem === 0) {
        ch_exec(
            "UPDATE TB_TURMA_PROFESSORES SET ATIVO = 0
              WHERE ID_TURMA = ? AND ID_USUARIO = ? AND PAPEL <> 'SUBSTITUTO' AND ISNULL(VINCULO_DIRETO, 0) = 0",
            [(int)$linha['ID_TURMA'], (int)$linha['ID_PROFESSOR']]
        );
    }
    return true;
}

/** Sincroniza TB_TURMAS.ID_PROFESSOR com o regente ativo da turma (legado). */
function eq_sincronizar_regente_turma($idTurma)
{
    $idTurma = (int)$idTurma;
    $idReg = ch_val(
        "SELECT TOP 1 ID_USUARIO FROM TB_TURMA_PROFESSORES
          WHERE ID_TURMA = ? AND ATIVO = 1 AND PAPEL = 'REGENTE'
          ORDER BY CASE TURNO WHEN 'INTEGRAL' THEN 1 WHEN 'MANHA' THEN 2 ELSE 3 END, ID",
        [$idTurma]
    );
    return ch_exec('UPDATE TB_TURMAS SET ID_PROFESSOR = ? WHERE ID = ?', [$idReg ? (int)$idReg : null, $idTurma]);
}

/**
 * Atribui professor sem matéria em turmas de Creche (regente por período ou
 * apoio o dia todo) ou Pré-escola (PEB I regente da turma).
 * Deve ser chamado DENTRO de uma transação.
 */
function eq_atribuir_vinculo_direto($idTurma, $idProfessor, $papel, $turno)
{
    $idTurma = (int)$idTurma;
    $idProfessor = (int)$idProfessor;
    $papel = strtoupper(trim((string)$papel));
    $turno = strtoupper(trim((string)$turno));

    $turma = ch_row(
        'SELECT t.ID, t.NOME, t.SERIE, t.ETAPA_ENSINO, p.TURNO AS TURNO_TURMA
           FROM TB_TURMAS t
           INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
          WHERE t.ID = ? AND t.ATIVO = 1',
        [$idTurma]
    );
    if (!$turma) {
        return [false, ['Turma não encontrada ou inativa.']];
    }
    $series = ch_series_ensino();
    $etapa = $turma['ETAPA_ENSINO'] ?: ($series[$turma['SERIE'] ?? '']['etapa'] ?? '');
    $turnoTurma = $turma['TURNO_TURMA'] ?: 'INTEGRAL';

    $prof = ch_row(
        'SELECT ID, NOME, CATEGORIA FROM TB_USUARIOS WHERE ID = ? AND NIVEL = 3 AND ISNULL(ATIVO, 1) = 1',
        [$idProfessor]
    );
    if (!$prof) {
        return [false, ['Selecione um professor ativo válido.']];
    }
    $cat = (string)($prof['CATEGORIA'] ?? '');

    if ($etapa === 'INFANTIL_CRECHE') {
        if ($papel === 'REGENTE') {
            if (!in_array($turno, ['MANHA', 'TARDE'], true)) {
                return [false, ['Na Creche, cada professor regente assume um período (Manhã ou Tarde).']];
            }
            if ($cat !== '' && $cat !== 'PDI_CRECHE') {
                return [false, ['Para regente de período na Creche, selecione um professor da categoria "PDI — Creche".']];
            }
        } elseif ($papel === 'AUXILIAR') {
            $turno = 'INTEGRAL';
            if ($cat !== '' && $cat !== 'APOIO_CRECHE') {
                return [false, ['Para apoio de Creche (dia todo), selecione um professor da categoria "Professor de Apoio — Creche".']];
            }
        } else {
            return [false, ['Função inválida para turma de Creche.']];
        }
    } elseif ($etapa === 'INFANTIL_PRE_ESCOLA') {
        $papel = 'REGENTE';
        if ($turnoTurma !== 'INTEGRAL') {
            $turno = $turnoTurma;
        } elseif (!in_array($turno, ['MANHA', 'TARDE', 'INTEGRAL'], true)) {
            $turno = 'INTEGRAL';
        }
        if ($cat !== '' && !in_array($cat, ['PEB_I', 'PDI'], true)) {
            return [false, ['No Pré I e Pré II, a regência sem matéria é atribuída a professor PEB I.']];
        }
    } else {
        return [false, ['Esta turma utiliza atribuição por matérias.']];
    }

    /* Verifica conflito de responsável no mesmo turno (REGENTE). */
    if ($papel === 'REGENTE') {
        $regentesAtivos = ch_rows(
            "SELECT tp.ID_USUARIO, tp.TURNO, u.NOME
               FROM TB_TURMA_PROFESSORES tp
               INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
              WHERE tp.ID_TURMA = ? AND tp.ATIVO = 1 AND tp.PAPEL = 'REGENTE' AND tp.ID_USUARIO <> ?",
            [$idTurma, $idProfessor]
        ) ?: [];
        foreach ($regentesAtivos as $ra) {
            if (eq_turno_sobrepoe($turno, $ra['TURNO'])) {
                return [false, ['Já existe professor responsável atribuído neste período (' . $ra['NOME'] . '). Remova-o antes de trocar.']];
            }
        }
    }

    /* Verifica se o professor já está ativo nesta mesma turma. */
    $existente = ch_row(
        'SELECT ID, PAPEL, TURNO, ATIVO, ISNULL(VINCULO_DIRETO, 0) AS VINCULO_DIRETO
           FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ID_USUARIO = ?',
        [$idTurma, $idProfessor]
    );
    if ($existente && (int)$existente['ATIVO'] === 1) {
        if ($existente['PAPEL'] === 'SUBSTITUTO') {
            return [false, ['Este professor está com uma substituição ativa nesta turma. Encerre a substituição antes de atribuí-lo como titular.']];
        }
        if ((int)$existente['VINCULO_DIRETO'] === 1) {
            return [false, ['Este professor já está atribuído nesta turma (' . eq_turno_rotulo($existente['TURNO']) . ').']];
        }
        /* Já estava apenas como AUXILIAR por matéria específica: promove ao vínculo direto. */
        $ok = ch_exec(
            "UPDATE TB_TURMA_PROFESSORES
                SET PAPEL = ?, TURNO = ?, ATIVO = 1, VINCULO_DIRETO = 1
              WHERE ID = ?",
            [$papel, $turno, (int)$existente['ID']]
        );
    } elseif ($existente) {
        $ok = ch_exec(
            "UPDATE TB_TURMA_PROFESSORES
                SET PAPEL = ?, TURNO = ?, ATIVO = 1, VINCULO_DIRETO = 1,
                    ID_SUBSTITUIDO = NULL, DATA_INICIO = NULL, DATA_FIM = NULL, MOTIVO = NULL,
                    VER_OBSERVACOES = 0, OBS_LIBERADA_POR = NULL, OBS_LIBERADA_EM = NULL
              WHERE ID = ?",
            [$papel, $turno, (int)$existente['ID']]
        );
    } else {
        $ok = ch_exec(
            'INSERT INTO TB_TURMA_PROFESSORES (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ATIVO, VINCULO_DIRETO)
             VALUES (?, ?, ?, ?, 1, 1)',
            [$idTurma, $idProfessor, $papel, $turno]
        );
    }

    if (!$ok || !eq_sincronizar_regente_turma($idTurma)) {
        return [false, ['Não foi possível gravar a atribuição na turma.']];
    }
    return [true, []];
}

/** Remove um vínculo direto sem matéria (Creche / Apoio / Pré-escola). */
function eq_remover_vinculo_direto($idTurmaProf)
{
    $linha = ch_row(
        "SELECT ID, ID_TURMA, ID_USUARIO, PAPEL
           FROM TB_TURMA_PROFESSORES
          WHERE ID = ? AND ATIVO = 1 AND PAPEL IN ('REGENTE', 'AUXILIAR')",
        [(int)$idTurmaProf]
    );
    if (!$linha) return false;

    $temAulas = (int)ch_val(
        'SELECT COUNT(*) FROM TB_ATRIBUICOES a
          INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
          WHERE d.ID_TURMA = ? AND a.ID_PROFESSOR = ?',
        [(int)$linha['ID_TURMA'], (int)$linha['ID_USUARIO']]
    );
    if ($temAulas > 0) {
        $ok = ch_exec(
            "UPDATE TB_TURMA_PROFESSORES SET PAPEL = 'AUXILIAR', VINCULO_DIRETO = 0 WHERE ID = ?",
            [(int)$linha['ID']]
        );
    } else {
        $ok = ch_exec(
            'UPDATE TB_TURMA_PROFESSORES SET ATIVO = 0, VINCULO_DIRETO = 0 WHERE ID = ?',
            [(int)$linha['ID']]
        );
    }
    if (!$ok) return false;
    return eq_sincronizar_regente_turma((int)$linha['ID_TURMA']);
}
