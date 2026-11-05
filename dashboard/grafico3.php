
	
	<script type="text/javascript">
      google.charts.load('current', {'packages':['bar']});
      google.charts.setOnLoadCallback(drawStuff);

      function drawStuff() {
        var data = new google.visualization.arrayToDataTable( [  
                          ['Categoria', 'Quantidade'],  
						  <?php   while($errpod = sqlsrv_fetch_array( $stmt_errpod, SQLSRV_FETCH_ASSOC ) )  
         {     ?>



							[ '<?php echo $errpod['ERRPOD'];?>', '<?php echo $errpod['QTD'];?>' ],
			<?php } ?>
							
						] );
					

        var options = {
          width: '100%',
          legend: { position: 'none' },
		  backgroundColor: 'transparent',
			colors: [ '#004170', 'red' ],
          chart: {
			title: 'Solicitações',
			subtitle: 'Gráfico', },
			
		
          axes: {
            x: {
              0: { side: 'top', label: 'Erradicação / Poda'} // Top x-axis.
            }
          },
          bar: { groupWidth: "95%" }
        };

        var chart = new google.charts.Bar(document.getElementById('GRAFICO3'));
        // Convert the Classic options to Material options.
        chart.draw(data, google.charts.Bar.convertOptions(options));
      };
    </script>
    					<?php	sqlsrv_free_stmt( $stmt_errpod); ?>
