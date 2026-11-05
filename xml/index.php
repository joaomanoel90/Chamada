<?php
require_once('processa/index_processa.php');
?>

<?php
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

<header>
	<div class="row">
		<div class="col-sm-6">
			<h2><i class="fas fa-code"></i> XML</h2>
		</div>

		<div class="col-sm-6 text-sm-end h2">
			<a class="btn btn-info" href="add.php"><i class="fa fa-plus"></i> Novo XML</a>

		</div>

</header>

<hr>


<div class="table-responsive-sm">

	<table name="item" id="item" class="table table-info table-striped table-sm " style="vertical-align: middle;">
		<thead class="text-sm-center">
			<tr>
				<th>Combustível</th>
				<th>Valor ANP com Desconto</th>
				<th>Valor Nota</th>
				<th>Quantidade</th>
				<th>Data Emissão</th>
				<th>Posto</th>
				<th>Setor</th>
				<th>Chave</th>

			</tr>
		</thead>



		<tbody>
			<?php
			if ($rows_indice > 0) { // Verifique se existem registros
				while ($row = sqlsrv_fetch_array($stmt_indice, SQLSRV_FETCH_ASSOC)) {
					// Substitui vírgula por ponto, caso exista
					$valorANP = str_replace(',', '.', $row['VALOR_ANP_DESCONTO']);
					$valorNota = str_replace(',', '.', $row['VALOR_NOTA']);

					// Verifica se VALOR_NOTA é menor que VALOR_ANP_DESCONTO
					$rowClass = ($valorNota < $valorANP) ? 'table-success' : 'table-danger';


					?>
					<tr class="<?php echo $rowClass; ?>">
						<td><?php echo htmlspecialchars($row['COMBUSTIVEL']); ?></td>
						<td>
							<?php
							// Formata o preço
							echo 'R$ ' . number_format($valorANP, 2, ',', '.');
							?>
						</td>
						<td>
							<?php
							// Formata o preço
							echo 'R$ ' . number_format($valorNota, 2, ',', '.');
							?>
						</td>
						<td><?php echo htmlspecialchars($row['QUANTIDADE']); ?></td>
						<td>
							<?php
							if ($row['DATA_EMISSAO'] !== null) {
								echo $row['DATA_EMISSAO']->format('d-m-Y h:i A');
							} else {
								echo 'Data não disponível'; // Mensagem alternativa
							}
							?>
						</td>
						<td><?php echo htmlspecialchars($row['FANTASIA']); ?></td>
						<td><?php echo htmlspecialchars($row['SETOR']); ?></td>
						<td><?php echo htmlspecialchars($row['CHAVE']); ?></td>
					</tr>
				<?php }
				sqlsrv_free_stmt($stmt_indice); // Libere o statement após o uso
			} else { ?>
				<tr>
					<td colspan="8" class="text-center">Nenhum registro encontrado.</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
</div>




</body>

</html>

<script src="<?php echo BASEURL; ?>js/tabela.js"></script>
<?php include(FOOTER_TEMPLATE); ?>