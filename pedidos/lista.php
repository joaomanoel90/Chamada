<?php
require_once('processa/lista_processa.php');


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
    <?php
    $produtos_adicionados = [];
    foreach ($itens_pedido as $item_adicionado) {
        if (isset($item_adicionado['ID_Produto'])) {
            $produtos_adicionados[intval($item_adicionado['ID_Produto'])] = true;
        }
    }
    ?>

    <h2><i class="fas fa-plus"></i> Adicionar pedido a lista</h2>

    <?php if ($pedido): ?>
        <div class="alert alert-info">
            <strong>Pedido:</strong> <?php echo htmlspecialchars($pedido['ID_Pedido']); ?> <br>
            <strong>Objetivo:</strong> <?php echo htmlspecialchars($pedido['Objetivo']); ?> <br>
            <strong>Unidade:</strong> <?php echo htmlspecialchars($pedido['Unidade']); ?>
        </div>
    <?php endif; ?>

    <hr />

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div class="btn-group" role="group" aria-label="Modo de inclusao de produtos">
            <button type="button" class="btn btn-success active js-modo-lista" data-modo="select">
                <i class="fas fa-list"></i> Modo select
            </button>
            <button type="button" class="btn btn-outline-success js-modo-lista" data-modo="card">
                <i class="fas fa-grip"></i> Modo card
            </button>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-success me-2">Voltar</a>
            <?php if (isset($pedido['Status']) && $pedido['Status'] === 'Aberto'): ?>
                <?php if (count($itens_pedido) > 0): ?>
                    <a href="encaminha.php?id=<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-success" onclick="return confirm('Deseja realmente encaminhar este pedido para análise?');">
                        <i class="fas fa-paper-plane"></i> Encaminhar Pedido
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled title="Adicione ao menos um produto antes de encaminhar">
                        <i class="fas fa-paper-plane"></i> Encaminhar Pedido
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <section id="modo-select" class="modo-adicao">
        <form id="form-adicionar-item" action="lista.php" method="post">
            <input type="hidden" name="id_pedido" value="<?php echo intval($pedido['ID_Pedido']); ?>">

            <div class="row">
                <div class="form-group col-md-10">
                    <label class="fw-bold">Produto</label>
                    <select name="id_produto" class="form-control" id="id_produto" required>
                        <option value="">Selecione um produto</option>
                        <?php foreach ($produtos as $produto): ?>
                            <?php $produto_ja_adicionado = isset($produtos_adicionados[intval($produto['ID_Produto'])]); ?>
                            <option value="<?php echo $produto['ID_Produto']; ?>" <?php echo $produto_ja_adicionado ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($produto['Descricao'] . ' - ' . $produto['Unidade'] . ' - ' . $produto['Categoria']); ?>
                                <?php echo $produto_ja_adicionado ? ' (ja adicionado)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-md-2">
                    <label class="fw-bold">Quantidade</label>
                    <input type="number" class="form-control js-quantidade-select" name="quantidade" id="quantidade" required min="1" step="0.01">
                </div>
            </div>

            <div class="col-auto my-4">
                <button type="submit" class="btn btn-success"><i class='fas fa-plus'></i> Adicionar a Lista</button>
            </div>
        </form>
    </section>

    <section id="modo-card" class="modo-adicao d-none">
        <div class="row g-2 mb-3">
            <div class="col-md-6 col-lg-4">
                <label class="fw-bold">Buscar produto</label>
                <input type="search" class="form-control" id="busca-produto-card" placeholder="Digite para filtrar os cards">
            </div>
        </div>

        <div class="produtos-card-grid">
            <?php foreach ($produtos as $produto): ?>
                <?php
                $id_produto_card = intval($produto['ID_Produto']);
                $produto_ja_adicionado = isset($produtos_adicionados[$id_produto_card]);
                $texto_busca = strtolower($produto['Descricao'] . ' ' . $produto['Unidade'] . ' ' . $produto['Categoria']);
                $descricao_produto = trim($produto['Descricao']);
                $foto_produto = trim($produto['FotoBase64'] ?? '');
                if ($foto_produto !== '' && stripos($foto_produto, 'data:image/') !== 0) {
                    $foto_produto = 'data:image/jpeg;base64,' . $foto_produto;
                }
                $partes_descricao = preg_split('/\s+/', $descricao_produto);
                $iniciais_produto = '';
                foreach ($partes_descricao as $parte_descricao) {
                    if ($parte_descricao !== '') {
                        $iniciais_produto .= strtoupper(substr($parte_descricao, 0, 1));
                    }

                    if (strlen($iniciais_produto) >= 2) {
                        break;
                    }
                }
                $iniciais_produto = $iniciais_produto ?: 'PR';
                ?>
                <form class="produto-card js-produto-card <?php echo $produto_ja_adicionado ? 'produto-card-adicionado' : ''; ?>"
                    action="lista.php" method="post" data-produto-id="<?php echo $id_produto_card; ?>"
                    data-busca="<?php echo htmlspecialchars($texto_busca); ?>">
                    <input type="hidden" name="id_pedido" value="<?php echo intval($pedido['ID_Pedido']); ?>">
                    <input type="hidden" name="id_produto" value="<?php echo $id_produto_card; ?>">

                    <div class="produto-card-visual" aria-hidden="true">
                        <?php if ($foto_produto !== ''): ?>
                            <img src="<?php echo htmlspecialchars($foto_produto); ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-box-open"></i>
                            <span><?php echo htmlspecialchars($iniciais_produto); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="produto-card-corpo">
                        <div class="produto-card-titulo"><?php echo htmlspecialchars($produto['Descricao']); ?></div>
                        <div class="produto-card-meta">
                            <span><?php echo htmlspecialchars($produto['Unidade']); ?></span>
                            <span><?php echo htmlspecialchars($produto['Categoria']); ?></span>
                        </div>
                    </div>

                    <div class="produto-card-acoes">
                        <label class="fw-bold small mb-1" for="quantidade_card_<?php echo $id_produto_card; ?>">Qtd.</label>
                        <input type="number" class="form-control js-quantidade-card" name="quantidade"
                            id="quantidade_card_<?php echo $id_produto_card; ?>" min="1" step="0.01"
                            <?php echo $produto_ja_adicionado ? 'disabled' : 'required'; ?>>
                        <button type="submit" class="btn btn-success btn-sm mt-2 w-100" <?php echo $produto_ja_adicionado ? 'disabled' : ''; ?>>
                            <i class='fas fa-plus'></i> Adicionar
                        </button>
                        <?php if ($produto_ja_adicionado): ?>
                            <span class="badge bg-secondary mt-2 w-100">Ja adicionado</span>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3 produto-card-paginacao">
            <div class="d-flex align-items-center gap-2">
                <label class="fw-bold mb-0" for="cards-por-pagina">Cards por pagina</label>
                <select class="form-control form-control-sm" id="cards-por-pagina">
                    <option value="8">8</option>
                    <option value="12" selected>12</option>
                    <option value="24">24</option>
                    <option value="48">48</option>
                </select>
            </div>
            <div class="text-muted small" id="cards-resumo"></div>
            <div class="btn-group" role="group" aria-label="Paginacao dos cards">
                <button type="button" class="btn btn-outline-success btn-sm" id="cards-pagina-anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-outline-success btn-sm disabled" id="cards-pagina-atual"></button>
                <button type="button" class="btn btn-outline-success btn-sm" id="cards-proxima-pagina">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>


    <h4 class="mt-5">Itens adicionados</h4>
    <div class="table-responsive-sm">
        <table class="table table-light table-striped table-sm " id="tabela-itens">
            <thead>
                <tr>
                    <th>ID Lançamento</th>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens_pedido as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['ID']); ?></td>
                        <td><?php echo htmlspecialchars($item['ProdutoCompleto']); ?></td>
                        <td><?php echo htmlspecialchars($item['Quantidade']); ?></td>
                        <td class="actions text-sm-end">
                            <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#delete-modal"
                                data-customer=<?php echo $item['ID']; ?>><i class="fas fa-trash-alt	"></i> Excluir
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<style>
    .produtos-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 12px;
    }

    .produto-card {
        display: flex;
        flex-direction: column;
        min-height: 320px;
        gap: 12px;
        padding: 14px;
        background: #fff;
        border: 1px solid #d7dee5;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(44, 62, 80, 0.08);
    }

    .produto-card-visual {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 132px;
        border: 1px solid #d7dee5;
        border-radius: 8px;
        background: #eef4f1;
        color: #2f6848;
        text-align: center;
    }

    .produto-card-visual i {
        font-size: 2rem;
    }

    .produto-card-visual img {
        display: block;
        width: 100%;
        height: 132px;
        object-fit: contain;
        border-radius: 6px;
    }

    .produto-card-visual span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #fff;
        border: 1px solid #cddbd3;
        font-weight: 800;
        font-size: 0.85rem;
        color: #1f5137;
    }

    .produto-card-corpo {
        min-width: 0;
        flex: 1;
    }

    .produto-card-titulo {
        font-weight: 700;
        line-height: 1.25;
        color: #1f2d3d;
        overflow-wrap: anywhere;
    }

    .produto-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
        color: #52616f;
        font-size: 0.875rem;
    }

    .produto-card-meta span {
        padding: 3px 8px;
        border: 1px solid #d7dee5;
        border-radius: 999px;
        background: #f7f9fb;
    }

    .produto-card-acoes {
        width: 100%;
    }

    .produto-card-adicionado {
        background: #f3f5f7;
        opacity: 0.8;
    }

    @media (max-width: 575.98px) {
        .produto-card {
            min-height: 300px;
        }

        .produto-card-visual {
            min-height: 120px;
        }
    }
