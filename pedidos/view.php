<?php
require_once('processa/view_processa.php');

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
  include(HEADER_TEMPLATE2);
} else {
	include(HEADER_TEMPLATE);
}
?>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if (empty($erro) && $pedido): ?>
    <h2><i class="fas fa-eye"></i> Visualização do Pedido</h2>

    <div class="alert alert-info">
        <strong>Pedido:</strong> <?php echo htmlspecialchars($pedido['ID_Pedido']); ?> <br>
        <strong>Objetivo:</strong> <?php echo htmlspecialchars($pedido['Objetivo']); ?> <br>
        <strong>Unidade:</strong> <?php echo htmlspecialchars($pedido['Unidade']); ?> <br>
        <strong>Status:</strong> <?php echo htmlspecialchars($pedido['Status']); ?> <br>
        <strong>Data de Cadastro:</strong> <?php echo ($pedido['DataCadastro'] instanceof DateTime) ? $pedido['DataCadastro']->format('d/m/Y H:i') : '-'; ?> <br>
        <strong>Data de Envio:</strong> <?php echo ($pedido['DataEnvio'] instanceof DateTime) ? $pedido['DataEnvio']->format('d/m/Y H:i') : '-'; ?> <br>
        <strong>Data de Finalização:</strong> <?php echo ($pedido['DataFinalizado'] instanceof DateTime) ? $pedido['DataFinalizado']->format('d/m/Y H:i') : '-'; ?>
        <?php if (strtolower($pedido['Status']) === 'cancelado'): ?>
            <br>
            <strong>Data de Cancelamento:</strong> <?php echo (isset($pedido['DataCancelado']) && $pedido['DataCancelado'] instanceof DateTime) ? $pedido['DataCancelado']->format('d/m/Y H:i') : '-'; ?> <br>
            <strong>Motivo do Cancelamento:</strong> <?php echo htmlspecialchars($pedido['MotivoCancelamento'] ?? '-'); ?>
        <?php endif; ?>

    </div>

    <h4 class="mt-5">Itens do Pedido</h4>
    <div class="table-responsive-sm">
        <table class="table table-light table-striped table-sm" id="tabela-itens"> 
            <thead> 
                <tr> 
                    <th>#</th> 
                    <th>Produto</th> 
                    <th>Quantidade Solicitada</th> 
                    <th>Quantidade Liberada</th> 

                </tr> 
            </thead>
          <tbody>
<?php 
$contador = 1; // inicia numeração
foreach ($itens_pedido as $item): 
    $liberado = !empty($item['Liberado']) ? $item['Liberado'] : '0,00';
    $classeLinha = ($liberado == 0) ? 'text-decoration-line-through' : '';
?>
    <tr class="<?php echo $classeLinha; ?>">
        <td><?php echo $contador++; ?></td> <!-- número sequencial -->
        <td><?php echo htmlspecialchars($item['ProdutoCompleto']); ?></td>
        <td><?php echo htmlspecialchars($item['Quantidade']); ?></td>
        <td><?php echo htmlspecialchars($liberado); ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
        </table>
    </div>

    <div class="mt-4">
        <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
    </div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
