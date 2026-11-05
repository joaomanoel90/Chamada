<?php
require_once('processa/add_processa.php');
include(HEADER_TEMPLATE);

$contratos = $contratos ?? []; // Verifica se a variável existe
?>

<body>
    <h2><i class="fas fa-plus"></i> Leitor de Nota XML</h2>
    <form action="add.php" method="post" id="myForm">
        <div class="row">
            <div class="form-group col-md-4">
                <label for="fileInput">Arquivo XML</label>
                <div class="input-group mb-3">
                    <input type="file" class="form-control" id="fileInput" accept=".xml" required>
                    <button class="btn btn-outline-secondary" type="button" id="readButton">Ler Nota</button>
                </div>
            </div>

            <div class="form-group col-md-4">
                <label for="id_contrato">Contrato</label>
                <select class="form-control" name="id_contrato" id="id_contrato" required>
                    <option value="" disabled selected>Selecione um contrato</option>
                    <?php foreach ($contratos as $contrato): ?>
                        <option value="<?php echo htmlspecialchars($contrato['ID']); ?>">
                            <?php echo htmlspecialchars($contrato['NUMERO_CONTRATO']) . '/' . htmlspecialchars($contrato['ANO_CONTRATO']) . ' - ' . htmlspecialchars($contrato['FANTASIA']) . ' (' . htmlspecialchars($contrato['COMBUSTIVEL']) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group col-md-4">
                <label for="id_setor">Setor</label>
                <select class="form-control" name="id_setor" id="id_setor" required>
                    <option value="" disabled selected>Selecione um Setor</option>
                </select>
            </div>
        </div>

        <div class="table-responsive-sm">
            <table id="xmlTable" class="table table-info table-striped table-sm">
                <thead id="tableHeader" style="display: none;">
                    <tr>
                        <th>Campo</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <input type="hidden" name="combustivel" id="combustivel">
        <input type="hidden" name="data_emissao" id="data_emissao">
        <input type="hidden" name="cnpj_posto" id="cnpj_posto">
        <input type="hidden" name="chave" id="chave">
        <input type="hidden" name="destinatario_cnpj" id="destinatario_cnpj">
        <input type="hidden" name="produtos" id="produtos">

        <div class="col-auto my-5">
            <button type="submit" name="submit" class="btn btn-info">Salvar</button>
            <a href="index.php" class="btn btn-outline-info">Cancelar</a>
        </div>
    </form>

    <div id="errorContainer" style="display:none; color: red;"></div> <!-- Seção de erro -->

    <script>
        document.getElementById('readButton').addEventListener('click', function () {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];

            if (!file) {
                showError('Por favor, selecione um arquivo XML.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                const parser = new DOMParser();
                const xmlDoc = parser.parseFromString(event.target.result, "text/xml");

                if (xmlDoc.getElementsByTagName('parsererror').length > 0) {
                    showError('Erro ao analisar o arquivo XML.');
                    return;
                }

                const infNFe = xmlDoc.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "infNFe")[0];
                if (!infNFe) {
                    showError('Elemento <infNFe> não encontrado no XML.');
                    return;
                }

                const chaveValor = xmlDoc.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "chNFe")[0]?.textContent || 'N/A';
                const emit = infNFe.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "emit")[0];
                const dest = infNFe.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "dest")[0];

                const data = [
                    { campo: 'Chave de Acesso', valor: chaveValor },
                    { campo: 'CNPJ Emitente', valor: emit.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "CNPJ")[0]?.textContent || 'N/A' },
                    { campo: 'Nome Emitente', valor: emit.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "xNome")[0]?.textContent || 'N/A' },
                    { campo: 'Data de Emissão', valor: infNFe.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "dhEmi")[0]?.textContent || 'N/A' },
                    { campo: 'Total da Nota', valor: infNFe.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "vNF")[0]?.textContent || 'N/A' },
                    { campo: 'Destinatário', valor: dest.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "xNome")[0]?.textContent || 'N/A' },
                    { campo: 'CNPJ Destinatário', valor: dest.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "CNPJ")[0]?.textContent || 'N/A' }
                ];

                const produtos = infNFe.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "det");
                const produtosArray = [];
                for (let i = 0; i < produtos.length; i++) {
                    const produto = produtos[i].getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "xProd")[0]?.textContent || 'N/A';
                    const vUnCom = produtos[i].getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "vUnCom")[0]?.textContent || 'N/A';
                    const quantidade = produtos[i].getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "qCom")[0]?.textContent || 'N/A';

                    data.push({ campo: `Produto ${i + 1}`, valor: produto });
                    data.push({ campo: `Valor Unitário ${i + 1}`, valor: vUnCom });
                    data.push({ campo: `Quantidade ${i + 1}`, valor: quantidade });

                    produtosArray.push({
                        produto: produto,
                        valor_litro: vUnCom,
                        quantidade: quantidade
                    });
                }

                document.getElementById('tableHeader').style.display = '';
                const tableBody = document.getElementById('xmlTable').getElementsByTagName('tbody')[0];
                tableBody.innerHTML = '';

                data.forEach(item => {
                    const tr = document.createElement('tr');
                    const tdCampo = document.createElement('td');
                    const tdValor = document.createElement('td');

                    tdCampo.textContent = item.campo;
                    tdValor.textContent = item.valor;

                    tr.appendChild(tdCampo);
                    tr.appendChild(tdValor);
                    tableBody.appendChild(tr);
                });

                document.getElementById('combustivel').value = produtosArray.map(p => p.produto).join(', ');
                document.getElementById('data_emissao').value = infNFe.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "dhEmi")[0]?.textContent || 'N/A';
                document.getElementById('cnpj_posto').value = emit.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "CNPJ")[0]?.textContent || 'N/A';
                document.getElementById('chave').value = chaveValor;
                document.getElementById('destinatario_cnpj').value = dest.getElementsByTagNameNS("http://www.portalfiscal.inf.br/nfe", "CNPJ")[0]?.textContent || 'N/A';
                document.getElementById('produtos').value = JSON.stringify(produtosArray);

                alert('Nota lida com sucesso!');
            };

            reader.readAsText(file);
        });

        /// Listar Setores
        document.getElementById('id_contrato').addEventListener('change', function () {
            const idContrato = this.value;

            if (idContrato) {
                fetch('processa/get_setores.php?id_contrato=' + idContrato)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro na resposta da rede');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log(data); // Para debugging
                        const setorSelect = document.getElementById('id_setor');
                        setorSelect.innerHTML = '<option value="" disabled selected>Selecione um Setor</option>';

                        if (data.length > 0) {
                            data.forEach(setor => {
                                const option = document.createElement('option');
                                option.value = setor.ID; // Use o 'ID' como valor
                                option.textContent = setor.SETOR; // Mostre o nome do setor
                                setorSelect.appendChild(option);
                            });
                        } else {
                            const option = document.createElement('option');
                            option.value = '';
                            option.textContent = 'Nenhum setor disponível';
                            option.disabled = true; // Adiciona o atributo disabled
                            option.selected = true;  // Adiciona o atributo selected
                            setorSelect.appendChild(option);
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar setores:', error);
                        showError('Ocorreu um erro ao buscar os setores. Tente novamente mais tarde.');
                    });
            }
        });

        // Verifica se o formulário pode ser enviado
        document.getElementById('myForm').addEventListener('submit', function (event) {
            const setorSelect = document.getElementById('id_setor');
            if (setorSelect.value === '') {
                event.preventDefault(); // Impede o envio do formulário
                showError('Por favor, selecione um setor válido.');
            }
        });

        function showError(message) {
            const errorContainer = document.getElementById('errorContainer');
            errorContainer.style.display = 'block';
            errorContainer.innerText = message;
        }
    </script>
</body>

</html>