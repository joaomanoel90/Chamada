<?php
require_once('processa/consumo_processa.php');
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
    .summary-card {
        border: 1px solid #dde5ec;
        border-radius: 8px;
        background: #fff;
        padding: 14px 16px;
        height: 100%;
    }

    .summary-card .label {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }

    .summary-card .value {
        font-size: 1.45rem;
        font-weight: 700;
        color: #243746;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 8px;
    }

    .table td,
    .table th {
        vertical-align: middle;
    }

    .produto-row {
        cursor: pointer;
    }

    .produto-row .toggle-icon {
        width: 1.5rem;
        display: inline-block;
        color: #198754;
    }

    .produto-row.shown .toggle-icon i {
        transform: rotate(90deg);
    }

    .toggle-icon i {
        transition: transform 0.15s ease-in-out;
    }

    .detail-box {
        padding: 12px 16px 16px;
        background: #f8fafc;
    }

    .detail-table {
        margin-bottom: 0;
    }

    #item.dataTable tbody tr.child td {
        padding: 0;
        background: #f8fafc;
    }

    @media (max-width: 768px) {
        .table {
            min-width: 760px;
        }
    }
</style>

<header>
    <div class="row align-items-center">
        <div class="col-sm-8">
            <h2><i class="fas fa-chart-column"></i> Consumo por Produto</h2>
        </div>
        <div class="col-sm-4 text-sm-end">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</header>

<hr>

<form method="get" class="mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="fw-bold">M&ecirc;s</label>
            <select name="mes" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($meses_disponiveis as $mes): ?>
                    <option value="<?php echo htmlspecialchars($mes['valor']); ?>"
                        <?php if ($mes_filtro === $mes['valor']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($mes['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="fw-bold">Unidade</label>
            <select name="id_unidade" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($unidades as $unidade): ?>
                    <option value="<?php echo intval($unidade['ID']); ?>"
                        <?php if ($id_unidade == $unidade['ID']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($unidade['unidade']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="consumo.php" class="btn btn-outline-success">Limpar</a>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="summary-card">
            <div class="label">M&ecirc;s filtrado</div>
            <div class="value"><?php echo htmlspecialchars($periodo_label); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="summary-card">
            <div class="label">Produtos com sa&iacute;da</div>
            <div class="value"><?php echo intval($total_produtos); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="summary-card">
            <div class="label">Total consumido</div>
            <div class="value"><?php echo number_format($total_geral, 2, ',', '.'); ?></div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table name="item" id="item" class="table table-light table-striped table-sm">
        <thead class="text-sm-center">
            <tr>
                <th>Produto</th>
                <th class="text-end">Total consumido</th>
                <th class="text-center">Unidades</th>
                <th class="text-center">Pedidos</th>
                <th class="text-center">&Uacute;ltima libera&ccedil;&atilde;o</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!empty($produtos_consumo)): ?>
                <?php foreach ($produtos_consumo as $produto): ?>
                    <tr class="produto-row">
                        <td>
                            <span class="toggle-icon"><i class="fas fa-chevron-right"></i></span>
                            <?php echo htmlspecialchars($produto['Produto']); ?>

                            <div class="detail-template d-none">
                                <div class="detail-box">
                                    <table class="table table-sm detail-table">
                                        <thead>
                                            <tr>
                                                <th>Unidade</th>
                                                <th class="text-end">Quantidade consumida</th>
                                                <th class="text-center">Pedidos</th>
                                                <th class="text-center">Data refer&ecirc;ncia</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($produto['unidades'] as $unidade_consumo): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($unidade_consumo['Unidade']); ?></td>
                                                    <td class="text-end">
                                                        <?php echo number_format($unidade_consumo['QuantidadeTotal'], 2, ',', '.'); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo intval($unidade_consumo['TotalPedidos']); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $unidade_consumo['UltimaSaida'] ? $unidade_consumo['UltimaSaida']->format('d/m/Y H:i') : ''; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                        <td class="text-end">
                            <strong><?php echo number_format($produto['QuantidadeTotal'], 2, ',', '.'); ?></strong>
                        </td>
                        <td class="text-center">
                            <?php echo intval($produto['TotalUnidades']); ?>
                        </td>
                        <td class="text-center">
                            <?php echo intval($produto['TotalPedidos']); ?>
                        </td>
                        <td class="text-center">
                            <?php echo $produto['UltimaSaida'] ? $produto['UltimaSaida']->format('d/m/Y H:i') : ''; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center py-4">
                        Nenhum produto com sa&iacute;da encontrado para os filtros selecionados.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const unidadeSelect = document.querySelector('select[name="id_unidade"]');
        if (unidadeSelect) {
            $(unidadeSelect).select2({
                theme: "bootstrap-5",
                placeholder: "Selecione a unidade",
                allowClear: true,
                width: '100%'
            });
        }

        const mesSelect = document.querySelector('select[name="mes"]');
        if (mesSelect) {
            $(mesSelect).select2({
                theme: "bootstrap-5",
                placeholder: "Todos",
                allowClear: true,
                width: '100%'
            });
        }

        const tabela = $('#item').DataTable({
            order: [[0, 'asc']],
            responsive: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Portuguese-Brasil.json"
            }
        });

        $('#item tbody').on('click', 'tr.produto-row', function() {
            const tr = $(this);
            const row = tabela.row(tr);
            const detailHtml = tr.find('.detail-template').html();

            if (!detailHtml) {
                return;
            }

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
                return;
            }

            row.child(detailHtml).show();
            tr.addClass('shown');
        });
    });
</script>

<?php include(FOOTER_TEMPLATE); ?>
