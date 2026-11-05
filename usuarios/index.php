<?php
require_once('../config.php');

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

$nivel = $_SESSION['usuarioNiveisAcessoId'] ?? 0;
if ($nivel != 1) {
    header("Location: " . BASEURL);
    exit;
}

require_once('processa/index_processa.php');
include(HEADER_TEMPLATE);
?>
<header>
	<div class="row">
		<div class="col-sm-6">
			<h2><i class="fas fa-users"></i> Usuários</h2>
		</div>

		<div class="col-sm-6 text-sm-end h2">
			<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Novo Usuário</a>

		</div>
	</div>
</header>

<hr>


<div class="table-responsive-sm">

	<table name="item" id="item" data-vazio="Nenhum registro encontrado." class="table table-light table-striped table-sm">
		<thead class="text-sm-center">
			<tr>
				<th width="5%">ID</th>
				<th width="20%">Nome</th>
				<th width="25%">E-mail</th>
				<th width="20%">CPF</th>
				<th width="25%">Nível</th>
				<th width="5%">Opções</th>
			</tr>
		</thead>



		<tbody>
			<?php if ($rows = 1) {
				while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {




					?>
					<tr>
						<td>

							<?php echo $row['ID']; ?>
						</td>
						<td>
							<?php echo $row['NOME']; ?>
						</td>
						<td>
							<?php echo htmlspecialchars($row['EMAIL'] ?? ''); ?>
						</td>
						<td>
							<?php echo $row['CPF']; ?>
						</td>
						<td>
							<?php echo $row['NIVEL']; ?>
						</td>

						<td class="actions text-sm-end">

							<div class="btn-group">
								<button type="button" class="btn btn-success btn-sm dropdown-toggle"
									data-bs-toggle="dropdown" aria-expanded="false">
									<i class="fa-solid fa-gear"></i>
								</button>
								<ul class="dropdown-menu">
									<li><a href="edit.php?id=<?php echo $row['ID']; ?>" class="dropdown-item"><i
												class="far fa-edit"></i> Editar </a></li>
									<li><a href="acesso.php?id=<?php echo $row['ID']; ?>" class="dropdown-item"><i
												class="far fa-check-square	"></i> Acesso Unidades </a></li>
								</ul>
							</div>

						</td>


					</tr>

				<?php }
				sqlsrv_free_stmt($stmt);
			} ?>
		</tbody>
	</table>
</div>
</div>




</body>

</html>

<script src="<?php echo BASEURL; ?>js/tabela.js"></script>

<?php include(FOOTER_TEMPLATE); ?>