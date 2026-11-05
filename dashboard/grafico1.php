
<?php
  $cont_finalizados = 0;
  $cont_aguardando = 0;
 while($row = sqlsrv_fetch_array( $stmt_grafico, SQLSRV_FETCH_ASSOC ) )  
         { 
        if(!empty($row['RELDATA']))
	      $cont_finalizados++;	
	      else if (empty($row['RELDATA']))
	      $cont_aguardando++;
	    

       
     
}; 
sqlsrv_free_stmt( $stmt_grafico);

?>
<script type="text/javascript">
					google.charts.load( "current", {
						packages: [ "corechart" ]
					} );
					google.charts.setOnLoadCallback( drawChart );

					function drawChart() {
						var data = google.visualization.arrayToDataTable( [
							[ 'Categoria', 'Quantidade' ],
							[ 'Aguardando', <?php echo $cont_aguardando;?> ],
							[ 'Finalizados', <?php echo $cont_finalizados;?> ],
							
						] );

						var options = {
							title: 'Laudos',
							subtitle: 'Gráfico',
							is3D: true,
							backgroundColor: 'transparent',
							colors: [ '#FDB927', '#552583' ],
							 width: '100%',
							 height: '100%'

						};



						var chart = new google.visualization.PieChart( document.getElementById( 'GRAFICO1' ) );
						chart.draw( data, options );
					}
				</script>