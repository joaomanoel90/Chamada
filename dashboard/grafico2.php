	


 <script type="text/javascript">
					google.charts.load( "current", {
						packages: [ "corechart" ]
					} );
					google.charts.setOnLoadCallback( drawChart );

					function drawChart() {
						var data = google.visualization.arrayToDataTable( [
							[ 'Categoria', 'Quantidade' ],
<?php   while($dia = sqlsrv_fetch_array( $stmt_dias, SQLSRV_FETCH_ASSOC ) )  
         {     ?>



							[ '<?php echo $dia['DIAS'].' Dia(s) - '.$dia['QTD'];?>', <?php echo $dia['QTD'];?> ],
			<?php } ?>
							
						] );

						var options = {
							title: 'Dias',
							subtitle: 'Gráfico',
							is3D: true,
							backgroundColor: 'transparent',
							colors: [ '#0C2340', '#FA4616'],
							 width: '100%',
							 height: '100%'
						};



						var chart = new google.visualization.PieChart( document.getElementById( 'GRAFICO2' ) );
						chart.draw( data, options );
					}
				</script> 
				<?php 
										sqlsrv_free_stmt( $stmt_dias);

				?>