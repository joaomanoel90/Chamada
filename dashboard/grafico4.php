
<script type="text/javascript">
					google.charts.load( "current", {
						packages: [ "corechart" ]
					} );
					google.charts.setOnLoadCallback( drawChart );

					function drawChart() {
						var data = google.visualization.arrayToDataTable( [
							[ 'Categoria', 'Quantidade' ],
<?php   while($autoriza = sqlsrv_fetch_array( $stmt_auto, SQLSRV_FETCH_ASSOC ) )  
         {     ?>



							[ '<?php echo $autoriza['AUTORIZA'];?>', <?php echo $autoriza['QTD'];?> ],
			<?php } ?>
						] );

						var options = {
							pieSliceText: 'label',
          slices: {  1: {offset: 0.3},
                    2: {offset: 0.3},
					3: {offset: 0.3},
					4: {offset: 0.3},
					0: {offset: 0.3}
          },
							title: 'Autorizações',
							subtitle: 'Gráfico',
							is3D: true,
							backgroundColor: 'transparent',
							colors: [ '#004170', '#DA291C', '#CEAB5D'  ],
							 width: '100%',
							 height: '100%'

						};



						var chart = new google.visualization.PieChart( document.getElementById( 'GRAFICO4' ) );
						chart.draw( data, options );
					}
				</script>
				    					<?php	sqlsrv_free_stmt( $stmt_auto); ?>
