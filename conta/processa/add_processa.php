<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
if (!empty($_POST)) {

    $id = ($_POST['id']);
    $senha = $_POST['senha'] ?? '';
    $email = $_POST['email'] ?? '';

    if (!empty($senha)) {
        $senha = md5($senha);
        $sql = "UPDATE TB_USUARIOS SET SENHA = ?, MODIFIED = ?, EMAIL = ? WHERE ID = ?";
        $params = array($senha, GETDATE(), $email, $id);
    } else {
        $sql = "UPDATE TB_USUARIOS SET EMAIL = ?, MODIFIED = ?  WHERE ID = ?";
        $params = array($email, GETDATE(), $id);
    }

    $stmt = sqlsrv_query($conn, $sql, $params);

    echo '<script>
    alert("Senha Alterada com Sucesso!");
    window.location.href = "' . BASEURL . 'index.php' . '";
</script>';
    exit;

?>
    <?php
    // if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
    //     header("location:" . BASEURL . 'solicitacoes/index2.php');
    // } else {
    //     header("location:" . BASEURL);
    // }
    ?>

    <?php

}
