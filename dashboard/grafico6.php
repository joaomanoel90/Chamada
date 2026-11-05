<script type="text/javascript">
      google.charts.load('current', {'packages':['bar']});
      google.charts.setOnLoadCallback(drawStuff);

      function drawStuff() {
        var data = new google.visualization.arrayToDataTable( [  
                          ['Espécies', 'Quantidade'],  
						  <?php   while($erradicacao = sqlsrv_fetch_array( $stmt_erradicacao, SQLSRV_FETCH_ASSOC ) )  
         {     ?>



							[ '<?php echo $erradicacao['NOME_POPULAR'];?>', '<?php echo $erradicacao['QTD'];?>' ],
			<?php } ?>
							
						] );
					

        var options = {
          width: '100%',
          legend: { position: 'none' },
		  backgroundColor: 'transparent',
			colors: [ 'green' ],
          chart: {
			title: 'Erradicações',
			subtitle: 'Gráfico', },
			
			bars: 'horizontal',

          axes: {
            x: {
              0: { side: 'bottom', label: 'Erradicação'} // Top x-axis.
            }

          },
          bar: { groupWidth: "95%" }
        };

        var chart = new google.charts.Bar(document.getElementById('GRAFICO6'));
        // Convert the Classic options to Material options.
        chart.draw(data, google.charts.Bar.convertOptions(options));
      };
    </script>
        					<?php	sqlsrv_free_stmt( $stmt_erradicacao); ?>
