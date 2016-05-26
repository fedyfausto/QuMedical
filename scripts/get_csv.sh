#!/bin/bash
QUERY="select CompositeSequence_Identifier,$1 from Processed";
#echo $QUERY;
curl --user admin:admin --header "Accept: text/csv" -d "$(echo $QUERY)" "http://localhost:2480/command/EsperimentoTest/sql" > test.csv
exit;