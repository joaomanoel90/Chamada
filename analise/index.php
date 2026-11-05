<?php
require_once('processa/index_processa.php');
?>

<?php
if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
    header("location:" . BASEURL);
    exit();
} else {
    include(HEADER_TEMPLATE);
}
?>

<header>
    <div class="row">
        <div class="col-sm-6">
            <h2><i class="fas fa-search"></i> Analisar Pedidos</h2>
            <?php if (!empty($status_filtro)): ?>
                <p class="text-muted mb-0">
                    Filtrando por: <strong><?php echo htmlspecialchars($status_filtro, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <a href="index.php" class="ms-2">Limpar filtro</a>
                </p>
            <?php endif; ?>
        </div>

</header>

<hr>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php unset($_SESSION['message'], $_SESSION['type']); endif; ?>

<div class="table-responsive-sm">

    <table name="item" id="item" class="table table-light table-striped table-sm " style="vertical-align: middle;">
        <thead class="text-sm-center">
            <tr>
                <th>ID</th>
                <th>Unidade</th>
                <th>Objetivo</th>
                <th>Status</th>
                <th>Opções</th>
            </tr>
        </thead>



        <tbody>
            <?php if ($rows_indice = 1) {
                while ($row = sqlsrv_fetch_array($stmt_indice, SQLSRV_FETCH_ASSOC)) {
                    $sum_liberado = 0;
                    $sql_sum = "SELECT ISNULL(SUM(Liberado), 0) as total FROM TB_ITENS_PEDIDO WHERE ID_Pedido = ?";
                    $stmt_sum = sqlsrv_query($conn, $sql_sum, [$row['ID_Pedido']]);
                    if ($stmt_sum && $row_sum = sqlsrv_fetch_array($stmt_sum, SQLSRV_FETCH_ASSOC)) {
                        $sum_liberado = floatval($row_sum['total']);
                    }
            ?>
                    <tr class="<?php echo ($row['Ativo'] == 0) ? 'inactive-row' : ''; ?>">
                        <td>

                            <?php echo $row['ID_Pedido']; ?>
                        </td>
                        <td>
                            <?php echo $row['Unidade']; ?>
                        </td>
                        <td>
                            <?php echo $row['Objetivo']; ?>
                        </td>
                        <td>
                            <?php
                            switch ($row['Status']) {
                                case 'Aberto':
                                    echo '❗ Aberto (não encaminhado)';
                                    break;

                                case 'Em Análise':
                                    echo '🔎 Em Análise';
                                    break;

                                case 'Em Separação':
                                    echo '📦 Em Separação';
                                    break;

                                case 'Finalizado':
                                    echo '✅ Finalizado';
                                    break;

                                case 'Cancelado':
                                    echo '❌ Cancelado';
                                    break;

                                default:
                                    echo $row['Status'];
                                    break;
                            }
                            ?>
                        </td>

                        <td class="actions text-sm-end">
                            <div class="btn-group">
                                <?php if (in_array($row['Status'], ['Em Análise', 'Em Separação', 'Finalizado', 'Cancelado'])): ?>
                                    <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-gear"></i>
                                    </button>
                                    <ul class="dropdown-menu">

                                        <?php if ($row['Status'] == 'Em Análise'): ?>
                                            <li>
                                                <a href="lista.php?id=<?php echo $row['ID_Pedido']; ?>" class="dropdown-item">
                                                    <i class="fas fa-search"></i> Analisar Lista
                                                </a>
                                            </li>

                                        <?php elseif ($row['Status'] == 'Em Separação'): ?>
                                            <li>
                                                <a href="almoxarifado.php?id=<?php echo $row['ID_Pedido']; ?>" class="dropdown-item">
                                                    <i class="fas fa-box"></i> Lista a Separar
                                                </a>
                                            </li>

                                            <li>
                                                <a href="finalizar.php?id=<?php echo $row['ID_Pedido']; ?>"
                                                    class="dropdown-item text-success"
                                                    onclick="return confirm('Deseja finalizar este pedido?');">
                                                    <i class="fas fa-check"></i> Finalizar Pedido
                                                </a>
                                            </li>

                                        <?php elseif ($row['Status'] == 'Finalizado' || $row['Status'] == 'Cancelado'): ?>

                                            <!-- 👁️ VISUALIZAR -->
                                            <li>
                                                <a href="view.php?id=<?php echo $row['ID_Pedido']; ?>" class="dropdown-item">
                                                    <i class="fa fa-eye"></i> Visualizar Pedido
                                                </a>
                                            </li>

                                            <?php if ($row['Status'] == 'Finalizado'): ?>
                                            <!-- 🧾 COMPROVANTE -->
                                            <li>
                                                <a href="comprovante.php?id=<?php echo $row['ID_Pedido']; ?>"
                                                    class="dropdown-item text-primary" target="_blank">
                                                    <i class="fas fa-file-alt"></i> Ver Comprovante
                                                </a>
                                            </li>

                                            <!-- 📧 ENVIAR E-MAIL -->
                                            <li>
                                                <a href="enviar_email.php?id=<?php echo $row['ID_Pedido']; ?>"
                                                    class="dropdown-item text-warning" onclick="return confirm('Deseja enviar um e-mail de aviso para o criador deste pedido?');">
                                                    <i class="fas fa-envelope"></i> Enviar por E-mail
                                                </a>
                                            </li>
                                            <?php endif; ?>

                                        <?php endif; ?>

                                        <?php if ($row['Status'] != 'Finalizado' && $row['Status'] != 'Cancelado'): ?>
                                            <?php if ($sum_liberado > 0): ?>
                                                <li>
                                                    <a href="status.php?id=<?php echo $row['ID_Pedido']; ?>" class="dropdown-item">
                                                        <i class="far fa-flag"></i> Alterar Status
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <span class="dropdown-item text-muted disabled" title="Libere ao menos 1 item para poder alterar o status">
                                                        <i class="far fa-flag"></i> Alterar Status (Sem itens liberados)
                                                    </span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($row['Status'] != 'Cancelado'): ?>
                                            <li>
                                                <a href="cancelar.php?id=<?php echo $row['ID_Pedido']; ?>" class="dropdown-item text-danger">
                                                    <i class="fas fa-times-circle"></i> Cancelar Pedido
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                    </ul>
                                <?php endif; ?>
                            </div>
                        </td>


                    </tr>

                <?php }
                sqlsrv_free_stmt($stmt_indice);
            } else { ?>
                <tr>
                    <td colspan="6">Nenhum registro encontrado.</td>
                </tr>
            <?php }; ?>
        </tbody>
    </table>
</div>
<script src="<?php echo BASEURL; ?>js/tabela.js"></script>
<?php include(FOOTER_TEMPLATE); ?>