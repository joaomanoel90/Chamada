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
			<h2><i class="fas fa-shopping-basket"></i> Pedidos</h2>
			<?php if (!empty($status_filtro)): ?>
				<p class="text-muted mb-0">
					Filtrando por: <strong><?php echo htmlspecialchars($status_filtro, ENT_QUOTES, 'UTF-8'); ?></strong>
					<a href="index.php" class="ms-2">Limpar filtro</a>
				</p>
			<?php endif; ?>
		</div>

		<div class="col-sm-6 text-sm-end h2">
			<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTutorial">
				<i class="fa-solid fa-circle-play"></i> Como utilizar?
			</button>

			<a class="btn btn-success" href="add.php">
				<i class="fa fa-plus"></i> Novo Pedido
			</a>
		</div>


</header>

<hr>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php unset($_SESSION['message'], $_SESSION['type']); endif; ?>


<div class="table-responsive-sm">

	<table name="item" id="item" class="table table-light table-striped table-sm " style="vertical-align: middle;">
		<thead class="text-sm-center">
			<tr>
				<th>ID</th>
				<th>Unidade</th>
				<th>Objetivo</th>
				<th>Status</th>
				<th>Opções</th>
			</tr>
		</thead>



		<tbody>
			<?php if ($rows_indice) {
				while ($row = sqlsrv_fetch_array($stmt_indice, SQLSRV_FETCH_ASSOC)) {
			?>
					<tr class="<?php echo ($row['Ativo'] == 0) ? 'inactive-row' : ''; ?>">
						<td>

							<?php echo $row['ID_Pedido']; ?>
						</td>
						<td>
							<?php echo $row['Unidade']; ?>
						</td>
						<td class="text-wrap text-break">
							<?php echo $row['Objetivo']; ?>
						</td>

						<td>
							<?php
							switch ($row['Status']) {
								case 'Aberto':
									echo '❗ Aberto (não encaminhado)';
									break;

								case 'Em Análise':
									echo '🔎 Em Análise';
									break;

								case 'Em Separação':
									echo '📦 Em Separação';
									break;

								case 'Finalizado':
									echo '✅ Finalizado';
									break;

								case 'Cancelado':
									echo '❌ Cancelado';
									break;

								default:
									echo $row['Status'];
									break;
							}
							?>
						</td>




						<td class="actions text-sm-end">

							<div class="btn-group">
								<?php if ($row['Status'] == 'Aberto' || $row['Status'] == 'Finalizado' || $row['Status'] == 'Cancelado') { ?>

									<button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown"
										aria-expanded="false">
										<i class="fa-solid fa-gear"></i>
									</button>

									<ul class="dropdown-menu">
										<?php if ($row['Status'] == 'Aberto') { ?>
											<li>
												<a href="edit.php?id=<?php echo $row['ID_Pedido']; ?>" class="dropdown-item">
													<i class="far fa-edit"></i> Editar
												</a>
											</li>
											<li>
												<a href="lista.php?id=<?php echo $row['ID_Pedido']; ?>" class="dropdown-item">
													<i class="far fa-plus-square"></i> Inserir Produtos
												</a>
											</li>
											<li>
												<a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#encaminha-modal"
													data-customer="<?php echo $row['ID_Pedido']; ?>">
													<i class="fa fa-location-arrow"></i> Encaminhar
												</a>
											</li>
											<li>
												<a href="#" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancela-modal"
													data-customer="<?php echo $row['ID_Pedido']; ?>">
													<i class="fas fa-times-circle"></i> Cancelar
												</a>
											</li>
										<?php } elseif ($row['Status'] == 'Finalizado' || $row['Status'] == 'Cancelado') { ?>
											<li>
												<a href="view.php?id=<?php echo $row['ID_Pedido']; ?>" class="dropdown-item">
													<i class="fa fa-eye"></i> Visualizar
												</a>
											</li>
										<?php } ?>
									</ul>

								<?php } ?>

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
<?php include('modal_encaminhar.php'); ?>
<?php include('modal_cancelar.php'); ?>
<div class="modal fade" id="modalTutorial" tabindex="-1" aria-labelledby="modalTutorialLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered">
		<div class="modal-content">

			<div class="modal-header">
				<h5 class="modal-title" id="modalTutorialLabel">
					<i class="fa-solid fa-circle-play"></i> Como utilizar o sistema
				</h5>

				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
			</div>

			<div class="modal-body p-0">

				<video id="videoTutorial" class="w-100" controls>
					<source src="<?php echo BASEURL; ?>videos/pedidos.mp4" type="video/mp4">
					Seu navegador não suporta a reprodução de vídeos.
				</video>

			</div>

		</div>
	</div>
</div>
<script>
	const modalTutorial = document.getElementById('modalTutorial');
	const videoTutorial = document.getElementById('videoTutorial');

	modalTutorial.addEventListener('hidden.bs.modal', function() {
		videoTutorial.pause();
		videoTutorial.currentTime = 0;
	});
</script>

<?php include(FOOTER_TEMPLATE); ?>