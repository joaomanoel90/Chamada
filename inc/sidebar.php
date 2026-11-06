<?php
/* ============================================================================
   MENU LATERAL — fonte única
   ----------------------------------------------------------------------------
   Existiam DOIS menus: inc/header.php (completo) e inc/header2.php (reduzido,
   sem Turmas, Períodos, Unidades e Usuários). A ideia era usar o reduzido para
   o professor, mas várias telas — alunos, chamada, feriados, períodos,
   relatórios, observações — chamavam o header2 fixo. Resultado: o administrador
   perdia o link "Usuários" do menu assim que entrava em qualquer uma dessas
   telas, e não havia como chegar ao cadastro de usuários.

   Agora os dois cabeçalhos incluem este arquivo, e o que aparece é definido pelo
   nível de quem está logado — não pela tela em que ele está.
   ============================================================================ */
require_once(ABSPATH . 'inc/chamada.php');

$ch_nivel  = ch_nivel();
$ch_gestor = ch_gestor();   // níveis 1 e 2
$ch_admin  = ch_admin();    // nível 1

/* id do módulo => [rótulo, ícone, caminho, quem vê] */
$ch_links_chamada = [
    ['chamada',     'Chamada do Dia',  'fas fa-clipboard-user',  'chamada/index.php',     true],
    ['observacoes', 'Observações',     'far fa-comment-dots',    'observacoes/index.php', true],
    ['relatorios',  'Relatório Anual', 'fas fa-file-lines',      'relatorios/index.php',  true],
];
/* O contador de transferências só é consultado para quem pode tratá-las. */
$ch_transf_aguardando = $ch_gestor ? tr_pendentes_recebidas() : 0;

$ch_links_cadastros = [
    ['alunos',   'Alunos',   'fas fa-child-reaching', 'alunos/index.php',   true],
    ['transferencias', 'Transferências', 'fas fa-right-left', 'transferencias/index.php', $ch_gestor, $ch_transf_aguardando],
    ['turmas',   'Turmas',   'fas fa-children',       'turmas/index.php',   $ch_gestor],
    ['periodos', 'Períodos', 'fas fa-clock',          'periodos/index.php', $ch_gestor],
    // Feriados é leitura para todos: o professor precisa saber que dia não
    // tem aula. Só cadastrar/editar/excluir é que exige gestão.
    ['feriados', 'Feriados', 'fas fa-calendar-day',   'feriados/index.php', true],
    ['unidades', 'Unidades', 'far fa-building',       'unidades/index.php', $ch_gestor],
    ['usuarios', 'Usuários', 'fas fa-users',          'usuarios/index.php', $ch_admin],
];

/** Um item do menu. O title aparece como dica quando o menu está recolhido.
    O 6º elemento, opcional, é um contador (ex.: transferências aguardando). */
