<?php
ob_start();
require_once("tema.php");
require_once('auth_periodo.php');

// Verifique se o usuário está logado antes de mostrar a sidebar
if (isset($_SESSION['usuarioId'])) {
?>

  <script>
    var usuarioLogado = <?php echo $_SESSION['usuarioId']; ?>;
    var projeto1Prefixo = "<?php echo BASEURL; ?>";
  </script>



  <body class="no-print" onload="applyAccessibilitySettings()">
    <?php require_once(ABSPATH . 'inc/sidebar.php'); ?>

    <!-- Conteúdo Principal -->
    <div class="content">
    <?php
  } else {
    header("location:" . BASEURL . "login.php");
    exit;
  }
    ?>

    <script>
      let showingAlert = false;
      const title = '<?php echo 'Chamada de Alunos' ?>';
      const messages = 'Prefeitura de Angatuba';

      const changeTitle = setInterval(() => {
        document.title = showingAlert ? title : messages;
        showingAlert = !showingAlert;
      }, 700);

    </script>
