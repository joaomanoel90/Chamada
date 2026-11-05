<!-- Modal -->
<div class="modal fade" id="encaminha-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Encaminhar Pedido?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Tem certeza que deseja encaminhar o pedido selecionado?




      </div>
      <div class="modal-footer">
        <a id="confirm" class="btn btn-success" href="#">Sim</a>
        <a id="cancel" class="btn btn-outline-success" data-bs-dismiss="modal">Não</a>
      </div>
    </div>
  </div>
</div>

<script>
  $('#encaminha-modal').on('show.bs.modal', function (event) {

    var button = $(event.relatedTarget);

    var id = button.data('customer');

    var modal = $(this);
    modal.find('.modal-title').text('Deseja Encaminhar o Pedido? ' + id);
    modal.find('#confirm').attr('href', 'encaminha.php?id=' + id);
  })
</script>