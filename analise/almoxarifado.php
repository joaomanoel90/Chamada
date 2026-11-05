<?php
require_once('processa/almoxarifado_processa.php');
include(HEADER_TEMPLATE);

// Verifica permissão
if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
    header("location:" . BASEURL);
    exit;
}
?>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if (empty($erro) && $pedido): ?>
    <div id="area-impressao">

        <h2><i class="fas fa-box"></i> Itens a Separar</h2>

        <div class="alert alert-info">
            <strong>Pedido:</strong> <?php echo htmlspecialchars($pedido['ID_Pedido']); ?> <br>
            <strong>Unidade:</strong> <?php echo htmlspecialchars($pedido['Unidade']); ?> <br>

        </div>

        <h4 class="mt-5">Itens do Pedido</h4>
        <div class="table-responsive-sm">
            <table class="table table-light table-striped table-sm" id="tabela-itens">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Produto</th>
                        <th>Quantidade Liberada</th>

                    </tr>
                </thead>
                <tbody>
                    <?php
                    $contador = 1; // inicia numeração
                    foreach ($itens_pedido as $item):
                        $liberado = !empty($item['Liberado']) ? $item['Liberado'] : '0,00';
                        $classeLinha = ($liberado == '0,00') ? 'text-decoration-line-through' : '';
                    ?>
                        <tr class="<?php echo $classeLinha; ?>">
                            <td><?php echo $contador++; ?></td> <!-- número sequencial -->
                            <td>
                                <strong><?php echo htmlspecialchars($item['Produto']); ?></strong><br>
                                <!-- <small>
                                    Marca: <?php //echo htmlspecialchars($item['Marca'] ?? 'Não definida'); 
                                            ?>
                                </small> -->
                            </td>
                            <td><?php echo htmlspecialchars($liberado); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">
        <a href="index.php" class="btn btn-outline-success">Voltar</a>
        <button class="btn btn-success no-print" onclick="imprimirComprovante()">
            <i class="fas fa-print"></i> Imprimir
        </button>
    </div>



    <script>
        function imprimirComprovante() {

            let conteudo = document.getElementById('area-impressao').innerHTML;

            let win = window.open('', '', 'width=900,height=700');

            win.document.write(`
        <html>
        <head>
            <title>Itens a Separar</title>

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

                h2, h4{
                    margin-bottom:10px;
                }

                .alert{
                    border:1px solid #999;
                    padding:10px;
                    margin-bottom:20px;
                }

                table{
                    width:100%;
                    border-collapse: collapse;
                    margin-top:10px;
                }

                th, td{
                    border:1px solid #000;
                    padding:8px;
                    text-align:left;
                }

                th{
                    background:#f2f2f2;
                    font-weight:bold;
                    text-transform:uppercase;
                }

                tr{
                    page-break-inside: avoid;
                }

                .text-decoration-line-through{
                    text-decoration: line-through;
                    color:#777;
                }

                @page{
                    size:A4 portrait;
                    margin:10mm;
                }

                @media print{

                    body{
                        margin:0;
                        padding:0;
                    }

                    table{
                        page-break-inside:auto;
                    }

                    tr{
                        page-break-inside:avoid;
                        page-break-after:auto;
                    }

                    thead{
                        display:table-header-group;
                    }

                    tfoot{
                        display:table-footer-group;
                    }

                }

            </style>

        </head>

        <body>

            ${conteudo}

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
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>