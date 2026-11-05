<?php
require_once('../config.php');

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

$nivel = $_SESSION['usuarioNiveisAcessoId'] ?? 0;
if ($nivel != 1 && $nivel != 2) {
    header("Location: " . BASEURL);
    exit;
}

require_once('processa/index_processa.php');
include(HEADER_TEMPLATE);
?>

<h2 class="mb-4">
    <i class="fas fa-boxes"></i>
    Reserva de Estoque dos Pedidos em Aberto
</h2>

<div class="row mb-4 g-3">

    <div class="col-md-3">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-body">
                <small class="text-muted">Produtos</small>
                <h3><?php echo intval($totalProdutos); ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-body">
                <small class="text-muted">Itens com Falta</small>
                <h3 class="text-danger"><?php echo intval($totalFalta); ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-body">
                <small class="text-muted">Pedidos em Aberto</small>
                <h3><?php echo intval($pedidosAbertos); ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-body">
                <small class="text-muted">Saldo Total</small>
                <h3><?php echo intval($saldoTotal); ?></h3>
            </div>
        </div>
    </div>

</div>

<div class="card shadow-sm border-0 rounded-4">

    <div class="card-body">
        <div id="area-impressao">
            <div class="table-responsive">

                <table class="table table-hover align-middle table-sm">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th style="width:120px;">Estoque Atual</th>
                            <th style="width:120px;">Reservado</th>
                            <th style="width:120px;">Disponível</th>
                            <th style="width:180px;">Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($produtos)): ?>

                            <?php foreach ($produtos as $p): ?>

                                <?php
                                $classe = '';

                                if ($p['Disponivel'] < 0) {
                                    $classe = 'table-danger';
                                } elseif ($p['Disponivel'] <= 5) {
                                    $classe = 'table-warning';
                                }
                                ?>

                                <tr class="<?php echo $classe; ?>">
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($p['Produto']); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo number_format($p['EstoqueAtual'], 0, ',', '.'); ?>
                                    </td>

                                    <td>
                                        <?php echo number_format($p['Reservado'], 0, ',', '.'); ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?php echo number_format($p['Disponivel'], 0, ',', '.'); ?>
                                        </strong>
                                    </td>

                                    <td>

                                        <?php if ($p['Disponivel'] < 0): ?>

                                            <span class="badge bg-danger">
                                                Estoque Insuficiente
                                            </span>

                                        <?php elseif ($p['Disponivel'] <= 5): ?>

                                            <span class="badge bg-warning text-dark">
                                                Estoque Baixo
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-success">
                                                OK
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="text-center">
                                    Nenhum registro encontrado.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>
    </div>
</div>
<br>
<button class="btn btn-success no-print" onclick="imprimirComprovante()">
    <i class="fas fa-print"></i> Imprimir
</button>
<script>
    function imprimirComprovante() {

        let tabelaOriginal = document.querySelector('#area-impressao table tbody');

        let linhas = tabelaOriginal.querySelectorAll('tr');

        let linhasFiltradas = '';

        linhas.forEach(function(linha) {

            let reservado = linha.children[2]?.innerText.trim() || '0';

            reservado = reservado.replace(/\./g, '').replace(',', '.');

            if (parseFloat(reservado) > 0) {

                linhasFiltradas += `
                    <tr class="${linha.className}">
                        <td>${linha.children[0].innerHTML}</td>
                        <td>${linha.children[1].innerHTML}</td>
                        <td>${linha.children[2].innerHTML}</td>
                        <td>${linha.children[3].innerHTML}</td>
                    </tr>
                `;
            }

        });

        let htmlTabela = `
            <table>

                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Estoque Atual</th>
                        <th>Reservado</th>
                        <th>Disponível</th>
                    </tr>
                </thead>

                <tbody>
                    ${linhasFiltradas}
                </tbody>

            </table>
        `;

        let win = window.open('', '', 'width=1000,height=800');

        win.document.write(`
        <html>

        <head>

            <title>Reserva de Estoque</title>

            <style>

                *{
                    box-sizing:border-box;
                }

                body{
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    color:#000;
                    padding:20px;
                }

                h1{
                    text-align:center;
                    margin-bottom:20px;
                    font-size:20px;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                }

                th, td{
                    border:1px solid #000;
                    padding:8px;
                }

                th{
                    background:#f2f2f2;
                    text-transform:uppercase;
                    font-size:11px;
                }

                td:nth-child(2),
                td:nth-child(3),
                td:nth-child(4){
                    text-align:center;
                    width:140px;
                }

                tr{
                    page-break-inside:avoid;
                }

                .table-danger{
                    background:#f8d7da !important;
                }

                .table-warning{
                    background:#fff3cd !important;
                }

                @page{
                    size:A4 portrait;
                    margin:10mm;
                }

                @media print{

                    thead{
                        display:table-header-group;
                    }

                    tr{
                        page-break-inside:avoid;
                    }

                }

            </style>

        </head>

        <body>

            <h1>Reserva de Estoque dos Pedidos em Aberto</h1>

            ${htmlTabela}

            <script>

                window.onload = function(){

                    window.focus();

                    setTimeout(() => {

                        window.print();

                        window.onafterprint = function(){
                            window.close();
                        };

                    }, 300);

                }

            <\/script>

        </body>

        </html>
        `);

        win.document.close();
    }
</script>
<?php include(FOOTER_TEMPLATE); ?>