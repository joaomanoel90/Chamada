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
	.chart-container { position: relative; height: 300px; width: 100%; }
	.card-dashboard h2 { font-size: 2rem; margin: 0; }
	.card-dashboard h6 { margin-bottom: 10px; color: #666; }
	.dashboard-card {
		transition: transform .25s ease, box-shadow .25s ease;
		border-radius: 18px;
	}
	.dashboard-card-link { color: inherit; text-decoration: none; }
	.dashboard-card-link:focus-visible { border-radius: 18px; outline: 3px solid #2c3e50; outline-offset: 3px; }
	.dashboard-card:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 14px 30px rgba(0,0,0,.25) !important; z-index: 10; }
</style>

<h1><i class="fas fa-clipboard-user"></i> Chamada de Alunos</h1>
<p class="text-muted mb-0"><?= ch_data_longa($hoje) ?></p>
<hr>

<?= ch_flash_html() ?>

<div class="container-fluid px-0">

	<!-- CARDS -->
	<div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-4">

		<a class="col dashboard-card-link" href="<?= ch_url('chamada/index.php') ?>" aria-label="Ir para a chamada do dia">
			<div class="card border-0 rounded-4 dashboard-card card-dashboard h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Chamadas pendentes hoje</h6>
					<h2 class="mb-0 <?= $pendentes ? 'text-danger' : 'text-success' ?>"><?= count($pendentes) ?></h2>
				</div>
			</div>
		</a>

		<div class="col">
			<div class="card border-0 rounded-4 dashboard-card card-dashboard h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Lançadas hoje</h6>
					<h2 class="mb-0"><?= $feitasHoje ?></h2>
				</div>
			</div>
		</div>

		<a class="col dashboard-card-link" href="<?= ch_url('turmas/index.php') ?>" aria-label="Ver turmas">
			<div class="card border-0 rounded-4 dashboard-card card-dashboard h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Turmas ativas</h6>
					<h2 class="mb-0"><?= $turmasAtivas ?></h2>
				</div>
			</div>
		</a>

		<a class="col dashboard-card-link" href="<?= ch_url('alunos/index.php') ?>" aria-label="Ver alunos">
			<div class="card border-0 rounded-4 dashboard-card card-dashboard h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Alunos ativos</h6>
					<h2 class="mb-0"><?= $alunosAtivos ?></h2>
				</div>
			</div>
		</a>

		<div class="col">
			<div class="card border-0 rounded-4 dashboard-card card-dashboard h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Frequência no mês</h6>
					<h2 class="mb-0"><?= $freqMes === null ? '—' : number_format($freqMes, 1, ',', '.') . '%' ?></h2>
				</div>
			</div>
		</div>

		<a class="col dashboard-card-link" href="<?= ch_url('observacoes/index.php') ?>" aria-label="Ver observações">
			<div class="card border-0 rounded-4 dashboard-card card-dashboard h-100">
				<div class="card-body text-center">
					<h6 class="mb-2">Observações no mês</h6>
					<h2 class="mb-0"><?= $obsMes ?></h2>
				</div>
			</div>
		</a>

	</div>

	<?php if ($transfAguardando > 0): ?>
		<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
			<span>
				<i class="fas fa-right-left"></i>
				<b><?= (int)$transfAguardando ?></b> aluno(s) transferidos aguardando a sua escola
				escolher a turma e a data de início.
			</span>
			<a class="btn btn-sm btn-dark" href="<?= ch_url('transferencias/index.php') ?>">
				<i class="fas fa-door-open"></i> Receber alunos
			</a>
		</div>
	<?php endif; ?>

	<?php if ($bloqueadasHoje > 0): ?>
		<div class="alert alert-info">
			<i class="fas fa-circle-info"></i>
			<?= $bloqueadasHoje ?> turma(s) não têm chamada hoje por feriado ou fim de semana.
		</div>
	<?php endif; ?>

	<div class="row g-3">

		<!-- PENDÊNCIAS -->
		<div class="col-lg-7">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body">
					<h5 class="mb-3"><i class="fas fa-list-check"></i> Chamadas pendentes de hoje</h5>

					<?php if (!$turmasHoje): ?>
						<p class="text-muted mb-0">
							Nenhuma turma disponível.
							<?php if (ch_gestor()): ?>
								Cadastre em <a href="<?= ch_url('turmas/index.php') ?>">Turmas</a>.
							<?php else: ?>
								Peça à gestão o vínculo com a sua unidade.
							<?php endif; ?>
						</p>
					<?php elseif (!$pendentes): ?>
						<div class="alert alert-success mb-0">
							<i class="fas fa-circle-check"></i> Todas as chamadas de hoje já foram lançadas.
						</div>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-sm align-middle mb-0">
								<thead><tr><th>Unidade</th><th>Turma</th><th>Período</th><th class="text-center">Alunos</th><th></th></tr></thead>
								<tbody>
									<?php foreach ($pendentes as $t): ?>
									<tr>
										<td><small><?= ch_h($t['UNIDADE']) ?></small></td>
										<td><?= ch_h($t['NOME']) ?></td>
										<td><small><?= ch_h($t['PERIODO']) ?></small></td>
										<td class="text-center"><?= (int)$t['ALUNOS'] ?></td>
										<td class="text-end">
											<a class="btn btn-sm btn-primary" href="<?= ch_url('chamada/index.php?turma_id=' . (int)$t['ID'] . '&data=' . $hoje) ?>">
												<i class="fas fa-pen"></i> Lançar
											</a>
										</td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- GRÁFICO -->
		<div class="col-lg-5">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body">
					<h5 class="mb-3"><i class="fas fa-chart-line"></i> Frequência mensal — <?= $ano ?></h5>
					<?php if (!$dadosFreq): ?>
						<p class="text-muted mb-0">Ainda não há chamadas lançadas neste ano.</p>
					<?php else: ?>
						<div class="chart-container"><canvas id="graficoFrequencia"></canvas></div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	</div>

	<div class="row g-3 mt-1">

		<!-- FERIADOS -->
		<div class="col-lg-5">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body">
					<h5 class="mb-3"><i class="fas fa-calendar-day"></i> Próximos feriados</h5>
					<?php if (!$proximosFeriados): ?>
						<p class="text-muted mb-0">Nenhum feriado futuro cadastrado.</p>
					<?php else: ?>
						<table class="table table-sm align-middle mb-0">
							<tbody>
								<?php foreach ($proximosFeriados as $f): ?>
								<tr>
									<td><?= ch_data_br($f['DATA']) ?></td>
									<td><?= ch_h($f['DESCRICAO']) ?></td>
									<td class="text-end">
										<?php if ($f['TIPO'] === 'INTEGRAL'): ?>
											<span class="badge bg-danger">Integral</span>
										<?php else: ?>
											<span class="badge bg-warning text-dark"><?= ch_h($f['HORA_INICIO']) ?>–<?= ch_h($f['HORA_FIM']) ?></span>
										<?php endif; ?>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<a class="btn btn-sm btn-outline-secondary mt-3" href="<?= ch_url('feriados/index.php') ?>">Ver todos</a>
				</div>
			</div>
		</div>

		<!-- OBSERVAÇÕES -->
		<div class="col-lg-7">
			<div class="card border-0 rounded-4 dashboard-card h-100">
				<div class="card-body">
					<h5 class="mb-3"><i class="far fa-comment-dots"></i> Observações recentes</h5>
					<?php if (!$obsRecentes): ?>
						<p class="text-muted mb-0">Nenhuma observação registrada.</p>
					<?php else: ?>
						<?php foreach ($obsRecentes as $o): ?>
							<div class="border-bottom pb-2 mb-2">
								<div class="d-flex justify-content-between">
									<a href="<?= ch_url('alunos/view.php?id=' . (int)$o['ID_ALUNO']) ?>"><strong><?= ch_h($o['ALUNO']) ?></strong></a>
									<small class="text-muted"><?= ch_data_br($o['DATA']) ?></small>
								</div>
								<small class="text-muted"><?= ch_h($o['TURMA']) ?> &middot; <?= ch_h($o['CATEGORIA']) ?></small>
								<div><?= ch_h(mb_strimwidth($o['TEXTO'], 0, 140, '…')) ?></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
					<a class="btn btn-sm btn-outline-secondary mt-2" href="<?= ch_url('observacoes/index.php') ?>">Ver todas</a>
				</div>
			</div>
		</div>

	</div>

	<!-- RELATÓRIOS -->
	<div class="row g-3 mt-1 mb-4">
		<div class="col-12">
			<div class="card border-0 rounded-4 dashboard-card">
				<div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
					<div>
						<h5 class="mb-1"><i class="fas fa-file-lines"></i> Relatórios anuais de <?= $ano ?></h5>
						<span class="text-muted">
							<?= $relatoriosPendentes ?> aluno(s) ainda sem parecer registrado.
						</span>
					</div>
					<a class="btn btn-primary" href="<?= ch_url('relatorios/index.php?ano=' . $ano) ?>">
						<i class="fas fa-folder-open"></i> Abrir relatórios
					</a>
				</div>
			</div>
		</div>
	</div>

</div>

<?php if ($dadosFreq): ?>
<script>
	new Chart(document.getElementById('graficoFrequencia'), {
		type: 'line',
		data: {
			labels: <?= json_encode($labelsFreq) ?>,
			datasets: [{
				label: 'Frequência (%)',
				data: <?= json_encode($dadosFreq) ?>,
				borderColor: '#2c3e50',
				backgroundColor: 'rgba(44,62,80,.12)',
				fill: true,
				tension: .3
			}]
		},
		options: {
			maintainAspectRatio: false,
			responsive: true,
			scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }
		}
	});
</script>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
