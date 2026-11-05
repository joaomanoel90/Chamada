<?php
require_once('processa/movimento_processa.php');
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

<style>
    /* =========================
   TABELA RESPONSIVA
========================= */

    .table-responsive {

        width: 100%;

        overflow-x: auto;

        -webkit-overflow-scrolling: touch;

        border-radius: 12px;
    }

    /* =========================
   TABELA
========================= */

    .table {

        width: 100%;

        min-width: 100%;

        border-collapse: collapse;

        table-layout: auto;
    }

    /* =========================
   CABEÇALHO
========================= */

    .table th {

        white-space: nowrap;

        padding: 10px;

        vertical-align: middle;
    }

    /* =========================
   CÉLULAS
========================= */

    .table td {

        padding: 10px;

        vertical-align: middle;

        /* QUEBRA TEXTO */
        white-space: normal;

        word-break: break-word;

        overflow-wrap: break-word;

        max-width: 250px;
    }

    /* =========================
   EVITA ESTOURAR A TELA
========================= */

    .content {

        overflow-x: hidden;
    }

    /* =========================
   MOBILE
========================= */

    @media (max-width: 768px) {

        .table {

            min-width: 700px;
        }

        .table td,
        .table th {

            font-size: 0.82rem;

            padding: 0.55rem;
        }
    }
</style>
<h2><i class="fas fa-history"></i> Histórico de Movimentações</h2>

<form method="get" class="mb-4">
    <div class="row">
        <div class="col-md-4">
            <label class="fw-bold">Produto</label>
            <select name="id_produto" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($produtos as $p): ?>
                    <option value="<?php echo intval($p['ID']); ?>"
                        <?php if (isset($_GET['id_produto']) && $_GET['id_produto'] == $p['ID']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($p['Descricao']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="fw-bold">Fornecedor</label>
            <select name="id_fornecedor" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($fornecedores as $f): ?>
                    <option value="<?php echo intval($f['ID']); ?>"
                        <?php if (isset($_GET['id_fornecedor']) && $_GET['id_fornecedor'] == $f['ID']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($f['Fornecedor']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="fw-bold">&nbsp;</label><br>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="movimento.php" class="btn btn-outline-success">Limpar</a>
        </div>
    </div>
</form>
<div class="table-responsive">

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Produto</th>
                <th>Tipo</th>
                <th>Quantidade</th>
                <th>Almoxarifado</th>
                <th>Fornecedor</th>
                <th>Nº Nota</th>
                <th>Pedido</th>
                <th>Unidade</th>
                <th>Data</th>
            </tr>
        </thead>

        <tbody>

            <?php if (!empty($movimentacoes)): ?>

                <?php foreach ($movimentacoes as $m): ?>

                    <?php

                    $classeLinha = '';
                    $tipoTexto   = '';

                    switch ($m['TipoMovimento']) {

                        case 'E':
                            $classeLinha = 'table-success';
                            $tipoTexto   = 'Entrada';
                            break;

                        case 'S':
                            $classeLinha = 'table-danger';
                            $tipoTexto   = 'Saída';
                            break;

                        case 'A':
                            $classeLinha = 'table-warning';
                            $tipoTexto   = 'Ajuste';
                            break;

                        case 'C':
                            $classeLinha = 'table-secondary';
                            $tipoTexto   = 'Cancelado';
                            break;

                        default:
                            $classeLinha = '';
                            $tipoTexto   = $m['TipoMovimento'];
                            break;
                    }

                    ?>

                    <tr class="<?php echo $classeLinha; ?>">

                        <td>
                            <?php echo intval($m['ID']); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($m['Produto']); ?>
                        </td>

                        <td>

                            <?php if ($m['TipoMovimento'] == 'E'): ?>

                                <span class="badge bg-success">
                                    Entrada
                                </span>

                            <?php elseif ($m['TipoMovimento'] == 'S'): ?>

                                <span class="badge bg-danger">
                                    Saída
                                </span>

                            <?php elseif ($m['TipoMovimento'] == 'A'): ?>

                                <span class="badge bg-warning text-dark">
                                    Ajuste
                                </span>

                            <?php elseif ($m['TipoMovimento'] == 'C'): ?>

                                <span class="badge bg-secondary">
                                    Cancelado
                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    <?php echo htmlspecialchars($tipoTexto); ?>
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?php echo number_format($m['Quantidade'], 0, ',', '.'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($m['Almoxarifado'] ?? '-'); ?>
                        </td>

                        <td>
                            <?php echo ($m['Fornecedor']); ?>
                        </td>

                        <td>
                            <?php echo ($m['Numero_Nota']); ?>
                        </td>

                        <td>

                            <?php if (!empty($m['ID_Pedido'])): ?>

                                <?php echo htmlspecialchars($m['ID_Pedido']); ?>

                                <?php if (!empty($m['Objetivo'])): ?>
                                    - <?php echo htmlspecialchars($m['Objetivo']); ?>
                                <?php endif; ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                        <td>
                            <?php echo ($m['Unidade']); ?>
                        </td>

                        <td>

                            <?php
                            echo $m['DataMovimento']
                                ? $m['DataMovimento']->format('d/m/Y H:i')
                                : '';
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="10" class="text-center py-4">
                        Nenhuma movimentação encontrada.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>
    </table>


</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Inicializa Select2 com tema Bootstrap 5
        const selectOptions = {
            theme: "bootstrap-5",
            placeholder: "Selecione",
            allowClear: true,
            width: '100%'
        };

        const produtoSelect = document.querySelector('select[name="id_produto"]');
        if (produtoSelect) $(produtoSelect).select2(selectOptions);

        const fornecedorSelect = document.querySelector('select[name="id_fornecedor"]');
        if (fornecedorSelect) $(fornecedorSelect).select2(selectOptions);
    });
</script>

<?php include(FOOTER_TEMPLATE); ?>