</style>


<script>
    $(document).ready(function() {
        $('#id_produto').select2({
            theme: 'bootstrap-5',
            placeholder: "Selecione um produto",
            allowClear: true,
            width: '100%'
        });

        const idPedido = <?php echo isset($pedido['ID_Pedido']) ? intval($pedido['ID_Pedido']) : 0; ?>;
        const modoStorageKey = 'pedidoListaModo:' + idPedido;
        const quantidades = {};
        const $selectProduto = $('#id_produto');
        const $quantidadeSelect = $('#quantidade');
        const $botoesModo = $('.js-modo-lista');
        const $modoSelect = $('#modo-select');
        const $modoCard = $('#modo-card');
        const $cardsProdutos = $('.js-produto-card');
        const $cardsResumo = $('#cards-resumo');
        const $cardsPaginaAnterior = $('#cards-pagina-anterior');
        const $cardsProximaPagina = $('#cards-proxima-pagina');
        const $cardsPaginaAtual = $('#cards-pagina-atual');
        const $cardsPorPagina = $('#cards-por-pagina');
        let paginaCards = 1;

        function quantidadeValida(valor) {
            return valor !== '' && parseFloat(valor) > 0;
        }

        function salvarQuantidade(produtoId, valor) {
            if (!produtoId) {
                return;
            }

            if (valor === '') {
                delete quantidades[produtoId];
            } else {
                quantidades[produtoId] = valor;
            }
        }

        function sincronizarSelect() {
            const produtoId = $selectProduto.val();
            $quantidadeSelect.val(produtoId && quantidades[produtoId] ? quantidades[produtoId] : '');
        }

        function sincronizarCards() {
            $cardsProdutos.each(function() {
                const produtoId = $(this).data('produto-id').toString();
                const $input = $(this).find('.js-quantidade-card');

                if (!$input.prop('disabled')) {
                    $input.val(quantidades[produtoId] || '');
                }
            });
        }

        function cardsFiltrados() {
            const termo = $('#busca-produto-card').val().toLowerCase();

            return $cardsProdutos.filter(function() {
                return $(this).data('busca').indexOf(termo) !== -1;
            });
        }

        function atualizarPaginacaoCards() {
            const $filtrados = cardsFiltrados();
            const porPagina = parseInt($cardsPorPagina.val(), 10) || 12;
            const total = $filtrados.length;
            const totalPaginas = Math.max(1, Math.ceil(total / porPagina));

            paginaCards = Math.min(Math.max(paginaCards, 1), totalPaginas);

            const inicio = (paginaCards - 1) * porPagina;
            const fim = inicio + porPagina;

            $cardsProdutos.hide();
            $filtrados.slice(inicio, fim).show();

            const primeiroItem = total === 0 ? 0 : inicio + 1;
            const ultimoItem = Math.min(fim, total);

            $cardsResumo.text(total === 0 ? 'Nenhum produto encontrado' : primeiroItem + ' a ' + ultimoItem + ' de ' + total + ' produtos');
            $cardsPaginaAtual.text(paginaCards + ' / ' + totalPaginas);
            $cardsPaginaAnterior.prop('disabled', paginaCards <= 1);
            $cardsProximaPagina.prop('disabled', paginaCards >= totalPaginas);
        }

        function alternarModo(modo) {
            modo = modo === 'card' ? 'card' : 'select';
            const usandoCard = modo === 'card';

            $modoSelect.toggleClass('d-none', usandoCard);
            $modoCard.toggleClass('d-none', !usandoCard);

            $botoesModo.each(function() {
                const ativo = $(this).data('modo') === modo;
                $(this).toggleClass('active btn-success', ativo);
                $(this).toggleClass('btn-outline-success', !ativo);
            });

            localStorage.setItem(modoStorageKey, modo);
            sincronizarSelect();
            sincronizarCards();
            atualizarPaginacaoCards();
        }

        $selectProduto.on('change', sincronizarSelect);

        $quantidadeSelect.on('input', function() {
            salvarQuantidade($selectProduto.val(), $(this).val());
        });

        $('.js-quantidade-card').on('input', function() {
            const produtoId = $(this).closest('.js-produto-card').data('produto-id').toString();
            salvarQuantidade(produtoId, $(this).val());

            if ($selectProduto.val() === produtoId) {
                $quantidadeSelect.val($(this).val());
            }
        });

        $botoesModo.on('click', function() {
            salvarQuantidade($selectProduto.val(), $quantidadeSelect.val());
            alternarModo($(this).data('modo'));
        });

        $('#form-adicionar-item').on('submit', function(event) {
            salvarQuantidade($selectProduto.val(), $quantidadeSelect.val());

            if (!$selectProduto.val() || !quantidadeValida($quantidadeSelect.val())) {
                event.preventDefault();
            }
        });

        $('.js-produto-card').on('submit', function(event) {
            const $input = $(this).find('.js-quantidade-card');

            if (!quantidadeValida($input.val())) {
                event.preventDefault();
                $input.trigger('focus');
            }
        });

        $('#busca-produto-card').on('input', function() {
            paginaCards = 1;
            atualizarPaginacaoCards();
        });

        $cardsPorPagina.on('change', function() {
            paginaCards = 1;
            atualizarPaginacaoCards();
        });

        $cardsPaginaAnterior.on('click', function() {
            if (paginaCards > 1) {
                paginaCards--;
                atualizarPaginacaoCards();
            }
        });

        $cardsProximaPagina.on('click', function() {
            paginaCards++;
            atualizarPaginacaoCards();
        });

        alternarModo(localStorage.getItem(modoStorageKey) || 'select');
    });
</script>



<?php include('modal.php'); ?>

<?php include(FOOTER_TEMPLATE); ?>
