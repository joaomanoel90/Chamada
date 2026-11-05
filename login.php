<?php
require_once 'config.php';

if (isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "index.php");
    exit;
}
// session_name(str_replace("/", "", BASEURL));
// session_start();

include(TEMA_TEMPLATE);
?>

<style>
  body {
    background: linear-gradient(135deg, #eef2f7 0%, #dfe7ef 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    font-family: Arial, sans-serif;
  }

  .login-container {

    background: #fff;

    width: 100%;
    max-width: 460px;

    padding: 40px;

    border-radius: 22px;

    box-shadow:
      0 10px 35px rgba(0, 0, 0, 0.08),
      0 2px 8px rgba(0, 0, 0, 0.04);

    position: relative;

    overflow: hidden;
  }

  .login-container::before {

    content: "";

    position: absolute;

    top: 0;
    left: 0;

    width: 100%;
    height: 6px;

    background: linear-gradient(90deg, #2c3e50, #415b76);
  }

  .logo-area {

    text-align: center;

    margin-bottom: 30px;
  }

  .logo-area img {

    width: 85px;

    margin-bottom: 18px;
  }

  .logo-area h1 {

    color: #2c3e50;

    font-size: 2rem;

    margin-bottom: 5px;

    font-weight: bold;
  }

  .logo-area p {

    color: #6c757d;

    margin: 0;

    font-size: 0.95rem;
  }

  .form-label {

    font-weight: 600;

    color: #2c3e50;

    margin-bottom: 6px;
  }

  .input-group {

    border-radius: 12px;

    overflow: hidden;

    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  }

  .input-group-text {

    background: #2c3e50;

    color: #fff;

    border: none;

    width: 48px;

    justify-content: center;
  }

  .form-control {

    border: 1px solid #dce3ea;

    border-left: none;

    height: 48px;

    font-size: 0.95rem;
  }

  .form-control:focus {

    border-color: #2c3e50;

    box-shadow: none;
  }

  .btn-login {

    width: 100%;

    height: 50px;

    border: none;

    border-radius: 12px;

    background-color: #2c3e50;

    color: #fff;

    font-size: 1rem;

    font-weight: 600;

    transition: all 0.2s ease;
  }

  .btn-login:hover {

    background-color: #1a252f;

    transform: translateY(-1px);

    box-shadow: 0 8px 20px rgba(44, 62, 80, 0.25);
  }

  .alert {

    border: none;

    border-radius: 16px;

    padding: 14px 16px;

    margin-bottom: 20px;
  }

  .alert-danger {

    background: #fff1f0;

    color: #842029;
  }

  .alert-success {

    background: #eefaf1;

    color: #146c43;
  }

  .alert .icone {

    font-size: 1.6rem;

    margin-right: 12px;
  }

  .footer-login {

    text-align: center;

    margin-top: 25px;

    color: #7b8794;

    font-size: 0.82rem;
  }

  @media (max-width: 576px) {

    .login-container {

      padding: 28px 22px;

      border-radius: 18px;
    }

    .logo-area h1 {

      font-size: 1.7rem;
    }

    .logo-area img {

      width: 70px;
    }
  }
</style>

<body>

  <div class="login-container">

    <div class="logo-area">

      <img src="images/brasao.png" alt="Brasão Prefeitura">

      <h1>Chamada de Alunos</h1>

      <p>Prefeitura Municipal de Angatuba</p>

    </div>

    <!-- ERRO LOGIN -->
    <?php if (isset($_SESSION['loginErro'])) { ?>

      <div class="alert alert-danger shadow-sm">

        <div class="d-flex align-items-start">

          <div class="icone">
            <i class="fas fa-triangle-exclamation"></i>
          </div>

          <div>

            <strong>Acesso não permitido</strong><br>

            <small>
              <?php echo $_SESSION['loginErro']; ?>
            </small>

          </div>

        </div>

      </div>

      <?php unset($_SESSION['loginErro']); ?>

    <?php } ?>

    <!-- SUCESSO -->
    <?php if (isset($_SESSION['logindeslogado'])) { ?>

      <div class="alert alert-success shadow-sm">

        <div class="d-flex align-items-start">

          <div class="icone">
            <i class="fas fa-circle-check"></i>
          </div>

          <div>

            <strong>Sessão encerrada</strong><br>

            <small>
              <?php echo $_SESSION['logindeslogado']; ?>
            </small>

          </div>

        </div>

      </div>

      <?php unset($_SESSION['logindeslogado']); ?>

    <?php } ?>

    <!-- FORM -->
    <form method="post" action="valida.php">

      <div class="mb-3">

        <label class="form-label">CPF</label>

        <div class="input-group">

          <span class="input-group-text">
            <i class="fas fa-id-card"></i>
          </span>

          <input
            class="form-control"
            placeholder="Digite seu CPF"
            name="cpf"
            type="text"
            id="cpfcnpj"
            maxlength="14"
            autocomplete="username"
            required>

        </div>

      </div>

      <div class="mb-4">

        <label class="form-label">Senha</label>

        <div class="input-group">

          <span class="input-group-text">
            <i class="fas fa-lock"></i>
          </span>

          <input
            class="form-control"
            placeholder="Digite sua senha"
            name="senha"
            type="password"
            autocomplete="current-password"
            required>

        </div>

      </div>

      <button class="btn-login" type="submit">
        <i class="fas fa-right-to-bracket"></i>
        Entrar no Sistema
      </button>

    </form>

    <div class="footer-login">

      &copy; <?php echo date('Y'); ?>
      Prefeitura Municipal de Angatuba

    </div>

  </div>

</body>

<script src="<?php echo BASEURL; ?>js/mask.js"></script>

</html>