<?php
require_once('processa/status_processa.php');

// Verifica se o usuário está logado e se tem nível de acesso 1 ou 2
if (isset($_SESSION['usuarioId']) && 
    (isset($_SESSION['usuarioNiveisAcessoId']) && 
    ($_SESSION['usuarioNiveisAcessoId'] == 1 || $_SESSION['usuarioNiveisAcessoId'] == 2))) {
    
    include(HEADER_TEMPLATE);

    if (!isset($pedido['ID'])) {
        echo "<p>Pedido não encontrado.</p>";
        include(FOOTER_TEMPLATE);
        exit;
    }

 // Bloqueia se o status não for permitido
if (strtolower($pedido['Status']) === 'aberto') {
    echo "<p>Este pedido não está em análise e não pode ser editado aqui.</p>";
    include(FOOTER_TEMPLATE);
    exit;
}

// Bloqueia se nenhum item tiver sido liberado
if (!isset($total_liberado) || $total_liberado <= 0) {
    echo "<div class='alert alert-warning mt-4'><h4><i class='fas fa-exclamation-triangle'></i> Nenhum item liberado</h4><p>Não é possível alterar o status deste pedido porque nenhum item foi liberado ainda (ou todos os itens estão com quantidade 0). Acesse a lista do pedido e libere pelo menos 1 item primeiro.</p><a href='lista.php?id=" . $pedido['ID'] . "' class='btn btn-success me-2'><i class='fas fa-search'></i> Analisar Lista</a><a href='index.php' class='btn btn-outline-secondary'>Voltar</a></div>";
    include(FOOTER_TEMPLATE);
    exit;
}


    $id = $pedido['ID'];
    ?>

    <h2><i class="far fa-edit"></i> Editar Status do Pedido: <?= htmlspecialchars($pedido['ID']) ?></h2>

    <form action="status.php?id=<?= $id ?>" method="post">
        <input type="hidden" class="form-control" name="id" id="id" value="<?= $id ?>" required>

        <hr />

        <div class="row">
            <div class="form-group col-md-4">
                <label class="fw-bold">Status</label>
                <select class="form-control" name="status" id="status" required>
                    <option value="">Selecione...</option>
                    <?php
                    $status = ['Aberto','Em Análise','Em Separação'];
                    foreach ($status as $stat) {
                        $selected = ($pedido['Status'] === $stat) ? 'selected' : '';
                        echo "<option value='$stat' $selected>" . str_replace('_', ' ', $stat) . "</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="col-auto my-5">
           <a href="index.php" class="btn btn-outline-success">Cancelar</a>

           <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar Alterações</button>
        </div>
    </form>

    <?php include(FOOTER_TEMPLATE); ?>

<?php
} else {
    // Usuário não autorizado ou não logado
    header("Location: " . BASEURL);
    exit;
}
?>
