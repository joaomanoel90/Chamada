<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_admin();

/* Módulos gravados por ch_auditar(). A whitelist evita que um filtro qualquer
   vire uma expressão SQL — os valores também são sempre parametrizados. */
$modulos = ['Permissões', 'AEE', 'Feriados', 'Calendário Escolar', 'Unidades', 'Usuários', 'Família', 'Alunos'];

$fModulo = (string)($_GET['modulo'] ?? '');
if (!in_array($fModulo, $modulos, true)) {
    $fModulo = '';
}
$fUsuario = (int)($_GET['usuario_id'] ?? 0);
$fDe = (string)(ch_data_iso((string)($_GET['de'] ?? '')) ?? '');
$fAte = (string)(ch_data_iso((string)($_GET['ate'] ?? '')) ?? '');
if ($fDe !== '' && $fAte !== '' && $fDe > $fAte) {
    [$fDe, $fAte] = [$fAte, $fDe];
}
$fBusca = trim((string)($_GET['busca'] ?? ''));
if (mb_strlen($fBusca) > 100) {
    $fBusca = mb_substr($fBusca, 0, 100);
}

$where = ['1=1'];
$params = [];
if ($fModulo !== '') {
    $where[] = 'a.MODULO = ?';
    $params[] = $fModulo;
}
if ($fUsuario > 0) {
    $where[] = 'a.ID_USUARIO = ?';
    $params[] = $fUsuario;
}
if ($fDe !== '') {
    $where[] = 'CONVERT(date, a.CRIADO_EM) >= ?';
    $params[] = $fDe;
}
if ($fAte !== '') {
    $where[] = 'CONVERT(date, a.CRIADO_EM) <= ?';
    $params[] = $fAte;
}
if ($fBusca !== '') {
    $like = '%' . $fBusca . '%';
    $where[] = '(a.ACAO LIKE ? OR a.REGISTRO_ID LIKE ? OR a.DETALHES LIKE ? OR a.USUARIO_NOME LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$porPagina = 30;
$total = (int)ch_val('SELECT COUNT(*) FROM dbo.TB_AUDITORIA a WHERE ' . $whereSql, $params);
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = (int)($_GET['pagina'] ?? 1);
$pagina = max(1, min($paginas, $pagina));
$offset = ($pagina - 1) * $porPagina;

$logs = ch_rows(
    'SELECT a.ID, CONVERT(varchar(19), a.CRIADO_EM, 120) AS CRIADO_EM_TXT,
            a.ID_USUARIO, a.USUARIO_NOME, a.MODULO, a.ACAO, a.REGISTRO_TIPO,
            a.REGISTRO_ID, a.IP, a.DETALHES,
            u.NOME AS USUARIO_ATUAL, u.ATIVO AS USUARIO_ATIVO
       FROM dbo.TB_AUDITORIA a
       LEFT JOIN dbo.TB_USUARIOS u ON u.ID = a.ID_USUARIO
      WHERE ' . $whereSql . '
      ORDER BY a.ID DESC
      OFFSET ? ROWS FETCH NEXT ? ROWS ONLY',
    array_merge($params, [$offset, $porPagina])
);

$usuariosFiltro = ch_rows('SELECT ID, NOME, ATIVO FROM dbo.TB_USUARIOS ORDER BY NOME');

$filtros = array_filter([
    'modulo'     => $fModulo,
    'usuario_id' => $fUsuario > 0 ? $fUsuario : null,
    'de'         => $fDe,
    'ate'        => $fAte,
    'busca'      => $fBusca,
], function ($v) {
    return $v !== '' && $v !== null;
});
