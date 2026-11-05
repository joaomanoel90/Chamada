<?php

/** O nome do banco de dados*/
define('DB_NAME', 'db_chamada');
/** Usuário do banco de dados SQL */

define('DB_USER', 'user_chamada');

/** Senha do banco de dados SQL */
define('DB_PASSWORD', '');

/** nome do host do SQL */
define('DB_HOST', '127.0.0.1');
/** porta do host do SQL */
define('DB_PORT', '1433');
/** caminho absoluto para a pasta do sistema **/
if (!defined('ABSPATH'))
	define('ABSPATH', dirname(__FILE__) . '/');

/** caminho no server para o sistema **/
if (!defined('BASEURL'))
	define('BASEURL', '/Chamada/');

/** caminho do arquivo de banco de dados **/
if (!defined('DBAPI'))
	define('DBAPI', ABSPATH . 'inc/database.php');

/** caminhos dos templates de header e footer **/
define('TEMA_TEMPLATE', ABSPATH . 'inc/tema.php');
define('HEADER_TEMPLATE', ABSPATH . 'inc/header.php');
define('HEADER_TEMPLATE2', ABSPATH . 'inc/header2.php');
define('FOOTER_TEMPLATE', ABSPATH . 'inc/footer.php');

$baseurl = BASEURL;
$baseurl_sem_barras = str_replace("/", "", $baseurl);
session_name($baseurl_sem_barras);
session_start();

// Trava global de autenticação para todas as telas
if (php_sapi_name() !== 'cli') {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $public_scripts = ['login.php', 'valida.php', 'sair.php'];
    if (!in_array($script, $public_scripts, true)) {
        if (!isset($_SESSION['usuarioId'])) {
            if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Not logged in']);
                exit;
            }
            header("Location: " . BASEURL . "login.php");
            exit;
        }
    }
}
