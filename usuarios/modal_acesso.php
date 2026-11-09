<?php
/* Fragmento de tela: so faz sentido incluido por uma pagina que ja carregou
   o config.php. Aberto direto na URL nao tem ch_h() nem as variaveis que
   espera, e morreria com erro fatal. ABSPATH e o sinal de que vim de dentro. */
if (!defined('ABSPATH')) {
	http_response_code(404);
	exit;
}
?>
<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="acesso-modal" tabindex="-1" aria-labelledby="acessoModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="processa/delete_acesso_processa.php">
      <?= ch_csrf_campo() ?>
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="acessoModalLabel">Confirmação de Exclusão</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          Tem certeza que deseja excluir este acesso?
          <input type="hidden" name="id_acesso" id="id_acesso_modal" value="">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-success" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Excluir</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// Passa o ID do botão para o campo hidden do modal
var acessoModal = document.getElementById('acesso-modal')
acessoModal.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget
  var id = button.getAttribute('data-id') || button.getAttribute('data-customer')
  var input = acessoModal.querySelector('#id_acesso_modal')
  input.value = id
})
</script>