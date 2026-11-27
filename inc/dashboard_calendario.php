<!-- CALENDÁRIO ESCOLAR -->
<div class="card border-0 rounded-4 dashboard-card h-100">
		<div class="card-body">
			<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0"><i class="fas fa-calendar-days"></i> Calendário escolar — <?php if ($calAnoTodo): ?>ano de <?= $calAno ?><?php else: ?><?= ch_h(ch_mes_nome($calMes)) ?> de <?= $calAno ?><?php endif; ?></h5>
				<?php if (!$calAnoTodo): ?>
				<div class="d-flex align-items-center gap-1">
					<a class="btn btn-sm btn-outline-secondary" href="<?= ch_h($calUrlMes($calAnoAnterior, $calMesAnterior)) ?>" data-cal-mes aria-label="Mês anterior"><i class="fas fa-chevron-left"></i></a>
					<a class="btn btn-sm btn-outline-secondary" href="<?= ch_h($calUrlMes($calAnoSeguinte, $calMesSeguinte)) ?>" data-cal-mes aria-label="Próximo mês"><i class="fas fa-chevron-right"></i></a>
				</div>
				<?php endif; ?>
			</div>

			<form class="row g-2 align-items-end mb-3" method="get" action="<?= ch_url('index.php') ?>" data-cal-ajax>
				<div class="col-auto">
					<label class="form-label small mb-0" for="calAno">Ano</label>
					<select class="form-select form-select-sm" id="calAno" name="cal_ano" style="min-width: 6.5rem;">
						<?php for ($anoOpcao = min($ano, $calAno) - 1; $anoOpcao <= max($ano, $calAno) + 1; $anoOpcao++): ?>
						<option value="<?= $anoOpcao ?>"<?= $anoOpcao === $calAno ? ' selected' : '' ?>><?= $anoOpcao ?></option>
						<?php endfor; ?>
					</select>
				</div>
				<div class="col-auto">
					<label class="form-label small mb-0" for="calMes">Mês</label>
					<select class="form-select form-select-sm" id="calMes" name="cal_mes" style="min-width: 9rem;">
						<option value="0"<?= $calAnoTodo ? ' selected' : '' ?>>Ano todo</option>
						<?php for ($mesOpcao = 1; $mesOpcao <= 12; $mesOpcao++): ?>
						<option value="<?= $mesOpcao ?>"<?= $mesOpcao === $calMes ? ' selected' : '' ?>><?= ch_h(ch_mes_nome($mesOpcao)) ?></option>
						<?php endfor; ?>
					</select>
				</div>
				<div class="col-auto">
					<label class="form-label small mb-0" for="calCalendario">Calendário</label>
					<select class="form-select form-select-sm" id="calCalendario" name="calendario" style="min-width: 10rem;">
						<option value=""<?= $calCodigo === '' ? ' selected' : '' ?>>Todos</option>
						<?php foreach ($calOpcoes as $codigoOpcao => $rotuloOpcao): ?>
						<option value="<?= ch_h($codigoOpcao) ?>"<?= $calCodigo === $codigoOpcao ? ' selected' : '' ?>><?= ch_h($rotuloOpcao) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-auto">
					<button type="submit" class="btn btn-sm btn-outline-primary">Ver calendário</button>
				</div>
				<div class="col-auto align-self-center">
					<span class="small text-muted cal-aviso" role="status" aria-live="polite"></span>
				</div>
			</form>

			<?php if (!$calRegras): ?>
				<div class="alert alert-info py-2 mb-3">
					<i class="fas fa-circle-info"></i> Os períodos do ano letivo ainda não foram definidos.
					<a href="<?= ch_url('feriados/index.php') ?>">Abrir Calendário Escolar</a>.
				</div>
			<?php else: ?>
				<div class="d-flex flex-wrap gap-2 mb-3">
					<?php foreach ($calRegras as $info): ?>
					<span class="badge bg-light text-dark border fw-normal">
						<strong><?= ch_h($info['ROTULO']) ?></strong>:
						<?= $info['DADOS']['INICIO'] ? ch_data_br($info['DADOS']['INICIO']) : 'início não definido' ?>
						—
						<?= $info['DADOS']['FIM'] ? ch_data_br($info['DADOS']['FIM']) : 'fim não definido' ?>
						· <?= (int)$info['TOTAL'] ?> dias letivos
					</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="cal-meses<?= $calAnoTodo ? ' cal-meses-ano' : '' ?>">
				<?php foreach ($calGrades as $grade): ?>
				<section class="cal-mes">
					<?php if ($calAnoTodo): ?><h6 class="cal-mes-titulo"><?= ch_h($grade['ROTULO']) ?></h6><?php endif; ?>
					<div class="cal-semana" aria-hidden="true">
						<?php foreach ($calDiasSemana as $rotuloDia): ?>
						<div><?= $rotuloDia ?></div>
						<?php endforeach; ?>
					</div>
					<div class="cal-grade">
						<?php foreach ($grade['CELULAS'] as $celula): ?>
						<div class="<?= ch_h($celula['CLASSE']) ?>"<?php if ($celula['MARCAS']): ?> title="<?= ch_h(implode(' | ', array_column($celula['MARCAS'], 'TITULO'))) ?>"<?php endif; ?>>
							<div class="cal-num"><?= (int)$celula['DIA'] ?></div>
							<?php if ($celula['MARCAS']): ?>
							<div class="cal-marcas">
								<?php foreach ($celula['MARCAS'] as $marca): ?>
								<span class="cal-marca cal-marca-<?= ch_h(strtolower($marca['TIPO'])) ?>"><?= ch_h($marca['SIGLA']) ?></span>
								<?php endforeach; ?>
							</div>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div>
				</section>
				<?php endforeach; ?>
			</div>

			<div class="d-flex flex-wrap gap-3 mt-3 small text-muted">
				<span><span class="cal-marca cal-marca-inicio">I</span> Início do ano letivo</span>
				<span><span class="cal-marca cal-marca-fim">T</span> Fim do ano letivo</span>
				<span><span class="cal-marca cal-marca-feriado_integral">F</span> Feriado integral</span>
				<span><span class="cal-marca cal-marca-feriado_parcial">P</span> Feriado parcial</span>
				<span><span class="cal-marca cal-marca-excecao_letivo">L</span> Exceção letiva</span>
				<span><span class="cal-marca cal-marca-excecao_nao_letivo">N</span> Exceção não letiva</span>
			</div>

			<?php if ($calMarcas): ?>
			<div class="table-responsive mt-3">
				<table class="table table-sm align-middle mb-0">
					<thead><tr><th>Data</th><th>Marcação</th><th>Calendário</th><th>Abrangência</th></tr></thead>
					<tbody>
						<?php foreach ($calMarcas as $dataIso => $marcas): ?>
							<?php foreach ($marcas as $marca): ?>
							<tr>
								<td class="text-nowrap"><small><?= ch_data_br($dataIso) ?></small></td>
								<td><span class="cal-marca cal-marca-<?= ch_h(strtolower($marca['TIPO'])) ?>"><?= ch_h($marca['SIGLA']) ?></span> <?= ch_h($marca['TITULO']) ?></td>
								<td><small class="text-muted"><?= ch_h(ch_calendario_rotulo($marca['CALENDARIO'])) ?></small></td>
								<td><small class="text-muted"><?= ch_h($marca['ESCOPO']) ?></small></td>
							</tr>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php else: ?>
			<p class="text-muted mb-0 mt-3">Nenhuma marcação de calendário <?= $calAnoTodo ? 'neste ano' : 'neste mês' ?>.</p>
			<?php endif; ?>
			<a class="btn btn-sm btn-outline-secondary mt-3" href="<?= ch_url('feriados/index.php?ano=' . $calAno) ?>">Abrir calendário completo</a>
		</div>
</div>
