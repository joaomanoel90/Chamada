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
			<h2><i class="fas fa-truck"></i> Fornecedores</h2>
		</div>

		<div class="col-sm-6 text-sm-end h2">
			<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Fornecedor</a>

		</div>

</header>

<hr>


<div class="table-responsive-sm">

	<table name="item" id="item" class="table table-light table-striped table-sm " style="vertical-align: middle;">
		<thead class="text-sm-center">
			<tr>
				<th width="5%">ID</th>
				<th width="40%">Fornecedor</th>
				<th width="40%">CNPJ</th>


				<th width="5%">Opções</th>
			</tr>
		</thead>



		<tbody>
			<?php if ($rows_indice = 1) {
				while ($row = sqlsrv_fetch_array($stmt_indice, SQLSRV_FETCH_ASSOC)) {
					?>
					<tr>
						<td>

							<?php echo $row['ID']; ?>
						</td>
						<td>
							<?php echo $row['Fornecedor']; ?>
						</td>
					<td>
							<?php echo $row['CNPJ']; ?>
						</td>

						<td class="actions text-sm-end">

							<div class="btn-group">
								<button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown"
									aria-expanded="false">
									<i class="fa-solid fa-gear"></i>
								</button>
								<ul class="dropdown-menu">
									<li><a href="edit.php?id=<?php echo $row['ID']; ?>" class="dropdown-item"><i
												class="far fa-edit"></i> Editar </a></li>


								</ul>
							</div>

						</td>


					</tr>

				<?php }
				sqlsrv_free_stmt($stmt_indice);
			} else { ?>
				<tr>
					<td colspan="6">Nenhum registro encontrado.</td>
				</tr>
			<?php }
			; ?>
		</tbody>
	</table>
</div>
</div>




</body>

</html>

<script src="<?php echo BASEURL; ?>js/tabela.js"></script>
<?php include(FOOTER_TEMPLATE); ?>