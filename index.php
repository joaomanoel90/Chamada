<?php
require_once('config.php');

if (!isset($_SESSION['usuarioId'])) {
	header("Location: " . BASEURL . "login.php");
	exit;
}

$nivel = $_SESSION['usuarioNiveisAcessoId'] ?? 0;
if ($nivel != 1 && $nivel != 2) {
	include(HEADER_TEMPLATE2);
} else {
	include(HEADER_TEMPLATE);
}
?>
<?php require_once('processa/dashboard_processa.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
	.chart-container {
		position: relative;
		height: 320px;
		width: 100%;
	}

	.chart-card {
		height: 100%;
	}

	.chart-card .card-body {
		height: 100%;
		display: flex;
		flex-direction: column;
	}

	.chart-card canvas {
		flex: 1;
		max-height: 260px;
	}

	.card-dashboard h2 {
		font-size: 2rem;
		margin: 0;
	}

	.card-dashboard h6 {
		margin-bottom: 10px;
		color: #666;
	}

	.card-dashboard {
		min-height: 120px;
	}

	.dashboard-card {
		transition:
			transform 0.25s ease,
			box-shadow 0.25s ease;

		border-radius: 18px;
	}

	.dashboard-card-link {
		color: inherit;
		text-decoration: none;
	}

	.dashboard-card-link:focus-visible {
		border-radius: 18px;
		outline: 3px solid #2c3e50;
		outline-offset: 3px;
	}

	.dashboard-card:hover {

		transform: translateY(-5px) scale(1.02);

		box-shadow:
			0 14px 30px rgba(0, 0, 0, 0.58) !important;

		z-index: 10;
	}
</style>

<h1>
	<i class="fas fa-chart-pie"></i> Dashboard
</h1>

<hr />

<div class="container-fluid">

	<!-- CARDS -->
	<div class="row row-cols-2 row-cols-md-6 g-3 mb-4">

		<a class="col dashboard-card-link" href="pedidos/index.php?status=<?php echo rawurlencode('Aberto'); ?>" aria-label="Ver pedidos em aberto">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Pedidos em Aberto</h6>
					<h2 class="mb-0"><?php echo intval($abertos); ?></h2>
				</div>
			</div>
		</a>

		<a class="col dashboard-card-link" href="<?php echo ($nivel == 1 || $nivel == 2) ? 'analise/index.php' : 'pedidos/index.php'; ?>?status=<?php echo rawurlencode('Em Análise'); ?>" aria-label="Ver pedidos em análise">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Pedidos em Análise</h6>
					<h2 class="mb-0"><?php echo intval($analise); ?></h2>
				</div>
			</div>
		</a>

		<a class="col dashboard-card-link" href="<?php echo ($nivel == 1 || $nivel == 2) ? 'analise/index.php' : 'pedidos/index.php'; ?>?status=<?php echo rawurlencode('Em Separação'); ?>" aria-label="Ver pedidos em separação">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Pedidos em Separação</h6>
					<h2 class="mb-0"><?php echo intval($separacao); ?></h2>
				</div>
			</div>
		</a>

		<a class="col dashboard-card-link" href="<?php echo ($nivel == 1 || $nivel == 2) ? 'analise/index.php' : 'pedidos/index.php'; ?>?status=<?php echo rawurlencode('Finalizado'); ?>" aria-label="Ver pedidos finalizados">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Pedidos Finalizados</h6>
					<h2 class="mb-0"><?php echo intval($finalizados); ?></h2>
				</div>
			</div>
		</a>

		<a class="col dashboard-card-link" href="<?php echo ($nivel == 1 || $nivel == 2) ? 'analise/index.php' : 'pedidos/index.php'; ?>?status=<?php echo rawurlencode('Cancelado'); ?>" aria-label="Ver pedidos cancelados">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Pedidos Cancelados</h6>
					<h2 class="mb-0"><?php echo intval($cancelados); ?></h2>
				</div>
			</div>
		</a>

		<a class="col dashboard-card-link" href="pedidos/index.php" aria-label="Ver todos os pedidos">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Total</h6>
					<h2 class="mb-0"><?php echo intval($total); ?></h2>
				</div>
			</div>
		</a>

	</div>

	<!-- GRÁFICOS SUPERIORES -->
	<div class="row g-3">

		<div class="col-md-8">
			<div class="card border-0 rounded-4 dashboard-card">
				<div class="card-body">

					<h5 class="mb-3">Pedidos por Mês</h5>

					<div class="chart-container">
						<canvas id="graficoMes"></canvas>
					</div>

				</div>
			</div>
		</div>

		<div class="col-md-4">
			<div class="card border-0 rounded-4 dashboard-card">
				<div class="card-body">

					<h5 class="mb-3">Status dos Pedidos</h5>

					<div class="chart-container">
						<canvas id="graficoStatus"></canvas>
					</div>

				</div>
			</div>
		</div>

	</div>

	<!-- GRÁFICO POR UNIDADE -->
	<?php if (
		$_SESSION['usuarioNiveisAcessoId'] == 1 ||
		$_SESSION['usuarioNiveisAcessoId'] == 2
	): ?>

		<div class="row g-3 mt-1">

			<div class="col-md-12">

				<div class="card border-0 rounded-4 dashboard-card">
					<div class="card-body">

						<h5 class="mb-3">Pedidos por Unidade</h5>

						<div class="chart-container">
							<canvas id="graficoUnidades"></canvas>
						</div>

					</div>

				</div>

			</div>

		</div>

	<?php endif; ?>

</div>

<script>
	// ==============================
	// GRÁFICO MÊS
	// ==============================

	new Chart(document.getElementById('graficoMes'), {

		type: 'bar',

		data: {

			labels: <?php echo json_encode($labelsMes); ?>,

			datasets: [{
				label: 'Pedidos',
				data: <?php echo json_encode($dadosMes); ?>,
				borderWidth: 1
			}]
		},

		options: {

			maintainAspectRatio: false,
			responsive: true,

			scales: {
				y: {
					beginAtZero: true,
					ticks: {
						precision: 0,
						stepSize: 1
					}
				}
			}
		}
	});

	// ==============================
	// STATUS
	// ==============================

	new Chart(document.getElementById('graficoStatus'), {

		type: 'doughnut',

		data: {

			labels: [
				'Em Análise',
				'Em Separação',
				'Finalizado',
				'Cancelado'
			],

			datasets: [{
				data: [
					<?php echo intval($analise); ?>,
					<?php echo intval($separacao); ?>,
					<?php echo intval($finalizados); ?>,
					<?php echo intval($cancelados); ?>
				]
			}]
		},

		options: {
			maintainAspectRatio: false,
			responsive: true
		}
	});

	// ==============================
	// UNIDADES
	// ==============================

	<?php if (
		$_SESSION['usuarioNiveisAcessoId'] == 1 ||
		$_SESSION['usuarioNiveisAcessoId'] == 2
	): ?>

		new Chart(document.getElementById('graficoUnidades'), {

			type: 'bar',

			data: {

				labels: <?php echo json_encode($labelsUnidades); ?>,

				datasets: [{
					label: 'Pedidos',
					data: <?php echo json_encode($dadosUnidades); ?>,
					borderWidth: 1
				}]
			},

			options: {

				maintainAspectRatio: false,
				responsive: true,

				plugins: {
					legend: {
						display: false
					}
				},

				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
							stepSize: 1
						}
					}
				}
			}
		});

	<?php endif; ?>
</script>

<?php include(FOOTER_TEMPLATE); ?>