<?php
require_once('processa/lista_processa.php');
include(HEADER_TEMPLATE);

if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
    header("location:" . BASEURL);
    exit;
}
?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php unset($_SESSION['message'], $_SESSION['type']); endif; ?>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if ($pedido): ?>

    <h2>Analisar Pedido</h2>

    <div class="alert alert-info">
        <strong>Pedido:</strong> <?php echo $pedido['ID_Pedido']; ?><br>
        <strong>Objetivo:</strong> <?php echo $pedido['Objetivo']; ?><br>
        <strong>Unidade:</strong> <?php echo $pedido['Unidade']; ?>
    </div>

    <form method="post" onsubmit="if(this.submitted) return false; this.submitted = true; this.querySelector('.btn-success').innerText = 'Salvando...'; return true;">
        <input type="hidden" name="id_pedido" value="<?php echo $pedido['ID_Pedido']; ?>">

        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Qtd Pedida</th>
                    <th>Estoque Atual</th>
                    <th>Qtd Liberada</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($itens_pedido as $item): ?>
                    <tr>

                        <td><?php echo $item['Produto']; ?></td>
                        <td><?php echo number_format($item['Quantidade'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['EstoqueAtual'], 2, ',', '.'); ?></td>

                        <td>
                            <input type="number"
                                name="liberada[<?php echo $item['ID']; ?>]"
                                value="<?php
                                        if (isset($valores_postados[$item['ID']])) {
                                            echo htmlspecialchars($valores_postados[$item['ID']]);
                                        } elseif ($item['Liberado'] !== null) {
                                            echo htmlspecialchars($item['Liberado']);
                                        } else {
                                            echo '';
                                        }
                                        ?>"
                                class="form-control form-control-sm"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                required>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-success">Salvar</button>
        </div>

    </form>

<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>