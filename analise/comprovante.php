<?php
require_once('processa/comprovante_processa.php');
include(HEADER_TEMPLATE);

// 🔒 Permissão
if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
    header("location:" . BASEURL);
    exit;
}
?>

<style>
    #area-impressao {
        background: #fff;
        padding: 20px;
        font-family: Arial, sans-serif;
        font-size: 12px;
    }

    /* 🔥 PADRÃO VISUAL */
    .bloco {
        border: 1px solid #000;
        border-radius: 8px;
        margin-top: 8px;
        overflow: hidden;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        border: 1px solid #000;
        padding: 6px;
    }

    th {
        background: #f2f2f2;
        text-transform: uppercase;
        font-size: 11px;
    }

    .titulo {
        text-align: center;
        font-weight: bold;
        font-size: 15px;
    }

    .subtitulo {
        text-align: center;
        font-size: 12px;
    }

    .assinatura {
        height: 100px;
    }

    .linha-assinatura {
        border-top: 1px solid #000;
        margin-top: 5px;
        padding-top: 3px;
        text-align: center;
        font-size: 11px;
    }
</style>

<?php if ($pedido): ?>

    <button class="btn btn-success mb-3 no-print" onclick="imprimirComprovante()">
        <i class="fas fa-print"></i> Imprimir
    </button>

    <div id="area-impressao">

        <!-- CABEÇALHO -->
        <div class="bloco">
            <table>
                <tr>
                    <td style="width:15%; text-align:center;">
                        <img src="../images/brasao1.png" style="max-height:70px;">
                    </td>
                    <td style="width:70%;">
                        <div class="titulo">PREFEITURA MUNICIPAL</div>
                        <!-- <div class="subtitulo">SECRETARIA MUNICIPAL DE EDUCAÇÃO</div> -->
                        <div class="subtitulo"><strong>COMPROVANTE DE ENTREGA DE MATERIAL</strong></div>
                    </td>
                    <td style="width:15%; text-align:center;">
                        <strong>Nº</strong><br>
                        <?php echo $pedido['ID']; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- DADOS -->
        <div class="bloco">
            <table>
                <tr>
                    <td><strong>UNIDADE:</strong><br><?php echo $pedido['Unidade']; ?></td>
                    <td><strong>DATA PEDIDO:</strong><br><?php echo $pedido['DataCadastro']->format('d/m/Y'); ?></td>
                    <td><strong>DATA ENVIO:</strong><br><?php echo $pedido['DataEnvio']->format('d/m/Y'); ?></td>
                    <td><strong>FINALIZADO:</strong><br><?php echo $pedido['DataFinalizado']->format('d/m/Y'); ?></td>
                </tr>
            </table>
        </div>

        <div class="bloco">
            <table>
                <tr>
                    <td><strong>OBJETIVO:</strong><br><?php echo $pedido['Objetivo']; ?></td>
                </tr>
            </table>
        </div>

        <div class="bloco">
            <table>
                <tr>
                    <td><strong>RESPONSÁVEL PELA ENTREGA:</strong><br><?php echo $pedido['UsuarioFinalizou']; ?></td>
                </tr>
            </table>
        </div>

        <!-- ITENS -->
        <div class="bloco">
            <table>
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th>Produto</th>
                        <th style="width:15%">Unidade</th>
                        <th style="width:15%">Quantidade</th>
                    </tr>
                </thead>

                <tbody>
                    <?php $i = 1;
                    foreach ($itens as $item): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo $item['ProdutoCompleto']; ?></td>
                            <td><?php echo $item['Unidade']; ?></td>
                            <td><?php echo number_format($item['Liberado'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>


        <div class="bloco" style="margin-top:40px;">
            <table style="border:none;">
                <tr>

                    <td style="width:50%; padding:20px; border:none;">

                        <div class="assinatura"></div>

                        <div class="linha-assinatura">
                            RESPONSÁVEL PELA ENTREGA<br>
                            <?php echo $pedido['UsuarioFinalizou']; ?>
                        </div>

                    </td>

                    <td style="width:50%; padding:20px; border:none;">

                        <div class="assinatura"></div>

                        <div class="linha-assinatura">
                            RECEBEDOR<br>
                            <?php echo $pedido['NomeRecebedor']; ?> - <?php echo $pedido['DocumentoRecebedor']; ?>
                        </div>

                    </td>

                </tr>
            </table>
        </div>


    </div>

    <script>
        function imprimirComprovante() {

            let conteudo = document.getElementById('area-impressao').innerHTML;

            let win = window.open('', '_blank');

            win.document.write(`
    <html>
    <head>
        <title>Comprovante</title>
        <style>

            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                margin: 10mm;
            }

            .bloco {
                border: 1px solid #000;
                border-radius: 8px;
                margin-top: 8px;
                overflow: hidden;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th, td {
                border: 1px solid #000;
                padding: 6px;
            }

            th {
                background: #f2f2f2;
                text-transform: uppercase;
                font-size: 11px;
            }

            .assinatura {
                height: 100px;
            }

            .linha-assinatura {
                border-top: 1px solid #000;
                margin-top: 5px;
                padding-top: 3px;
                text-align: center;
                font-size: 11px;
            }

            .titulo {
                text-align: center;
                font-weight: bold;
                font-size: 15px;
            }

            .subtitulo {
                text-align: center;
                font-size: 12px;
            }

            thead {
                display: table-header-group;
            }

            @page {
                size: A4;
                margin: 10mm;
            }

        </style>
    </head>
    <body>

        ${conteudo}

        <script>
            window.onload = function() {
                window.print();
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