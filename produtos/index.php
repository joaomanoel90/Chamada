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
			<h2><i class="fas fa-box	"></i> Produtos</h2>
		</div>

		<div class="col-sm-6 text-sm-end h2">
			<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Novo Produto</a>

		</div>

</header>

<hr>


<div class="table-responsive-sm">

	<table name="item" id="item" class="table table-light table-striped table-sm " style="vertical-align: middle;">
		<thead class="text-sm-center">
			<tr>
				<th>ID</th>
				<th>Produto</th>
				<th>Unidade</th>
				<th>Categoria</th>
				<th>Saldo</th>
				<th>Opções</th>
			</tr>
		</thead>



		<tbody>
			<?php if ($rows_indice = 1) {
				while ($row = sqlsrv_fetch_array($stmt_indice, SQLSRV_FETCH_ASSOC)) {
			?>
					<tr class="<?php echo ($row['Ativo'] == 0) ? 'inactive-row' : ''; ?>">
						<td>

							<?php echo $row['ID_Produto']; ?>
						</td>
						<td>
							<?php echo $row['Descricao']; ?>
						</td>
						<td>
							<?php echo $row['Unidade']; ?>
						</td>
						<td>
							<?php echo $row['Categoria']; ?>
						</td>
						<td>
							<?php echo $row['SaldoAtual']; ?>
						</td>

						<td class="actions text-sm-end">

							<div class="btn-group">
								<button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown"
									aria-expanded="false">
									<i class="fa-solid fa-gear"></i>
								</button>
								<ul class="dropdown-menu">
									<li><a href="movimento.php?id_produto=<?php echo $row['ID_Produto']; ?>" class="dropdown-item"><i
												class="fas fa-history"></i> Movimentação </a></li>
									<li>
									<li><a href="edit.php?id=<?php echo $row['ID_Produto']; ?>" class="dropdown-item"><i
												class="far fa-edit"></i> Editar </a></li>
									<li>

									<li><a href="foto.php?id=<?php echo $row['ID_Produto']; ?>" class="dropdown-item"><i
												class="fas fa-camera"></i> Adicionar foto </a></li>
									<li>

									<li><a href="entrada.php?id=<?php echo $row['ID_Produto']; ?>" class="dropdown-item"><i
												class="far fa-plus-square"></i> Lançar Entrada </a></li>
									<li>

									<li>
										<a href="saldo.php?id=<?php echo $row['ID_Produto']; ?>" class="dropdown-item">
											<i class="fas fa-balance-scale"></i>
											Ajustar Saldo
										</a>
									</li>
									<?php if ($row['Ativo'] == 1) { ?>
										<a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#delete-modal"
											data-customer=<?php echo $row['ID_Produto']; ?>><i class="fas fa-arrow-down"></i> Desativar
										</a>
									<?php } else { ?>
										<a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#ativa-modal"
											data-customer=<?php echo $row['ID_Produto']; ?>><i class="fas fa-arrow-up"></i> Ativar
										</a>
									<?php }; ?>


									</li>
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
			<?php }; ?>
		</tbody>
	</table>
</div>
<script src="<?php echo BASEURL; ?>js/tabela.js"></script>
<?php include('modal_ativa.php'); ?>
<?php include('modal.php'); ?>
<?php include(FOOTER_TEMPLATE); ?>
