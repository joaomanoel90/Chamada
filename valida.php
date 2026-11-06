<?php
require_once('config.php');
require_once(DBAPI);

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// O campo usuário e senha preenchido entra no if para validar
if (isset($_POST['cpf']) && isset($_POST['senha'])) {

	// FILTER_SANITIZE_STRING foi depreciado no PHP 8.1
	$usuario = trim($_POST['cpf']);
	$senha = md5($_POST['senha']);

	// Buscar conexão
	$conn = open_database();
	if ($conn === false) {
		echo "<h3>Erro na conexão com SQL Server</h3>";
		echo "<pre>";
		print_r(sqlsrv_errors());
		echo "</pre>";
		exit;
	}
	// Verificar se a conexão foi realizada
	if ($conn === false) {

		echo "<h3>Erro ao conectar ao banco de dados.</h3>";
		echo "<pre>";

		if (function_exists('sqlsrv_errors')) {
			print_r(sqlsrv_errors());
		}

		echo "</pre>";
		exit;
	}

	// Consulta parametrizada
	$sql = "SELECT *
            FROM TB_USUARIOS
            WHERE CPF = ?
              AND SENHA = ?";

	$params = [$usuario, $senha];

	$stmt = sqlsrv_query($conn, $sql, $params);

	// Verificar erro na consulta
	if ($stmt === false) {

		echo "<h3>Erro ao executar consulta SQL.</h3>";
		echo "<pre>";

		if (function_exists('sqlsrv_errors')) {
			print_r(sqlsrv_errors());
		}

		echo "</pre>";
		exit;
	}

	$resultado = sqlsrv_has_rows($stmt);

	if ($resultado === true) {

		$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

		/* Usuário desativado não entra. ISNULL porque registros antigos podem
		   ter ATIVO nulo — nesses casos considera ativo, para não trancar
		   ninguém para fora depois da atualização do banco. */
		if ((int)($row['ATIVO'] ?? 1) !== 1) {
			sqlsrv_free_stmt($stmt);
			$_SESSION['loginErro'] = "Usuário inativo. Procure a administração da rede.";
			header("Location: login.php");
			exit;
		}

		$_SESSION['usuarioId'] = $row['ID'];
		$_SESSION['usuarioNome'] = $row['NOME'];
		$_SESSION['usuarioNiveisAcessoId'] = $row['NIVEL'];
		$_SESSION['usuarioCPF'] = $row['CPF'];

		if (
			$_SESSION['usuarioNiveisAcessoId'] == "1" ||
			$_SESSION['usuarioNiveisAcessoId'] == "2" ||
			$_SESSION['usuarioNiveisAcessoId'] == "3"
		) {
			header("Location: " . BASEURL . "index.php");
			exit;
		}

		sqlsrv_free_stmt($stmt);
	} else {

		sqlsrv_free_stmt($stmt);

		$_SESSION['loginErro'] = "Usuário ou senha inválido";

		header("Location: login.php");
		exit;
	}
} else {

	$_SESSION['loginErro'] = "Usuário ou senha não preenchidos";

	header("Location: login.php");
	exit;
}
