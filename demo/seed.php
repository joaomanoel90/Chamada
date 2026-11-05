<?php
/* Popula o banco da DEMONSTRAÇÃO com dados fictícios. */
$pdo = new PDO('sqlite:' . __DIR__ . '/demo.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
mt_srand(2026);
$ano = 2026;
$hoje = '2026-11-05';

$unidades = [
    'CMEI "VÓ CARMELA"', 'CMEI "VÓ IA"', 'CMEI "MENINO JESUS"',
    'CEMEIF "VÓ VIRGÍNIA"', 'CEMEIF "NHÁ NICA"',
];
foreach ($unidades as $u) {
    $pdo->prepare('INSERT INTO TB_UNIDADES (unidade) VALUES (?)')->execute([$u]);
}

// senha de todos: demo123
$senha = md5('demo123');
$usuarios = [
    ['MARIANA COSTA (GESTÃO)', 1, '111.111.111-11'],
    ['ANA PAULA RIBEIRO',      3, '222.222.222-22'],
    ['CARLOS EDUARDO LIMA',    3, '333.333.333-33'],
    ['JULIANA ALMEIDA',        3, '444.444.444-44'],
];
foreach ($usuarios as $u) {
    $pdo->prepare('INSERT INTO TB_USUARIOS (NOME, SENHA, NIVEL, CPF, ATIVO) VALUES (?,?,?,?,1)')
        ->execute([$u[0], $senha, $u[1], $u[2]]);
}
// vínculos usuário <-> unidade (TB_ACESSO)
// a gestão também precisa de vínculo: as telas filtram por TB_ACESSO
$vinculos = [[2,1],[2,3],[3,2],[4,4],[4,5]];
for ($un = 1; $un <= count($unidades); $un++) { $vinculos[] = [1, $un]; }
foreach ($vinculos as $v) {
    $pdo->prepare('INSERT INTO TB_ACESSO (id_usuario, id_unidade) VALUES (?,?)')->execute($v);
}

foreach ([['Manhã','07:00','12:00'], ['Tarde','13:00','18:00'], ['Integral','07:00','18:00']] as $p) {
    $pdo->prepare('INSERT INTO TB_PERIODOS (NOME,HORA_INICIO,HORA_FIM) VALUES (?,?,?)')->execute($p);
}

$turmas = [
    [1,'Berçário I',   2026,1,2,15],
    [1,'Maternal I',   2026,1,2,20],
    [2,'Berçário II',  2026,2,3,15],
    [4,'Maternal II',  2026,3,4,20],
    [5,'Maternal III', 2026,2,4,20],
];
foreach ($turmas as $t) {
    $pdo->prepare('INSERT INTO TB_TURMAS (ID_UNIDADE,NOME,ANO_LETIVO,ID_PERIODO,ID_PROFESSOR,CAPACIDADE) VALUES (?,?,?,?,?,?)')->execute($t);
}

$primeiros = ['Alice','Bento','Cecília','Davi','Elisa','Felipe','Gabriela','Heitor','Isadora','João','Lara','Miguel','Nina','Otávio','Pietra','Rafael','Sofia','Theo','Valentina','Yuri','Beatriz','Caio','Helena','Lucas'];
$sobrenomes = ['Silva','Souza','Oliveira','Santos','Pereira','Costa','Almeida','Ferreira','Rodrigues','Martins'];
$responsaveis = ['Maria','José','Ana','Paulo','Fernanda','Ricardo','Cláudia','Marcos'];
$mat = 1;
$alunosPorTurma = [];
foreach ($turmas as $ti => $t) {
    $qtd = [6,7,5,6,5][$ti];
    for ($i = 0; $i < $qtd; $i++) {
        $nome = $primeiros[($mat - 1) % count($primeiros)] . ' ' . $sobrenomes[mt_rand(0, count($sobrenomes)-1)] . ' ' . $sobrenomes[mt_rand(0, count($sobrenomes)-1)];
        $idadeAnos = $ti === 0 || $ti === 2 ? mt_rand(0,1) : mt_rand(2,3);
        $nasc = date('Y-m-d', strtotime($hoje . " -$idadeAnos years -" . mt_rand(10,330) . ' days'));
        $pdo->prepare('INSERT INTO TB_ALUNOS (ID_TURMA,MATRICULA,NOME,NASCIMENTO,DATA_MATRICULA,RESPONSAVEL,TELEFONE) VALUES (?,?,?,?,?,?,?)')
            ->execute([$ti+1, sprintf('MAT-%04d', $mat), $nome, $nasc, $ano.'-02-02',
                       $responsaveis[mt_rand(0,count($responsaveis)-1)] . ' ' . $sobrenomes[mt_rand(0,count($sobrenomes)-1)],
                       '(15) 9' . mt_rand(1000,9999) . '-' . mt_rand(1000,9999)]);
        $alunosPorTurma[$ti+1][] = $mat;
        $mat++;
    }
}

$feriados = [
    [$ano.'-02-16','Carnaval','INTEGRAL',null,null],
    [$ano.'-02-17','Carnaval','INTEGRAL',null,null],
    [$ano.'-04-03','Paixão de Cristo','INTEGRAL',null,null],
    [$ano.'-04-21','Tiradentes','INTEGRAL',null,null],
    [$ano.'-05-01','Dia do Trabalho','INTEGRAL',null,null],
    [$ano.'-06-04','Corpus Christi','INTEGRAL',null,null],
    [$ano.'-07-09','Revolução Constitucionalista','INTEGRAL',null,null],
    [$ano.'-09-07','Independência do Brasil','INTEGRAL',null,null],
    [$ano.'-10-12','Nossa Senhora Aparecida','INTEGRAL',null,null],
    [$ano.'-10-15','Dia do Professor','PARCIAL','12:00','18:00'],
    [$ano.'-11-02','Finados','INTEGRAL',null,null],
    [$ano.'-11-20','Consciência Negra','INTEGRAL',null,null],
    [$ano.'-12-24','Véspera de Natal','PARCIAL','12:00','18:00'],
    [$ano.'-12-25','Natal','INTEGRAL',null,null],
];
foreach ($feriados as $f) {
    $pdo->prepare('INSERT INTO TB_FERIADOS (DATA,DESCRICAO,TIPO,HORA_INICIO,HORA_FIM) VALUES (?,?,?,?,?)')->execute($f);
}
$bloqueados = [];
foreach ($feriados as $f) { if ($f[2] === 'INTEGRAL') $bloqueados[$f[0]] = true; }

// chamada: dias úteis de 02/02 até hoje
$periodos = [1=>['07:00','12:00'],2=>['13:00','18:00'],3=>['07:00','18:00']];
$chamadas = 0;
$inicio = new DateTime($ano.'-02-02');
$fim = new DateTime($hoje);
for ($d = clone $inicio; $d <= $fim; $d->modify('+1 day')) {
    $iso = $d->format('Y-m-d');
    $w = (int)$d->format('w');
    if ($w === 0 || $w === 6 || isset($bloqueados[$iso])) continue;
    foreach ($turmas as $ti => $t) {
        [$pi,$pf] = $periodos[$t[3]];
        // feriado parcial que atinge o período da turma
        $pula = false;
        foreach ($feriados as $f) {
            if ($f[0] === $iso && $f[2] === 'PARCIAL' && $f[3] < $pf && $f[4] > $pi) { $pula = true; break; }
        }
        if ($pula) continue;
        foreach ($alunosPorTurma[$ti+1] as $idAluno) {
            $r = mt_rand(1,100);
            $s = $r <= 88 ? 'P' : ($r <= 93 ? 'A' : ($r <= 97 ? 'F' : 'J'));
            $j = $s === 'J' ? 'Falta comunicada pela família.' : null;
            $pdo->prepare('INSERT INTO TB_CHAMADA (ID_ALUNO,DATA,STATUS,JUSTIFICATIVA,ID_USUARIO) VALUES (?,?,?,?,?)')
                ->execute([$idAluno, $iso, $s, $j, $t[4]]);
            $chamadas++;
        }
    }
}

$categorias = ['Desenvolvimento geral','Linguagem e comunicação','Coordenação motora','Convívio social','Alimentação','Sono e descanso','Autonomia','Contato com a família'];
$textos = [
    'Ampliou o repertório de palavras e passou a formar frases curtas durante as rodas de conversa.',
    'Demonstrou mais segurança ao explorar o parque, subindo e descendo com apoio mínimo.',
    'Interagiu bem com os colegas em brincadeiras coletivas, compartilhando brinquedos espontaneamente.',
    'Aceitou melhor a variedade de alimentos oferecidos na refeição, experimentando novos sabores.',
    'Adaptou-se bem ao horário de descanso, dormindo com mais tranquilidade após o almoço.',
    'Conquistou mais autonomia na higiene, avisando quando precisa ir ao banheiro.',
    'Família orientada sobre a importância da rotina de sono; houve melhora nas semanas seguintes.',
    'Mostrou interesse por livros de imagens, manuseando com cuidado e nomeando figuras.',
];
$obs = 0;
foreach ($alunosPorTurma as $idTurma => $ids) {
    $prof = $turmas[$idTurma-1][4];
    foreach ($ids as $idAluno) {
        for ($k = 0; $k < mt_rand(3,5); $k++) {
            $mes = mt_rand(2,10); $dia = mt_rand(1,28);
            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            if ($data > $hoje) continue;
            $pdo->prepare('INSERT INTO TB_OBSERVACOES (ID_ALUNO,DATA,CATEGORIA,TEXTO,ID_USUARIO) VALUES (?,?,?,?,?)')
                ->execute([$idAluno, $data, $categorias[mt_rand(0,count($categorias)-1)], $textos[mt_rand(0,count($textos)-1)], $prof]);
            $obs++;
        }
    }
}

foreach ($pdo->query('SELECT COUNT(*) FROM TB_UNIDADES') as $r) $u = $r[0];
foreach ($pdo->query('SELECT COUNT(*) FROM TB_ALUNOS') as $r) $a = $r[0];
foreach ($pdo->query('SELECT COUNT(*) FROM TB_TURMAS') as $r) $t = $r[0];
echo "Demonstração criada:\n";
echo "  unidades:    $u\n  turmas:      $t\n  alunos:      $a\n  chamadas:    $chamadas\n  observações: $obs\n  feriados:    " . count($feriados) . "\n";
echo "  login: CPF 111.111.111-11 (gestão) ou 222.222.222-22 (professor) — senha demo123\n";
