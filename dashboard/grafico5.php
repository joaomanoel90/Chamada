   <script type="text/javascript">
      google.charts.load('current', {'packages':['table']});
      google.charts.setOnLoadCallback(drawTable);

      function drawTable() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Espécies mais solicitadas');
        data.addColumn('string', 'Quantidade')
        data.addRows([
			<?php   while($especie = sqlsrv_fetch_array( $stmt_especie, SQLSRV_FETCH_ASSOC ) )  
         {     ?>



							[ '<?php echo $especie['NOME_POPULAR'];?>', '<?php echo $especie['QTD'];?>' ],
			<?php } ?>
        ]);

        var table = new google.visualization.Table(document.getElementById('GRAFICO5'));

        table.draw(data, {showRowNumber: true, width: '100%', height: '200px'});
		
      }
    </script>
        					<?php	sqlsrv_free_stmt($stmt_especie); ?>
