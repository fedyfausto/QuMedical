<?php
$query="select CompositeSequence_Identifier,Sample_1,Sample_2 from Processed";
exec("curl --user admin:admin --header 'Accept: text/csv' -d '$query' 'http://localhost:2480/command/EsperimentoTest/sql' > test.csv")
?>