<?php
require_once 'config.php';
require_once(ABSPATH . 'inc/chamada.php');

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
    min-height: 100vh;
    min-height: 100svh;
    display: grid;
    place-items: center;
    padding: 24px;
    background:
      radial-gradient(ellipse at 10% 10%, rgba(68, 123, 148, .08), transparent 34%),
      radial-gradient(ellipse at 90% 90%, rgba(63, 120, 92, .06), transparent 32%),
      #f5f7f9 !important;
    color: #293847;
    font-family: 'Nunito', 'Segoe UI', system-ui, sans-serif;
  }

  .login-container {
    width: 100%;
    max-width: 440px;
    padding: 38px 40px 26px;
    border: 1px solid #e3e8ed;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 12px 32px rgba(41, 56, 71, .1);
    position: relative;
    overflow: hidden;
  }

  .login-container::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: #447b94;
  }

  .logo-area {
    text-align: center;
    margin-bottom: 28px;
  }

  .logo-area img {
    width: 72px;
    height: 72px;
    object-fit: contain;
    padding: 10px;
    margin-bottom: 16px;
    border-radius: 18px;
    background: #f2f5f7;
  }

  .logo-area h1 {
    margin: 0 0 5px;
    color: #293847;
    font-size: 1.65rem;
    font-weight: 700;
    letter-spacing: -.3px;
  }

  .logo-area p {
    margin: 0;
    color: #667583;
    font-size: .95rem;
  }

  .form-label {
    margin-bottom: 7px;
    color: #293847;
    font-size: .9rem;
    font-weight: 700;
  }

  .input-group {
    border: 1px solid #dfe5ea;
    border-radius: 10px;
    overflow: hidden;
    transition: border-color .15s ease, box-shadow .15s ease;
  }

  .input-group:focus-within {
    border-color: #447b94;
    box-shadow: 0 0 0 3px rgba(68, 123, 148, .14);
  }

  .login-container .input-group-text {
    width: 48px;
    justify-content: center;
    border: 0;
    background: #f2f5f7;
    color: #315f75;
  }

  .login-container .form-control {
    height: 48px;
    border: 0;
    border-radius: 0 !important;
    color: #293847;
    font-size: .95rem;
    box-shadow: none;
  }

  .login-container .form-control:focus {
    border: 0;
    box-shadow: none;
  }

  .login-container .form-control::placeholder {
    color: #87939e;
    opacity: 1;
  }

  .btn-login {
    width: 100%;
    min-height: 50px;
    border: none;
    border-radius: 10px;
    background: #447b94;
    color: #fff;
    font-size: .98rem;
    font-weight: 700;
    transition: background-color .15s ease, box-shadow .15s ease;
  }

  .btn-login:hover {
    background: #315f75;
    box-shadow: 0 4px 12px rgba(49, 95, 117, .18);
  }

  .btn-login:focus-visible {
    outline: 3px solid rgba(68, 123, 148, .35);
    outline-offset: 3px;
  }

  .alert {
    border: 1px solid transparent;
    border-radius: 12px;
    padding: 13px 15px;
    margin-bottom: 20px;
    box-shadow: none;
  }

  .alert-danger {
    border-color: #f0d7d7;
    background: #f9eeee;
    color: #963f3f;
  }

  .alert-success {
    border-color: #d7e8dc;
    background: #eef5f0;
    color: #35664f;
  }

  .alert .icone {
    margin-right: 12px;
    font-size: 1.35rem;
  }

  .footer-login {
    text-align: center;
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid #edf0f2;
    color: #667583;
    font-size: .82rem;
  }

  @media (max-width: 576px) {
    .login-container {
      padding: 32px 22px 22px;
      border-radius: 16px;
    }

    .logo-area h1 {
      font-size: 1.5rem;
    }

    .logo-area img {
      width: 64px;
      height: 64px;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .input-group, .btn-login { transition: none; }
  }
</style>

<body>

  <div class="login-container">

    <div class="logo-area">

      <img src="images/brasao.png" alt="Brasão Prefeitura">

      <h1>Chamada de Alunos</h1>

      <p>Prefeitura Municipal de Angatuba</p>

    </div>

    <?= ch_flash_html() ?>

    <!-- ERRO LOGIN -->
    <?php if (isset($_SESSION['loginErro'])) { ?>

      <div class="alert alert-danger">

        <div class="d-flex align-items-start">

          <div class="icone">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
          </div>

          <div>

            <strong>Acesso não permitido</strong><br>

            <small>
              <?php echo htmlspecialchars((string)$_SESSION['loginErro'], ENT_QUOTES, 'UTF-8'); ?>
            </small>

          </div>

        </div>

      </div>

      <?php unset($_SESSION['loginErro']); ?>

    <?php } ?>

    <!-- SUCESSO -->
    <?php if (isset($_SESSION['logindeslogado'])) { ?>

      <div class="alert alert-success">

        <div class="d-flex align-items-start">

          <div class="icone">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
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
      <?= ch_csrf_campo() ?>

      <div class="mb-3">

        <label class="form-label" for="cpfcnpj">CPF</label>

        <div class="input-group">

          <span class="input-group-text" aria-hidden="true">
            <i class="fas fa-id-card" aria-hidden="true"></i>
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

        <label class="form-label" for="senha">Senha</label>

        <div class="input-group">

          <span class="input-group-text" aria-hidden="true">
            <i class="fas fa-lock" aria-hidden="true"></i>
          </span>

          <input
            class="form-control"
            id="senha"
            placeholder="Digite sua senha"
            name="senha"
            type="password"
            autocomplete="current-password"
            required>

        </div>

      </div>

      <button class="btn-login" type="submit">
        <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
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