function ch_menu_item($item)
{
    [$id, $rotulo, $icone, $caminho, $pode] = $item;
    if (!$pode) return;
    $badge = '';
    if (!empty($item[5])) {
        $n = (int)$item[5];
        $badge = ' <span class="badge bg-warning text-dark ms-1">' . $n . '</span>';
        $rotulo .= ' (' . $n . ' aguardando)';
    }
    echo '<a class="nav-link text-white" id="' . ch_h($id) . '" href="' . ch_h(BASEURL . $caminho)
       . '" title="' . ch_h($rotulo) . '"><i class="' . ch_h($icone) . '"></i> <span>'
       . ch_h($item[1]) . '</span>' . $badge . '</a>' . "\n";
}
?>

    <!-- Fixa o menu aberto / devolve ao modo recolhido -->
    <button class="toggle-btn" id="sidebarToggle" type="button"
            aria-controls="sidebar" aria-expanded="false" title="Manter o menu aberto">☰</button>

    <div class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <img src="<?php echo BASEURL; ?>images/brasao.png" alt="Brasão de Angatuba" style="width:48px" />
        <h2>Prefeitura de Angatuba</h2>
      </div>

      <a class="nav-link text-white" id="dashboard" href="<?php echo BASEURL; ?>index.php" title="Dashboard"><i class="fas fa-gauge-high"></i> <span>Dashboard</span></a>

      <hr class="border-light">
      <p class="text-white-50 small mb-1 px-2">CHAMADA</p>
      <?php foreach ($ch_links_chamada as $item) ch_menu_item($item); ?>

      <hr class="border-light">
      <p class="text-white-50 small mb-1 px-2">CADASTROS</p>
      <?php foreach ($ch_links_cadastros as $item) ch_menu_item($item); ?>

      <hr class="border-light">
      <a class="nav-link text-white" id="conta" href="<?php echo BASEURL; ?>conta/index.php" title="Alterar Senha"><i class="fas fa-key"></i> <span>Alterar Senha</span></a>
      <a class="nav-link text-white" href="<?php echo BASEURL; ?>sair.php" title="Sair"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a>
      <hr class="border-light">
    </div>

    <script>
      (function () {
        var sidebar = document.getElementById('sidebar');
        var botao   = document.getElementById('sidebarToggle');
        var links   = [].slice.call(document.querySelectorAll('.sidebar .nav-link'));

        /* --- item ativo ---------------------------------------------------
           Antes comparava link.href.includes(location.pathname), o que só
           funcionava na listagem: em usuarios/add.php nenhum item ficava
           marcado. Agora compara o MÓDULO (primeiro pedaço do caminho), então
           o menu continua aceso nas telas de cadastro e edição. */
        function marcaAtivo() {
          var prefixo = '<?php echo BASEURL; ?>';
          function modulo(caminho) {
            var p = caminho.indexOf(prefixo) === 0 ? caminho.slice(prefixo.length) : caminho.replace(/^\//, '');
            var primeiro = p.split('/')[0];
            return primeiro === '' ? 'index.php' : primeiro;
          }
          var atual = modulo(window.location.pathname);
          links.forEach(function (a) {
            var alvo = modulo(new URL(a.href, window.location.origin).pathname);
            a.classList.toggle('active', alvo === atual);
          });
        }

        /* --- menu fixado aberto -------------------------------------------
           O botão ☰ adicionava a classe .active, mas não havia regra de CSS
           para ela: o botão não fazia nada. Agora fixa o menu aberto e a
           escolha é lembrada entre as telas. */
        function aplicaFixado(ligado) {
          sidebar.classList.toggle('active', ligado);
          botao.setAttribute('aria-expanded', ligado ? 'true' : 'false');
          botao.title = ligado ? 'Recolher o menu' : 'Manter o menu aberto';
        }

        /* A gaveta fixada só faz sentido no celular. No computador o menu
           abre no hover e o botão fica escondido, então não restaura estado
           nenhum — senão um "fixado" gravado no celular deixaria o menu
           aberto para sempre no desktop, sem botão para fechar. */
        function ehCelular() { return window.matchMedia('(max-width: 768px)').matches; }

        document.addEventListener('DOMContentLoaded', function () {
          marcaAtivo();
          var fixado = false;
          if (ehCelular()) {
            try { fixado = localStorage.getItem('ch_sidebar_fixado') === '1'; } catch (e) {}
          }
          aplicaFixado(fixado);
        });

        botao.addEventListener('click', function () {
          var ligado = !sidebar.classList.contains('active');
          aplicaFixado(ligado);
          try { localStorage.setItem('ch_sidebar_fixado', ligado ? '1' : '0'); } catch (e) {}
        });

        /* Ao sair da faixa do celular, desfaz a gaveta para o menu voltar ao
           comportamento de hover. */
        window.addEventListener('resize', function () {
          if (!ehCelular() && sidebar.classList.contains('active')) aplicaFixado(false);
        });
      })();
    </script>
