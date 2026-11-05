<!-- Modal Cancelar Pedido -->
<div class="modal fade" id="cancela-modal" tabindex="-1" aria-labelledby="cancelaModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="cancelar.php" method="post" id="form-cancelar-pedido">
        <input type="hidden" name="id" id="cancel_pedido_id" value="">

        <div class="modal-header bg-light">
          <h5 class="modal-title text-danger" id="cancelaModalLabel">
            <i class="fas fa-times-circle"></i> Cancelar Pedido
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <p class="mb-3">
            Deseja realmente cancelar o pedido <strong id="cancel_pedido_display">#</strong>?
          </p>

          <div class="mb-3">
            <label for="motivo_cancelamento_modal" class="form-label fw-bold">
              Motivo do Cancelamento <span class="text-danger">*</span>
            </label>
            <textarea class="form-control" name="motivo_cancelamento" id="motivo_cancelamento_modal" rows="3" required placeholder="Digite o motivo do cancelamento..."></textarea>
          </div>

          <div class="alert alert-warning py-2 mb-0 small">
            <i class="fas fa-exclamation-triangle"></i> Esta ação cancelará o pedido e não poderá ser desfeita.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-times"></i> Confirmar Cancelamento
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  $('#cancela-modal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var id = button.data('customer');
    var modal = $(this);
    modal.find('#cancel_pedido_id').val(id);
    modal.find('#cancel_pedido_display').text('#' + id);
    modal.find('#motivo_cancelamento_modal').val('');
  });
</script>
