<?php
/* ============================================================================
   ADAPTADOR DE DEMONSTRAÇÃO — NÃO USAR EM PRODUÇÃO
   ----------------------------------------------------------------------------
   O servidor de produção usa SQL Server + driver sqlsrv (inc/database.php).
   Este arquivo só existe para demonstrar o módulo em um ambiente sem SQL Server:
   ele implementa as funções sqlsrv_* sobre PDO/SQLite e traduz o T-SQL usado.

   Em produção este arquivo NÃO é carregado: basta não usar o auto_prepend_file.
   ============================================================================ */

/* O servidor embutido do PHP aplica o auto_prepend_file também ao servir
   arquivos estáticos (quando o router devolve false), podendo executar este
   arquivo duas vezes na mesma requisição. Por isso as funções sqlsrv_* ficam
   em um arquivo separado, carregado com require_once — que o PHP garante
   incluir uma única vez, evitando "Cannot redeclare function". */
require_once __DIR__ . '/sqlsrv_shim.php';

if (!defined('BASEURL')) define('BASEURL', '/Chamada/');
if (!defined('SQLSRV_FETCH_ASSOC')) define('SQLSRV_FETCH_ASSOC', 2);
if (!defined('SQLSRV_FETCH_NUMERIC')) define('SQLSRV_FETCH_NUMERIC', 1);

date_default_timezone_set('America/Sao_Paulo');
