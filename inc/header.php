<?php
ob_start();
require_once("tema.php");


// Verifique se o usuário está logado antes de mostrar a sidebar
if (isset($_SESSION['usuarioId'])) {
?>

  <script>
    var usuarioLogado = <?php echo $_SESSION['usuarioId']; ?>;
    var projeto1Prefixo = <?php echo BASEURL; ?>;
  </script>


  <body class="no-print" onload="applyAccessibilitySettings()">
    <!-- Botão de Toggle Sidebar -->
    <button class="toggle-btn" id="sidebarToggle">☰</button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <img src="https://cdn-icons-png.flaticon.com/128/3649/3649291.png" alt="Produtos" />
        <h2>Prefeitura de Angatuba</h2>
      </div>

      <!-- Links da Sidebar -->
      <a class="nav-link text-white" id="dashboard" href="<?php echo BASEURL; ?>index.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
      </a>
      <a class="nav-link text-white" id="analise" href="<?php echo BASEURL; ?>analise/index.php"><i class="fas fa-search"></i> <span>Analisar Pedidos</span></a>
      <a class="nav-link text-white" id="consumo" href="<?php echo BASEURL; ?>analise/consumo.php"><i class="fas fa-chart-column"></i> <span>Consumo por Unidade</span></a>
      <a class="nav-link text-white" id="pedidos" href="<?php echo BASEURL; ?>pedidos/index.php"><i class="fas fa-shopping-basket	"></i> <span>Pedidos</span></a>
      <a class="nav-link text-white" id="reserva" href="<?php echo BASEURL; ?>reserva/index.php"><i class="fas fa-warehouse	"></i> <span>Reserva de Estoque</span></a>

      <hr class="border-light">

      <a class="nav-link text-white" id="eventuais" href="<?php echo BASEURL; ?>produtos/index.php"><i class="fas fa-box"></i> <span>Produtos</span></a>
      <a class="nav-link text-white" id="unidades" href="<?php echo BASEURL; ?>unidades/index.php"><i class="far fa-building"></i> <span>Unidades</span></a>
      <a class="nav-link text-white" id="fornecedores" href="<?php echo BASEURL; ?>fornecedores/index.php"><i class="fas fa-truck"></i> <span>Fornecedores</span></a>
      <a class="nav-link text-white" id="almoxarifados" href="<?php echo BASEURL; ?>almoxarifados/index.php"><i class="fas fa-warehouse"></i> <span>Almoxarifados</span></a>
      <a class="nav-link text-white" id="usuarios" href="<?php echo BASEURL; ?>usuarios/index.php"><i class="fas fa-users"></i> <span>Usuários</span></a>
      <a class="nav-link text-white" id="config_email" href="<?php echo BASEURL; ?>config_email/index.php"><i class="fas fa-envelope"></i> <span>Configurar E-mail</span></a>

      <hr class="border-light">
      <p class="text-white-50 small mb-1 px-2">CHAMADA DE ALUNOS</p>
      <a class="nav-link text-white" id="chamada" href="<?php echo BASEURL; ?>chamada/index.php"><i class="fas fa-clipboard-user"></i> <span>Chamada do Dia</span></a>
      <a class="nav-link text-white" id="alunos" href="<?php echo BASEURL; ?>alunos/index.php"><i class="fas fa-child-reaching"></i> <span>Alunos</span></a>
      <a class="nav-link text-white" id="turmas" href="<?php echo BASEURL; ?>turmas/index.php"><i class="fas fa-children"></i> <span>Turmas</span></a>
      <a class="nav-link text-white" id="periodos" href="<?php echo BASEURL; ?>periodos/index.php"><i class="fas fa-clock"></i> <span>Períodos</span></a>
      <a class="nav-link text-white" id="feriados" href="<?php echo BASEURL; ?>feriados/index.php"><i class="fas fa-calendar-day"></i> <span>Feriados</span></a>
      <a class="nav-link text-white" id="observacoes" href="<?php echo BASEURL; ?>observacoes/index.php"><i class="far fa-comment-dots"></i> <span>Observações</span></a>
      <a class="nav-link text-white" id="relatorios" href="<?php echo BASEURL; ?>relatorios/index.php"><i class="fas fa-file-lines"></i> <span>Relatório Anual</span></a>

      <hr class="border-light">
      <a class="nav-link text-white" href="<?php echo BASEURL; ?>conta/index.php"><i class="fas fa-key"></i> <span>Alterar Senha</span></a>
      <a class="nav-link text-white" href="<?php echo BASEURL; ?>sair.php"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a>
      <hr class="border-light">

      <p class="text-white small">



        <?php //echo ($_SESSION['usuarioId']); 
        ?>
        <?php //echo ($_SESSION['usuarioCPF']); 
        ?><br>
        <?php
        //$ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        //echo "IP: " . $ip;
        ?>
      </p>
    </div>

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
      const title = '<?php echo 'Administração' ?>';
      const messages = 'Prefeitura de Angatuba';

      const changeTitle = setInterval(() => {
        document.title = showingAlert ? title : messages;
        showingAlert = !showingAlert;
      }, 700);

      // Seleciona todos os links da sidebar
      const links = document.querySelectorAll('.nav-link');

      // Função para remover a classe 'active' de todos os links
      function removeActiveClass() {
        links.forEach(link => {
          link.classList.remove('active');
        });
      }

      // Adiciona o evento de clique em cada link
      links.forEach(link => {
        link.addEventListener('click', (event) => {
          // Remove a classe 'active' de todos os links
          removeActiveClass();
          // Adiciona a classe 'active' ao link clicado
          event.currentTarget.classList.add('active');
        });
      });

      // Marcar o link atual como ativo ao carregar a página
      document.addEventListener('DOMContentLoaded', () => {
        const currentUrl = window.location.pathname;
        links.forEach(link => {
          if (link.href.includes(currentUrl)) {
            link.classList.add('active');
          }
        });
      });

      // Função para o botão de toggle da sidebar
      const sidebarToggle = document.getElementById('sidebarToggle');
      const sidebar = document.getElementById('sidebar');

      sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
      });
    </script>

  </body>