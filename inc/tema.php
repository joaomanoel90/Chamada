<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title>Chamada de Alunos — Prefeitura de Angatuba</title>
	<meta name="description" content="">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!------------------CSS------------------>

	<!--Acessibilidade-->
	<link rel="stylesheet" type="text/css" href="<?php echo BASEURL; ?>css/accessibility.css">


	<!--Personalizar datatables-->
	<link rel="stylesheet" type="text/css" href="<?php echo BASEURL; ?>css/tabela.css">


	<!--Style-->
	<link rel="stylesheet" type="text/css" href="<?php echo BASEURL; ?>css/style.css">



	<!--Fontes Icones-->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css">



	<!--Bootstrap-->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet"
		integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">


	<!--Bootstrap Datatables-->
	<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
	<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap5.min.css">

	<!-- CSS do Bootstrap 5.1.3 removido: duplicava o 5.2.0 carregado acima e
	     sobrescrevia estilos (era o que deixava o cabeçalho das tabelas e os
	     menus com comportamento inconsistente). -->

	<link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.2.4/css/fixedHeader.bootstrap.min.css">
	<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.2/css/buttons.dataTables.min.css">


	<!--Select2-->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" />
	<link rel="stylesheet"
		href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />



	<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>
	<!-- ÚNICA carga do Bootstrap (bundle = Bootstrap + Popper). Antes havia três:
	     5.1.3 aqui e 5.2.0 duas vezes no rodapé. Cada cópia registrava seu próprio
	     handler de dropdown e uma sobrescrevia o posicionamento da outra. -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa"
		crossorigin="anonymous"></script>
	<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
	<script src="https://cdn.datatables.net/fixedheader/3.2.4/js/dataTables.fixedHeader.min.js"></script>
	<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
	<script src="https://cdn.datatables.net/responsive/2.3.0/js/responsive.bootstrap5.min.js"></script>




	<script src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>


	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
	<script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>
	<script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.bootstrap5.min.js"></script>
	<script src="https://cdn.datatables.net/buttons/2.3.5/js/buttons.print.min.js"></script>



	<script src="<?php echo BASEURL; ?>js/accessibility.js"></script>
	<script src="<?php echo BASEURL; ?>js/mask.js"></script>


	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">


	<style>
		body {
			margin: 0;
			font-family: 'Nunito', sans-serif;
			background: linear-gradient(120deg, #f0f4f8, #dbe4ec);

			/* padding-top: 80px;  */
			padding-bottom: 50px;
		}

		body.cadastro {
			padding-top: 50px;
		}

		.Scroll {
			height: 92%;
			overflow-y: auto;
		}


		@media print {
			.no-print {
				display: none !important;
			}

			.force-print {
				display: block !important;
			}
		}


		.bg-custom {
			background-color: #2c3e50;
			color: white;
			border: none;
			padding: 5px 20px;
			border-radius: 8px;
			text-decoration: none;
			transition: background 0.3s;
		}

		.btn:hover {
			background-color: #1a252f;
		}
	</style>








	<!-- Tema escolar: carregado por ÚLTIMO para prevalecer sobre style.css
	     e sobre o <style> embutido acima. -->
	<link rel="stylesheet" type="text/css" href="<?php echo BASEURL; ?>css/escola.css">
</head>